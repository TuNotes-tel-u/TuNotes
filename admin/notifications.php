<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/../helpers/admin_view.php';

// =============================================================================
// POST handler (create / update / delete).
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        admin_flash_redirect('notifications.php', 'error', 'CSRF token tidak valid.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $title        = trim((string) ($_POST['title'] ?? ''));
        $message      = trim((string) ($_POST['message'] ?? ''));
        $type         = trim((string) ($_POST['type'] ?? 'info'));
        $target_role  = (string) ($_POST['target_role'] ?? 'all');
        $scheduled_at = trim((string) ($_POST['scheduled_at'] ?? '')) ?: null;

        $errors = [];
        if ($title === '' || mb_strlen($title) > 255) $errors[] = 'Judul wajib (maks 255 char).';
        if ($message === '')                          $errors[] = 'Pesan wajib diisi.';
        if (!in_array($target_role, ['all', 'user', 'admin'], true)) $target_role = 'all';
        if (!in_array($type, ['info', 'maintenance', 'academic', 'warning', 'success'], true)) $type = 'info';

        if (!empty($errors)) admin_flash_redirect('notifications.php', 'error', implode(' ', $errors));

        if ($action === 'create') {
            $sql  = "INSERT INTO notifications (title, message, type, target_role, scheduled_at, created_by)
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssssi', $title, $message, $type, $target_role, $scheduled_at, $admin_user['id']);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                admin_flash_redirect('notifications.php', 'success', 'Notifikasi berhasil dibuat.');
            }
            mysqli_stmt_close($stmt);
            admin_flash_redirect('notifications.php', 'error', 'Gagal menyimpan notifikasi.');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) admin_flash_redirect('notifications.php', 'error', 'ID tidak valid.');
        $sql  = "UPDATE notifications SET title=?, message=?, type=?, target_role=?, scheduled_at=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssssi', $title, $message, $type, $target_role, $scheduled_at, $id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            admin_flash_redirect('notifications.php', 'success', 'Notifikasi diperbarui.');
        }
        mysqli_stmt_close($stmt);
        admin_flash_redirect('notifications.php', 'error', 'Gagal update.');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) admin_flash_redirect('notifications.php', 'error', 'ID tidak valid.');
        $sql  = "DELETE FROM notifications WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        admin_flash_redirect('notifications.php', 'success', 'Notifikasi dihapus.');
    }
    admin_flash_redirect('notifications.php', 'error', 'Action tidak dikenal.');
}

// =============================================================================
// GET render
// =============================================================================
$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM notifications");
$total = (int) mysqli_fetch_assoc($rs)['c'];

$sql  = "SELECT n.id, n.title, n.message, n.type, n.target_role, n.scheduled_at,
                n.created_at, u.name AS creator_name
         FROM notifications n LEFT JOIN users u ON u.id = n.created_by
         ORDER BY n.created_at DESC LIMIT 50";
$rs = mysqli_query($conn, $sql);
$notifs = [];
while ($r = mysqli_fetch_assoc($rs)) $notifs[] = $r;

