<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/../helpers/admin_view.php';

// =============================================================================
// POST handler (PRG pattern). Logic identik dengan Tahap 6.
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        admin_flash_redirect('users.php', 'error', 'CSRF token tidak valid. Silakan reload halaman.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = trim((string) ($_POST['name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role     = (string) ($_POST['role'] ?? 'user');
        $status   = (string) ($_POST['status'] ?? 'active');

        $errors = [];
        if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Nama wajib (maks 100 char).';
        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) $errors[] = 'Username 3–50 char alfanumerik + underscore.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))       $errors[] = 'Email tidak valid.';
        if (strlen($password) < 8)                            $errors[] = 'Password minimal 8 karakter.';
        if (!in_array($role, ['user', 'admin', 'super_admin'], true))         $role = 'user';
        if (!in_array($status, ['active', 'suspended', 'inactive'], true))    $status = 'active';
        if ($role === 'super_admin' && !$is_super) {
            $errors[] = 'Hanya super_admin yang boleh membuat akun super_admin.';
        }

        if (empty($errors)) {
            $sql  = "SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ss', $email, $username);
            mysqli_stmt_execute($stmt);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($exists) {
                $errors[] = 'Email atau username sudah dipakai.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $sql  = "INSERT INTO users (name, username, email, password_hash, role, status)
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'ssssss', $name, $username, $email, $hash, $role, $status);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    admin_flash_redirect('users.php', 'success', "Pengguna '$username' berhasil ditambahkan.");
                }
                $errors[] = 'Gagal menyimpan.';
                mysqli_stmt_close($stmt);
            }
        }
        admin_flash_redirect('users.php', 'error', implode(' ', $errors));
    }

    if ($action === 'update') {
        $id     = (int) ($_POST['id'] ?? 0);
        $name   = trim((string) ($_POST['name'] ?? ''));
        $email  = trim((string) ($_POST['email'] ?? ''));
        $role   = (string) ($_POST['role'] ?? 'user');
        $status = (string) ($_POST['status'] ?? 'active');
        $newpw  = (string) ($_POST['password'] ?? '');

        if ($id <= 0) admin_flash_redirect('users.php', 'error', 'ID tidak valid.');
        $sql  = "SELECT id, role FROM users WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $target = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$target) admin_flash_redirect('users.php', 'error', 'User tidak ditemukan.');

        $errors = [];
        if ($name === '' || mb_strlen($name) > 100)     $errors[] = 'Nama wajib (maks 100).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
        if (!in_array($role, ['user', 'admin', 'super_admin'], true))      $role = $target['role'];
        if (!in_array($status, ['active', 'suspended', 'inactive'], true)) $status = 'active';
        if ($newpw !== '' && strlen($newpw) < 8) $errors[] = 'Password baru minimal 8 char (atau kosongkan).';
        if (($target['role'] === 'super_admin' || $role === 'super_admin') && !$is_super) {
            $errors[] = 'Hanya super_admin yang boleh ubah role super_admin.';
        }

        if (empty($errors)) {
            $sql  = "SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'si', $email, $id);
            mysqli_stmt_execute($stmt);
            $dupe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($dupe) {
                $errors[] = 'Email sudah dipakai user lain.';
            } else {
                if ($newpw !== '') {
                    $hash = password_hash($newpw, PASSWORD_BCRYPT, ['cost' => 12]);
                    $sql  = "UPDATE users SET name=?, email=?, role=?, status=?, password_hash=? WHERE id=?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, 'sssssi', $name, $email, $role, $status, $hash, $id);
                } else {
                    $sql  = "UPDATE users SET name=?, email=?, role=?, status=? WHERE id=?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $role, $status, $id);
                }
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    admin_flash_redirect('users.php', 'success', 'Data pengguna diperbarui.');
                }
                $errors[] = 'Gagal update.';
                mysqli_stmt_close($stmt);
            }
        }
        admin_flash_redirect('users.php', 'error', implode(' ', $errors));
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) admin_flash_redirect('users.php', 'error', 'ID tidak valid.');
        if ($id === (int) $admin_user['id']) {
            admin_flash_redirect('users.php', 'error', 'Tidak bisa menghapus akun sendiri.');
        }
        $sql  = "SELECT id, username, role FROM users WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $target = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$target) admin_flash_redirect('users.php', 'error', 'User tidak ditemukan.');
        if ($target['role'] === 'super_admin' && !$is_super) {
            admin_flash_redirect('users.php', 'error', 'Hanya super_admin yang boleh menghapus sesama super_admin.');
        }
        $sql  = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        admin_flash_redirect('users.php', 'success', "Pengguna '" . $target['username'] . "' dihapus.");
    }
    admin_flash_redirect('users.php', 'error', 'Action tidak dikenal.');
}

