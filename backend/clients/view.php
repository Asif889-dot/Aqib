<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: ../../index.php');
    exit;
}

$currentPage = 'clients_list';
$assetPath   = '../../assets';
$rootPath    = '../../';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: list.php?msg=notfound'); exit; }

require_once __DIR__ . '/../../config/database.php';

$pdo = null;
$client = null;

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $client = $stmt->fetch();
} catch (Exception $e) {}

if (!$client) { header('Location: list.php?msg=notfound'); exit; }

$pageTitle = $client['name'];

$userName  = $_SESSION['user_name']  ?? 'User';
$userRole  = $_SESSION['user_role']  ?? 'user';
$userEmail = $_SESSION['user_email'] ?? '';
$initials  = '';
foreach (explode(' ', trim($userName)) as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = mb_substr($initials, 0, 2) ?: 'U';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Aqibsb</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $assetPath ?>/css/sidebar.css">
    <link rel="stylesheet" href="<?= $assetPath ?>/css/dashboard.css">
</head>
<body>
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main-wrapper">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    <main class="content">
        <nav class="mb-3">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="list.php">Clients</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($client['name']) ?></li>
            </ol>
        </nav>

        <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>Client updated successfully!
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-header">
                <h3 class="panel-title"><?= htmlspecialchars($client['name']) ?></h3>
                <div class="panel-actions">
                    <a href="edit.php?id=<?= $id ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-pencil-fill"></i> Edit
                    </a>
                    <a href="list.php" class="btn btn-light btn-sm">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            <div class="panel-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>Father Name:</strong> <?= htmlspecialchars($client['father_name']) ?></div>
                    <div class="col-md-6"><strong>CNIC:</strong> <?= htmlspecialchars($client['cnic']) ?></div>
                    <div class="col-md-6"><strong>IRIS Email:</strong> <?= htmlspecialchars($client['iris_email']) ?></div>
                    <div class="col-md-6"><strong>WhatsApp:</strong> <?= htmlspecialchars($client['whatsapp']) ?></div>
                    <div class="col-md-6"><strong>Entry Year:</strong> <?= (int)$client['entry_year'] ?></div>
                    <div class="col-md-6"><strong>Amount:</strong> Rs <?= number_format((float)$client['amount'], 2) ?></div>
                    <div class="col-md-6"><strong>Status:</strong>
                        <span class="badge bg-<?= $client['status'] === 'active' ? 'success' : 'danger' ?>">
                            <?= ucfirst($client['status']) ?>
                        </span>
                    </div>
                    <div class="col-md-6"><strong>Created:</strong> <?= date('M d, Y H:i', strtotime($client['created_at'])) ?></div>
                </div>

                <?php if (!empty($client['profile_pic']) || !empty($client['cnic_front_pic']) || !empty($client['cnic_back_pic'])): ?>
                    <hr class="my-4">
                    <h5 class="mb-3">Uploaded Images</h5>
                    <div class="row g-3">
                        <?php if (!empty($client['profile_pic'])): ?>
                            <div class="col-md-4">
                                <strong>Profile</strong><br>
                                <img src="<?= $rootPath . htmlspecialchars($client['profile_pic']) ?>" class="img-fluid rounded mt-2">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($client['cnic_front_pic'])): ?>
                            <div class="col-md-4">
                                <strong>CNIC Front</strong><br>
                                <img src="<?= $rootPath . htmlspecialchars($client['cnic_front_pic']) ?>" class="img-fluid rounded mt-2">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($client['cnic_back_pic'])): ?>
                            <div class="col-md-4">
                                <strong>CNIC Back</strong><br>
                                <img src="<?= $rootPath . htmlspecialchars($client['cnic_back_pic']) ?>" class="img-fluid rounded mt-2">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/sidebar.js"></script>
</body>
</html>