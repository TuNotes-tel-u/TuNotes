<?php
/**
 * /api/comments.php — komentar pada catatan.
 *
 *   GET    ?note_id=X                         → list komentar terstruktur (root + 1 level reply)
 *   POST   { note_id, content, parent_id? }   → tambah komentar / reply (wajib login)
 *   DELETE ?id=X                              → hapus (owner atau admin/super_admin)
 *
 * Schema diff: tabel pakai `status ENUM('active','deleted')` untuk soft delete,
 * bukan `deleted_at` TIMESTAMP. Saat soft delete, `content` di-replace dengan
 * '[Komentar dihapus]' supaya thread tetap utuh tapi konten lama disembunyikan.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

only_methods('GET', 'POST', 'DELETE');
start_session_safe();

$method = $_SERVER['REQUEST_METHOD'];

function format_comment_row(array $row): array
{
    return [
        'id'         => (int) $row['id'],
        'note_id'    => (int) $row['note_id'],
        'parent_id'  => isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
        'content'    => $row['content'],
        'deleted'    => $row['status'] === 'deleted',
        'created_at' => $row['created_at'],
        'commenter'  => [
            'id'         => (int) $row['user_id'],
            'name'       => $row['commenter_name'] ?? null,
            'username'   => $row['commenter_username'] ?? null,
            'avatar_url' => $row['commenter_avatar'] ?? null,
        ],
        'replies'    => [],
    ];
}

// =============================================================================
// GET — list komentar untuk satu catatan
// =============================================================================
if ($method === 'GET') {
    $note_id = (int) ($_GET['note_id'] ?? 0);
    if ($note_id <= 0) {
        json_error('note_id wajib ada.', 'INVALID_PARAM', 400);
    }

    // Cek catatan exists & accessible (kalau privat, butuh login & owner)
    $sql  = "SELECT id, user_id, is_public FROM notes WHERE id = ? AND status = 'active' LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$note) {
        json_error('Catatan tidak ditemukan.', 'NOT_FOUND', 404);
    }
    if ((int) $note['is_public'] === 0) {
        $uid = current_user_id();
        if ($uid === null || $uid !== (int) $note['user_id']) {
            json_error('Akses ditolak — catatan privat.', 'FORBIDDEN', 403);
        }
    }

    // Ambil semua komentar (active + deleted, supaya thread utuh).
    // Sort ASC dulu untuk gampang build tree.
    $sql = "SELECT c.id, c.note_id, c.user_id, c.parent_id, c.content, c.status, c.created_at,
                   u.name AS commenter_name, u.username AS commenter_username, u.avatar_url AS commenter_avatar
            FROM comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.note_id = ?
            ORDER BY c.created_at ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $all = [];
    while ($r = mysqli_fetch_assoc($result)) {
        $all[] = format_comment_row($r);
    }
    mysqli_stmt_close($stmt);

    // Build tree 1 level: root → replies. Reply ke reply tetap dilampirkan ke
    // root terdekat (flat per parent — sesuai spec "tidak perlu deep nesting").
    $rootIndex = []; // id → index di $roots
    $roots = [];
    foreach ($all as $c) {
        if ($c['parent_id'] === null) {
            $rootIndex[$c['id']] = count($roots);
            $roots[] = $c;
        }
    }
    foreach ($all as $c) {
        if ($c['parent_id'] !== null && isset($rootIndex[$c['parent_id']])) {
            $idx = $rootIndex[$c['parent_id']];
            $roots[$idx]['replies'][] = $c;
        }
    }
    // Root: terbaru di atas. Replies sudah ASC by created_at.
    $roots = array_reverse($roots);

    json_success('OK', $roots, ['total' => count($all)]);
}

// =============================================================================
// POST — tambah komentar / reply
// =============================================================================
if ($method === 'POST') {
    $uid       = require_login();
    $input     = read_json_input();
    $note_id   = (int) ($input['note_id'] ?? 0);
    $content   = trim((string) ($input['content'] ?? ''));
    $parent_id = !empty($input['parent_id']) ? (int) $input['parent_id'] : null;

    if ($note_id <= 0) {
        json_error('note_id tidak valid.', 'INVALID_PARAM', 400);
    }
    $len = mb_strlen($content);
    if ($len < 1 || $len > 1000) {
        json_error('Komentar harus 1–1000 karakter.', 'VALIDATION_FAILED', 400);
    }

    // Cek catatan
    $sql  = "SELECT id FROM notes WHERE id = ? AND status = 'active' LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$note) {
        json_error('Catatan tidak ditemukan.', 'NOT_FOUND', 404);
    }

    // Validasi parent_id (kalau ada)
    if ($parent_id !== null) {
        $sql  = "SELECT id, note_id FROM comments WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $parent_id);
        mysqli_stmt_execute($stmt);
        $parent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$parent || (int) $parent['note_id'] !== $note_id) {
            json_error('Parent comment tidak valid.', 'INVALID_PARENT', 400);
        }
    }

    $sql  = "INSERT INTO comments (note_id, user_id, parent_id, content) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iiis', $note_id, $uid, $parent_id, $content);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('[TuNotes] comments.create gagal: ' . mysqli_stmt_error($stmt));
        json_error('Gagal menyimpan komentar.', 'INTERNAL_ERROR', 500);
    }
    $new_id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Return komentar baru dengan data commenter
    $sql = "SELECT c.id, c.note_id, c.user_id, c.parent_id, c.content, c.status, c.created_at,
                   u.name AS commenter_name, u.username AS commenter_username, u.avatar_url AS commenter_avatar
            FROM comments c JOIN users u ON u.id = c.user_id
            WHERE c.id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $new_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    json_success('Komentar dipost.', format_comment_row($row), null, 201);
}

// =============================================================================
// DELETE — soft delete kalau punya reply, hard delete kalau tidak
// =============================================================================
if ($method === 'DELETE') {
    $uid = require_login();
    $id  = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('ID komentar tidak valid.', 'INVALID_ID', 400);
    }

    // Cek ownership / admin
    $sql  = "SELECT user_id FROM comments WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $comment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$comment) {
        json_error('Komentar tidak ditemukan.', 'NOT_FOUND', 404);
    }

    $role    = current_user_role();
    $isAdmin = in_array($role, ['admin', 'super_admin'], true);
    if (!$isAdmin && (int) $comment['user_id'] !== $uid) {
        json_error('Hanya pemilik atau admin yang boleh menghapus.', 'FORBIDDEN', 403);
    }

    // Punya reply atau tidak?
    $sql  = "SELECT COUNT(*) AS c FROM comments WHERE parent_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $childCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    mysqli_stmt_close($stmt);

    if ($childCount > 0) {
        // Soft delete: thread tetap utuh, content di-mask.
        $sql  = "UPDATE comments SET status = 'deleted', content = '[Komentar dihapus]' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        json_success('Komentar di-soft-delete (ada reply).', ['id' => $id, 'deleted' => true, 'soft' => true]);
    } else {
        // Hard delete: row dihapus permanen.
        $sql  = "DELETE FROM comments WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        json_success('Komentar dihapus.', ['id' => $id, 'deleted' => true, 'soft' => false]);
    }
}
