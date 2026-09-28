<?php
/**
 * Login Backend Handler
 * 
 * Location: backend/login.php
 * Project:  C:\xampp\htdocs\aqibsb\
 * 
 * Uses the Database class from config/database.php
 * Database: clients
 */

declare(strict_types=1);

// ============================================================
// 1. ERROR HANDLING
// ============================================================
error_reporting(E_ALL);

// ⚠️ TEMPORARY: set to '1' to see real errors during debugging.
// Change back to '0' once login works.
ini_set('display_errors', '1');
ini_set('log_errors', '1');

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/php-errors.log');

// ============================================================
// 2. SESSION
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// ============================================================
// 3. RESPONSE HEADERS
// ============================================================
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// ============================================================
// 4. CONSTANTS
// ============================================================
define('MIN_PASSWORD_LENGTH', 6);

// ============================================================
// 5. HELPERS
// ============================================================
function sendResponse(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function logEvent(string $type, array $data = []): void
{
    global $logDir;
    $entry = sprintf(
        "[%s] [%s] [IP: %s] %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($type),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        json_encode($data, JSON_UNESCAPED_UNICODE)
    );
    @file_put_contents($logDir . '/login.log', $entry, FILE_APPEND | LOCK_EX);
}

// ============================================================
// 6. METHOD CHECK
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, 'Method Not Allowed.');
}

// ============================================================
// 7. DATABASE CONNECTION
// ============================================================
require_once __DIR__ . '/../config/database.php';

$pdo = null; // ← Initialize to fix Intelephense P1116 warning

try {
    $db  = new Database();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    logEvent('db_error', ['error' => $e->getMessage()]);
    http_response_code(500);
    sendResponse(false, 'Database connection failed: ' . $e->getMessage());
}

// Safety net
if (!$pdo instanceof PDO) {
    http_response_code(500);
    sendResponse(false, 'Database unavailable.');
}

// ============================================================
// 8. INPUT COLLECTION
// ============================================================
$email      = trim((string)($_POST['email'] ?? ''));
$password   = (string)($_POST['password'] ?? '');
$rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === 'on';
$ipAddress  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent  = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);

// ============================================================
// 9. VALIDATION
// ============================================================
if ($email === '' || $password === '') {
    sendResponse(false, 'Please fill in all required fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Please enter a valid email address.');
}

if (mb_strlen($email) > 190) {
    sendResponse(false, 'Email address is too long.');
}

if (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
    sendResponse(false, 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.');
}

if (mb_strlen($password) > 200) {
    sendResponse(false, 'Invalid email or password.');
}

$email = mb_strtolower($email, 'UTF-8');

// ============================================================
// 10. FETCH USER
// ============================================================
try {
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password, role
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();
} catch (PDOException $e) {
    logEvent('db_query_error', ['error' => $e->getMessage()]);
    http_response_code(500);
    sendResponse(false, 'An internal error occurred: ' . $e->getMessage());
}

// ============================================================
// 11. VERIFY CREDENTIALS
// ============================================================
$dummyHash = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

if ($user) {
    $passwordOk = password_verify($password, $user['password']);
} else {
    // Constant-time defense against user enumeration
    password_verify($password, $dummyHash);
    $passwordOk = false;
}

if (!$user || !$passwordOk) {
    logEvent('login_failed', ['email' => $email]);
    sendResponse(false, 'Invalid email or password.');
}

// ============================================================
// 12. AUTO-REHASH (if algorithm changed)
// ============================================================
if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
    try {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = :p WHERE id = :id');
        $stmt->execute([':p' => $newHash, ':id' => $user['id']]);
    } catch (PDOException $e) {
        logEvent('rehash_error', ['error' => $e->getMessage()]);
    }
}

// ============================================================
// 13. SESSION SETUP
// ============================================================
session_regenerate_id(true);

$_SESSION['logged_in']     = true;
$_SESSION['user_id']       = (int)$user['id'];
$_SESSION['user_name']     = $user['name'];
$_SESSION['user_email']    = $user['email'];
$_SESSION['user_role']     = $user['role'];
$_SESSION['login_time']    = time();
$_SESSION['last_activity'] = time();
$_SESSION['ip_address']    = $ipAddress;
$_SESSION['user_agent']    = $userAgent;

// ============================================================
// 14. UPDATE LAST LOGIN (optional — only if column exists)
// ============================================================
try {
    $stmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);
} catch (PDOException $e) {
    // Non-fatal — column may not exist. Ignore.
}

// ============================================================
// 15. SUCCESS RESPONSE
// ============================================================
logEvent('login_success', ['user_id' => $user['id'], 'email' => $email]);

// Redirect — relative to index.php (project root)
$redirect = ($user['role'] === 'admin')
    ? 'frontend/dashboard.php'
    : 'frontend/dashboard.php';

sendResponse(true, 'Login successful! Welcome back, ' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '!', [
    'redirect' => $redirect,
    'user' => [
        'id'    => (int)$user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ],
]);