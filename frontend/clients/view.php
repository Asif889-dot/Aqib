<?php
/**
 * View Client — Single Client Details
 * Location: frontend/clients/view.php
 */

session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: ../../index.php');
    exit;
}

$currentPage = 'clients_list';
$assetPath   = '../../assets';
$rootPath    = '../../';
$pageTitle   = 'Client Details';

$userName  = $_SESSION['user_name']  ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$userRole  = $_SESSION['user_role']  ?? 'user';
$initials  = '';
foreach (explode(' ', trim($userName)) as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = mb_substr($initials, 0, 2) ?: 'U';

// ============================================================
// LOAD CLIENT
// ============================================================
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: list.php?msg=notfound');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$pdo = null;
$client = null;
$loadError = '';

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $client = $stmt->fetch();

    if (!$client) {
        header('Location: list.php?msg=notfound');
        exit;
    }
} catch (Exception $e) {
    $loadError = 'Could not load client: ' . $e->getMessage();
}

// ---------- Helpers ----------
function nameInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach ($parts as $part) {
        if ($part !== '') $out .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return mb_substr($out, 0, 2) ?: '?';
}

function hasImage(?string $path): bool {
    if (empty($path)) return false;
    return file_exists(__DIR__ . '/../../' . $path);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($client['name'] ?? 'Client') ?> — Aqibsb Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/view-client.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <main class="content">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="list.php">Clients</a></li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= htmlspecialchars($client['name'] ?? 'Client') ?>
                </li>
            </ol>
        </nav>

        <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <div>Client updated successfully!</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($loadError): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($loadError) ?>
            </div>
        <?php else: ?>

        <!-- ============ HERO CARD ============ -->
        <div class="panel profile-hero mb-4">
            <div class="profile-hero-bg"></div>
            <div class="panel-body profile-hero-body">

                <div class="profile-avatar-wrap">
                    <?php if (hasImage($client['profile_pic'])): ?>
                        <img src="<?= $rootPath . htmlspecialchars($client['profile_pic']) ?>"
                             class="profile-avatar" alt="<?= htmlspecialchars($client['name']) ?>"
                             data-lightbox="<?= $rootPath . htmlspecialchars($client['profile_pic']) ?>"
                             title="Click to view full size">
                    <?php else: ?>
                        <div class="profile-avatar profile-avatar-initials">
                            <?= htmlspecialchars(nameInitials($client['name'])) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="profile-info">
                    <h1 class="profile-name"><?= htmlspecialchars($client['name']) ?></h1>
                    <p class="profile-sub">
                        <i class="bi bi-person-badge me-1"></i>
                        s/o <?= htmlspecialchars($client['father_name']) ?>
                    </p>
                    <div class="profile-meta">
                        <?php if ($client['status'] === 'active'): ?>
                            <span class="status-pill status-active">
                                <span class="dot"></span> Active
                            </span>
                        <?php else: ?>
                            <span class="status-pill status-inactive">
                                <span class="dot"></span> Inactive
                            </span>
                        <?php endif; ?>

                        <span class="meta-item">
                            <i class="bi bi-calendar-check"></i>
                            <?= (int)$client['entry_year'] ?>
                        </span>

                        <span class="meta-item">
                            <i class="bi bi-clock-history"></i>
                            Added <?= date('M d, Y', strtotime($client['created_at'])) ?>
                        </span>
                    </div>
                </div>

                <div class="profile-actions">
                    <a href="edit.php?id=<?= $id ?>" class="btn btn-primary">
                        <i class="bi bi-pencil-fill"></i> Edit
                    </a>
                    <a href="list.php" class="btn btn-light">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>

            </div>
        </div>

        <!-- ============ QUICK STATS ============ -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="quick-stat">
                    <div class="quick-stat-icon bg-primary-light">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <div class="quick-stat-value">Rs <?= number_format((float)$client['amount'], 0) ?></div>
                        <div class="quick-stat-label">Amount</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="quick-stat">
                    <div class="quick-stat-icon bg-success-light">
                        <i class="bi bi-whatsapp"></i>
                    </div>
                    <div>
                        <div class="quick-stat-value" style="font-size:15px;">
                            <?= htmlspecialchars($client['whatsapp']) ?>
                        </div>
                        <div class="quick-stat-label">WhatsApp</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="quick-stat">
                    <div class="quick-stat-icon bg-warning-light">
                        <i class="bi bi-credit-card-2-front"></i>
                    </div>
                    <div>
                        <div class="quick-stat-value" style="font-size:14px;font-family:monospace;">
                            <?= htmlspecialchars($client['cnic']) ?>
                        </div>
                        <div class="quick-stat-label">CNIC</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="quick-stat">
                    <div class="quick-stat-icon bg-info-light">
                        <i class="bi bi-calendar3"></i>
                    </div>
                    <div>
                        <div class="quick-stat-value"><?= (int)$client['entry_year'] ?></div>
                        <div class="quick-stat-label">Entry Year</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            <!-- ============ LEFT: DETAILS ============ -->
            <div class="col-12 col-lg-7">

                <!-- Personal Information -->
                <div class="panel mb-4">
                    <div class="panel-header">
                        <h3 class="panel-title">
                            <i class="bi bi-person-vcard me-2 text-primary"></i>Personal Information
                        </h3>
                    </div>
                    <div class="panel-body">
                        <div class="info-grid">

                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?= htmlspecialchars($client['name']) ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Father Name</div>
                                <div class="info-value"><?= htmlspecialchars($client['father_name']) ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">CNIC Number</div>
                                <div class="info-value">
                                    <span class="copyable" data-copy="<?= htmlspecialchars($client['cnic']) ?>">
                                        <code><?= htmlspecialchars($client['cnic']) ?></code>
                                        <button class="btn-copy" title="Copy">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">WhatsApp Number</div>
                                <div class="info-value">
                                    <a href="https://wa.me/<?= preg_replace('/\D/', '', $client['whatsapp']) ?>"
                                       target="_blank" rel="noopener" class="whatsapp-link">
                                        <i class="bi bi-whatsapp"></i>
                                        <?= htmlspecialchars($client['whatsapp']) ?>
                                    </a>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Entry Year</div>
                                <div class="info-value"><?= (int)$client['entry_year'] ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Amount</div>
                                <div class="info-value amount-highlight">
                                    Rs <?= number_format((float)$client['amount'], 2) ?>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Status</div>
                                <div class="info-value">
                                    <?php if ($client['status'] === 'active'): ?>
                                        <span class="status-pill status-active">
                                            <span class="dot"></span> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="status-pill status-inactive">
                                            <span class="dot"></span> Inactive
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Created</div>
                                <div class="info-value">
                                    <?= date('M d, Y \a\t H:i', strtotime($client['created_at'])) ?>
                                </div>
                            </div>

                            <?php if (!empty($client['updated_at']) && $client['updated_at'] !== $client['created_at']): ?>
                            <div class="info-item">
                                <div class="info-label">Last Updated</div>
                                <div class="info-value">
                                    <?= date('M d, Y \a\t H:i', strtotime($client['updated_at'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

                <!-- IRIS Credentials -->
                <div class="panel mb-4">
                    <div class="panel-header">
                        <h3 class="panel-title">
                            <i class="bi bi-shield-lock me-2 text-primary"></i>IRIS Credentials
                        </h3>
                        <span class="badge bg-secondary-subtle text-secondary small">
                            <i class="bi bi-lock-fill"></i> Encrypted (AES-256)
                        </span>
                    </div>
                    <div class="panel-body">
                        <div class="info-grid">

                            <div class="info-item">
                                <div class="info-label">IRIS Email</div>
                                <div class="info-value">
                                    <span class="copyable" data-copy="<?= htmlspecialchars($client['iris_email']) ?>">
                                        <code><?= htmlspecialchars($client['iris_email']) ?></code>
                                        <button class="btn-copy" title="Copy">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">IRIS Password</div>
                                <div class="info-value">
                                    <div class="password-reveal" id="passwordReveal">
                                        <span class="password-masked" id="passwordMasked">••••••••••••</span>
                                        <span class="password-plain d-none" id="passwordPlain"></span>
                                        <button type="button"
                                                class="btn-reveal"
                                                id="btnRevealPassword"
                                                data-id="<?= (int)$client['id'] ?>"
                                                title="Reveal password">
                                            <i class="bi bi-eye-fill" id="revealIcon"></i>
                                            <span id="revealText">Reveal</span>
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-1" id="revealHint">
                                        <i class="bi bi-info-circle"></i>
                                        Click <strong>Reveal</strong> to decrypt. This is logged.
                                    </small>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <!-- ============ RIGHT: IMAGES ============ -->
            <div class="col-12 col-lg-5">

                <!-- Profile Picture -->
                <div class="panel mb-4">
                    <div class="panel-header">
                        <h3 class="panel-title">
                            <i class="bi bi-person-circle me-2 text-primary"></i>Profile Picture
                        </h3>
                    </div>
                    <div class="panel-body p-3">
                        <?php if (hasImage($client['profile_pic'])): ?>
                            <img src="<?= $rootPath . htmlspecialchars($client['profile_pic']) ?>"
                                 class="image-thumb"
                                 alt="Profile Picture"
                                 data-lightbox="<?= $rootPath . htmlspecialchars($client['profile_pic']) ?>"
                                 data-caption="Profile Picture">
                        <?php else: ?>
                            <div class="image-empty">
                                <i class="bi bi-image"></i>
                                <p>No profile picture uploaded</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- CNIC Front -->
                <div class="panel mb-4">
                    <div class="panel-header">
                        <h3 class="panel-title">
                            <i class="bi bi-card-image me-2 text-primary"></i>CNIC Front Side
                        </h3>
                    </div>
                    <div class="panel-body p-3">
                        <?php if (hasImage($client['cnic_front_pic'])): ?>
                            <img src="<?= $rootPath . htmlspecialchars($client['cnic_front_pic']) ?>"
                                 class="image-thumb"
                                 alt="CNIC Front"
                                 data-lightbox="<?= $rootPath . htmlspecialchars($client['cnic_front_pic']) ?>"
                                 data-caption="CNIC Front Side">
                        <?php else: ?>
                            <div class="image-empty">
                                <i class="bi bi-image"></i>
                                <p>No CNIC front image uploaded</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- CNIC Back -->
                <div class="panel mb-4">
                    <div class="panel-header">
                        <h3 class="panel-title">
                            <i class="bi bi-card-image me-2 text-primary"></i>CNIC Back Side
                        </h3>
                    </div>
                    <div class="panel-body p-3">
                        <?php if (hasImage($client['cnic_back_pic'])): ?>
                            <img src="<?= $rootPath . htmlspecialchars($client['cnic_back_pic']) ?>"
                                 class="image-thumb"
                                 alt="CNIC Back"
                                 data-lightbox="<?= $rootPath . htmlspecialchars($client['cnic_back_pic']) ?>"
                                 data-caption="CNIC Back Side">
                        <?php else: ?>
                            <div class="image-empty">
                                <i class="bi bi-image"></i>
                                <p>No CNIC back image uploaded</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>

        <?php endif; ?>

    </main>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox">
    <button class="lightbox-close" id="lightboxClose" aria-label="Close">
        <i class="bi bi-x-lg"></i>
    </button>
    <img src="" alt="" id="lightboxImg">
    <div class="lightbox-caption" id="lightboxCaption"></div>
</div>

<!-- Toast container -->
<div id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/sidebar.js"></script>
<script>
    window.VIEW_CONFIG = {
        decryptUrl: '../../backend/clients/decrypt.php',
        clientId:   <?= (int)$id ?>
    };
</script>
<script src="../../assets/js/view-client.js"></script>

</body>
</html>