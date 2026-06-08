<?php
/**
 * /api/read-notes.php — Reading Board (Read Notes).
 *
 *   GET  (tanpa param / ?view=reading_board)      → Kanban grouped by status
 *   POST ?action=add_to_reading                   { note_id, status? }   (idempotent)
 *   POST ?action=update_reading&id=X              { status?, progress? }
 *   (PUT diterima untuk update_reading dengan body yang sama.)
 *   DELETE ?action=delete_reading&id=X
 *
 * Semua endpoint WAJIB LOGIN.
 *
 * Schema translation:
 *   - reading_progress: kolom DB `progress_percent` + enum `belum_dibaca|sedang_dipelajari|selesai`.
 *     API: field `progress` + enum `unread|learning|done`.
 *
 * Catatan: endpoint ini sebelumnya jadi satu di /api/study.php, dipisah supaya
 * modul Read Notes berdiri sendiri.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/note_formatter.php';

only_methods('GET', 'POST', 'PUT', 'DELETE');
start_session_safe();

$uid    = require_login();
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

// -----------------------------------------------------------------------------
// Translation tables (API ↔ DB)
// -----------------------------------------------------------------------------
const READING_API_TO_DB = [
    'unread'   => 'belum_dibaca',
    'learning' => 'sedang_dipelajari',
    'done'     => 'selesai',
];
const READING_DB_TO_API = [
    'belum_dibaca'      => 'unread',
    'sedang_dipelajari' => 'learning',
    'selesai'           => 'done',
];

// -----------------------------------------------------------------------------
// Formatter
// -----------------------------------------------------------------------------
function format_reading_item(array $row): array
{
    // Reuse format_note_row untuk data catatan, tambah reading_*
    $note = format_note_row($row);
    $note['reading'] = [
        'id'          => (int) $row['reading_id'],
        'status'      => READING_DB_TO_API[$row['reading_status']] ?? 'unread',
        'progress'    => (int) ($row['reading_progress'] ?? 0),
        'target_date' => $row['target_date'] ?? null,
        'updated_at'  => $row['reading_updated_at'] ?? null,
    ];
    return $note;
}

// -----------------------------------------------------------------------------
// Ownership helper
// -----------------------------------------------------------------------------
function get_reading_or_403(mysqli $conn, int $id, int $uid): array
{
    $sql  = "SELECT * FROM reading_progress WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) {
        json_error('Reading entry tidak ditemukan.', 'NOT_FOUND', 404);
    }
    if ((int) $row['user_id'] !== $uid) {
        json_error('Akses ditolak.', 'FORBIDDEN', 403);
    }
    return $row;
}

// =============================================================================
// GET — reading board
// =============================================================================
if ($method === 'GET') {
    $sql = "SELECT rp.id AS reading_id, rp.status AS reading_status,
                   rp.progress_percent AS reading_progress, rp.target_date,
                   rp.updated_at AS reading_updated_at,
                   n.*, u.name AS uploader_name, u.username AS uploader_username, u.avatar_url AS uploader_avatar
            FROM reading_progress rp
            JOIN notes n ON n.id = rp.note_id AND n.status = 'active'
            JOIN users u ON u.id = n.user_id
            WHERE rp.user_id = ?
            ORDER BY rp.updated_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $board = ['unread' => [], 'learning' => [], 'done' => []];
    $total = 0;
    $done  = 0;
    while ($r = mysqli_fetch_assoc($result)) {
        $item = format_reading_item($r);
        $apiStatus = $item['reading']['status'];
        if (!isset($board[$apiStatus])) $apiStatus = 'unread';
        $board[$apiStatus][] = $item;
        $total++;
        if ($apiStatus === 'done') $done++;
    }
    mysqli_stmt_close($stmt);

    // "Target Minggu Ini" — item learning dengan target_date ≤ 7 hari ke depan.
    $week_target = 0;
    foreach ($board['learning'] as $it) {
        if (!empty($it['reading']['target_date'])) {
            $diff_days = (strtotime($it['reading']['target_date']) - time()) / 86400;
            if ($diff_days <= 7) $week_target++;
        }
    }

    json_success('OK', $board, [
        'total'       => $total,
        'done_count'  => $done,
        'week_target' => $week_target,
    ]);
}

// =============================================================================
// POST + PUT — add & update
// =============================================================================
if ($method === 'POST' || $method === 'PUT') {
    $input = read_json_input();

    // ------------------------------------------------------------------------
    // add_to_reading (idempotent)
    // ------------------------------------------------------------------------
    if ($action === 'add_to_reading') {
        $note_id = (int) ($input['note_id'] ?? 0);
        if ($note_id <= 0) {
            json_error('note_id wajib.', 'INVALID_PARAM', 400);
        }
        $status_in = (string) ($input['status'] ?? 'unread');
        $db_status = READING_API_TO_DB[$status_in] ?? 'belum_dibaca';

        // Cek note ada
        $sql  = "SELECT id FROM notes WHERE id = ? AND status = 'active' LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $note_id);
        mysqli_stmt_execute($stmt);
        $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$note) {
            json_error('Catatan tidak ditemukan.', 'NOT_FOUND', 404);
        }

        // Idempotent: cek existing
        $sql  = "SELECT id FROM reading_progress WHERE user_id = ? AND note_id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $uid, $note_id);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($exists) {
            json_success('Sudah ada di reading board.', [
                'id'      => (int) $exists['id'],
                'note_id' => $note_id,
                'status'  => $status_in,
            ]);
        }

        $sql  = "INSERT INTO reading_progress (user_id, note_id, status, progress_percent) VALUES (?, ?, ?, 0)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iis', $uid, $note_id, $db_status);
        if (!mysqli_stmt_execute($stmt)) {
            error_log('[TuNotes] add_to_reading gagal: ' . mysqli_stmt_error($stmt));
            json_error('Gagal menyimpan.', 'INTERNAL_ERROR', 500);
        }
        $new_id = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        json_success('Ditambah ke reading board.', [
            'id'       => $new_id,
            'note_id'  => $note_id,
            'status'   => READING_DB_TO_API[$db_status],
            'progress' => 0,
        ], null, 201);
    }

    // ------------------------------------------------------------------------
    // update_reading
    // ------------------------------------------------------------------------
    if ($action === 'update_reading') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) json_error('id reading tidak valid.', 'INVALID_ID', 400);
        get_reading_or_403($conn, $id, $uid);

        $sets  = [];
        $types = '';
        $vals  = [];
        if (array_key_exists('status', $input)) {
            $db_status = READING_API_TO_DB[(string) $input['status']] ?? null;
            if ($db_status === null) {
                json_error('status harus unread|learning|done.', 'VALIDATION_FAILED', 400);
            }
            $sets[]  = 'status = ?';
            $types  .= 's';
            $vals[]  = $db_status;
        }
        if (array_key_exists('progress', $input)) {
            $p = (int) $input['progress'];
            if ($p < 0)   $p = 0;
            if ($p > 100) $p = 100;
            $sets[]  = 'progress_percent = ?';
            $types  .= 'i';
            $vals[]  = $p;
        }
        if (empty($sets)) {
            json_error('Tidak ada field yang diubah.', 'NOTHING_TO_UPDATE', 400);
        }
        $sql    = 'UPDATE reading_progress SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $types .= 'i';
        $vals[] = $id;
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$vals);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Return state baru
        $sql  = "SELECT id, status, progress_percent, target_date, updated_at FROM reading_progress WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        json_success('Reading diperbarui.', [
            'id'         => (int) $row['id'],
            'status'     => READING_DB_TO_API[$row['status']] ?? 'unread',
            'progress'   => (int) $row['progress_percent'],
            'target_date'=> $row['target_date'],
            'updated_at' => $row['updated_at'],
        ]);
    }

    json_error('Action POST/PUT tidak dikenal. Gunakan add_to_reading atau update_reading.', 'INVALID_ACTION', 400);
}

// =============================================================================
// DELETE
// =============================================================================
if ($method === 'DELETE') {

    if ($action === 'delete_reading') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) json_error('id reading tidak valid.', 'INVALID_ID', 400);
        get_reading_or_403($conn, $id, $uid);

        $sql  = "DELETE FROM reading_progress WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $id, $uid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        json_success('Dihapus dari reading board.');
    }

    json_error('Action DELETE tidak dikenal. Gunakan delete_reading.', 'INVALID_ACTION', 400);
}
