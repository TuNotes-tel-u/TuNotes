<?php
/**
 * /api/favorites.php — manajemen like/favorit catatan.
 *
 *   GET    [?page=1&limit=12&sort=date_added|rating|pages] → list favorit user (wajib login)
 *   POST   { note_id }                                     → toggle ON (idempotent, 200 kalau sudah ada)
 *   DELETE ?note_id=X                                      → toggle OFF (idempotent)
 *
 * Semua operasi wajib login.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/note_formatter.php';

only_methods('GET', 'POST', 'DELETE');
start_session_safe();

$method = $_SERVER['REQUEST_METHOD'];

// =============================================================================
// GET — daftar favorit user yang login
// =============================================================================
if ($method === 'GET') {
    $uid    = require_login();
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $limit  = (int) ($_GET['limit'] ?? 12);
    if ($limit <= 0 || $limit > 100) $limit = 12;
    $offset = ($page - 1) * $limit;

    $sort = (string) ($_GET['sort'] ?? 'date_added');
    switch ($sort) {
        case 'rating': $orderBy = 'n.avg_rating DESC, n.rating_count DESC, f.created_at DESC'; break;
        case 'pages':  $orderBy = 'n.page_count DESC, f.created_at DESC'; break;
        case 'date_added':
        default:       $orderBy = 'f.created_at DESC'; $sort = 'date_added'; break;
    }

    // Count
    $sql = "SELECT COUNT(*) AS c
            FROM favorites f
            JOIN notes n ON n.id = f.note_id AND n.status = 'active'
            WHERE f.user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    mysqli_stmt_close($stmt);
    $total_pages = $total > 0 ? (int) ceil($total / $limit) : 0;

    // Data
    $sql = "SELECT n.*, f.created_at AS favorited_at,
                   u.name AS uploader_name, u.username AS uploader_username, u.avatar_url AS uploader_avatar
            FROM favorites f
            JOIN notes n ON n.id = f.note_id AND n.status = 'active'
            JOIN users u ON u.id = n.user_id
            WHERE f.user_id = ?
            ORDER BY $orderBy
            LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $uid, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) {
        $rows[] = format_note_row($r);
    }
    mysqli_stmt_close($stmt);

    json_success('OK', $rows, [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => $total_pages,
        'sort'        => $sort,
    ]);
}

// =============================================================================
// POST — tambah ke favorit (idempotent)
// =============================================================================
if ($method === 'POST') {
    $uid     = require_login();
    $input   = read_json_input();
    $note_id = (int) ($input['note_id'] ?? 0);
    if ($note_id <= 0) {
        json_error('note_id tidak valid.', 'INVALID_ID', 400);
    }

    // Cek note exists & active
    $sql  = "SELECT id FROM notes WHERE id = ? AND status = 'active' LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$note) {
        json_error('Catatan tidak ditemukan.', 'NOT_FOUND', 404);
    }

    // Idempotent: kalau sudah ada, return 200 dengan flag.
    $sql  = "SELECT id FROM favorites WHERE user_id = ? AND note_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $note_id);
    mysqli_stmt_execute($stmt);
    $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($exists) {
        json_success('Sudah di favorit.', ['note_id' => $note_id, 'favorited' => true]);
    }

    $sql  = "INSERT INTO favorites (user_id, note_id) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $note_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('[TuNotes] favorites.add gagal: ' . mysqli_stmt_error($stmt));
        json_error('Gagal menambah favorit.', 'INTERNAL_ERROR', 500);
    }
    mysqli_stmt_close($stmt);

    json_success('Ditambahkan ke favorit.', ['note_id' => $note_id, 'favorited' => true], null, 201);
}

// =============================================================================
// DELETE — hapus dari favorit (idempotent)
// =============================================================================
if ($method === 'DELETE') {
    $uid     = require_login();
    $note_id = (int) ($_GET['note_id'] ?? 0);
    if ($note_id <= 0) {
        json_error('note_id tidak valid.', 'INVALID_ID', 400);
    }

    $sql  = "DELETE FROM favorites WHERE user_id = ? AND note_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $note_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Idempotent: tidak peduli row terhapus atau memang gak ada.
    json_success('Dihapus dari favorit.', ['note_id' => $note_id, 'favorited' => false]);
}
