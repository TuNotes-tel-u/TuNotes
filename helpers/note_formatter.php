<?php
/**
 * Helper untuk format row tabel `notes` (+ kolom join opsional) menjadi
 * struktur JSON yang konsisten di semua endpoint:
 *   - /api/notes.php (list + detail)
 *   - /api/favorites.php (list catatan favorit, dengan tambahan `favorited_at`)
 *
 * Kontrak:
 *   $row diharapkan punya kolom-kolom dasar tabel `notes`. Kalau ada kolom
 *   join (uploader_name, uploader_username, uploader_avatar) → diisi ke field
 *   `uploader`. Kalau ada `favorited_at` → ditambahkan ke output.
 */

require_once __DIR__ . '/../config/constants.php';

if (!function_exists('format_note_row')) {
    function format_note_row(array $row): array
    {
        $tags = [];
        if (!empty($row['tags'])) {
            $decoded = json_decode($row['tags'], true);
            if (is_array($decoded)) $tags = $decoded;
        }

        $out = [
            'id'              => (int) $row['id'],
            'user_id'         => (int) $row['user_id'],
            'course_id'       => isset($row['course_id']) ? (int) $row['course_id'] : null,
            'title'           => $row['title'] ?? null,
            'course_name'     => $row['course_name'],
            'lecturer_name'   => $row['lecturer_name'] ?? null,
            'category'        => $row['category'] ?? null,
            'tags'            => $tags,
            'description'     => $row['description'] ?? null,
            'file_name'       => $row['file_name'],
            'file_path'       => $row['file_path'],
            'file_url'        => UPLOAD_URL . '/' . $row['file_path'],
            'file_size'       => (int) $row['file_size'],
            'file_type'       => $row['file_type'],
            'page_count'      => isset($row['page_count']) ? (int) $row['page_count'] : null,
            'visibility'      => ((int) $row['is_public']) === 1 ? 'public' : 'private',
            'downloads_count' => (int) ($row['downloads_count'] ?? 0),
            'view_count'      => (int) ($row['view_count'] ?? 0),
            'avg_rating'      => isset($row['avg_rating']) ? (float) $row['avg_rating'] : 0.0,
            'rating_count'    => (int) ($row['rating_count'] ?? 0),
            'created_at'      => $row['created_at'],
            'updated_at'      => $row['updated_at'],
        ];

        if (isset($row['uploader_name'])) {
            $out['uploader'] = [
                'id'         => (int) $row['user_id'],
                'name'       => $row['uploader_name'],
                'username'   => $row['uploader_username'] ?? null,
                'avatar_url' => $row['uploader_avatar'] ?? null,
            ];
        }
        if (isset($row['favorited_at'])) {
            $out['favorited_at'] = $row['favorited_at'];
        }
        return $out;
    }
}