// =============================================================================
// GET render
// =============================================================================
$filter = $_GET['status'] ?? '';
$q      = trim((string) ($_GET['q'] ?? ''));

$where  = ['1=1'];
$params = [];
$types  = '';
if (in_array($filter, ['active', 'suspended', 'inactive'], true)) {
    $where[]  = 'status = ?'; $params[] = $filter; $types .= 's';
}
if ($q !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ? OR username LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}
$whereSql = implode(' AND ', $where);

$sql  = "SELECT id, name, username, email, role, status, notes_count, last_active_at, created_at
         FROM users WHERE $whereSql ORDER BY created_at DESC LIMIT 200";
$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res  = mysqli_stmt_get_result($stmt);
$users = [];
while ($r = mysqli_fetch_assoc($res)) $users[] = $r;
mysqli_stmt_close($stmt);

$counts = ['total' => 0, 'active' => 0, 'suspended' => 0, 'inactive' => 0];
$rs = mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM users GROUP BY status");
while ($r = mysqli_fetch_assoc($rs)) {
    if (isset($counts[$r['status']])) $counts[$r['status']] = (int) $r['c'];
    $counts['total'] += (int) $r['c'];
}

$page_title   = 'Manajemen Pengguna';
$current_page = 'users';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-header">
        <div>
            <h1>Manajemen Pengguna</h1>
            <p class="page-subtitle">Kelola akun pengguna TuNotes — tambah, edit role/status, atau hapus.</p>
        </div>
        <button type="button" id="btnAddUser" class="btn-admin primary">
            <i class="fa-solid fa-plus"></i> Tambah Pengguna
        </button>
    </div>

    <?php if ($flash): ?>
        <div class="admin-flash admin-flash--<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <?= stat_card_html('fa-user-group',         'blue',   $counts['total'],     'Total Pengguna') ?>
        <?= stat_card_html('fa-circle-check',       'green',  $counts['active'],    'Aktif') ?>
        <?= stat_card_html('fa-clock',              'yellow', $counts['suspended'], 'Ditangguhkan') ?>
        <?= stat_card_html('fa-triangle-exclamation','red',   $counts['inactive'],  'Tidak Aktif') ?>
    </div>

    <form method="GET" class="filter-bar" id="filterForm">
        <input type="search" name="q" class="search-input" placeholder="Cari nama, email, atau username…" value="<?= h($q) ?>" onchange="this.form.submit()">
        <div class="filter-tabs">
            <a class="filter-tab<?= $filter===''?' filter-tab--active':'' ?>"          href="users.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>">Semua</a>
            <a class="filter-tab<?= $filter==='active'?' filter-tab--active':'' ?>"    href="users.php?status=active<?= $q!==''?'&q='.urlencode($q):'' ?>">Aktif</a>
            <a class="filter-tab<?= $filter==='suspended'?' filter-tab--active':'' ?>" href="users.php?status=suspended<?= $q!==''?'&q='.urlencode($q):'' ?>">Ditangguhkan</a>
            <a class="filter-tab<?= $filter==='inactive'?' filter-tab--active':'' ?>"  href="users.php?status=inactive<?= $q!==''?'&q='.urlencode($q):'' ?>">Tidak Aktif</a>
        </div>
    </form>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Pengguna</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Catatan</th>
                <th>Terakhir Aktif</th>
                <th style="text-align:right;">Aksi</th>
            </tr>
        </thead>
        <tbody id="usersTbody">
        <?php if (empty($users)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-secondary);padding:1.5rem;">Tidak ada pengguna yang cocok.</td></tr>
        <?php else: foreach ($users as $u): ?>
            <tr data-id="<?= (int) $u['id'] ?>"
                data-name="<?= h($u['name']) ?>"
                data-username="<?= h($u['username']) ?>"
                data-email="<?= h($u['email']) ?>"
                data-role="<?= h($u['role']) ?>"
                data-status="<?= h($u['status']) ?>">
                <td>
                    <div class="table-user-cell">
                        <?= avatar_html($u['name'], $u['username'], 38) ?>
                        <div>
                            <div style="font-weight:500;color:var(--text-primary);"><?= h($u['name']) ?></div>
                            <div style="color:var(--text-secondary);font-size:0.8rem;">@<?= h($u['username']) ?></div>
                        </div>
                    </div>
                </td>
                <td><?= h($u['email']) ?></td>
                <td><?= role_badge($u['role']) ?></td>
                <td><?= status_badge($u['status']) ?></td>
                <td style="font-variant-numeric:tabular-nums;"><?= (int) $u['notes_count'] ?></td>
                <td style="color:var(--text-secondary);font-size:0.85rem;"><?= time_ago($u['last_active_at']) ?></td>
                <td>
                    <div class="action-icons" style="justify-content:flex-end;">
                        <button class="action-icon action-icon--view"  data-action="view-user"  title="Lihat"><i class="fa-solid fa-eye"></i></button>
                        <button class="action-icon action-icon--edit"  data-action="edit-user"  title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                        <button class="action-icon action-icon--delete" data-action="delete-user"
                                data-username="<?= h($u['username']) ?>"
                                data-role="<?= h($u['role']) ?>" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</main>

