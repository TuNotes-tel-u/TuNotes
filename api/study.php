<?php
/**
 * /api/study.php — Study List, Course Detail, Homework.
 *
 *   GET  ?view=study_list                          → semester + courses user
 *   GET  ?view=course_detail&course_id=X           → header + notes + homework
 *
 *   POST ?action=add_semester                      { name, year?, term?, is_active? }
 *   POST ?action=add_course                        { semester_id?, code?, name, instructor?, location?, start_date?, link?, status? }
 *   POST ?action=add_homework                      { course_id, title, description?, due_date?, status? }
 *   POST ?action=update_course&id=X                { semester_id?, code?, name?, instructor?, location?, start_date?, link?, status? }
 *   POST ?action=update_homework&id=X              { title?, description?, due_date?, status? }
 *   (PUT diterima untuk semua update_X dengan body yang sama.)
 *
 *   DELETE ?action=delete_semester&id=X            → manual cascade courses + homework
 *   DELETE ?action=delete_course&id=X              → FK CASCADE homework, notes.course_id → NULL
 *   DELETE ?action=delete_homework&id=X
 *
 * Endpoint Reading Board (Read Notes) sudah dipisah ke /api/read-notes.php.
 *
 * Semua endpoint WAJIB LOGIN.
 *
 * Schema translation:
 *   - homework: kolom DB `is_done` (boolean). API: `status` enum `pending|done`.
 *   - semesters: tidak punya kolom `year`/`term`. API terima `year + term` lalu compose ke `name`.
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
$view   = isset($_GET['view'])   ? (string) $_GET['view']   : '';

// -----------------------------------------------------------------------------
// Formatters
// -----------------------------------------------------------------------------
function format_semester(array $row): array
{
    return [
        'id'         => (int) $row['id'],
        'name'       => $row['name'],
        'is_active'  => (int) $row['is_active'] === 1,
        'created_at' => $row['created_at'],
    ];
}

function format_course(array $row): array
{
    return [
        'id'            => (int) $row['id'],
        'user_id'       => (int) $row['user_id'],
        'semester_id'   => isset($row['semester_id']) && $row['semester_id'] !== null ? (int) $row['semester_id'] : null,
        'code'          => $row['code'] ?? null,
        'name'          => $row['name'],
        'instructor'    => $row['instructor'] ?? null,
        'location'      => $row['location'] ?? null,
        'start_date'    => $row['start_date'] ?? null,
        'link'          => $row['link'] ?? null,
        'status'        => $row['status'] ?? 'active',
        'notes_count'   => isset($row['notes_count']) ? (int) $row['notes_count'] : 0,
        'created_at'    => $row['created_at'],
        'updated_at'    => $row['updated_at'],
        'semester_name' => $row['semester_name'] ?? null,
    ];
}

function format_homework(array $row): array
{
    return [
        'id'          => (int) $row['id'],
        'course_id'   => (int) $row['course_id'],
        'title'       => $row['title'],
        'description' => $row['description'] ?? null,
        'due_date'    => $row['due_date'] ?? null,
        'status'      => ((int) $row['is_done']) === 1 ? 'done' : 'pending',
        'created_at'  => $row['created_at'],
        'updated_at'  => $row['updated_at'],
    ];
}

// -----------------------------------------------------------------------------
// Ownership helpers
// -----------------------------------------------------------------------------
function get_course_or_403(mysqli $conn, int $course_id, int $uid): array
{
    $sql  = "SELECT c.*, s.name AS semester_name
             FROM courses c
             LEFT JOIN semesters s ON s.id = c.semester_id
             WHERE c.id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $course_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) {
        json_error('Course tidak ditemukan.', 'NOT_FOUND', 404);
    }
    if ((int) $row['user_id'] !== $uid) {
        json_error('Akses ditolak — bukan pemilik course.', 'FORBIDDEN', 403);
    }
    return $row;
}

function get_semester_or_403(mysqli $conn, int $semester_id, int $uid): array
{
    $sql  = "SELECT * FROM semesters WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $semester_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) {
        json_error('Semester tidak ditemukan.', 'NOT_FOUND', 404);
    }
    if ((int) $row['user_id'] !== $uid) {
        json_error('Akses ditolak — bukan pemilik semester.', 'FORBIDDEN', 403);
    }
    return $row;
}

function get_homework_or_403(mysqli $conn, int $id, int $uid): array
{
    $sql  = "SELECT * FROM homework WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) {
        json_error('Homework tidak ditemukan.', 'NOT_FOUND', 404);
    }
    if ((int) $row['user_id'] !== $uid) {
        json_error('Akses ditolak.', 'FORBIDDEN', 403);
    }
    return $row;
}

// =============================================================================
// GET — 2 view
// =============================================================================
if ($method === 'GET') {

    // ------------------------------------------------------------------------
    // study_list
    // ------------------------------------------------------------------------
    if ($view === 'study_list') {
        // Semua semester
        $sql  = "SELECT id, name, is_active, created_at FROM semesters
                 WHERE user_id = ? ORDER BY is_active DESC, created_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        $result    = mysqli_stmt_get_result($stmt);
        $semesters = [];
        $byId      = [];
        while ($r = mysqli_fetch_assoc($result)) {
            $f          = format_semester($r);
            $f['courses'] = [];
            $semesters[] = $f;
            $byId[$f['id']] = count($semesters) - 1;
        }
        mysqli_stmt_close($stmt);

        // Semua course + notes_count
        $sql = "SELECT c.id, c.user_id, c.semester_id, c.code, c.name, c.instructor, c.location,
                       c.start_date, c.link, c.status, c.created_at, c.updated_at,
                       (SELECT COUNT(*) FROM notes WHERE course_id = c.id AND status = 'active') AS notes_count
                FROM courses c
                WHERE c.user_id = ?
                ORDER BY c.created_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $courses_without_semester = [];
        $total_courses = 0;
        while ($r = mysqli_fetch_assoc($result)) {
            $c = format_course($r);
            $total_courses++;
            if ($c['semester_id'] !== null && isset($byId[$c['semester_id']])) {
                $semesters[$byId[$c['semester_id']]]['courses'][] = $c;
            } else {
                $courses_without_semester[] = $c;
            }
        }
        mysqli_stmt_close($stmt);

        if (!empty($courses_without_semester)) {
            $semesters[] = [
                'id'         => null,
                'name'       => 'Tanpa Semester',
                'is_active'  => false,
                'created_at' => null,
                'courses'    => $courses_without_semester,
            ];
        }

        // Total notes user (di semua catatan, terlepas course_id)
        $sql  = "SELECT COUNT(*) AS c FROM notes WHERE user_id = ? AND status = 'active'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        $total_notes = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
        mysqli_stmt_close($stmt);

        $active_semesters = 0;
        foreach ($semesters as $s) {
            if (!empty($s['is_active'])) $active_semesters++;
        }

        json_success('OK', $semesters, [
            'total_courses'    => $total_courses,
            'total_notes'      => $total_notes,
            'active_semesters' => $active_semesters,
        ]);
    }

    // ------------------------------------------------------------------------
    // course_detail
    // ------------------------------------------------------------------------
    if ($view === 'course_detail') {
        $course_id = (int) ($_GET['course_id'] ?? 0);
        if ($course_id <= 0) {
            json_error('course_id wajib ada.', 'INVALID_PARAM', 400);
        }
        $course = get_course_or_403($conn, $course_id, $uid);

        $sql = "SELECT n.*, u.name AS uploader_name, u.username AS uploader_username, u.avatar_url AS uploader_avatar
                FROM notes n JOIN users u ON u.id = n.user_id
                WHERE n.course_id = ? AND n.status = 'active'
                ORDER BY n.created_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $course_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $notes = [];
        while ($r = mysqli_fetch_assoc($result)) $notes[] = format_note_row($r);
        mysqli_stmt_close($stmt);

        $sql = "SELECT * FROM homework
                WHERE course_id = ? AND user_id = ?
                ORDER BY is_done ASC, (due_date IS NULL) ASC, due_date ASC, created_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $course_id, $uid);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $homeworks = [];
        while ($r = mysqli_fetch_assoc($result)) $homeworks[] = format_homework($r);
        mysqli_stmt_close($stmt);

        json_success('OK', [
            'course'   => format_course($course),
            'notes'    => $notes,
            'homework' => $homeworks,
        ]);
    }

    json_error('view tidak dikenal. Gunakan study_list atau course_detail. (reading_board pindah ke /api/read-notes.php)', 'INVALID_VIEW', 400);
}

// =============================================================================
// POST + PUT — create & update
// =============================================================================
if ($method === 'POST' || $method === 'PUT') {
    $input = read_json_input();

    // ------------------------------------------------------------------------
    // add_semester
    // ------------------------------------------------------------------------
    if ($action === 'add_semester') {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' && !empty($input['year']) && !empty($input['term'])) {
            $term_pretty = ucfirst(strtolower(trim((string) $input['term'])));
            $name = (int) $input['year'] . ' ' . $term_pretty;
        }
        if ($name === '' || mb_strlen($name) > 60) {
            json_error('Nama semester wajib (maks 60 karakter).', 'VALIDATION_FAILED', 400);
        }
        $is_active = !empty($input['is_active']) ? 1 : 0;

        $sql  = "INSERT INTO semesters (user_id, name, is_active) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'isi', $uid, $name, $is_active);
        if (!mysqli_stmt_execute($stmt)) {
            error_log('[TuNotes] add_semester gagal: ' . mysqli_stmt_error($stmt));
            json_error('Gagal menyimpan semester.', 'INTERNAL_ERROR', 500);
        }
        $new_id = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $sql  = "SELECT id, name, is_active, created_at FROM semesters WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $new_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        json_success('Semester ditambahkan.', format_semester($row), null, 201);
    }

    // ------------------------------------------------------------------------
    // add_course
    // ------------------------------------------------------------------------
    if ($action === 'add_course') {
        $semester_id = isset($input['semester_id']) && $input['semester_id'] !== '' ? (int) $input['semester_id'] : null;
        if ($semester_id !== null) {
            get_semester_or_403($conn, $semester_id, $uid);
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            json_error('Nama mata kuliah wajib (maks 120 karakter).', 'VALIDATION_FAILED', 400);
        }
        $code       = trim((string) ($input['code'] ?? ''))       ?: null;
        $instructor = trim((string) ($input['instructor'] ?? '')) ?: null;
        $location   = trim((string) ($input['location'] ?? ''))   ?: null;
        $start_date = !empty($input['start_date']) ? (string) $input['start_date'] : null;
        $link       = trim((string) ($input['link'] ?? ''))       ?: null;
        $status_in  = (((string) ($input['status'] ?? 'active')) === 'inactive') ? 'inactive' : 'active';

        // 9 kolom: user_id (i), semester_id (i), code (s), name (s),
        //          instructor (s), location (s), start_date (s), link (s), status (s)
        $sql  = "INSERT INTO courses
                   (user_id, semester_id, code, name, instructor, location, start_date, link, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            'iisssssss',
            $uid, $semester_id, $code, $name, $instructor, $location, $start_date, $link, $status_in
        );
        if (!mysqli_stmt_execute($stmt)) {
            error_log('[TuNotes] add_course gagal: ' . mysqli_stmt_error($stmt));
            json_error('Gagal menyimpan course.', 'INTERNAL_ERROR', 500);
        }
        $new_id = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $course = get_course_or_403($conn, $new_id, $uid);
        json_success('Course ditambahkan.', format_course($course), null, 201);
    }

    // ------------------------------------------------------------------------
    // add_homework
    // ------------------------------------------------------------------------
    if ($action === 'add_homework') {
        $course_id = (int) ($input['course_id'] ?? 0);
        if ($course_id <= 0) {
            json_error('course_id wajib.', 'INVALID_PARAM', 400);
        }
        get_course_or_403($conn, $course_id, $uid);

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            json_error('Title wajib (maks 255).', 'VALIDATION_FAILED', 400);
        }
        $description = trim((string) ($input['description'] ?? '')) ?: null;
        $due_date    = !empty($input['due_date']) ? (string) $input['due_date'] : null;
        $is_done     = (((string) ($input['status'] ?? 'pending')) === 'done') ? 1 : 0;

        $sql  = "INSERT INTO homework (course_id, user_id, title, description, due_date, is_done)
                 VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iisssi', $course_id, $uid, $title, $description, $due_date, $is_done);
        if (!mysqli_stmt_execute($stmt)) {
            error_log('[TuNotes] add_homework gagal: ' . mysqli_stmt_error($stmt));
            json_error('Gagal menyimpan homework.', 'INTERNAL_ERROR', 500);
        }
        $new_id = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $sql  = "SELECT * FROM homework WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $new_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        json_success('Homework ditambahkan.', format_homework($row), null, 201);
    }

    // ------------------------------------------------------------------------
    // update_course
    // ------------------------------------------------------------------------
    if ($action === 'update_course') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) json_error('id course tidak valid.', 'INVALID_ID', 400);
        get_course_or_403($conn, $id, $uid);

        $sets  = [];
        $types = '';
        $vals  = [];

        if (array_key_exists('semester_id', $input)) {
            $sid = $input['semester_id'] === null || $input['semester_id'] === '' ? null : (int) $input['semester_id'];
            if ($sid !== null) get_semester_or_403($conn, $sid, $uid);
            $sets[]  = 'semester_id = ?';
            $types  .= 'i';
            $vals[]  = $sid;
        }
        foreach (['code','name','instructor','location','link','start_date','status'] as $f) {
            if (array_key_exists($f, $input)) {
                $v = $input[$f];
                if (is_string($v)) $v = trim($v);
                if ($v === '') $v = null;
                if ($f === 'name' && ($v === null || mb_strlen($v) > 120)) {
                    json_error('Nama mata kuliah wajib (maks 120).', 'VALIDATION_FAILED', 400);
                }
                if ($f === 'status' && $v !== null && !in_array($v, ['active','inactive'], true)) {
                    json_error("status harus 'active' atau 'inactive'.", 'VALIDATION_FAILED', 400);
                }
                $sets[]  = "$f = ?";
                $types  .= 's';
                $vals[]  = $v;
            }
        }
        if (empty($sets)) {
            json_error('Tidak ada field yang diubah.', 'NOTHING_TO_UPDATE', 400);
        }
        $sql    = 'UPDATE courses SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $types .= 'i';
        $vals[] = $id;
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$vals);
        if (!mysqli_stmt_execute($stmt)) {
            error_log('[TuNotes] update_course gagal: ' . mysqli_stmt_error($stmt));
            json_error('Gagal menyimpan perubahan.', 'INTERNAL_ERROR', 500);
        }
        mysqli_stmt_close($stmt);

        $course = get_course_or_403($conn, $id, $uid);
        json_success('Course diperbarui.', format_course($course));
    }

    // ------------------------------------------------------------------------
    // update_homework
    // ------------------------------------------------------------------------
    if ($action === 'update_homework') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) json_error('id homework tidak valid.', 'INVALID_ID', 400);
        get_homework_or_403($conn, $id, $uid);

        $sets  = [];
        $types = '';
        $vals  = [];

        if (array_key_exists('title', $input)) {
            $v = trim((string) $input['title']);
            if ($v === '' || mb_strlen($v) > 255) {
                json_error('Title wajib (maks 255).', 'VALIDATION_FAILED', 400);
            }
            $sets[]  = 'title = ?';
            $types  .= 's';
            $vals[]  = $v;
        }
        if (array_key_exists('description', $input)) {
            $v = trim((string) $input['description']);
            $sets[]  = 'description = ?';
            $types  .= 's';
            $vals[]  = ($v === '' ? null : $v);
        }
        if (array_key_exists('due_date', $input)) {
            $v = !empty($input['due_date']) ? (string) $input['due_date'] : null;
            $sets[]  = 'due_date = ?';
            $types  .= 's';
            $vals[]  = $v;
        }
        if (array_key_exists('status', $input)) {
            $is_done = ($input['status'] === 'done') ? 1 : 0;
            $sets[]  = 'is_done = ?';
            $types  .= 'i';
            $vals[]  = $is_done;
        }
        if (empty($sets)) {
            json_error('Tidak ada field yang diubah.', 'NOTHING_TO_UPDATE', 400);
        }
        $sql    = 'UPDATE homework SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $types .= 'i';
        $vals[] = $id;
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$vals);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $sql  = "SELECT * FROM homework WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        json_success('Homework diperbarui.', format_homework($row));
    }

    json_error('Action POST/PUT tidak dikenal.', 'INVALID_ACTION', 400);
}

// =============================================================================
// DELETE
// =============================================================================
if ($method === 'DELETE') {

    if ($action === 'delete_semester') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) json_error('id semester tidak valid.', 'INVALID_ID', 400);
        get_semester_or_403($conn, $id, $uid);

        // Manual cascade: hapus courses milik semester ini (FK CASCADE akan urus homework).
        // Lakukan courses dulu karena FK courses.semester_id ON DELETE SET NULL — kalau semester
        // dihapus duluan, courses-nya jadi orphan dengan semester_id NULL.
        $sql  = "DELETE FROM courses WHERE semester_id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $id, $uid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $sql  = "DELETE FROM semesters WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $id, $uid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        json_success('Semester (beserta courses & homework) dihapus.');
    }

    if ($action === 'delete_course') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) json_error('id course tidak valid.', 'INVALID_ID', 400);
        get_course_or_403($conn, $id, $uid);

        // FK CASCADE handle homework. Notes.course_id ON DELETE SET NULL (notes tetap ada).
        $sql  = "DELETE FROM courses WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $id, $uid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        json_success('Course dihapus. Catatan tetap ada (course_id di-NULL).');
    }

    if ($action === 'delete_homework') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) json_error('id homework tidak valid.', 'INVALID_ID', 400);
        get_homework_or_403($conn, $id, $uid);

        $sql  = "DELETE FROM homework WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $id, $uid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        json_success('Homework dihapus.');
    }

    json_error('Action DELETE tidak dikenal.', 'INVALID_ACTION', 400);
}
