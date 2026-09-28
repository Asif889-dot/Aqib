<?php
/**
 * Add Client — Backend Handler
 * Location: backend/clients/add.php
 * PHP: 8.0.30 compatible
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
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// ============================================================
// HELPERS
// ============================================================
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

// ============================================================
// AES-256-CBC ENCRYPTION HELPERS
// ============================================================

/**
 * Get the encryption key.
 * Priority:
 *   1. Environment variable IRIS_ENC_KEY (must be exactly 32 bytes)
 *   2. Fallback dev key (32 bytes, fixed) — with a warning logged
 *
 * Cached per-request so it never changes between encrypt/decrypt calls.
 */
function getEncryptionKey(): string
{
    static $cachedKey = null;
    if ($cachedKey !== null) {
        return $cachedKey;
    }

    $envKey = getenv('IRIS_ENC_KEY');

    if (is_string($envKey) && strlen($envKey) === 32) {
        $cachedKey = $envKey;
        return $cachedKey;
    }

    // Fallback for local dev — MUST be replaced with env var in production.
    // Exactly 32 bytes so it's a valid AES-256 key.
    $cachedKey = 'AqibsbLocalDevKey_2024_ChangeMe!!';

    logIt('encryption_warning', [
        'msg' => 'Using fallback encryption key. Set IRIS_ENC_KEY env var in production.'
    ]);

    return $cachedKey;
}

/**
 * Encrypt a plain-text string with AES-256-CBC.
 * Returns base64(iv + cipher).
 */
function encryptString(string $plain, string $key): string
{
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new Exception('Encryption failed.');
    }
    return base64_encode($iv . $cipher);
}

/**
 * Decrypt a base64(iv + cipher) string encrypted with encryptString().
 */
function decryptString(string $encoded, string $key): string
{
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

// ============================================================
// FILE UPLOAD HELPERS
// ============================================================

/**
 * Handle one uploaded image.
 * @throws Exception on validation failure
 * @return string|null relative path (e.g., "uploads/profiles/xxx.jpg") or null
 */
function handleUpload(string $field, string $subdir, int $maxBytes, array $allowed): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload failed for {$field} (code {$file['error']}).");
    }
    if ($file['size'] > $maxBytes) {
        throw new Exception("File too large (max " . round($maxBytes / 1024 / 1024, 1) . "MB).");
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Invalid upload.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        throw new Exception('Only JPG, PNG, or WEBP images are allowed.');
    }

    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $ext = 'jpg';
            break;
        case 'image/png':
            $ext = 'png';
            break;
        case 'image/webp':
            $ext = 'webp';
            break;
        default:
            $ext = 'jpg';
    }

    $targetDir = __DIR__ . '/../../uploads/' . $subdir . '/';
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
        throw new Exception('Cannot create upload folder.');
    }

    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath = $targetDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('Failed to save uploaded file.');
    }

    return 'uploads/' . $subdir . '/' . $filename;
}

/**
 * Delete an uploaded file by its relative path (used on rollback).
 */
function deleteUploadedFile(?string $relPath): void
{
    if (!$relPath) return;
    $abs = __DIR__ . '/../../' . $relPath;
    if (is_file($abs)) {
        @unlink($abs);
    }
}

// ============================================================
// AUTH
// ============================================================
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    respond(false, 'Session expired. Please login again.');
}

// ============================================================
// METHOD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed.');
}

// ============================================================
// DB
// ============================================================
require_once __DIR__ . '/../../config/database.php';

$pdo = null; // initialize to silence Intelephense P1116

try {
    $db  = new Database();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    logIt('db_error', ['error' => $e->getMessage()]);
    respond(false, 'Database connection failed.');
}
if (!$pdo instanceof PDO) respond(false, 'Database unavailable.');

// ============================================================
// COLLECT INPUT
// ============================================================
$name         = trim((string)($_POST['name']          ?? ''));
$fatherName   = trim((string)($_POST['father_name']   ?? ''));
$irisEmail    = trim((string)($_POST['iris_email']    ?? ''));
$irisPassword = (string)($_POST['iris_password']      ?? '');
$cnic         = trim((string)($_POST['cnic']          ?? ''));
$entryYear    = trim((string)($_POST['entry_year']    ?? ''));
$amount       = trim((string)($_POST['amount']        ?? '0'));
$status       = trim((string)($_POST['status']        ?? 'active'));
$whatsapp     = trim((string)($_POST['whatsapp']      ?? ''));

// ============================================================
// VALIDATE
// ============================================================
$errors = [];

if ($name === '' || mb_strlen($name) < 3)                          $errors['name']         = 'Name must be at least 3 characters.';
if ($name !== '' && mb_strlen($name) > 150)                        $errors['name']         = 'Name is too long.';

if ($fatherName === '' || mb_strlen($fatherName) < 3)              $errors['father_name']  = 'Father name must be at least 3 characters.';
if ($fatherName !== '' && mb_strlen($fatherName) > 150)            $errors['father_name']  = 'Father name is too long.';

if (!filter_var($irisEmail, FILTER_VALIDATE_EMAIL))                $errors['iris_email']   = 'Invalid email address.';
if (mb_strlen($irisEmail) > 190)                                   $errors['iris_email']   = 'Email is too long.';

if (mb_strlen($irisPassword) < 6)                                  $errors['iris_password']= 'Password must be at least 6 characters.';
if (mb_strlen($irisPassword) > 100)                                $errors['iris_password']= 'Password is too long.';

if (!preg_match('/^\d{5}-\d{7}-\d$/', $cnic))                      $errors['cnic']         = 'CNIC must be in format 12345-1234567-1.';

