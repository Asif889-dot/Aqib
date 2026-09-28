<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: ../index.php');
    exit;
}

// ---- Page-specific values ----
$currentPage = 'dashboard';       // highlights this menu item
$assetPath   = '../assets';       // path to /assets from THIS file
$rootPath    = '../';             // path to project root from THIS file
$pageTitle   = 'Dashboard';
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
</head>
<body>

<!-- ============ SIDEBAR (reusable) ============ -->
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<!-- ============ MAIN ============ -->
<div class="main-wrapper">

    <!-- ============ TOPBAR ============ -->
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <main class="content">

        <div class="welcome-banner">
            <div class="welcome-text">
                <h2>Welcome back, <?= htmlspecialchars($userName) ?>! 👋</h2>
                <p>Here's what's happening today.</p>
            </div>
            <div class="welcome-avatar"><?= htmlspecialchars($initials) ?></div>
        </div>

        <!-- ... your dashboard content ... -->

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $assetPath ?>/js/sidebar.js"></script>
<script src="<?= $assetPath ?>/js/dashboard.js"></script>
</body>
</html>