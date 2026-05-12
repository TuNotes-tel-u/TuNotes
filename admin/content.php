<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/../helpers/upload_helper.php';
require_once __DIR__ . '/../helpers/admin_view.php';

// =============================================================================
// POST handler — hard delete + unlink file.
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        admin_flash_redirect('content.php', 'error', 'CSRF token tidak valid.');
    }
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) admin_flash_redirect('content.php', 'error', 'ID tidak valid.');

        $sql  = "SELECT file_path, user_id FROM notes WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$note) admin_flash_redirect('content.php', 'error', 'Catatan tidak ditemukan.');

        $sql  = "DELETE FROM notes WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        delete_stored_file($note['file_path']);

        $sql  = "UPDATE users SET notes_count = GREATEST(CAST(notes_count AS SIGNED) - 1, 0) WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $note['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        admin_flash_redirect('content.php', 'success', 'Catatan dihapus (file + row).');
    }
    admin_flash_redirect('content.php', 'error', 'Action tidak dikenal.');
}

// =============================================================================
// GET render
// =============================================================================
$tabFilter = $_GET['tab']  ?? 'all';      // all|published|reported|review (schema dukung 'all' & 'published')
$q         = trim((string) ($_GET['q'] ?? ''));

$where  = ["n.status = 'active'"];
$params = []; $types = '';

if ($q !== '') {
    $where[] = '(n.title LIKE ? OR n.course_name LIKE ? OR n.description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}
$whereSql = implode(' AND ', $where);

$sql = "SELECT n.id, n.title, n.course_name, n.category, n.file_name, n.file_type,
               n.downloads_count, n.avg_rating, n.rating_count, n.created_at,
               u.name AS uploader_name, u.username
        FROM notes n JOIN users u ON u.id = n.user_id
        WHERE $whereSql
        ORDER BY n.downloads_count DESC, n.created_at DESC LIMIT 200";
$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$notes = [];
while ($r = mysqli_fetch_assoc($res)) $notes[] = $r;
mysqli_stmt_close($stmt);

// Stats
$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM notes WHERE status='active'");
$totalNotes = (int) mysqli_fetch_assoc($rs)['c'];
$rs = mysqli_query($conn, "SELECT IFNULL(SUM(downloads_count),0) AS s FROM notes WHERE status='active'");
$totalDownloads = (int) mysqli_fetch_assoc($rs)['s'];
$rs = mysqli_query($conn, "SELECT IFNULL(AVG(avg_rating),0) AS a FROM notes WHERE status='active' AND rating_count > 0");
$avgRating = round((float) mysqli_fetch_assoc($rs)['a'], 1);
$reportedCount = 0; // Schema belum punya tabel reports — placeholder.

$page_title   = 'Moderasi Konten';
$current_page = 'content';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-header">
        <div>
            <h1>Moderasi Konten</h1>
            <p class="page-subtitle">Pantau & moderasi catatan publik di TuNotes.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="admin-flash admin-flash--<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <?= stat_card_html('fa-file-lines', 'blue',   $totalNotes,     'Total Catatan') ?>
        <?= stat_card_html('fa-star',       'yellow', $avgRating,      'Rata-rata Rating') ?>
        <?= stat_card_html('fa-download',   'purple', $totalDownloads, 'Total Unduhan') ?>
        <?= stat_card_html('fa-flag',       'red',    $reportedCount,  'Dilaporkan') ?>
    </div>

    <form method="GET" class="filter-bar">
        <input type="search" name="q" class="search-input" placeholder="Cari judul, mata kuliah, atau deskripsi…" value="<?= h($q) ?>" onchange="this.form.submit()">
        <div class="filter-tabs">
            <a class="filter-tab<?= $tabFilter==='all'?' filter-tab--active':'' ?>"        href="content.php<?= $q!==''?'?q='.urlencode($q):'' ?>">Semua</a>
            <a class="filter-tab<?= $tabFilter==='published'?' filter-tab--active':'' ?>"  href="content.php?tab=published<?= $q!==''?'&q='.urlencode($q):'' ?>">Dipublikasi</a>
            <a class="filter-tab<?= $tabFilter==='reported'?' filter-tab--active':'' ?>"   href="content.php?tab=reported<?= $q!==''?'&q='.urlencode($q):'' ?>">Dilaporkan</a>
            <a class="filter-tab<?= $tabFilter==='review'?' filter-tab--active':'' ?>"     href="content.php?tab=review<?= $q!==''?'&q='.urlencode($q):'' ?>">Dalam Review</a>
        </div>
    </form>

    <div class="content-list" id="contentList">
    <?php if (empty($notes)): ?>
        <div style="text-align:center;color:var(--text-secondary);padding:2rem;background:#fff;border-radius:12px;">
            Tidak ada catatan yang cocok.
        </div>
    <?php else: foreach ($notes as $n): ?>
        <div class="content-card" data-id="<?= (int) $n['id'] ?>" data-title="<?= h($n['title'] ?: $n['course_name']) ?>">
            <div class="content-icon"><i class="fa-solid fa-file-lines"></i></div>
            <div class="content-body">
                <h3 class="content-title"><?= h($n['title'] ?: $n['course_name']) ?></h3>
                <div class="content-meta">
                    <span class="content-meta-strong"><?= h($n['uploader_name']) ?></span>
                    <?php if ($n['category']): ?>· <?= h($n['category']) ?><?php endif; ?>
                    · <?= h(substr($n['created_at'], 0, 10)) ?>
                    · <span style="text-transform:uppercase;font-size:0.75rem;background:#f3f4f6;padding:0.1rem 0.4rem;border-radius:4px;"><?= h($n['file_type']) ?></span>
                </div>
            </div>
            <div class="content-stats">
                <span class="star"><i class="fa-solid fa-star"></i> <?= number_format((float) $n['avg_rating'], 1) ?></span>
                <span class="dl"><i class="fa-solid fa-download"></i> <?= (int) $n['downloads_count'] ?></span>
                <?= status_badge('published') ?>
            </div>
            <div class="action-icons">
                <a class="action-icon action-icon--view" href="../pages/DetailCatatan.html?id=<?= (int) $n['id'] ?>" target="_blank" title="Lihat"><i class="fa-solid fa-eye"></i></a>
                <button class="action-icon action-icon--delete" data-action="delete-note" title="Hapus"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
    <?php endforeach; endif; ?>
    </div>
</main>

<script>
const CSRF = '<?= h(csrf_token()) ?>';
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.getElementById('contentList').addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action="delete-note"]');
    if (!btn) return;
    const card = btn.closest('.content-card');
    const title = card.dataset.title;
    const ok = await showConfirmModal({
        title: 'Hapus Catatan?',
        message: 'Catatan "' + title + '" akan dihapus permanen beserta file-nya. Aksi tidak bisa dibatalkan.',
        confirmLabel: 'Hapus Catatan', danger: true,
    });
    if (!ok) return;
    submitAdminForm('content.php', 'delete', { id: card.dataset.id }, CSRF);
});
</script>
