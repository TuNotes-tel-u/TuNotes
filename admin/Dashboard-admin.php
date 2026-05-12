<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../helpers/admin_view.php';

// Quick stats
$stats = ['users' => 0, 'notes' => 0, 'comments' => 0, 'downloads' => 0];
$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users");
$stats['users'] = (int) mysqli_fetch_assoc($rs)['c'];
$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM notes WHERE status='active'");
$stats['notes'] = (int) mysqli_fetch_assoc($rs)['c'];
$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM comments WHERE status='active'");
$stats['comments'] = (int) mysqli_fetch_assoc($rs)['c'];
$rs = mysqli_query($conn, "SELECT IFNULL(SUM(downloads_count),0) AS s FROM notes WHERE status='active'");
$stats['downloads'] = (int) mysqli_fetch_assoc($rs)['s'];

// Pengguna baru (5 terakhir register)
$new_users = [];
$rs = mysqli_query($conn, "SELECT id, name, username, email, role, created_at FROM users
                            ORDER BY created_at DESC LIMIT 5");
while ($r = mysqli_fetch_assoc($rs)) $new_users[] = $r;

// Catatan terbaru
$recent_notes = [];
$rs = mysqli_query($conn, "SELECT n.id, n.title, n.course_name, n.created_at, u.name AS uploader_name
                            FROM notes n JOIN users u ON u.id = n.user_id
                            WHERE n.status='active'
                            ORDER BY n.created_at DESC LIMIT 5");
while ($r = mysqli_fetch_assoc($rs)) $recent_notes[] = $r;

$page_title    = 'Dashboard';
$current_page  = 'dashboard';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p class="page-subtitle">Halo, <strong><?= h($admin_user['name']) ?></strong> 👋 — selamat datang kembali di panel admin TuNotes.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="admin-flash admin-flash--<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <?= stat_card_html('fa-user-group',     'blue',   $stats['users'],     'Total Pengguna') ?>
        <?= stat_card_html('fa-file-lines',     'green',  $stats['notes'],     'Total Catatan') ?>
        <?= stat_card_html('fa-comments',       'yellow', $stats['comments'],  'Total Komentar') ?>
        <?= stat_card_html('fa-download',       'purple', $stats['downloads'], 'Total Unduhan') ?>
    </div>

    <div class="two-col">
        <div class="admin-section">
            <h2><i class="fa-solid fa-user-plus"></i>Pengguna Baru</h2>
            <?php if (empty($new_users)): ?>
                <p style="color:var(--text-secondary);">Belum ada pengguna baru.</p>
            <?php else: ?>
                <ul class="activity-list">
                <?php foreach ($new_users as $u): ?>
                    <li class="activity-item">
                        <?= avatar_html($u['name'], $u['username'], 36) ?>
                        <div class="activity-item__body">
                            <div class="activity-item__title"><?= h($u['name']) ?> <?= role_badge($u['role']) ?></div>
                            <div class="activity-item__meta"><?= h($u['email']) ?> · <?= time_ago($u['created_at']) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="admin-section">
            <h2><i class="fa-solid fa-file-circle-plus"></i>Catatan Terbaru</h2>
            <?php if (empty($recent_notes)): ?>
                <p style="color:var(--text-secondary);">Belum ada catatan.</p>
            <?php else: ?>
                <ul class="activity-list">
                <?php foreach ($recent_notes as $n): ?>
                    <li class="activity-item">
                        <div class="content-icon" style="width:36px;height:36px;font-size:0.95rem;">
                            <i class="fa-solid fa-file-lines"></i>
                        </div>
                        <div class="activity-item__body">
                            <div class="activity-item__title"><?= h($n['title'] ?: $n['course_name']) ?></div>
                            <div class="activity-item__meta">oleh <?= h($n['uploader_name']) ?> · <?= time_ago($n['created_at']) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-section">
        <h2><i class="fa-solid fa-bolt"></i>Akses Cepat</h2>
        <div class="quick-grid">
            <a class="quick-card" href="users.php"><i class="fa-solid fa-users"></i><span>Pengguna</span></a>
            <a class="quick-card" href="content.php"><i class="fa-solid fa-file-lines"></i><span>Konten</span></a>
            <a class="quick-card" href="comments.php"><i class="fa-solid fa-comments"></i><span>Komentar</span></a>
            <a class="quick-card" href="analytics.php"><i class="fa-solid fa-chart-line"></i><span>Analitik</span></a>
            <a class="quick-card" href="notifications.php"><i class="fa-solid fa-bell"></i><span>Notifikasi</span></a>
        </div>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
