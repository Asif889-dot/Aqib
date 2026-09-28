<?php
/**
 * Logout Handler
 * Location: logout.php (project root)
 * 
 * Securely destroys the user's session, clears cookies, and redirects to login.
 * 
 * Optional query params:
 *   ?reason=timeout   → shows a "session expired" message on the login page
 *   ?reason=manual    → standard manual logout
 */

declare(strict_types=1);

// ============================================================
// 1. LOG SETUP
// ============================================================
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

/**
 * Log the logout event.
 */
function logLogout(array $data): void {
    global $logDir;
    $entry = sprintf("[%s] [LOGOUT] [IP: %s] %s\n",
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? '-',
        json_encode($data, JSON_UNESCAPED_UNICODE));
    @file_put_contents($logDir . '/login.log', $entry, FILE_APPEND | LOCK_EX);
}

// ============================================================
// 2. START SESSION (needed to read & destroy it)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// ============================================================
// 3. CAPTURE USER INFO BEFORE DESTROYING (for logging)
// ============================================================
$userId    = $_SESSION['user_id']    ?? null;
$userEmail = $_SESSION['user_email'] ?? 'anonymous';
$reason    = trim((string)($_GET['reason'] ?? 'manual'));

// Log the logout
logLogout([
    'user_id' => $userId,
    'email'   => $userEmail,
    'reason'  => $reason,
]);

// ============================================================
// 4. CLEAR REMEMBER-ME COOKIE + REVOKE TOKEN
// ============================================================
if (isset($_COOKIE['remember_me'])) {
    // Format: selector:validator
    $cookieValue = (string)$_COOKIE['remember_me'];

    if (strpos($cookieValue, ':') !== false) {
        [$selector, $validator] = explode(':', $cookieValue, 2);

        if ($selector !== '') {
            try {
                require_once __DIR__ . '/config/database.php';
                $db  = new Database();
                $pdo = $db->getConnection();

                // Delete this specific token from the DB
                $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :sel LIMIT 1');
                $stmt->execute([':sel' => $selector]);
            } catch (Exception $e) {
                // Non-fatal — silently ignore
            }
        }
    }

    // Expire the cookie in the browser
    setcookie('remember_me', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Also unset from $_COOKIE so PHP doesn't see it again this request
    unset($_COOKIE['remember_me']);
}

// ============================================================
// 5. CLEAR SESSION DATA
// ============================================================
$_SESSION = [];

// ============================================================
// 6. DELETE THE SESSION COOKIE
// ============================================================
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path']   ?: '/',
            'domain'   => $params['domain'] ?: '',
            'secure'   => (bool)($params['secure']   ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

// ============================================================
// 7. DESTROY SERVER-SIDE SESSION
// ============================================================
session_destroy();

// ============================================================
// 8. REGENERATE ID (defensive — prevents session fixation reuse)
//    This must be after session_destroy.
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id(true);
    $_SESSION = [];
    session_destroy();
}

// ============================================================
// 9. REDIRECT TO LOGIN
// ============================================================
$redirect = 'index.php';
if ($reason !== '' && $reason !== 'manual') {
    $redirect .= '?logged_out=' . urlencode($reason);
}

header('Location: ' . $redirect);
exit;