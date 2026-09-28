<?php
/**
 * Pull From GitHub — Backend Handler
 * Location: backend/admin/pull.php
 *
 * ADMIN ONLY
 * POST: action = pull | fetch | diagnostic
 *       mode   = ff_only | ff_merge | hard_reset | backup_branch
 *
 * Returns JSON:
 *   { success, message, output, mode, branch }
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
ini_set('error_log', $logDir . '/php-errors.log');

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function logIt(string $type, array $data = []): void {
    global $logDir;
    $entry = sprintf("[%s] [%s] [IP: %s] %s\n",
        date('Y-m-d H:i:s'), strtoupper($type),
        $_SERVER['REMOTE_ADDR'] ?? '-', json_encode($data, JSON_UNESCAPED_UNICODE));
    @file_put_contents($logDir . '/updates.log', $entry, FILE_APPEND | LOCK_EX);
}

// ============================================================
// AUTH
// ============================================================
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    respond(false, 'Session expired. Please login again.');
}

if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    logIt('unauthorized_attempt', [
        'user_id' => $_SESSION['user_id'] ?? null,
        'role'    => $_SESSION['user_role'] ?? null,
    ]);
    respond(false, 'Only administrators can pull from GitHub.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed.');
}

// ============================================================
// INPUT
// ============================================================
$action = trim((string)($_POST['action'] ?? 'pull'));
$mode   = trim((string)($_POST['mode']   ?? 'ff_only'));

$allowedModes = ['ff_only', 'ff_merge', 'hard_reset', 'backup_branch'];
if ($action === 'pull' && !in_array($mode, $allowedModes, true)) {
    respond(false, 'Invalid pull mode.');
}

// ============================================================
// PROJECT ROOT
// ============================================================
$projectRoot = realpath(__DIR__ . '/../..');
if ($projectRoot === false) {
    respond(false, 'Cannot resolve project root.');
}
if (!is_dir($projectRoot . '/.git')) {
    respond(false, 'Not a Git repository at ' . htmlspecialchars($projectRoot)
        . ' — run "git init" and "git remote add origin <url>" first.');
}

// ============================================================
// GIT RUNNER
// ============================================================
/**
 * Run a Git command with proper env so Windows can spawn child processes.
 * Windows-specific: PATH, SystemRoot, USERPROFILE must be set for git.exe.
 */