<script>
const CSRF = '<?= h(csrf_token()) ?>';
const CURRENT_UID  = <?= (int) $admin_user['id'] ?>;
const CURRENT_ROLE = <?= json_encode($admin_user['role']) ?>;
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
const ROLE_OPTS = [
    { value: 'user', label: 'User' },
    { value: 'admin', label: 'Admin' },
    { value: 'super_admin', label: 'Super Admin' },
];
const STATUS_OPTS = [
    { value: 'active', label: 'Aktif' },
    { value: 'suspended', label: 'Ditangguhkan' },
    { value: 'inactive', label: 'Tidak Aktif' },
];

document.getElementById('btnAddUser').addEventListener('click', async () => {
    const data = await showFormModal({
        title: 'Tambah Pengguna Baru',
        fields: [
            { name: 'name',     label: 'Nama Lengkap',     type: 'text',   required: true },
            { name: 'username', label: 'Username',         type: 'text',   required: true, placeholder: 'huruf/angka/underscore, 3–50 char' },
            { name: 'email',    label: 'Email',            type: 'email',  required: true },
            { name: 'password', label: 'Password (min 8)', type: 'text',   required: true },
            { name: 'role',     label: 'Role',             type: 'select', value: 'user',   options: ROLE_OPTS },
            { name: 'status',   label: 'Status',           type: 'select', value: 'active', options: STATUS_OPTS },
        ],
        submitLabel: 'Tambah Pengguna',
    });
    if (!data) return;
    submitAdminForm('users.php', 'create', data, CSRF);
});

document.getElementById('usersTbody').addEventListener('click', async (e) => {
    const viewBtn = e.target.closest('[data-action="view-user"]');
    if (viewBtn) {
        const row = viewBtn.closest('tr');
        await showAlertModal({
            type: 'info',
            title: row.dataset.name + ' (@' + row.dataset.username + ')',
            message: 'Email: ' + row.dataset.email + '\nRole: ' + row.dataset.role + '\nStatus: ' + row.dataset.status,
        });
        return;
    }
    const editBtn = e.target.closest('[data-action="edit-user"]');
    if (editBtn) {
        const row = editBtn.closest('tr');
        const u = {
            id: row.dataset.id, name: row.dataset.name, email: row.dataset.email,
            username: row.dataset.username, role: row.dataset.role, status: row.dataset.status,
        };
        const data = await showFormModal({
            title: 'Edit Pengguna: ' + u.name,
            fields: [
                { name: 'name',   label: 'Nama Lengkap', type: 'text',   required: true, value: u.name },
                { name: 'email',  label: 'Email',        type: 'email',  required: true, value: u.email },
                { name: 'role',   label: 'Role',         type: 'select', value: u.role,   options: ROLE_OPTS },
                { name: 'status', label: 'Status',       type: 'select', value: u.status, options: STATUS_OPTS },
                { name: 'password', label: 'Password Baru (kosongkan kalau tidak ganti)', type: 'text' },
            ],
            submitLabel: 'Simpan Perubahan',
        });
        if (!data) return;
        data.id = u.id;
        submitAdminForm('users.php', 'update', data, CSRF);
        return;
    }
    const delBtn = e.target.closest('[data-action="delete-user"]');
    if (delBtn) {
        const row = delBtn.closest('tr');
        const id  = parseInt(row.dataset.id, 10);
        const username = delBtn.dataset.username;
        const role = delBtn.dataset.role;
        if (id === CURRENT_UID) {
            await showAlertModal({ type: 'error', title: 'Tidak Bisa', message: 'Tidak bisa menghapus akun sendiri.' });
            return;
        }
        if (role === 'super_admin' && CURRENT_ROLE !== 'super_admin') {
            await showAlertModal({ type: 'error', title: 'Akses Ditolak', message: 'Hanya super_admin yang boleh menghapus sesama super_admin.' });
            return;
        }
        const ok = await showConfirmModal({
            title: 'Hapus Pengguna?',
            message: 'Yakin hapus user "' + username + '"? Semua catatan, komentar, dan data terkait ikut terhapus (FK CASCADE).',
            confirmLabel: 'Hapus Pengguna', danger: true,
        });
        if (!ok) return;
        submitAdminForm('users.php', 'delete', { id }, CSRF);
    }
});
</script>
