<?php
/**
 * /api/ai.php — Notes AI Assistant.
 *
 *   POST   { prompt, mode, note_id?, allow_edit }   → panggil AI + simpan ke DB
 *   GET    ?id=X | ?page=1&limit=10                 → detail / list riwayat user
 *   DELETE ?id=X                                    → hapus entry (owner only)
 *
 * Semua endpoint WAJIB LOGIN.
 *
 * Schema translation:
 *   - API field `allow_edit` (boolean) ↔ DB kolom `can_edit` (TINYINT).
 *   - API field `note_id` (single int)  ↔ DB kolom `source_note_ids` JSON
 *     (serialize ke [note_id] saat insert, ambil [0] saat read).
 *
 * Rate limit: maks 30 request POST per jam per user.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/ai_client.php';

only_methods('GET', 'POST', 'DELETE');
start_session_safe();

$uid    = require_login();
$method = $_SERVER['REQUEST_METHOD'];

const AI_RATE_LIMIT_PER_HOUR = 30;
const VALID_MODES            = ['auto', 'research', 'all_sources'];

function format_ai_result(array $row): array
{
    $source = [];
    if (!empty($row['source_note_ids'])) {
        $d = json_decode($row['source_note_ids'], true);
        if (is_array($d)) $source = $d;
    }
    return [
        'id'         => (int) $row['id'],
        'prompt'     => $row['prompt'],
        'response'   => $row['response'],
        'mode'       => $row['mode'] ?? 'auto',
        'note_id'    => !empty($source) ? (int) $source[0] : null,
        'allow_edit' => ((int) ($row['can_edit'] ?? 0)) === 1,
        'provider'   => $row['provider'] ?? null,
        'tokens_used'=> isset($row['tokens_used']) ? (int) $row['tokens_used'] : null,
        'created_at' => $row['created_at'],
    ];
}

// =============================================================================
// GET — detail atau list riwayat user
// =============================================================================
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        if ($id <= 0) json_error('id tidak valid.', 'INVALID_ID', 400);

        $sql  = "SELECT * FROM ai_results WHERE id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$row)                                  json_error('Entry tidak ditemukan.', 'NOT_FOUND', 404);
        if ((int) $row['user_id'] !== $uid)         json_error('Akses ditolak.', 'FORBIDDEN', 403);

        json_success('OK', format_ai_result($row));
    }

    $page   = max(1, (int) ($_GET['page']  ?? 1));
    $limit  = (int) ($_GET['limit'] ?? 10);
    if ($limit <= 0 || $limit > 100) $limit = 10;
    $offset = ($page - 1) * $limit;

    $sql  = "SELECT COUNT(*) AS c FROM ai_results WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    mysqli_stmt_close($stmt);

    $sql  = "SELECT * FROM ai_results WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $uid, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = format_ai_result($r);
    mysqli_stmt_close($stmt);

    json_success('OK', $rows, [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
    ]);
}

// =============================================================================
// POST — kirim prompt ke AI dan simpan hasil
// =============================================================================
if ($method === 'POST') {
    // Rate limit check
    $sql  = "SELECT COUNT(*) AS c FROM ai_results
             WHERE user_id = ? AND created_at >= NOW() - INTERVAL 1 HOUR";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $cnt = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    mysqli_stmt_close($stmt);
    if ($cnt >= AI_RATE_LIMIT_PER_HOUR) {
        json_error(
            'Rate limit tercapai: maksimal ' . AI_RATE_LIMIT_PER_HOUR . ' permintaan AI per jam. Coba lagi dalam beberapa menit.',
            'RATE_LIMIT', 429
        );
    }

    $input      = read_json_input();
    $prompt     = trim((string) ($input['prompt'] ?? ''));
    $mode       = (string) ($input['mode'] ?? 'auto');
    $note_id    = isset($input['note_id']) && $input['note_id'] !== null && $input['note_id'] !== ''
                  ? (int) $input['note_id'] : null;
    $allow_edit = !empty($input['allow_edit']) ? 1 : 0;

    // Validasi
    $plen = mb_strlen($prompt);
    if ($plen < 3 || $plen > 4000) {
        json_error('Prompt harus 3–4000 karakter. Sekarang ' . $plen . '.', 'VALIDATION_FAILED', 400);
    }
    if (!in_array($mode, VALID_MODES, true)) $mode = 'auto';

    // Konteks dari catatan referensi (kalau ada)
    $note_context = null;
    if ($note_id !== null && $note_id > 0) {
        $sql  = "SELECT id, user_id, title, course_name, lecturer_name, description, category, tags, is_public
                 FROM notes WHERE id = ? AND status = 'active' LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $note_id);
        mysqli_stmt_execute($stmt);
        $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$note) {
            json_error('Catatan referensi tidak ditemukan.', 'NOTE_NOT_FOUND', 404);
        }
        if ((int) $note['is_public'] === 0 && (int) $note['user_id'] !== $uid) {
            json_error('Akses ditolak ke catatan referensi.', 'NOTE_FORBIDDEN', 403);
        }
        $title = $note['title'] !== null && $note['title'] !== '' ? $note['title'] : $note['course_name'];
        $note_context = "Judul: $title. "
                      . "Mata kuliah: " . $note['course_name'] . ". "
                      . "Dosen: " . ($note['lecturer_name'] ?? '-') . ". "
                      . "Kategori: " . ($note['category'] ?? '-') . ". "
                      . "Deskripsi: " . ($note['description'] ?? '(tidak ada deskripsi)');
    }

    // Mode all_sources: cari top 3 catatan public yang match keyword
    $extra_context = '';
    if ($mode === 'all_sources') {
        $cleaned  = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($prompt));
        $tokens   = array_filter(preg_split('/\s+/', (string) $cleaned), function ($w) {
            return mb_strlen($w) >= 4;
        });
        $tokens   = array_slice(array_values(array_unique($tokens)), 0, 3);
        if (!empty($tokens)) {
            $likeClauses = [];
            $params      = [];
            $types       = '';
            foreach ($tokens as $kw) {
                $likeClauses[] = '(category LIKE ? OR tags LIKE ?)';
                $params[]      = '%' . $kw . '%';
                $params[]      = '%' . $kw . '%';
                $types        .= 'ss';
            }
            $sql  = "SELECT id, title, course_name, description
                     FROM notes
                     WHERE is_public = 1 AND status = 'active' AND ("
                  . implode(' OR ', $likeClauses) . ")
                     LIMIT 3";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $lines = [];
            while ($r = mysqli_fetch_assoc($res)) {
                $t = $r['title'] !== null && $r['title'] !== '' ? $r['title'] : $r['course_name'];
                $lines[] = '- "' . $t . '" (' . $r['course_name'] . '): '
                         . mb_substr($r['description'] ?? '', 0, 200);
            }
            mysqli_stmt_close($stmt);
            if (!empty($lines)) {
                $extra_context = implode("\n", $lines);
            }
        }
    }

    // System prompt
    $system = "Kamu adalah asisten belajar bernama TuNotes AI. Jawab dalam Bahasa Indonesia yang ramah dan jelas. "
            . "Fokus pada konteks akademik. Kalau ada catatan referensi, prioritaskan informasi dari catatan.";
    if ($mode === 'research') {
        $system .= " Berikan referensi/sumber kalau memungkinkan, gunakan format markdown.";
    }

    $full_prompt = $system;
    if ($note_context !== null) {
        $full_prompt .= "\n\nKonteks catatan referensi:\n" . $note_context;
    }
    if ($extra_context !== '') {
        $full_prompt .= "\n\nKonteks tambahan dari catatan publik:\n" . $extra_context;
    }
    $full_prompt .= "\n\nPertanyaan/permintaan user:\n" . $prompt;

    $ai = call_ai($full_prompt, [
        'mode'         => $mode,
        'note_context' => $note_context,
    ]);

    $response_text  = $ai['text'];
    $provider       = $ai['provider'] ?? 'mock';
    $tokens_used    = (int) ($ai['tokens_used'] ?? 0);
    $source_note_js = $note_id ? json_encode([$note_id]) : null;

    $sql = "INSERT INTO ai_results
              (user_id, prompt, mode, response, source_note_ids, can_edit, provider, tokens_used)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'issssisi',
        $uid, $prompt, $mode, $response_text, $source_note_js, $allow_edit, $provider, $tokens_used
    );
    if (!mysqli_stmt_execute($stmt)) {
        error_log('[TuNotes AI] insert gagal: ' . mysqli_stmt_error($stmt));
        json_error('Gagal menyimpan hasil AI.', 'INTERNAL_ERROR', 500);
    }
    $new_id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $sql  = "SELECT * FROM ai_results WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $new_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $out = format_ai_result($row);
    if (!empty($ai['error'])) $out['ai_error'] = $ai['error'];

    json_success('Respons AI siap.', $out, null, 201);
}

// =============================================================================
// DELETE — hapus entry
// =============================================================================
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) json_error('id tidak valid.', 'INVALID_ID', 400);

    $sql  = "SELECT user_id FROM ai_results WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row)                          json_error('Entry tidak ditemukan.', 'NOT_FOUND', 404);
    if ((int) $row['user_id'] !== $uid) json_error('Hanya pemilik yang boleh menghapus.', 'FORBIDDEN', 403);

    $sql  = "DELETE FROM ai_results WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    json_success('Entry AI dihapus.');
}