function runGit(string $cmd, string $cwd): array {
    if (!function_exists('proc_open')) {
        return ['ok' => false, 'out' => 'proc_open() is disabled in php.ini', 'code' => -1];
    }

    // Build environment — Git on Windows needs these
    $path = getenv('PATH') ?: '';
    $gitPaths = [
        'C:\\Program Files\\Git\\cmd',
        'C:\\Program Files\\Git\\bin',
        'C:\\Program Files (x86)\\Git\\cmd',
        'C:\\Program Files (x86)\\Git\\bin',
    ];
    // Prepend Git paths if not already present
    $pathExtra = [];
    foreach ($gitPaths as $gp) {
        if (stripos($path, $gp) === false && is_dir($gp)) {
            $pathExtra[] = $gp;
        }
    }
    if ($pathExtra) {
        $path = implode(';', $pathExtra) . ';' . $path;
    }

    $env = [
        'PATH'                => $path,
        'SystemRoot'          => getenv('SystemRoot') ?: 'C:\\Windows',
        'windir'              => getenv('windir') ?: 'C:\\Windows',
        'USERPROFILE'         => getenv('USERPROFILE') ?: $cwd,
        'HOME'                => getenv('USERPROFILE') ?: $cwd,
        'TEMP'                => getenv('TEMP') ?: 'C:\\Windows\\Temp',
        'TMP'                 => getenv('TMP')  ?: 'C:\\Windows\\Temp',
        'COMSPEC'             => getenv('COMSPEC') ?: 'C:\\Windows\\System32\\cmd.exe',
        // Critical: prevent git from hanging on credential prompt
        'GIT_TERMINAL_PROMPT' => '0',
        // Prevent git from trying to open a pager
        'GIT_PAGER'           => 'cat',
        'PAGER'               => 'cat',
        // Avoid git's safe.directory complaint about ownership
        'GIT_CONFIG_NOSYSTEM' => '0',
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = @proc_open($cmd, $descriptors, $pipes, $cwd, $env);

    if (!is_resource($proc)) {
        return ['ok' => false, 'out' => 'proc_open() failed to start process.', 'code' => -1];
    }

    // Set non-blocking to avoid hangs
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    fclose($pipes[0]);

    $stdout = '';
    $stderr = '';
    $start = time();
    $timeout = 60; // seconds

    // Read pipes with timeout
    while (true) {
        $status = proc_get_status($proc);
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        if (!$status['running']) break;
        if ((time() - $start) > $timeout) {
            proc_terminate($proc, 9);
            fclose($pipes[1]); fclose($pipes[2]);
            proc_close($proc);
            return ['ok' => false, 'out' => 'Git command timed out after ' . $timeout . 's', 'code' => -1];
        }
        usleep(50000); // 50ms
    }

    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $code = proc_close($proc);
    $combined = trim($stdout . ($stderr ? "\n--- STDERR ---\n" . $stderr : ''));

    return [
        'ok'   => $code === 0,
        'out'  => $combined ?: '(no output)',
        'code' => $code,
    ];
}

/**
 * Detect if the error is the specific getaddrinfo failure.
 */
function isDnsThreadError(string $out): bool {
    return stripos($out, 'getaddrinfo() thread failed to start') !== false
        || stripos($out, 'getaddrinfo failed') !== false
        || stripos($out, 'unable to access') !== false
        || stripos($out, 'Could not resolve host') !== false;
}

// ============================================================
// DIAGNOSTIC ACTION
// ============================================================
if ($action === 'diagnostic') {
    $results = [];
    $results['git_version'] = runGit('git --version', $projectRoot);
    $results['git_remote']  = runGit('git remote -v', $projectRoot);
    $results['git_branch']  = runGit('git rev-parse --abbrev-ref HEAD', $projectRoot);
    $results['git_status']  = runGit('git status --short', $projectRoot);
    $results['dns_lookup']  = runGit('nslookup github.com 2>&1', $projectRoot);
    $results['ls_remote']   = runGit('git ls-remote --heads origin 2>&1', $projectRoot);

    logIt('diagnostic_run', ['by' => $_SESSION['user_id'] ?? null]);

    respond(true, 'Diagnostics complete.', ['results' => $results]);
}

// ============================================================
// PRE-FLIGHT: verify Git is available
// ============================================================
$check = runGit('git --version', $projectRoot);
if (!$check['ok']) {
    logIt('git_missing', ['out' => $check['out']]);
    respond(false, 'Git is not available: ' . $check['out'], ['output' => $check['out']]);
}

// ============================================================
// GET BRANCH
// ============================================================
$branchCheck = runGit('git rev-parse --abbrev-ref HEAD', $projectRoot);
$branch = trim($branchCheck['out'] ?: 'main');
if ($branch === 'HEAD' || $branch === '') $branch = 'main';

$output = [];

// ============================================================
// FETCH ONLY
// ============================================================
if ($action === 'fetch') {
    $output[] = "> git fetch --all --prune";
    $r = runGit('git fetch --all --prune 2>&1', $projectRoot);
    $output[] = $r['out'];

    if (!$r['ok']) {
        $dns = isDnsThreadError($r['out']);
        logIt('fetch_failed', ['out' => $r['out'], 'dns_error' => $dns]);

        respond(false,
            $dns
                ? 'Network error: PHP cannot reach GitHub. This is a Windows firewall/DNS issue. See the "Diagnose Git" button for the fix.'
                : 'Fetch failed.',
            [
                'output'    => implode("\n\n", $output),
                'branch'    => $branch,
                'dns_error' => $dns,
            ]
        );
    }

    logIt('fetch_success', ['branch' => $branch, 'by' => $_SESSION['user_id'] ?? null]);
    respond(true, 'Fetch completed successfully.', [
        'output' => implode("\n\n", $output),
        'branch' => $branch,
    ]);
}

// ============================================================
// PULL — all modes
// ============================================================

// --- Backup + Hard Reset ---
if ($mode === 'backup_branch') {
    $backupName = 'backup-' . date('Ymd-His');

    $output[] = "> git branch {$backupName}";
    $r = runGit("git branch " . escapeshellarg($backupName) . " 2>&1", $projectRoot);
    $output[] = $r['out'];

    $output[] = "> git fetch --all --prune";
    $r = runGit('git fetch --all --prune 2>&1', $projectRoot);
    $output[] = $r['out'];
    if (!$r['ok']) {
        $dns = isDnsThreadError($r['out']);
        logIt('pull_failed_fetch', ['mode' => $mode, 'out' => $r['out'], 'dns_error' => $dns]);
        respond(false,
            $dns ? 'Network error — cannot reach GitHub. Check firewall/DNS.' : 'Fetch failed.',
            ['output' => implode("\n\n", $output), 'branch' => $branch, 'dns_error' => $dns]
        );
    }

    $output[] = "> git reset --hard origin/{$branch}";
    $r = runGit("git reset --hard " . escapeshellarg("origin/{$branch}") . " 2>&1", $projectRoot);
    $output[] = $r['out'];
    if (!$r['ok']) {
        logIt('pull_failed_reset', ['mode' => $mode, 'out' => $r['out']]);
        respond(false, 'Reset failed.',
            ['output' => implode("\n\n", $output), 'branch' => $branch]);
    }

    // Safe clean — only removes untracked files, preserves ignored (uploads/, logs/, .env)
    $output[] = "> git clean -fd (preserving ignored files)";
    $r = runGit('git clean -fd 2>&1', $projectRoot);
    $output[] = $r['out'];

    logIt('pull_success_backup', [
        'branch' => $branch, 'backup' => $backupName,
        'by' => $_SESSION['user_id'] ?? null,
    ]);
    respond(true, "Updated to origin/{$branch}. Backup saved as '{$backupName}'.", [
        'output' => implode("\n\n", $output),
        'branch' => $branch,
        'backup' => $backupName,
    ]);
}

// --- Hard Reset ---
if ($mode === 'hard_reset') {
    $output[] = "> git fetch --all --prune";
    $r = runGit('git fetch --all --prune 2>&1', $projectRoot);
    $output[] = $r['out'];
    if (!$r['ok']) {
        $dns = isDnsThreadError($r['out']);
        logIt('pull_failed_fetch', ['mode' => $mode, 'out' => $r['out'], 'dns_error' => $dns]);
        respond(false,
            $dns ? 'Network error — cannot reach GitHub.' : 'Fetch failed.',
            ['output' => implode("\n\n", $output), 'branch' => $branch, 'dns_error' => $dns]
        );
    }

    $output[] = "> git reset --hard origin/{$branch}";
    $r = runGit("git reset --hard " . escapeshellarg("origin/{$branch}") . " 2>&1", $projectRoot);
    $output[] = $r['out'];
    if (!$r['ok']) {
        logIt('pull_failed_reset', ['mode' => $mode, 'out' => $r['out']]);
        respond(false, 'Reset failed.',
            ['output' => implode("\n\n", $output), 'branch' => $branch]);
    }

    logIt('pull_success_hard_reset', ['branch' => $branch, 'by' => $_SESSION['user_id'] ?? null]);
    respond(true, "Hard-reset to origin/{$branch} completed.", [
        'output' => implode("\n\n", $output),
        'branch' => $branch,
    ]);
}

// --- Fetch (shared by ff_only and ff_merge) ---
$output[] = "> git fetch --all --prune";
$r = runGit('git fetch --all --prune 2>&1', $projectRoot);
$output[] = $r['out'];
if (!$r['ok']) {
    $dns = isDnsThreadError($r['out']);
    logIt('pull_failed_fetch', ['mode' => $mode, 'out' => $r['out'], 'dns_error' => $dns]);
    respond(false,
        $dns ? 'Network error — cannot reach GitHub.' : 'Fetch failed.',
        ['output' => implode("\n\n", $output), 'branch' => $branch, 'dns_error' => $dns]
    );
}

// --- Fast-forward only ---
if ($mode === 'ff_only') {
    $output[] = "> git merge --ff-only origin/{$branch}";
    $r = runGit("git merge --ff-only " . escapeshellarg("origin/{$branch}") . " 2>&1", $projectRoot);
    $output[] = $r['out'];

    if (!$r['ok']) {
        logIt('pull_failed_ff_only', ['out' => $r['out'], 'branch' => $branch]);
        respond(false,
            'Fast-forward not possible — local branch has diverged. '
          . 'Use "Fetch + Merge" or "Backup + Hard Reset" instead.',
            ['output' => implode("\n\n", $output), 'branch' => $branch]
        );
    }

    logIt('pull_success_ff_only', ['branch' => $branch, 'by' => $_SESSION['user_id'] ?? null]);
    respond(true, "Fast-forward update to origin/{$branch} completed.", [
        'output' => implode("\n\n", $output),
        'branch' => $branch,
    ]);
}

// --- Fetch + Merge ---
if ($mode === 'ff_merge') {
    $output[] = "> git merge --no-edit origin/{$branch}";
    $r = runGit("git merge --no-edit " . escapeshellarg("origin/{$branch}") . " 2>&1", $projectRoot);
    $output[] = $r['out'];

    if (!$r['ok']) {
        $conflict = stripos($r['out'], 'CONFLICT') !== false
                 || stripos($r['out'], 'Automatic merge failed') !== false;
        logIt('pull_failed_merge', ['out' => $r['out'], 'branch' => $branch, 'conflict' => $conflict]);
        respond(false,
            $conflict
                ? 'Merge conflict detected. Resolve manually or use "Backup + Hard Reset".'
                : 'Merge failed.',
            ['output' => implode("\n\n", $output), 'branch' => $branch, 'conflict' => $conflict]
        );
    }

    logIt('pull_success_merge', ['branch' => $branch, 'by' => $_SESSION['user_id'] ?? null]);
    respond(true, "Pull with merge from origin/{$branch} completed.", [
        'output' => implode("\n\n", $output),
        'branch' => $branch,
    ]);
}

respond(false, 'Unhandled pull mode.');