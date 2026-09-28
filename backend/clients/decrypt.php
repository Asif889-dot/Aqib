<?php
/**
 * Decrypt IRIS Password — AJAX Endpoint
 * Location: backend/clients/decrypt.php
 *
 * POST: id=<client_id>
 * Returns: { success, password }  (only for admins, logged)
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

function respond(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function logIt(string $type, array $data = []): void {
    global $logDir;
    $entry = sprintf("[%s] [%s] [IP: %s] %s\n",
        date('Y-m-d H:i:s'), strtoupper($type),
        $_SERVER['REMOTE_ADDR'] ?? '-', json_encode($data, JSON_UNESCAPED_UNICODE));
    @file_put_contents($logDir . '/clients.log', $entry, FILE_APPEND | LOCK_EX);
}

// ---------- Crypto ----------
function getEncryptionKey(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
    $env = getenv('IRIS_ENC_KEY');
    if (is_string($env) && strlen($env) === 32) return $cached = $env;
    return $cached = 'AqibsbLocalDevKey_2024_ChangeMe!!';
}

function decryptString(string $encoded, string $key): string {
    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) < 17) {
        throw new Exception('Invalid encrypted payload.');
    }
    $iv = substr($data, 0, 16);
    $cipher = substr($data, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) {
        throw new Exception('Decryption failed.');
    }
    return $plain;
}

// ---------- Auth ----------
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    respond(false, 'Session expired. Please login again.');
}

// Only admins can reveal passwords
if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    respond(false, 'Only administrators can view IRIS passwords.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed.');
}

// ---------- Input ----------
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) respond(false, 'Invalid client ID.');

// ---------- DB ----------
require_once __DIR__ . '/../../config/database.php';

$pdo = null;
try {
    $db = new Database();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    respond(false, 'Database connection failed.');
}
if (!$pdo instanceof PDO) respond(false, 'Database unavailable.');

try {
    $stmt = $pdo->prepare('SELECT id, name, iris_email, iris_password FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $client = $stmt->fetch();
} catch (PDOException $e) {
    respond(false, 'Database error.');
}

if (!$client) respond(false, 'Client not found.');

if (empty($client['iris_password'])) {
    respond(false, 'No IRIS password stored for this client.');
}

// ---------- Decrypt ----------
try {
    $plain = decryptString($client['iris_password'], getEncryptionKey());
} catch (Exception $e) {
    logIt('decrypt_error', [
        'client_id' => $id,
        'error' => $e->getMessage(),
        'by' => $_SESSION['user_id'] ?? null,
    ]);
    respond(false, 'Could not decrypt password. It may have been encrypted with a different key.');
}

// ---------- Log the reveal ----------
logIt('iris_password_revealed', [
    'client_id'   => $id,
    'client_name' => $client['name'],
    'iris_email'  => $client['iris_email'],
    'by_user_id'  => $_SESSION['user_id'] ?? null,
    'by_email'    => $_SESSION['user_email'] ?? null,
]);

respond(true, 'Password decrypted.', ['password' => $plain]);