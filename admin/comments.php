<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/../helpers/admin_view.php';

// =============================================================================
// POST handler — soft delete.
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        admin_flash_redirect('comments.php', 'error', 'CSRF token tidak valid.');
    }
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) admin_flash_redirect('comments.php', 'error', 'ID tidak valid.');
        $sql  = "UPDATE comments SET status='deleted', content='[Komentar dihapus oleh moderator]'
                 WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($affected > 0) {
            admin_flash_redirect('comments.php', 'success', 'Komentar di-soft-delete.');
        }
        admin_flash_redirect('comments.php', 'error', 'Komentar tidak ditemukan.');
    }
    admin_flash_redirect('comments.php', 'error', 'Action tidak dikenal.');
}

// =============================================================================
// GET render
// =============================================================================
$tabFilter = $_GET['tab'] ?? 'all';
$q         = trim((string) ($_GET['q'] ?? ''));
$page      = max(1, (int) ($_GET['page'] ?? 1));
$limit     = 20;
$offset    = ($page - 1) * $limit;

$where  = ["c.status='active'"];
$params = []; $types = '';
if ($q !== '') {
    $where[] = '(c.content LIKE ? OR u.name LIKE ? OR n.course_name LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}
$whereSql = implode(' AND ', $where);

$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM comments WHERE status='active'");
$totalActive = (int) mysqli_fetch_assoc($rs)['c'];
$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM comments WHERE status='deleted'");
$totalDeleted = (int) mysqli_fetch_assoc($rs)['c'];

$sql = "SELECT c.id, c.content, c.created_at, c.note_id,
               u.id AS user_id, u.name AS commenter_name, u.username,
               n.course_name, n.title AS note_title
        FROM comments c
        JOIN users u ON u.id = c.user_id
        JOIN notes n ON n.id = c.note_id
        WHERE $whereSql
        ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$limit, $offset]);
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$comments = [];
while ($r = mysqli_fetch_assoc($res)) $comments[] = $r;
mysqli_stmt_close($stmt);

$page_title   = 'Manajemen Komentar';
$current_page = 'comments';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-header">
        <div>
            <h1>Manajemen Komentar</h1>
            <p class="page-subtitle">Pantau & moderasi komentar pada catatan publik.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="admin-flash admin-flash--<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <?= stat_card_html('fa-comment',              'blue',  $totalActive,  'Total Komentar') ?>
        <?= stat_card_html('fa-circle-check',         'green', $totalActive,  'Disetujui') ?>
        <?= stat_card_html('fa-triangle-exclamation', 'red',   0,             'Dilaporkan') ?>
    </div>

    <form method="GET" class="filter-bar">
        <input type="search" name="q" class="search-input" placeholder="Cari isi komentar, commenter, atau judul catatan…" value="<?= h($q) ?>" onchange="this.form.submit()">
        <div class="filter-tabs">
            <a class="filter-tab<?= $tabFilter==='all'?' filter-tab--active':'' ?>"        href="comments.php<?= $q!==''?'?q='.urlencode($q):'' ?>">Semua</a>
            <a class="filter-tab<?= $tabFilter==='approved'?' filter-tab--active':'' ?>"   href="comments.php?tab=approved<?= $q!==''?'&q='.urlencode($q):'' ?>">Disetujui</a>
            <a class="filter-tab<?= $tabFilter==='reported'?' filter-tab--active':'' ?>"   href="comments.php?tab=reported<?= $q!==''?'&q='.urlencode($q):'' ?>">Dilaporkan</a>
        </div>
    </form>

    <div class="content-list" id="commentsList">
    <?php if (empty($comments)): ?>
        <div style="text-align:center;color:var(--text-secondary);padding:2rem;background:#fff;border-radius:12px;">
            Tidak ada komentar aktif.
        </div>
    <?php else: foreach ($comments as $c): ?>
        <div class="content-card" data-id="<?= (int) $c['id'] ?>" style="align-items:flex-start;flex-direction:column;gap:0.6rem;">
            <div style="display:flex;align-items:center;gap:0.6rem;width:100%;">
                <?= avatar_html($c['commenter_name'], $c['username'], 36) ?>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:500;color:var(--text-primary);font-size:0.92rem;">
                        <?= h($c['commenter_name']) ?>
                        <span style="color:var(--text-secondary);font-weight:400;">pada</span>
                        <a href="../pages/DetailCatatan.html?id=<?= (int) $c['note_id'] ?>" target="_blank" style="color:var(--primary);text-decoration:none;">
                            <?= h($c['note_title'] ?: $c['course_name']) ?>
                        </a>
                    </div>
                    <div style="color:var(--text-secondary);font-size:0.78rem;"><?= time_ago($c['created_at']) ?> · @<?= h($c['username']) ?></div>
                </div>
                <?= status_badge('approved') ?>
                <div class="action-icons">
                    <a class="action-icon action-icon--view" href="../pages/DetailCatatan.html?id=<?= (int) $c['note_id'] ?>" target="_blank" title="Lihat thread"><i class="fa-solid fa-eye"></i></a>
                    <button class="action-icon action-icon--delete" data-action="delete-comment" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
            <div style="background:#f9fafb;padding:0.75rem 1rem;border-radius:8px;color:#374151;font-size:0.9rem;width:100%;">
                <?= h($c['content']) ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
    </div>

    <?php
    $totalPages = $totalActive > 0 ? (int) ceil($totalActive / $limit) : 0;
    if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</main>

<script>
const CSRF = '<?= h(csrf_token()) ?>';
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.getElementById('commentsList').addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action="delete-comment"]');
    if (!btn) return;
    const card = btn.closest('.content-card');
    const ok = await showConfirmModal({
        title: 'Hapus Komentar?',
        message: 'Komentar akan di-soft-delete: content diganti placeholder, thread tetap utuh.',
        confirmLabel: 'Hapus Komentar', danger: true,
    });
    if (!ok) return;
    submitAdminForm('comments.php', 'delete', { id: card.dataset.id }, CSRF);
});
</script>
