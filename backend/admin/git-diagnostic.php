<?php
/**
 * Git Diagnostics — Verify Git works from PHP
 * Location: backend/admin/git-diagnostic.php
 * ADMIN ONLY
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['logged_in']) || ($_SESSION['user_role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    exit('Access denied.');
}

header('Content-Type: text/html; charset=utf-8');

$projectRoot = realpath(__DIR__ . '/../..');

function runCmd(string $cmd, string $cwd): array {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $env = [
        'PATH'                => getenv('PATH') ?: 'C:\\Program Files\\Git\\cmd;C:\\Windows\\System32',
        'GIT_TERMINAL_PROMPT' => '0',
        'HOME'                => getenv('USERPROFILE') ?: $cwd,
        'USERPROFILE'         => getenv('USERPROFILE') ?: $cwd,
        'SystemRoot'          => getenv('SystemRoot') ?: 'C:\\Windows',
        'windir'              => getenv('windir') ?: 'C:\\Windows',
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, $cwd, $env);
    if (!is_resource($proc)) return ['ok' => false, 'out' => 'proc_open failed', 'code' => -1];
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    return ['ok' => $code === 0, 'out' => trim($out . ($err ? "\n" . $err : '')), 'code' => $code];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Git Diagnostics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 30px; background: #f8f9fc; font-family: system-ui, sans-serif; }
        .card { margin-bottom: 16px; }
        pre { background: #0f172a; color: #cbd5e1; padding: 14px; border-radius: 8px;
              font-size: 12.5px; max-height: 300px; overflow: auto; margin: 0; }
        .ok   { color: #1cc88a; }
        .fail { color: #e74a3b; }
        h1 { font-size: 22px; margin-bottom: 20px; }
        h5 { font-size: 14px; text-transform: uppercase; letter-spacing: .5px;
             color: #6b7280; font-weight: 700; }
    </style>
</head>
<body>
<div class="container">

    <h1>🔧 Git Environment Diagnostics</h1>
    <p class="text-muted">Project root: <code><?= htmlspecialchars($projectRoot) ?></code></p>

    <?php
    $tests = [];

    $tests['PHP proc_open enabled'] = [
        function_exists('proc_open'),
        function_exists('proc_open') ? 'YES' : 'Disabled in php.ini (disable_functions)',
    ];

    $tests['PHP shell_exec enabled'] = [
        function_exists('shell_exec'),
        function_exists('shell_exec') ? 'YES' : 'Disabled (not required, proc_open is used)',
    ];

    $r = runCmd('git --version', $projectRoot);
    $tests['Git installed & on PATH'] = [
        $r['ok'],
        $r['out'],
    ];

    $r = runCmd('where git 2>&1', $projectRoot);
    $tests['Git location'] = [true, $r['out']];

    $gitDir = $projectRoot . '/.git';
    $tests['.git folder exists'] = [is_dir($gitDir), is_dir($gitDir) ? $gitDir : 'Not found — run: git init'];

    $r = runCmd('git remote -v', $projectRoot);
    $tests['Git remotes'] = [!empty(trim($r['out'])), $r['out'] ?: 'No remotes configured'];

    $r = runCmd('git rev-parse --abbrev-ref HEAD', $projectRoot);
    $tests['Current branch'] = [true, $r['out']];

    $r = runCmd('git status --short', $projectRoot);
    $tests['Local changes'] = [true, $r['out'] ?: '(clean — no uncommitted changes)'];

    // Network test 1: DNS
    $r = runCmd('nslookup github.com 2>&1', $projectRoot);
    $tests['DNS resolves github.com'] = [stripos($r['out'], 'Address') !== false || stripos($r['out'], 'Addresses') !== false, $r['out']];

    // Network test 2: TCP reachability via curl (uses same libcurl as git)
    $r = runCmd('git ls-remote --heads origin 2>&1', $projectRoot);
    $tests['git ls-remote origin (network test)'] = [$r['ok'], $r['out']];
    ?>

    <?php foreach ($tests as $label => $result):
        [$passed, $detail] = $result; ?>
        <div class="card">
            <div class="card-body">
                <h5>
                    <span class="<?= $passed ? 'ok' : 'fail' ?>">
                        <?= $passed ? '✅' : '❌' ?>
                    </span>
                    <?= htmlspecialchars($label) ?>
                </h5>
                <pre><?= htmlspecialchars($detail) ?></pre>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="alert alert-info mt-4">
        <strong>If "git ls-remote origin" fails with <code>getaddrinfo() thread failed to start</code>:</strong>
        <ol class="mb-0 mt-2">
            <li>Open <b>PowerShell as Administrator</b> and run:
                <pre class="mt-1">netsh advfirewall firewall add rule name="Localhost TCP Dynamic" dir=in action=allow protocol=TCP localip=127.0.0.1 localport=49152-65535
netsh advfirewall firewall add rule name="Localhost TCP Dynamic Out" dir=out action=allow protocol=TCP remoteip=127.0.0.1 remoteport=49152-65535</pre>
            </li>
            <li>Restart Apache from XAMPP Control Panel</li>
            <li>Reload this page — the network test should now pass ✅</li>
        </ol>
    </div>

    <p class="mt-3">
        <a href="../../frontend/admin/update.php" class="btn btn-primary">← Back to Update Page</a>
        <a href="git-diagnostic.php" class="btn btn-outline-secondary">Re-run diagnostics</a>
    </p>

</div>
</body>
</html>