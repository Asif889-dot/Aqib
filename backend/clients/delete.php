<?php
/**
 * Delete Client — Backend
 * Location: backend/clients/delete.php
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

function respond(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function logIt(string $type, array $data = []): void {
    global $logDir;
    $entry = sprintf("[%s] [%s] [IP: %s] %s\n",
        date('Y-m-d H:i:s'), strtoupper($type),
        $_SERVER['REMOTE_ADDR'] ?? '-', json_encode($data));
    @file_put_contents($logDir . '/clients.log', $entry, FILE_APPEND | LOCK_EX);
}

// Auth
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    respond(false, 'Session expired. Please login again.');
}

// Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed.');
}

// Only admins can delete
if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    respond(false, 'Only administrators can delete clients.');
}

// Input
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    respond(false, 'Invalid client ID.');
}

// DB
require_once __DIR__ . '/../../config/database.php';

$pdo = null;
try {
    $db  = new Database();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    logIt('db_error', ['error' => $e->getMessage()]);
    respond(false, 'Database connection failed.');
}
if (!$pdo instanceof PDO) respond(false, 'Database unavailable.');

// Fetch client to get file paths + name (for log)
try {
    $stmt = $pdo->prepare('SELECT id, name, profile_pic, cnic_front_pic, cnic_back_pic FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $client = $stmt->fetch();
} catch (PDOException $e) {
    logIt('delete_fetch_error', ['error' => $e->getMessage()]);
    respond(false, 'Database error.');
}

if (!$client) {
    respond(false, 'Client not found.');
}

// Delete from DB
try {
    $stmt = $pdo->prepare('DELETE FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
} catch (PDOException $e) {
    logIt('delete_db_error', ['error' => $e->getMessage()]);
    respond(false, 'Failed to delete client.');
}

// Remove uploaded files (best-effort)
foreach (['profile_pic', 'cnic_front_pic', 'cnic_back_pic'] as $field) {
    if (!empty($client[$field])) {
        $abs = __DIR__ . '/../../' . $client[$field];
        if (is_file($abs)) @unlink($abs);
    }
}

logIt('client_deleted', [
    'id'   => $id,
    'name' => $client['name'],
    'by'   => $_SESSION['user_id'] ?? null,
]);

respond(true, 'Client deleted successfully.');