<?php
/**
 * All Clients — List Page
 * Location: frontend/clients/list.php
 */

session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: ../../index.php');
    exit;
}

$currentPage = 'clients_list';
$assetPath   = '../../assets';
$rootPath    = '../../';
$pageTitle   = 'All Clients';

$userName  = $_SESSION['user_name']  ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$userRole  = $_SESSION['user_role']  ?? 'user';
$initials  = '';
foreach (explode(' ', trim($userName)) as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = mb_substr($initials, 0, 2) ?: 'U';

// ============================================================
// LOAD CLIENTS FROM DATABASE
// ============================================================
require_once __DIR__ . '/../../config/database.php';

$pdo = null;
try {
    $db  = new Database();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    $dbError = 'Could not connect to database.';
}

// ---------- Filters ----------
$search = trim((string)($_GET['q']      ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));
$year   = trim((string)($_GET['year']   ?? ''));
$sortBy = trim((string)($_GET['sort']   ?? 'newest'));
$page   = max(1, (int)($_GET['page']    ?? 1));
$perPage = 10;

// ---------- Build query ----------
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(c.name LIKE :q OR c.father_name LIKE :q OR c.cnic LIKE :q OR c.iris_email LIKE :q OR c.whatsapp LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

if ($status === 'active' || $status === 'inactive') {
    $where[] = 'c.status = :status';
    $params[':status'] = $status;
}

if ($year !== '' && ctype_digit($year)) {
    $where[] = 'c.entry_year = :year';
    $params[':year'] = (int)$year;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Sort
switch ($sortBy) {
    case 'oldest':   $orderSql = 'ORDER BY c.created_at ASC';  break;
    case 'name_asc': $orderSql = 'ORDER BY c.name ASC';        break;
    case 'name_desc':$orderSql = 'ORDER BY c.name DESC';       break;
    case 'amount_high': $orderSql = 'ORDER BY c.amount DESC';  break;
    case 'amount_low':  $orderSql = 'ORDER BY c.amount ASC';   break;
    default:         $orderSql = 'ORDER BY c.created_at DESC';
}

$clients = [];
$totalClients = 0;
$totalPages = 1;

if ($pdo instanceof PDO) {
    try {
        // Count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM clients c $whereSql");
        $countStmt->execute($params);
        $totalClients = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalClients / $perPage));

        // Clamp page
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        // Fetch — limit/offset cast to int to avoid binding issues
        $sql = "SELECT c.id, c.name, c.father_name, c.iris_email, c.cnic, c.entry_year,
                       c.amount, c.status, c.whatsapp, c.profile_pic, c.created_at
                FROM clients c
                $whereSql
                $orderSql
                LIMIT $perPage OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clients = $stmt->fetchAll();
    } catch (PDOException $e) {
        $dbError = 'Query error: ' . $e->getMessage();
    }
}

// ---------- Stats (independent of filters) ----------
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'total_amount' => 0];
if ($pdo instanceof PDO) {
    try {
        $s = $pdo->query("SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'active'   THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
            COALESCE(SUM(amount), 0) AS total_amount
            FROM clients")->fetch();
        $stats = [
            'total'        => (int)($s['total'] ?? 0),
            'active'       => (int)($s['active'] ?? 0),
            'inactive'     => (int)($s['inactive'] ?? 0),
            'total_amount' => (float)($s['total_amount'] ?? 0),
        ];
    } catch (PDOException $e) {}
}

// Available years for filter dropdown
$yearsAvailable = [];
if ($pdo instanceof PDO) {
    try {
        $yearsAvailable = $pdo->query("SELECT DISTINCT entry_year FROM clients ORDER BY entry_year DESC")
                              ->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}
}

// Helper: build pagination URL preserving filters
function pageUrl(int $p): string {
    $q = $_GET;
    $q['page'] = $p;
    return '?' . http_build_query($q);
}

// Helper: initials from name
function nameInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach ($parts as $part) {
        if ($part !== '') $out .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return mb_substr($out, 0, 2) ?: '?';
}
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
    <link rel="stylesheet" href="<?= $assetPath ?>/css/clients-list.css">
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
                <li class="breadcrumb-item active" aria-current="page">All Clients</li>
            </ol>
        </nav>

        <!-- Flash messages from redirects -->
        <?php if (!empty($_GET['msg'])): ?>
            <?php
            $flashType = 'success';
            $flashMsg  = '';
            switch ($_GET['msg']) {
                case 'added':     $flashMsg = 'Client added successfully!'; break;
                case 'updated':   $flashMsg = 'Client updated successfully!'; break;
                case 'deleted':   $flashMsg = 'Client deleted successfully.'; break;
                case 'notfound':  $flashType = 'danger'; $flashMsg = 'Client not found.'; break;
                case 'error':     $flashType = 'danger'; $flashMsg = 'Something went wrong. Please try again.'; break;
            }
            ?>
            <?php if ($flashMsg): ?>
                <div class="alert alert-<?= $flashType ?> alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-<?= $flashType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
                    <div><?= htmlspecialchars($flashMsg) ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Page header -->
        <div class="page-header">
            <div>
                <h1 class="page-heading">All Clients</h1>
                <p class="page-subheading">Manage your client records, review details, and update entries.</p>
            </div>
            <a href="add.php" class="btn btn-primary">
                <i class="bi bi-person-plus-fill"></i> Add New Client
            </a>
        </div>

        <!-- Stat cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-mini stat-mini-primary">
                    <div class="stat-mini-icon"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="stat-mini-value"><?= number_format($stats['total']) ?></div>
                        <div class="stat-mini-label">Total Clients</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-mini stat-mini-success">
                    <div class="stat-mini-icon"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <div class="stat-mini-value"><?= number_format($stats['active']) ?></div>
                        <div class="stat-mini-label">Active</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-mini stat-mini-danger">
                    <div class="stat-mini-icon"><i class="bi bi-x-circle-fill"></i></div>
                    <div>
                        <div class="stat-mini-value"><?= number_format($stats['inactive']) ?></div>
                        <div class="stat-mini-label">Inactive</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-mini stat-mini-info">
                    <div class="stat-mini-icon"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="stat-mini-value">Rs <?= number_format($stats['total_amount'], 0) ?></div>
                        <div class="stat-mini-label">Total Amount</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters & search -->
        <div class="panel mb-4">
            <div class="panel-body">
                <form method="GET" action="" class="row g-2 align-items-end">

                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="q"
                                   value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Name, CNIC, email, WhatsApp...">
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted">Status</label>
                        <select class="form-select" name="status">
                            <option value="all"      <?= $status === 'all'      ? 'selected' : '' ?>>All</option>
                            <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted">Entry Year</label>
                        <select class="form-select" name="year">
                            <option value="">All Years</option>
                            <?php foreach ($yearsAvailable as $y): ?>
                                <option value="<?= (int)$y ?>" <?= (string)$year === (string)$y ? 'selected' : '' ?>>
                                    <?= (int)$y ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted">Sort By</label>
                        <select class="form-select" name="sort">
                            <option value="newest"      <?= $sortBy === 'newest'      ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest"      <?= $sortBy === 'oldest'      ? 'selected' : '' ?>>Oldest First</option>
                            <option value="name_asc"    <?= $sortBy === 'name_asc'    ? 'selected' : '' ?>>Name A → Z</option>
                            <option value="name_desc"   <?= $sortBy === 'name_desc'   ? 'selected' : '' ?>>Name Z → A</option>
                            <option value="amount_high" <?= $sortBy === 'amount_high' ? 'selected' : '' ?>>Amount High → Low</option>
                            <option value="amount_low"  <?= $sortBy === 'amount_low'  ? 'selected' : '' ?>>Amount Low → High</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-funnel"></i> Apply
                        </button>
                        <a href="list.php" class="btn btn-light" title="Reset filters">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>

                </form>
            </div>
        </div>

        <!-- Clients table -->
        <div class="panel">
            <div class="panel-header">
                <h3 class="panel-title">
                    <i class="bi bi-people-fill me-2 text-primary"></i>
                    Clients
                    <span class="text-muted fw-normal">(<?= number_format($totalClients) ?>)</span>
                </h3>
                <div class="panel-actions">
                    <span class="text-muted small">
                        Showing <?= count($clients) ?> of <?= number_format($totalClients) ?>
                    </span>
                </div>
            </div>

            <div class="panel-body p-0">
                <?php if (!empty($dbError)): ?>
                    <div class="alert alert-danger m-3 mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($dbError) ?>
                    </div>
                <?php elseif (empty($clients)): ?>

                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                        <h4>No clients found</h4>
                        <p class="text-muted">
                            <?php if ($search !== '' || $status !== 'all' || $year !== ''): ?>
                                Try adjusting your filters or
                                <a href="list.php">clear all filters</a>.
                            <?php else: ?>
                                Get started by adding your first client.
                            <?php endif; ?>
                        </p>
                        <a href="add.php" class="btn btn-primary mt-2">
                            <i class="bi bi-person-plus-fill"></i> Add Client
                        </a>
                    </div>

                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table clients-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Client</th>
                                    <th class="d-none d-md-table-cell">CNIC</th>
                                    <th class="d-none d-lg-table-cell">WhatsApp</th>
                                    <th class="d-none d-lg-table-cell">Year</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end" style="width: 160px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = ($page - 1) * $perPage; foreach ($clients as $c): $i++; ?>
                                    <tr data-id="<?= (int)$c['id'] ?>">

                                        <td class="text-muted small"><?= $i ?></td>

                                        <!-- Client (avatar + name) -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($c['profile_pic']) && file_exists(__DIR__ . '/../../' . $c['profile_pic'])): ?>
                                                    <img src="<?= $rootPath . htmlspecialchars($c['profile_pic']) ?>"
                                                         class="client-avatar" alt="<?= htmlspecialchars($c['name']) ?>">
                                                <?php else: ?>
                                                    <div class="client-avatar client-avatar-initials">
                                                        <?= htmlspecialchars(nameInitials($c['name'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="min-w-0">
                                                    <div class="client-name"><?= htmlspecialchars($c['name']) ?></div>
                                                    <div class="client-sub text-muted small">
                                                        s/o <?= htmlspecialchars($c['father_name']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- CNIC -->
                                        <td class="d-none d-md-table-cell">
                                            <code class="text-dark"><?= htmlspecialchars($c['cnic']) ?></code>
                                        </td>

                                        <!-- WhatsApp -->
                                        <td class="d-none d-lg-table-cell">
                                            <a href="https://wa.me/<?= preg_replace('/\D/', '', $c['whatsapp']) ?>"
                                               target="_blank" rel="noopener" class="whatsapp-link">
                                                <i class="bi bi-whatsapp"></i>
                                                <?= htmlspecialchars($c['whatsapp']) ?>
                                            </a>
                                        </td>

                                        <!-- Entry year -->
                                        <td class="d-none d-lg-table-cell">
                                            <span class="badge bg-light text-dark"><?= (int)$c['entry_year'] ?></span>
                                        </td>

                                        <!-- Amount -->
                                        <td class="text-end">
                                            <span class="amount">Rs <?= number_format((float)$c['amount'], 0) ?></span>
                                        </td>

                                        <!-- Status badge -->
                                        <td class="text-center">
                                            <?php if ($c['status'] === 'active'): ?>
                                                <span class="status-pill status-active">
                                                    <span class="dot"></span> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="status-pill status-inactive">
                                                    <span class="dot"></span> Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions -->
                                        <td class="text-end">
                                            <div class="action-group">
                                                <a href="view.php?id=<?= (int)$c['id'] ?>"
                                                   class="btn-action btn-action-view"
                                                   title="View details">
                                                    <i class="bi bi-eye-fill"></i>
                                                </a>
                                                <a href="edit.php?id=<?= (int)$c['id'] ?>"
                                                   class="btn-action btn-action-edit"
                                                   title="Edit">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn-action btn-action-delete"
                                                        data-id="<?= (int)$c['id'] ?>"
                                                        data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>"
                                                        title="Delete">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </div>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-wrap">
                            <nav aria-label="Clients pagination">
                                <ul class="pagination pagination-sm mb-0">

                                    <!-- Prev -->
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= htmlspecialchars(pageUrl(max(1, $page - 1))) ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>

                                    <!-- Pages with ellipsis -->
                                    <?php
                                    $window = 2; // pages on each side of current
                                    $start  = max(1, $page - $window);
                                    $end    = min($totalPages, $page + $window);

                                    if ($start > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(pageUrl(1)) . '">1</a></li>';
                                        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    }

                                    for ($p = $start; $p <= $end; $p++) {
                                        $active = $p === $page ? 'active' : '';
                                        echo '<li class="page-item ' . $active . '">'
                                           . '<a class="page-link" href="' . htmlspecialchars(pageUrl($p)) . '">' . $p . '</a>'
                                           . '</li>';
                                    }

                                    if ($end < $totalPages) {
                                        if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(pageUrl($totalPages)) . '">' . $totalPages . '</a></li>';
                                    }
                                    ?>

                                    <!-- Next -->
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= htmlspecialchars(pageUrl(min($totalPages, $page + 1))) ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>

                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content delete-modal">
            <div class="modal-body text-center p-4">
                <div class="delete-icon-wrap">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <h5 class="mt-3 mb-2">Delete Client?</h5>
                <p class="text-muted mb-4">
                    Are you sure you want to delete<br>
                    <strong id="deleteClientName">this client</strong>?<br>
                    <small>This action cannot be undone.</small>
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger px-4" id="confirmDeleteBtn">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="deleteSpinner"></span>
                        <i class="bi bi-trash-fill"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $assetPath ?>/js/sidebar.js"></script>
<script>
    // Pass config to JS
    window.CLIENTS_CONFIG = {
        deleteUrl: '../../backend/clients/delete.php'
    };
</script>
<script src="<?= $assetPath ?>/js/clients-list.js"></script>

</body>
</html>