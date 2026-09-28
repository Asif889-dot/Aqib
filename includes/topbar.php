<?php
/**
 * Reusable Topbar
 * 
 * Variables expected (with defaults):
 *   $pageTitle — shown in the header
 *   $rootPath  — path to project root
 *   $userName, $userEmail, $userRole, $initials
 */

$pageTitle = $pageTitle ?? 'Dashboard';
$rootPath  = $rootPath  ?? '.';

$userName  = $userName  ?? ($_SESSION['user_name']  ?? 'User');
$userEmail = $userEmail ?? ($_SESSION['user_email'] ?? '');
$userRole  = $userRole  ?? ($_SESSION['user_role']  ?? 'user');

if (empty($initials)) {
    $initials = '';
    foreach (explode(' ', trim($userName)) as $part) {
        if ($part !== '') {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    }
    $initials = mb_substr($initials, 0, 2) ?: 'U';
}
?>

<header class="topbar">
    <button class="btn-toggle-sidebar d-lg-none" id="toggleSidebar" aria-label="Menu">
        <i class="bi bi-list"></i>
    </button>

    <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>

    <div class="topbar-right">
        <button class="topbar-btn" title="Notifications">
            <i class="bi bi-bell-fill"></i>
            <span class="notification-dot"></span>
        </button>
        <button class="topbar-btn" title="Messages">
            <i class="bi bi-chat-dots-fill"></i>
        </button>

        <div class="dropdown">
            <button class="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="user-info d-none d-md-block">
                    <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="user-role"><?= htmlspecialchars(ucfirst($userRole)) ?></div>
                </div>
                <i class="bi bi-chevron-down ms-1"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li class="dropdown-header">
                    <div class="fw-semibold"><?= htmlspecialchars($userName) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($userEmail) ?></small>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i> My Profile</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i> Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="<?= htmlspecialchars($rootPath) ?>logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>