$currentYear = (int)date('Y');
if (!ctype_digit($entryYear) || (int)$entryYear < 2000 || (int)$entryYear > $currentYear) {
    $errors['entry_year'] = 'Invalid entry year.';
}

if (!is_numeric($amount) || (float)$amount < 0)                    $errors['amount']       = 'Amount must be a positive number.';

if (!in_array($status, ['active', 'inactive'], true))              $errors['status']       = 'Invalid status.';

$whatsappClean = preg_replace('/[\s\-]/', '', $whatsapp);
if (!preg_match('/^(?:\+92|0)?3\d{9}$/', $whatsappClean))          $errors['whatsapp']     = 'Invalid WhatsApp number.';

if (!empty($errors)) {
    respond(false, 'Please fix the highlighted fields.', ['errors' => $errors]);
}

// ============================================================
// NORMALIZE WHATSAPP  →  03XXXXXXXXX
// ============================================================
if (substr($whatsappClean, 0, 3) === '+92') {
    $whatsappClean = '0' . substr($whatsappClean, 3);
} elseif (substr($whatsappClean, 0, 2) === '92') {
    $whatsappClean = '0' . substr($whatsappClean, 2);
} elseif (substr($whatsappClean, 0, 1) === '3') {
    $whatsappClean = '0' . $whatsappClean;
}

// ============================================================
// DUPLICATE CNIC CHECK
// ============================================================
try {
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE cnic = :cnic LIMIT 1');
    $stmt->execute([':cnic' => $cnic]);
    if ($stmt->fetch()) {
        respond(false, 'A client with this CNIC already exists.', [
            'errors' => ['cnic' => 'This CNIC is already registered.'],
        ]);
    }
} catch (PDOException $e) {
    logIt('db_check_error', ['error' => $e->getMessage()]);
    respond(false, 'Database error while checking CNIC.');
}

// ============================================================
// UPLOAD FILES
// ============================================================
$allowedImages = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

$profilePic = null;
$cnicFront  = null;
$cnicBack   = null;

try {
    $profilePic = handleUpload('profile_pic',    'profiles', 2 * 1024 * 1024, $allowedImages);
    $cnicFront  = handleUpload('cnic_front_pic', 'cnic',     3 * 1024 * 1024, $allowedImages);
    $cnicBack   = handleUpload('cnic_back_pic',  'cnic',     3 * 1024 * 1024, $allowedImages);
} catch (Exception $e) {
    // Roll back any files uploaded before the failure
    deleteUploadedFile($profilePic);
    deleteUploadedFile($cnicFront);
    deleteUploadedFile($cnicBack);

    logIt('upload_error', ['error' => $e->getMessage()]);
    respond(false, $e->getMessage());
}

// ============================================================
// ENCRYPT IRIS PASSWORD
// ============================================================
$irisPasswordEncrypted = ''; // initialize to silence Intelephense P1116

try {
    $irisPasswordEncrypted = encryptString($irisPassword, getEncryptionKey());
} catch (Exception $e) {
    // Roll back uploads on failure
    deleteUploadedFile($profilePic);
    deleteUploadedFile($cnicFront);
    deleteUploadedFile($cnicBack);

    logIt('encrypt_error', ['error' => $e->getMessage()]);
    respond(false, 'Could not securely store IRIS password.');
}

// ============================================================
// INSERT INTO DATABASE
// ============================================================
$newId = 0; // initialize to silence Intelephense P1116

try {
    $sql = 'INSERT INTO clients
              (name, father_name, iris_email, iris_password, cnic, entry_year,
               amount, status, whatsapp, profile_pic, cnic_front_pic, cnic_back_pic, created_by)
            VALUES
              (:name, :father_name, :iris_email, :iris_password, :cnic, :entry_year,
               :amount, :status, :whatsapp, :profile_pic, :cnic_front_pic, :cnic_back_pic, :created_by)';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':name',           $name,                  PDO::PARAM_STR);
    $stmt->bindValue(':father_name',    $fatherName,            PDO::PARAM_STR);
    $stmt->bindValue(':iris_email',     $irisEmail,             PDO::PARAM_STR);
    $stmt->bindValue(':iris_password',  $irisPasswordEncrypted, PDO::PARAM_STR);
    $stmt->bindValue(':cnic',           $cnic,                  PDO::PARAM_STR);
    $stmt->bindValue(':entry_year',     (int)$entryYear,        PDO::PARAM_INT);
    $stmt->bindValue(':amount',         (float)$amount);
    $stmt->bindValue(':status',         $status,                PDO::PARAM_STR);
    $stmt->bindValue(':whatsapp',       $whatsappClean,         PDO::PARAM_STR);
    $stmt->bindValue(':profile_pic',    $profilePic,            $profilePic === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':cnic_front_pic', $cnicFront,             $cnicFront === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':cnic_back_pic',  $cnicBack,              $cnicBack === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':created_by',     $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
    $stmt->execute();

    $newId = (int)$pdo->lastInsertId();
} catch (PDOException $e) {
    // Roll back uploads on DB failure
    deleteUploadedFile($profilePic);
    deleteUploadedFile($cnicFront);
    deleteUploadedFile($cnicBack);

    logIt('insert_error', ['error' => $e->getMessage()]);
    respond(false, 'Failed to save client. Please try again.');
}

// ============================================================
// SUCCESS
// ============================================================
logIt('client_added', [
    'id'   => $newId,
    'cnic' => $cnic,
    'by'   => $_SESSION['user_id'] ?? null,
]);

respond(true, 'Client added successfully!', [
    'client_id' => $newId,
    'redirect'  => '../../frontend/clients/list.php',
]);