$page_title   = 'Manajemen Notifikasi';
$current_page = 'notifications';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-header">
        <div>
            <h1>Manajemen Notifikasi</h1>
            <p class="page-subtitle">Kirim pengumuman atau notifikasi ke seluruh pengguna / role tertentu.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="admin-flash admin-flash--<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <?= stat_card_html('fa-bell', 'purple', $total, 'Total Notifikasi') ?>
    </div>

    <div class="notif-grid">
        <!-- KIRI: Form buat notifikasi -->
        <div class="admin-section">
            <h2><i class="fa-solid fa-paper-plane"></i>Buat Notifikasi Baru</h2>
            <form method="POST" id="notifCreateForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="nf_title">Judul</label>
                    <input type="text" class="form-input" id="nf_title" name="title" maxlength="255" required>
                </div>
                <div class="form-group">
                    <label for="nf_type">Tipe</label>
                    <select class="form-select" id="nf_type" name="type">
                        <option value="info">Informasi</option>
                        <option value="warning">Peringatan</option>
                        <option value="success">Sukses</option>
                        <option value="maintenance">Pemeliharaan</option>
                        <option value="academic">Akademik</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nf_target">Target</label>
                    <select class="form-select" id="nf_target" name="target_role">
                        <option value="all">Semua Pengguna</option>
                        <option value="user">User Biasa</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="nf_sched">Jadwal Kirim (opsional)</label>
                    <input type="datetime-local" class="form-input" id="nf_sched" name="scheduled_at">
                </div>
                <div class="form-group">
                    <label for="nf_msg">Pesan</label>
                    <textarea class="form-textarea" id="nf_msg" name="message" required></textarea>
                </div>
                <div style="display:flex;gap:0.5rem;">
                    <button type="submit" class="btn-admin primary"><i class="fa-solid fa-paper-plane"></i> Kirim Sekarang</button>
                    <button type="submit" class="btn-admin outline" name="action" value="create"><i class="fa-solid fa-clock"></i> Jadwalkan</button>
                </div>
            </form>
        </div>

        <!-- KANAN: Riwayat -->
        <div class="admin-section">
            <h2><i class="fa-solid fa-clock-rotate-left"></i>Riwayat Notifikasi</h2>
            <div class="notif-history-list" id="notifHistory">
                <?php if (empty($notifs)): ?>
                    <div style="color:var(--text-secondary);text-align:center;padding:1.5rem;">Belum ada notifikasi.</div>
                <?php else: foreach ($notifs as $n):
                    $status = $n['scheduled_at'] && strtotime($n['scheduled_at']) > time() ? 'scheduled' : 'sent';
                    $iconColors = [
                        'info' => 'blue', 'success' => 'green', 'warning' => 'yellow',
                        'maintenance' => 'red', 'academic' => 'purple',
                    ];
                    $iconColor = $iconColors[$n['type']] ?? 'blue';
                ?>
                <div class="notif-history-item"
                     data-id="<?= (int) $n['id'] ?>"
                     data-title="<?= h($n['title']) ?>"
                     data-message="<?= h($n['message']) ?>"
                     data-type="<?= h($n['type']) ?>"
                     data-target_role="<?= h($n['target_role']) ?>">
                    <div class="notif-history-item__header">
                        <div class="stat-icon stat-icon--<?= $iconColor ?>" style="width:32px;height:32px;font-size:0.9rem;border-radius:7px;">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div class="notif-history-item__title"><?= h($n['title']) ?></div>
                        <?= status_badge($status) ?>
                    </div>
                    <div class="notif-history-item__meta">
                        Target: <?= h($n['target_role']) ?> · <?= time_ago($n['created_at']) ?>
                        <?php if ($n['scheduled_at']): ?> · Jadwal: <?= h($n['scheduled_at']) ?><?php endif; ?>
                    </div>
                    <div class="notif-history-item__body"><?= h(mb_substr($n['message'], 0, 200)) ?></div>
                    <div class="notif-history-item__actions">
                        <a data-action="edit-notif">Edit</a>
                        <a data-action="delete-notif" class="danger">Hapus</a>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
const CSRF = '<?= h(csrf_token()) ?>';
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
const TARGET_OPTS = [
    { value: 'all',   label: 'Semua pengguna' },
    { value: 'user',  label: 'User biasa' },
    { value: 'admin', label: 'Admin' },
];
const TYPE_OPTS = [
    { value: 'info',        label: 'Informasi' },
    { value: 'warning',     label: 'Peringatan' },
    { value: 'success',     label: 'Sukses' },
    { value: 'maintenance', label: 'Pemeliharaan' },
    { value: 'academic',    label: 'Akademik' },
];

document.getElementById('notifHistory').addEventListener('click', async (e) => {
    const editLink = e.target.closest('[data-action="edit-notif"]');
    if (editLink) {
        e.preventDefault();
        const item = editLink.closest('.notif-history-item');
        const data = await showFormModal({
            title: 'Edit Notifikasi',
            fields: [
                { name: 'title',       label: 'Judul',  type: 'text',     required: true, value: item.dataset.title },
                { name: 'message',     label: 'Pesan',  type: 'textarea', required: true, rows: 5, value: item.dataset.message },
                { name: 'type',        label: 'Tipe',   type: 'select',   value: item.dataset.type,        options: TYPE_OPTS },
                { name: 'target_role', label: 'Target', type: 'select',   value: item.dataset.target_role, options: TARGET_OPTS },
            ],
            submitLabel: 'Simpan',
        });
        if (!data) return;
        data.id = item.dataset.id;
        submitAdminForm('notifications.php', 'update', data, CSRF);
        return;
    }
    const delLink = e.target.closest('[data-action="delete-notif"]');
    if (delLink) {
        e.preventDefault();
        const item = delLink.closest('.notif-history-item');
        const ok = await showConfirmModal({
            title: 'Hapus Notifikasi?',
            message: 'Notifikasi "' + item.dataset.title + '" akan dihapus permanen.',
            confirmLabel: 'Hapus', danger: true,
        });
        if (!ok) return;
        submitAdminForm('notifications.php', 'delete', { id: item.dataset.id }, CSRF);
    }
});
</script>
