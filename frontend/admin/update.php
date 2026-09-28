<?php

/**
 * Update From GitHub — Admin Page
 * Location: frontend/admin/update.php
 */

session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: ../../index.php');
    exit;
}
if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    exit('Access denied. Administrators only.');
}

$currentPage = 'admin_update';
$assetPath   = '../../assets';
$rootPath    = '../../';
$pageTitle   = 'Update From GitHub';

$userName  = $_SESSION['user_name']  ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$userRole  = $_SESSION['user_role']  ?? 'user';
$initials  = '';
foreach (explode(' ', trim($userName)) as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = mb_substr($initials, 0, 2) ?: 'U';

// ---- Local Git info for display ----
$projectRoot = realpath(__DIR__ . '/../..');

function runQuick(string $cmd, string $cwd): string
{
    if (!function_exists('proc_open')) return '(proc_open disabled)';
    $env = [
        'PATH'                => getenv('PATH') ?: 'C:\\Program Files\\Git\\cmd',
        'SystemRoot'          => getenv('SystemRoot') ?: 'C:\\Windows',
        'USERPROFILE'         => getenv('USERPROFILE') ?: $cwd,
        'GIT_TERMINAL_PROMPT' => '0',
        'GIT_PAGER'           => 'cat',
    ];
    $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
    if (!is_resource($proc)) return '(failed)';
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return trim($out . ($err ? "\n" . $err : '')) ?: '(empty)';
}

$gitVersion = runQuick('git --version 2>&1', $projectRoot);
$gitRemote  = runQuick('git remote -v 2>&1', $projectRoot);
$gitBranch  = trim(runQuick('git rev-parse --abbrev-ref HEAD 2>&1', $projectRoot));
$gitStatus  = runQuick('git status --short 2>&1', $projectRoot);
$gitLog     = runQuick('git log -5 --pretty=format:"%h|%an|%ar|%s" 2>&1', $projectRoot);

$currentBranch = ($gitBranch && $gitBranch !== '(empty)') ? $gitBranch : 'main';
$remoteLines   = array_filter(explode("\n", $gitRemote));
$statusLines   = array_filter(explode("\n", $gitStatus));

$commits = [];
foreach (explode("\n", $gitLog) as $line) {
    if (trim($line) === '') continue;
    $parts = explode('|', $line, 4);
    if (count($parts) === 4) {
        $commits[] = ['hash' => $parts[0], 'author' => $parts[1], 'when' => $parts[2], 'msg' => $parts[3]];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Aqibsb Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $assetPath ?>/css/sidebar.css">
    <link rel="stylesheet" href="<?= $assetPath ?>/css/dashboard.css">
    <link rel="stylesheet" href="<?= $assetPath ?>/css/update.css">
</head>

<body>

    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../../includes/topbar.php'; ?>

        <main class="content">

            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item">Admin</li>
                    <li class="breadcrumb-item active" aria-current="page">Update From GitHub</li>
                </ol>
            </nav>

            <!-- Warning -->
            <div class="alert alert-warning d-flex align-items-start gap-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                <div>
                    <strong>Admin only.</strong> Pulling overwrites local code. Back up your work first.
                    Uploaded images, logs, and <code>config/database.php</code> are ignored by Git — they're safe.
                    <br>
                    <a href="../../backend/admin/git-diagnostic.php" target="_blank" class="alert-link small">
                        <i class="bi bi-wrench-adjustable"></i> Open Git Diagnostics
                    </a>
                </div>
            </div>

            <!-- Repo Info -->
            <div class="panel mb-4">
                <div class="panel-header">
                    <h3 class="panel-title">
                        <i class="bi bi-git me-2 text-primary"></i>Repository Status
                    </h3>
                    <span class="badge bg-primary-subtle text-primary">
                        <i class="bi bi-branch"></i> <?= htmlspecialchars($currentBranch) ?>
                    </span>
                </div>
                <div class="panel-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="info-row">
                                <div class="info-row-icon"><i class="bi bi-hdd-network"></i></div>
                                <div>
                                    <div class="info-row-label">Git Version</div>
                                    <div class="info-row-value"><?= htmlspecialchars($gitVersion) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-row">
                                <div class="info-row-icon"><i class="bi bi-folder"></i></div>
                                <div>
                                    <div class="info-row-label">Project Root</div>
                                    <div class="info-row-value" style="font-size:12px;font-family:monospace;">
                                        <?= htmlspecialchars($projectRoot) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-muted small fw-bold mb-2">
                        <i class="bi bi-link-45deg"></i> Remotes
                    </h6>
                    <?php if (empty($remoteLines)): ?>
                        <div class="alert alert-secondary py-2 mb-0 small">
                            No Git remotes configured. Run <code>git remote add origin &lt;url&gt;</code> first.
                        </div>
                    <?php else: ?>
                        <pre class="code-block mb-0"><?= htmlspecialchars(implode("\n", $remoteLines)) ?></pre>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Local changes -->
            <?php if (!empty($statusLines)): ?>
                <div class="panel mb-4">
                    <div class="panel-header bg-warning-subtle">
                        <h3 class="panel-title text-warning-emphasis">
                            <i class="bi bi-exclamation-diamond me-2"></i>Uncommitted Local Changes
                        </h3>
                        <span class="badge bg-warning text-dark"><?= count($statusLines) ?> file(s)</span>
                    </div>
                    <div class="panel-body">
                        <p class="small text-muted mb-2">
                            These files are kept during a pull unless they conflict. Consider committing them.
                        </p>
                        <pre class="code-block mb-0" style="max-height:200px;overflow:auto;"><?= htmlspecialchars(implode("\n", $statusLines)) ?></pre>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent commits -->
            <div class="panel mb-4">
                <div class="panel-header">
                    <h3 class="panel-title">
                        <i class="bi bi-clock-history me-2 text-primary"></i>Latest Local Commits
                    </h3>
                </div>
                <div class="panel-body p-0">
                    <?php if (empty($commits)): ?>
                        <div class="p-3 text-muted small">No commits yet, or Git history unavailable.</div>
                    <?php else: ?>
                        <ul class="commit-list">
                            <?php foreach ($commits as $c): ?>
                                <li class="commit-item">
                                    <code class="commit-hash"><?= htmlspecialchars($c['hash']) ?></code>
                                    <div class="commit-body">
                                        <div class="commit-msg"><?= htmlspecialchars($c['msg']) ?></div>
                                        <div class="commit-meta">
                                            <i class="bi bi-person"></i> <?= htmlspecialchars($c['author']) ?>
                                            · <i class="bi bi-clock"></i> <?= htmlspecialchars($c['when']) ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action -->
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title">
                        <i class="bi bi-cloud-arrow-down-fill me-2 text-primary"></i>Pull Latest Changes
                    </h3>
                </div>
                <div class="panel-body">

                    <div class="pull-options mb-4">
                        <label class="pull-option">
                            <input type="radio" name="pullMode" value="ff_only" checked>
                            <div>
                                <div class="pull-option-title">Fetch + Fast-forward <span class="badge bg-success-subtle text-success">Safe</span></div>
                                <div class="pull-option-desc">Updates only if no local commits diverge.</div>
                            </div>
                        </label>
                        <label class="pull-option">
                            <input type="radio" name="pullMode" value="ff_merge">
                            <div>
                                <div class="pull-option-title">Fetch + Merge <span class="badge bg-warning-subtle text-warning">Recommended</span></div>
                                <div class="pull-option-desc">Standard <code>git pull</code>. May create merge conflicts.</div>
                            </div>
                        </label>
                        <label class="pull-option">
                            <input type="radio" name="pullMode" value="backup_branch">
                            <div>
                                <div class="pull-option-title">Backup + Hard Reset <span class="badge bg-info-subtle text-info">Safest Reset</span></div>
                                <div class="pull-option-desc">Creates a backup branch, then hard-resets to origin.</div>
                            </div>
                        </label>
                        <label class="pull-option">
                            <input type="radio" name="pullMode" value="hard_reset">
                            <div>
                                <div class="pull-option-title">Hard Reset <span class="badge bg-danger-subtle text-danger">Destructive</span></div>
                                <div class="pull-option-desc">Discards all local commits and matches GitHub exactly.</div>
                            </div>
                        </label>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-primary px-4" id="pullBtn">
                            <span class="spinner-border spinner-border-sm d-none me-2" id="pullSpinner"></span>
                            <i class="bi bi-cloud-arrow-down-fill me-1" id="pullIcon"></i>
                            <span id="pullText">Pull From GitHub</span>
                        </button>

                        <button type="button" class="btn btn-light" id="fetchBtn">
                            <span class="spinner-border spinner-border-sm d-none me-2" id="fetchSpinner"></span>
                            <i class="bi bi-arrow-repeat me-1" id="fetchIcon"></i>
                            <span id="fetchText">Fetch Only</span>
                        </button>

                        <button type="button" class="btn btn-outline-secondary" id="diagBtn">
                            <span class="spinner-border spinner-border-sm d-none me-2" id="diagSpinner"></span>
                            <i class="bi bi-wrench-adjustable me-1" id="diagIcon"></i>
                            <span id="diagText">Run Diagnostics</span>
                        </button>
                    </div>

                    <!-- Hint about the getaddrinfo error -->
                    <div class="alert alert-info mt-4 mb-0 small" id="dnsFixHint" style="display:none;">
                        <strong><i class="bi bi-lightbulb"></i> Network fix required</strong><br>
                        Your Git can't reach GitHub from PHP's context (<code>getaddrinfo() thread failed to start</code>).
                        <br><br>
                        <strong>Fix — run these in PowerShell as Administrator:</strong>
                        <pre class="mt-2 mb-0" style="background:#0f172a;color:#cbd5e1;padding:10px;border-radius:6px;font-size:12px;">netsh advfirewall firewall add rule name="Localhost TCP Dynamic" dir=in action=allow protocol=TCP localip=127.0.0.1 localport=49152-65535
netsh advfirewall firewall add rule name="Localhost TCP Dynamic Out" dir=out action=allow protocol=TCP remoteip=127.0.0.1 remoteport=49152-65535</pre>
                        <br>
                        Then <strong>restart Apache</strong> from XAMPP Control Panel and try again.
                    </div>

                    <!-- Output console -->
                    <div class="output-console mt-4 d-none" id="outputConsole">
                        <div class="output-header">
                            <span><i class="bi bi-terminal"></i> Git Output</span>
                            <button type="button" class="btn-close btn-close-white btn-sm" id="closeConsole"></button>
                        </div>
                        <pre class="output-body" id="outputBody"></pre>
                    </div>

                </div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $assetPath ?>/js/sidebar.js"></script>
    <script>
        window.UPDATE_CONFIG = {
            pullUrl: '../../backend/admin/pull.php',
            fetchUrl: '../../backend/admin/pull.php',
            diagUrl: '../../backend/admin/pull.php'
        };
    </script>
    <script src="<?= $assetPath ?>/js/update.js"></script>

</body>

</html>