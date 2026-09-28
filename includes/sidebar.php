<?php
/**
 * Reusable Sidebar Component
 * 
 * Usage in any page:
 *   <?php
 *   $currentPage = 'dashboard';   // highlight active item
 *   $assetPath   = '../assets';   // path to assets from THIS page
 *   $rootPath    = '../';         // path to project root from THIS page
 *   include __DIR__ . '/../includes/sidebar.php';
 *   ?>
 * 
 * Variables expected:
 *   $currentPage (string) — key of active menu item
 *   $assetPath   (string) — relative path to /assets
 *   $rootPath    (string) — path to project root from THIS page
 *   $userName, $userEmail, $userRole, $initials — from session
 */

// ---- Safe defaults ----
$currentPage = $currentPage ?? 'dashboard';
$assetPath   = $assetPath   ?? '.';
$rootPath    = $rootPath    ?? '.';

$userName  = $userName  ?? ($_SESSION['user_name']  ?? 'User');
$userEmail = $userEmail ?? ($_SESSION['user_email'] ?? '');
$userRole  = $userRole  ?? ($_SESSION['user_role']  ?? 'user');

// Avatar initials
if (empty($initials)) {
    $initials = '';
    foreach (explode(' ', trim($userName)) as $part) {
        if ($part !== '') {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    }
    $initials = mb_substr($initials, 0, 2) ?: 'U';
}

// ---- Live client count (cached per request) ----
$clientCount = 0;
if (!isset($GLOBALS['__sidebar_client_count'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $pdo = $db->getConnection();
        $clientCount = (int)$pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
    } catch (Exception $e) {
        $clientCount = 0;
    }
    $GLOBALS['__sidebar_client_count'] = $clientCount;
} else {
    $clientCount = $GLOBALS['__sidebar_client_count'];
}

/**
 * Menu items — add, remove, or reorder freely.
 * 
 * Supported keys per item:
 *   key   — unique string; matches $currentPage to highlight
 *   label — visible text
 *   icon  — Bootstrap Icons class (without "bi " prefix)
 *   url   — full path (usually $rootPath . 'path/to/page.php')
 *   roles — null (everyone) | ['admin'] | ['user'] | ['admin','user']
 *   badge — optional: ['text' => '12', 'class' => 'bg-primary']
 */
$menuGroups = [

    'Main' => [
        [
            'key'   => 'dashboard',
            'label' => 'Dashboard',
            'icon'  => 'bi-grid-1x2-fill',
            'url'   => $rootPath . 'frontend/dashboard.php',
            'roles' => null,
        ],
    ],

    'Clients' => [
        [
            'key'   => 'clients_list',
            'label' => 'All Clients',
            'icon'  => 'bi-people-fill',
            'url'   => $rootPath . 'frontend/clients/list.php',
            'roles' => null,
            'badge' => $clientCount > 0
                ? ['text' => (string)$clientCount, 'class' => 'bg-primary']
                : null,
        ],
        [
            'key'   => 'clients_add',
            'label' => 'Add Client',
            'icon'  => 'bi-person-plus-fill',
            'url'   => $rootPath . 'frontend/clients/add.php',
            'roles' => null,
        ],
    ],

    'Admin' => [
        [
            'key'   => 'admin_update',
            'label' => 'Update From GitHub',
            'icon'  => 'bi-cloud-arrow-down-fill',
            'url'   => $rootPath . 'frontend/admin/update.php',
            'roles' => ['admin'],
        ],
    ],
];

/**
 * Helper: check if current user can see the item.
 */
if (!function_exists('canSeeItem')) {
    function canSeeItem(array $item, string $role): bool
    {
        if (empty($item['roles'])) {
            return true;
        }
        return in_array($role, $item['roles'], true);
    }
}
?>

<!-- ==================== SIDEBAR ==================== -->
<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-header">
        <a href="<?= htmlspecialchars($rootPath) ?>frontend/dashboard.php" class="sidebar-brand">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Aqibsb</span>
        </a>
        <button class="btn-close-sidebar d-lg-none" id="closeSidebar" aria-label="Close">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <?php foreach ($menuGroups as $groupName => $items): ?>
            <?php
            // Skip entire group if user can't see any item in it
            $visibleItems = array_filter(
                $items,
                fn($item) => canSeeItem($item, $userRole)
            );
            if (empty($visibleItems)) {
                continue;
            }
            ?>

            <div class="nav-section"><?= htmlspecialchars($groupName) ?></div>

            <?php foreach ($visibleItems as $item): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>"
                   class="nav-link <?= $currentPage === $item['key'] ? 'active' : '' ?>"
                   title="<?= htmlspecialchars($item['label']) ?>">
                    <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="badge <?= htmlspecialchars($item['badge']['class'] ?? 'bg-primary') ?> ms-auto">
                            <?= htmlspecialchars($item['badge']['text']) ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <!-- User card at bottom -->
    <div class="sidebar-user">
        <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
        <div class="user-details">
            <div class="user-name" title="<?= htmlspecialchars($userName) ?>">
                <?= htmlspecialchars($userName) ?>
            </div>
            <div class="user-role">
                <span class="role-dot role-<?= htmlspecialchars($userRole) ?>"></span>
                <?= htmlspecialchars(ucfirst($userRole)) ?>
            </div>
        </div>
    </div>

    <!-- Footer (logout) -->
    <div class="sidebar-footer">
        <a href="<?= htmlspecialchars($rootPath) ?>logout.php" class="nav-link logout-link">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </div>

</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>