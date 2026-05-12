<?php
/**
 * /api/notes.php — CRUD catatan.
 *
 *   GET  ?id=X                                 → detail satu catatan
 *   GET  ?scope=public&sort=newest|popular|rating&page=1&limit=12   → list publik (default)
 *   GET  ?scope=mine&page=1&limit=12           → list milik user yang login (auth wajib)
 *   GET  ?course_id=X&page=1&limit=12          → list dalam satu course
 *   POST                                       → create (multipart, file wajib)
 *   POST ?action=update&id=X                   → update (multipart, file optional)
 *   DELETE ?id=X                               → hapus catatan + file di disk
 *
 * Catatan implementasi:
 *   - DB pakai kolom `is_public` (TINYINT). API translate ke string "public"/"private".
 *   - User-initiated DELETE = hard delete (row dihapus, file diunlink). Soft delete
 *     (status='deleted') dicadangkan untuk moderasi admin di Tahap 6.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/upload_helper.php';
require_once __DIR__ . '/../helpers/note_formatter.php';

only_methods('GET', 'POST', 'DELETE');
start_session_safe();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

// -----------------------------------------------------------------------------
// format_note_row() sekarang ada di helpers/note_formatter.php (dipakai juga
// oleh /api/favorites.php). Helper berikut khusus untuk rating: recalc cache
// `avg_rating` & `rating_count` di kolom notes setiap kali ratings berubah.
// -----------------------------------------------------------------------------
function recalc_note_rating(mysqli $conn, int $note_id): void
{
    $sql  = "UPDATE notes
             SET avg_rating   = COALESCE((SELECT AVG(score) FROM ratings WHERE note_id = ?), 0.0),
                 rating_count = (SELECT COUNT(*) FROM ratings WHERE note_id = ?)
             WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $note_id, $note_id, $note_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function get_note_rating_stats(mysqli $conn, int $note_id): array
{
    $sql  = "SELECT avg_rating, rating_count FROM notes WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return [
        'avg_rating'   => (float) ($row['avg_rating'] ?? 0),
        'rating_count' => (int) ($row['rating_count'] ?? 0),
    ];
}

// =============================================================================
// GET — detail atau list
// =============================================================================
if ($method === 'GET') {
    // ---------- Detail satu catatan ----------
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        if ($id <= 0) {
            json_error('ID catatan tidak valid.', 'INVALID_ID', 400);
        }

        $sql = "SELECT n.*, u.name AS uploader_name, u.username AS uploader_username, u.avatar_url AS uploader_avatar
                FROM notes n
                JOIN users u ON u.id = n.user_id
                WHERE n.id = ? AND n.status = 'active'
                LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            json_error('Catatan tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $uid = current_user_id();
        if ((int) $row['is_public'] === 0 && (int) $row['user_id'] !== (int) $uid) {
            json_error('Akses ditolak — catatan ini privat.', 'FORBIDDEN', 403);
        }

        json_success('OK', format_note_row($row));
    }

    // ---------- List dengan scope ----------
    $scope  = (string) ($_GET['scope'] ?? 'public');
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $limit  = (int) ($_GET['limit'] ?? 12);
    if ($limit <= 0 || $limit > 100) $limit = 12;
    $offset = ($page - 1) * $limit;

    $sort = (string) ($_GET['sort'] ?? 'newest');
    switch ($sort) {
        case 'popular': $orderBy = 'n.downloads_count DESC, n.created_at DESC'; break;
        case 'rating':  $orderBy = 'n.avg_rating DESC, n.rating_count DESC, n.created_at DESC'; break;
        case 'newest':
        default:        $orderBy = 'n.created_at DESC'; $sort = 'newest'; break;
    }

    $where  = ["n.status = 'active'"];
    $types  = '';
    $params = [];

    if ($scope === 'mine') {
        $uid     = require_login();
        $where[] = 'n.user_id = ?';
        $types  .= 'i';
        $params[] = $uid;
    } elseif (isset($_GET['course_id'])) {
        $cid = (int) $_GET['course_id'];
        if ($cid <= 0) {
            json_error('course_id tidak valid.', 'INVALID_PARAM', 400);
        }
        $where[] = 'n.course_id = ?';
        $types  .= 'i';
        $params[] = $cid;

        $uid = current_user_id();
        if ($uid !== null) {
            // Boleh lihat note public dalam course, atau note sendiri di course tsb.
            $where[] = '(n.is_public = 1 OR n.user_id = ?)';
            $types  .= 'i';
            $params[] = $uid;
        } else {
            $where[] = 'n.is_public = 1';
        }
    } else {
        // Default: public list (boleh diakses guest).
        $scope   = 'public';
        $where[] = 'n.is_public = 1';
    }

    $whereSql = implode(' AND ', $where);

    // --- Count total ---
    $countSql = "SELECT COUNT(*) AS c FROM notes n WHERE $whereSql";
    $stmt = mysqli_prepare($conn, $countSql);
    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $countRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $total    = (int) ($countRow['c'] ?? 0);
    mysqli_stmt_close($stmt);

    $total_pages = $total > 0 ? (int) ceil($total / $limit) : 0;

    // --- Data ---
    $dataSql = "SELECT n.*, u.name AS uploader_name, u.username AS uploader_username, u.avatar_url AS uploader_avatar
                FROM notes n
                JOIN users u ON u.id = n.user_id
                WHERE $whereSql
                ORDER BY $orderBy
                LIMIT ? OFFSET ?";
    $allTypes  = $types . 'ii';
    $allParams = $params;
    $allParams[] = $limit;
    $allParams[] = $offset;

    $stmt = mysqli_prepare($conn, $dataSql);
    mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
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
        'scope'       => $scope,
    ]);
}

// =============================================================================
// POST — create, update (?action=update), rate (?action=rate)
// =============================================================================
if ($method === 'POST') {
    // ---------- RATE (Tahap 3) ----------
    if ($action === 'rate') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            json_error('ID catatan tidak valid.', 'INVALID_ID', 400);
        }
        $uid = require_login();

        $input  = read_json_input();
        $rating = $input['rating'] ?? null;
        if (!is_numeric($rating) || (int) $rating != $rating || $rating < 1 || $rating > 5) {
            json_error('Rating harus integer 1–5.', 'VALIDATION_FAILED', 400);
        }
        $rating = (int) $rating;

        $sql  = "SELECT user_id FROM notes WHERE id = ? AND status = 'active' LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$note) {
            json_error('Catatan tidak ditemukan.', 'NOT_FOUND', 404);
        }
        if ((int) $note['user_id'] === $uid) {
            json_error('Tidak boleh memberi rating pada catatan sendiri.', 'SELF_RATING', 403);
        }

        // Upsert ke ratings (kolom DB-nya `score`, body API pakai `rating`).
        $sql  = "INSERT INTO ratings (user_id, note_id, score) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE score = VALUES(score)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $uid, $id, $rating);
        if (!mysqli_stmt_execute($stmt)) {
            error_log('[TuNotes] notes.rate gagal: ' . mysqli_stmt_error($stmt));
            json_error('Gagal menyimpan rating.', 'INTERNAL_ERROR', 500);
        }
        mysqli_stmt_close($stmt);

        recalc_note_rating($conn, $id);
        $stats = get_note_rating_stats($conn, $id);
        $stats['my_rating'] = $rating;
        json_success('Rating tersimpan.', $stats);
    }

    $uid = require_login();

    // ---------- UPDATE ----------
    if ($action === 'update') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            json_error('ID catatan tidak valid.', 'INVALID_ID', 400);
        }

        $sql  = "SELECT * FROM notes WHERE id = ? AND status = 'active' LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$note) {
            json_error('Catatan tidak ditemukan.', 'NOT_FOUND', 404);
        }
        if ((int) $note['user_id'] !== $uid) {
            json_error('Hanya pemilik catatan yang boleh mengubah.', 'FORBIDDEN', 403);
        }

        $sets  = [];
        $types = '';
        $vals  = [];

        if (array_key_exists('course_name', $_POST)) {
            $v = trim((string) $_POST['course_name']);
            if ($v === '' || mb_strlen($v) > 120) {
                json_error('Course name wajib diisi (maks 120).', 'VALIDATION_FAILED', 400);
            }
            $sets[] = 'course_name = ?'; $types .= 's'; $vals[] = $v;
        }
        if (array_key_exists('lecturer_name', $_POST)) {
            $v = trim((string) $_POST['lecturer_name']);
            $sets[] = 'lecturer_name = ?'; $types .= 's'; $vals[] = ($v === '' ? null : $v);
        }
        if (array_key_exists('category', $_POST)) {
            $v = trim((string) $_POST['category']);
            $sets[] = 'category = ?'; $types .= 's'; $vals[] = ($v === '' ? null : $v);
        }
        if (array_key_exists('tags', $_POST)) {
            $sets[] = 'tags = ?'; $types .= 's'; $vals[] = parse_tags_input($_POST['tags']);
        }
        if (array_key_exists('description', $_POST)) {
            $v = trim((string) $_POST['description']);
            $sets[] = 'description = ?'; $types .= 's'; $vals[] = ($v === '' ? null : $v);
        }
        if (array_key_exists('title', $_POST)) {
            $v = trim((string) $_POST['title']);
            $sets[] = 'title = ?'; $types .= 's'; $vals[] = ($v === '' ? null : $v);
        }
        if (array_key_exists('visibility', $_POST)) {
            $isp = ((string) $_POST['visibility']) === 'private' ? 0 : 1;
            $sets[] = 'is_public = ?'; $types .= 'i'; $vals[] = $isp;
        }

        // File replacement (optional)
        $oldStored = null;
        if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $info   = validate_uploaded_file($_FILES['file']);
            $stored = store_uploaded_file($info);
            $sets[] = 'file_path = ?'; $types .= 's'; $vals[] = $stored['file_path'];
            $sets[] = 'file_name = ?'; $types .= 's'; $vals[] = $info['name'];
            $sets[] = 'file_size = ?'; $types .= 'i'; $vals[] = $info['size'];
            $sets[] = 'file_type = ?'; $types .= 's'; $vals[] = $info['ext'];
            $oldStored = $note['file_path'];
        }

        if (empty($sets)) {
            json_error('Tidak ada field yang diubah.', 'NOTHING_TO_UPDATE', 400);
        }

        $sql    = 'UPDATE notes SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $types .= 'i';
        $vals[] = $id;
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$vals);
        if (!mysqli_stmt_execute($stmt)) {
            error_log('[TuNotes] notes.update gagal: ' . mysqli_stmt_error($stmt));
            // Kalau gagal update, hapus file baru yang sudah ditulis supaya tidak nyampah.
            if ($oldStored !== null && !empty($stored['file_path'])) {
                delete_stored_file($stored['file_path']);
            }
            json_error('Gagal menyimpan perubahan.', 'INTERNAL_ERROR', 500);
        }
        mysqli_stmt_close($stmt);

        if ($oldStored !== null) {
            delete_stored_file($oldStored);
        }

        // Return row terbaru
        $sql = "SELECT n.*, u.name AS uploader_name, u.username AS uploader_username, u.avatar_url AS uploader_avatar
                FROM notes n JOIN users u ON u.id = n.user_id WHERE n.id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        json_success('Catatan diperbarui.', format_note_row($row));
    }

    // ---------- CREATE ----------
    $course_name   = trim((string) ($_POST['course_name']   ?? ''));
    $lecturer_name = trim((string) ($_POST['lecturer_name'] ?? ''));
    $category      = trim((string) ($_POST['category']      ?? ''));
    $description   = trim((string) ($_POST['description']   ?? ''));
    $title         = trim((string) ($_POST['title']         ?? ''));
    $visibility    = ((string) ($_POST['visibility'] ?? 'public')) === 'private' ? 0 : 1;
    $course_id     = isset($_POST['course_id']) && $_POST['course_id'] !== ''
                     ? (int) $_POST['course_id'] : null;

    if ($course_name === '' || mb_strlen($course_name) > 120) {
        json_error('Course name wajib diisi (maks 120 karakter).', 'VALIDATION_FAILED', 400);
    }

    $tags_json = parse_tags_input($_POST['tags'] ?? '');

    if (!isset($_FILES['file'])) {
        json_error('File wajib di-upload (field "file").', 'FILE_REQUIRED', 400);
    }
    $info   = validate_uploaded_file($_FILES['file']);
    $stored = store_uploaded_file($info);

    $titleFinal    = $title === ''         ? null : $title;
    $lecturerFinal = $lecturer_name === '' ? null : $lecturer_name;
    $categoryFinal = $category === ''      ? null : $category;
    $descFinal     = $description === ''   ? null : $description;
    $courseIdFinal = $course_id ?: null;

    $sql = 'INSERT INTO notes
              (user_id, course_id, title, course_name, lecturer_name, category, tags, description,
               file_path, file_name, file_size, file_type, is_public)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'iissssssssisi',
        $uid,
        $courseIdFinal,
        $titleFinal,
        $course_name,
        $lecturerFinal,
        $categoryFinal,
        $tags_json,
        $descFinal,
        $stored['file_path'],
        $info['name'],
        $info['size'],
        $info['ext'],
        $visibility
    );
    if (!mysqli_stmt_execute($stmt)) {
        error_log('[TuNotes] notes.create gagal: ' . mysqli_stmt_error($stmt));
        // Rollback file fisik supaya tidak nyampah di /uploads.
        delete_stored_file($stored['file_path']);
        json_error('Gagal menyimpan catatan.', 'INTERNAL_ERROR', 500);
    }
    $new_id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Increment notes_count user
    $sql  = 'UPDATE users SET notes_count = notes_count + 1 WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $sql = "SELECT n.*, u.name AS uploader_name, u.username AS uploader_username, u.avatar_url AS uploader_avatar
            FROM notes n JOIN users u ON u.id = n.user_id WHERE n.id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $new_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    json_success('Upload berhasil.', format_note_row($row), null, 201);
}

// =============================================================================
// DELETE — hapus catatan (hard delete + unlink file), atau cabut rating
// =============================================================================
if ($method === 'DELETE') {
    // ---------- UNRATE (Tahap 3) ----------
    if ($action === 'rate') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            json_error('ID catatan tidak valid.', 'INVALID_ID', 400);
        }
        $uid = require_login();

        $sql  = "DELETE FROM ratings WHERE user_id = ? AND note_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $uid, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        recalc_note_rating($conn, $id);
        $stats = get_note_rating_stats($conn, $id);
        $stats['my_rating'] = null;
        json_success('Rating dihapus.', $stats);
    }

    $uid = require_login();
    $id  = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('ID catatan tidak valid.', 'INVALID_ID', 400);
    }

    $sql  = "SELECT user_id, file_path FROM notes WHERE id = ? AND status = 'active' LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$note) {
        json_error('Catatan tidak ditemukan.', 'NOT_FOUND', 404);
    }
    if ((int) $note['user_id'] !== $uid) {
        json_error('Hanya pemilik yang boleh menghapus.', 'FORBIDDEN', 403);
    }

    $sql  = 'DELETE FROM notes WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log('[TuNotes] notes.delete gagal: ' . mysqli_stmt_error($stmt));
        json_error('Gagal menghapus catatan.', 'INTERNAL_ERROR', 500);
    }
    mysqli_stmt_close($stmt);

    delete_stored_file($note['file_path']);

    // notes_count tidak boleh negatif walaupun ada inkonsistensi historis.
    $sql  = 'UPDATE users SET notes_count = GREATEST(CAST(notes_count AS SIGNED) - 1, 0) WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    json_success('Catatan dihapus.');
}
