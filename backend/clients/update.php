<?php
/**
 * Update Client — Backend Handler
 * Location: backend/clients/update.php
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
header('Cache-Control: no-store');

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

// ---------- Crypto (same as add.php) ----------
function getEncryptionKey(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
    $env = getenv('IRIS_ENC_KEY');
    if (is_string($env) && strlen($env) === 32) return $cached = $env;
    return $cached = 'AqibsbLocalDevKey_2024_ChangeMe!!';
}

function encryptString(string $plain, string $key): string {
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) throw new Exception('Encryption failed.');
    return base64_encode($iv . $cipher);
}

function decryptString(string $encoded, string $key): string {
    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) < 17) throw new Exception('Invalid payload.');
    $iv = substr($data, 0, 16);
    $cipher = substr($data, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) throw new Exception('Decryption failed.');
    return $plain;
}

// ---------- File helpers ----------
function handleUpload(string $field, string $subdir, int $maxBytes, array $allowed): ?string {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("Upload failed for {$field}.");
    if ($file['size'] > $maxBytes) throw new Exception("File too large.");
    if (!is_uploaded_file($file['tmp_name'])) throw new Exception('Invalid upload.');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) throw new Exception('Only JPG, PNG, or WEBP images are allowed.');

    $ext = match ($mime) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png'               => 'png',
        'image/webp'              => 'webp',
        default                   => 'jpg',
    };

    $targetDir = __DIR__ . '/../../uploads/' . $subdir . '/';
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) throw new Exception('Cannot create folder.');

    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath = $targetDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) throw new Exception('Failed to save file.');

    return 'uploads/' . $subdir . '/' . $filename;
}

function deleteUploadedFile(?string $relPath): void {
    if (!$relPath) return;
    $abs = __DIR__ . '/../../' . $relPath;
    if (is_file($abs)) @unlink($abs);
}

// ============================================================
// AUTH
// ============================================================
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    respond(false, 'Session expired. Please login again.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed.');
}

// ============================================================
// DB
// ============================================================
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

// ============================================================
// COLLECT INPUT
// ============================================================
$id           = (int)($_POST['id']              ?? 0);
$name         = trim((string)($_POST['name']           ?? ''));
$fatherName   = trim((string)($_POST['father_name']    ?? ''));
$irisEmail    = trim((string)($_POST['iris_email']     ?? ''));
$irisPassword = (string)($_POST['iris_password']       ?? '');
$cnic         = trim((string)($_POST['cnic']           ?? ''));
$entryYear    = trim((string)($_POST['entry_year']     ?? ''));
$amount       = trim((string)($_POST['amount']         ?? '0'));
$status       = trim((string)($_POST['status']         ?? 'active'));
$whatsapp     = trim((string)($_POST['whatsapp']       ?? ''));
$removeProfile = isset($_POST['remove_profile_pic']) && $_POST['remove_profile_pic'] === '1';
$removeCnicFront = isset($_POST['remove_cnic_front_pic']) && $_POST['remove_cnic_front_pic'] === '1';
$removeCnicBack  = isset($_POST['remove_cnic_back_pic'])  && $_POST['remove_cnic_back_pic'] === '1';

if ($id <= 0) respond(false, 'Invalid client ID.');

// ============================================================
// FETCH EXISTING CLIENT
// ============================================================
try {
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();
} catch (PDOException $e) {
    respond(false, 'Failed to load client.');
}

if (!$existing) respond(false, 'Client not found.');

// ============================================================
// VALIDATE
// ============================================================
$errors = [];

if ($name === '' || mb_strlen($name) < 3)               $errors['name']         = 'Name must be at least 3 characters.';
if ($name !== '' && mb_strlen($name) > 150)             $errors['name']         = 'Name is too long.';

if ($fatherName === '' || mb_strlen($fatherName) < 3)   $errors['father_name']  = 'Father name must be at least 3 characters.';
if ($fatherName !== '' && mb_strlen($fatherName) > 150) $errors['father_name']  = 'Father name is too long.';

if (!filter_var($irisEmail, FILTER_VALIDATE_EMAIL))     $errors['iris_email']   = 'Invalid email address.';
if (mb_strlen($irisEmail) > 190)                        $errors['iris_email']   = 'Email is too long.';

// Password: only validate if a new one was provided
$changePassword = $irisPassword !== '';
if ($changePassword && mb_strlen($irisPassword) < 6)    $errors['iris_password']= 'Password must be at least 6 characters.';
if ($changePassword && mb_strlen($irisPassword) > 100)  $errors['iris_password']= 'Password is too long.';

if (!preg_match('/^\d{5}-\d{7}-\d$/', $cnic))           $errors['cnic']         = 'CNIC must be in format 12345-1234567-1.';

$currentYear = (int)date('Y');
if (!ctype_digit($entryYear) || (int)$entryYear < 2000 || (int)$entryYear > $currentYear) {
    $errors['entry_year'] = 'Invalid entry year.';
}

if (!is_numeric($amount) || (float)$amount < 0)         $errors['amount']       = 'Amount must be a positive number.';
if (!in_array($status, ['active', 'inactive'], true))   $errors['status']       = 'Invalid status.';

$whatsappClean = preg_replace('/[\s\-]/', '', $whatsapp);
if (!preg_match('/^(?:\+92|0)?3\d{9}$/', $whatsappClean)) $errors['whatsapp']   = 'Invalid WhatsApp number.';

if (!empty($errors)) {
    respond(false, 'Please fix the highlighted fields.', ['errors' => $errors]);
}

// Normalize WhatsApp
if (substr($whatsappClean, 0, 3) === '+92')      $whatsappClean = '0' . substr($whatsappClean, 3);
elseif (substr($whatsappClean, 0, 2) === '92')   $whatsappClean = '0' . substr($whatsappClean, 2);
elseif (substr($whatsappClean, 0, 1) === '3')    $whatsappClean = '0' . $whatsappClean;

// ============================================================
// DUPLICATE CNIC CHECK (exclude self)
// ============================================================
try {
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE cnic = :cnic AND id != :id LIMIT 1');
    $stmt->execute([':cnic' => $cnic, ':id' => $id]);
    if ($stmt->fetch()) {
        respond(false, 'Another client with this CNIC already exists.', [
            'errors' => ['cnic' => 'This CNIC is already registered to another client.'],
        ]);
    }
} catch (PDOException $e) {
    respond(false, 'Database error while checking CNIC.');
}

// ============================================================
// HANDLE UPLOADS
// ============================================================
$allowedImages = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

$newProfilePic   = null;
$newCnicFront    = null;
$newCnicBack     = null;

try {
    $newProfilePic = handleUpload('profile_pic',    'profiles', 2 * 1024 * 1024, $allowedImages);
    $newCnicFront  = handleUpload('cnic_front_pic', 'cnic',     3 * 1024 * 1024, $allowedImages);
    $newCnicBack   = handleUpload('cnic_back_pic',  'cnic',     3 * 1024 * 1024, $allowedImages);
} catch (Exception $e) {
    deleteUploadedFile($newProfilePic);
    deleteUploadedFile($newCnicFront);
    deleteUploadedFile($newCnicBack);
    logIt('upload_error', ['error' => $e->getMessage()]);
    respond(false, $e->getMessage());
}

// Determine final file paths
$finalProfilePic = $existing['profile_pic'];
$finalCnicFront  = $existing['cnic_front_pic'];
$finalCnicBack   = $existing['cnic_back_pic'];

if ($newProfilePic) { deleteUploadedFile($existing['profile_pic']); $finalProfilePic = $newProfilePic; }
elseif ($removeProfile) { deleteUploadedFile($existing['profile_pic']); $finalProfilePic = null; }

if ($newCnicFront) { deleteUploadedFile($existing['cnic_front_pic']); $finalCnicFront = $newCnicFront; }
elseif ($removeCnicFront) { deleteUploadedFile($existing['cnic_front_pic']); $finalCnicFront = null; }

if ($newCnicBack) { deleteUploadedFile($existing['cnic_back_pic']); $finalCnicBack = $newCnicBack; }
elseif ($removeCnicBack) { deleteUploadedFile($existing['cnic_back_pic']); $finalCnicBack = null; }

// ============================================================
// ENCRYPT NEW PASSWORD (if changed)
// ============================================================
$finalPassword = $existing['iris_password'];
if ($changePassword) {
    try {
        $finalPassword = encryptString($irisPassword, getEncryptionKey());
    } catch (Exception $e) {
        deleteUploadedFile($newProfilePic);
        deleteUploadedFile($newCnicFront);
        deleteUploadedFile($newCnicBack);
        respond(false, 'Could not encrypt password.');
    }
}

// ============================================================
// UPDATE
// ============================================================
$updatedId = 0;
try {
    $sql = 'UPDATE clients SET
                name           = :name,
                father_name    = :father_name,
                iris_email     = :iris_email,
                iris_password  = :iris_password,
                cnic           = :cnic,
                entry_year     = :entry_year,
                amount         = :amount,
                status         = :status,
                whatsapp       = :whatsapp,
                profile_pic    = :profile_pic,
                cnic_front_pic = :cnic_front_pic,
                cnic_back_pic  = :cnic_back_pic
            WHERE id = :id';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':name',           $name,             PDO::PARAM_STR);
    $stmt->bindValue(':father_name',    $fatherName,       PDO::PARAM_STR);
    $stmt->bindValue(':iris_email',     $irisEmail,        PDO::PARAM_STR);
    $stmt->bindValue(':iris_password',  $finalPassword,    PDO::PARAM_STR);
    $stmt->bindValue(':cnic',           $cnic,             PDO::PARAM_STR);
    $stmt->bindValue(':entry_year',     (int)$entryYear,   PDO::PARAM_INT);
    $stmt->bindValue(':amount',         (float)$amount);
    $stmt->bindValue(':status',         $status,           PDO::PARAM_STR);
    $stmt->bindValue(':whatsapp',       $whatsappClean,    PDO::PARAM_STR);
    $stmt->bindValue(':profile_pic',    $finalProfilePic,  $finalProfilePic === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':cnic_front_pic', $finalCnicFront,   $finalCnicFront === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':cnic_back_pic',  $finalCnicBack,    $finalCnicBack === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':id',             $id,               PDO::PARAM_INT);
    $stmt->execute();
    $updatedId = $id;
} catch (PDOException $e) {
    // Roll back only newly uploaded files
    deleteUploadedFile($newProfilePic);
    deleteUploadedFile($newCnicFront);
    deleteUploadedFile($newCnicBack);
    logIt('update_error', ['error' => $e->getMessage(), 'id' => $id]);
    respond(false, 'Failed to update client. Please try again.');
}

logIt('client_updated', ['id' => $id, 'by' => $_SESSION['user_id'] ?? null]);

respond(true, 'Client updated successfully!', [
    'client_id' => $updatedId,
    'redirect'  => '../../frontend/clients/view.php?id=' . $updatedId . '&msg=updated',
]);