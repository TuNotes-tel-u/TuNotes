<?php
/**
 * Sidebar nav admin (3 section: UTAMA / MANAJEMEN / WAWASAN) + profile mini footer.
 * Active state berdasarkan $current_page.
 */
require_once __DIR__ . '/../../helpers/admin_view.php';

$_cp = isset($current_page) ? $current_page : '';
$_sections = [
    [
        'label' => 'UTAMA',
        'items' => [
            ['key' => 'dashboard', 'href' => 'Dashboard-admin.php', 'label' => 'Dashboard', 'icon' => 'fa-house'],
        ],
    ],
    [
        'label' => 'MANAJEMEN',
        'items' => [
            ['key' => 'users',    'href' => 'users.php',    'label' => 'Pengguna', 'icon' => 'fa-users'],
            ['key' => 'content',  'href' => 'content.php',  'label' => 'Konten',   'icon' => 'fa-file-lines'],
            ['key' => 'comments', 'href' => 'comments.php', 'label' => 'Komentar', 'icon' => 'fa-comments'],
        ],
    ],
    [
        'label' => 'WAWASAN',
        'items' => [
            ['key' => 'analytics',     'href' => 'analytics.php',     'label' => 'Analitik',   'icon' => 'fa-chart-line'],
            ['key' => 'notifications', 'href' => 'notifications.php', 'label' => 'Notifikasi', 'icon' => 'fa-bell'],
        ],
    ],
];
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar__header">
        <a href="Dashboard-admin.php" class="admin-sidebar__brand">
            <img src="../assets/images/Logo-Tunotes.png" alt="TuNotes">
            <div>
                <span class="brand-title">TuNotes</span>
                <span class="brand-badge">ADMIN</span>
            </div>
        </a>
    </div>

    <nav class="admin-sidebar__nav">
        <?php foreach ($_sections as $section): ?>
            <div class="nav-section-label"><?= htmlspecialchars($section['label']) ?></div>
            <?php foreach ($section['items'] as $item):
                $active = $_cp === $item['key'];
            ?>
                <a href="<?= htmlspecialchars($item['href']) ?>" class="nav-item<?= $active ? ' nav-item--active' : '' ?>">
                    <i class="fa-solid <?= htmlspecialchars($item['icon']) ?>"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <?php if (!empty($admin_user)): ?>
    <div class="admin-sidebar__footer">
        <div class="profile-mini">
            <?= avatar_html($admin_user['name'], $admin_user['username'], 38) ?>
            <div class="profile-mini__info">
                <div class="profile-mini__name"><?= htmlspecialchars($admin_user['name']) ?></div>
                <div class="profile-mini__role"><?= htmlspecialchars($admin_user['role']) ?></div>
            </div>
        </div>
        <a href="logout.php" class="logout-link">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
    <?php endif; ?>
</aside>
