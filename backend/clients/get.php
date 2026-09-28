<?php
/**
 * Get Single Client — JSON API
 * Location: backend/clients/get.php
 * 
 * Usage: GET backend/clients/get.php?id=5
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    respond(false, 'Session expired.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) respond(false, 'Invalid client ID.');

require_once __DIR__ . '/../../config/database.php';

$pdo = null;
try {
    $db  = new Database();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    respond(false, 'Database connection failed.');
}
if (!$pdo instanceof PDO) respond(false, 'Database unavailable.');

try {
    $stmt = $pdo->prepare(
        'SELECT id, name, father_name, iris_email, iris_password, cnic, entry_year,
                amount, status, whatsapp, profile_pic, cnic_front_pic, cnic_back_pic,
                created_at, updated_at
         FROM clients WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $client = $stmt->fetch();
} catch (PDOException $e) {
    respond(false, 'Database error.');
}

if (!$client) respond(false, 'Client not found.');

// Never send the raw encrypted password — just indicate whether it's set
$client['has_password'] = !empty($client['iris_password']);
unset($client['iris_password']);

respond(true, 'Client loaded.', ['client' => $client]);