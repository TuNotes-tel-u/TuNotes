<?php
/**
 * Helper rendering untuk halaman admin: avatar bulat, role/status badge,
 * time_ago, dan stat card komponen. Konsisten di 5 modul + dashboard.
 */

if (!function_exists('avatar_html')) {
    function avatar_html(string $name, string $username, int $size = 36): string
    {
        $name  = trim($name);
        $parts = $name !== '' ? preg_split('/\s+/', $name) : ['?'];
        $i1 = mb_substr($parts[0], 0, 1);
        $i2 = isset($parts[1]) && $parts[1] !== '' ? mb_substr($parts[1], 0, 1) : '';
        $initials = mb_strtoupper($i1 . $i2);
        if ($initials === '') $initials = '?';

        $colors = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#0891b2'];
        $hash   = crc32($username !== '' ? $username : $name);
        $color  = $colors[abs($hash) % count($colors)];
        $fz     = $size < 32 ? '0.65rem' : ($size < 48 ? '0.85rem' : '1rem');

        return '<span style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;'
             . 'background:' . $color . ';display:inline-flex;align-items:center;justify-content:center;'
             . 'color:#fff;font-weight:600;font-size:' . $fz . ';flex-shrink:0;letter-spacing:0.02em;">'
             . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('role_badge')) {
    function role_badge(string $role): string
    {
        $map = [
            'super_admin' => ['bg' => '#fce7f3', 'fg' => '#9d174d', 'label' => 'Super Admin'],
            'admin'       => ['bg' => '#ede9fe', 'fg' => '#5b21b6', 'label' => 'Admin'],
            'user'        => ['bg' => '#dbeafe', 'fg' => '#1e40af', 'label' => 'User'],
        ];
        $cfg = $map[$role] ?? ['bg' => '#f3f4f6', 'fg' => '#4b5563', 'label' => $role];
        return _badge_html($cfg['bg'], $cfg['fg'], $cfg['label']);
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        $map = [
            'active'    => ['bg' => '#dcfce7', 'fg' => '#166534', 'label' => 'Aktif'],
            'suspended' => ['bg' => '#fef3c7', 'fg' => '#92400e', 'label' => 'Ditangguhkan'],
            'inactive'  => ['bg' => '#fee2e2', 'fg' => '#991b1b', 'label' => 'Tidak Aktif'],
            // Content statuses
            'published' => ['bg' => '#dcfce7', 'fg' => '#166534', 'label' => 'Dipublikasi'],
            'reported'  => ['bg' => '#fee2e2', 'fg' => '#991b1b', 'label' => 'Dilaporkan'],
            'review'    => ['bg' => '#fef3c7', 'fg' => '#92400e', 'label' => 'Dalam Review'],
            // Notification statuses
            'sent'      => ['bg' => '#dcfce7', 'fg' => '#166534', 'label' => 'Terkirim'],
            'scheduled' => ['bg' => '#fef3c7', 'fg' => '#92400e', 'label' => 'Terjadwal'],
            // Comment statuses (Tahap 3 schema active/deleted)
            'approved'  => ['bg' => '#dcfce7', 'fg' => '#166534', 'label' => 'Disetujui'],
            'deleted'   => ['bg' => '#f3f4f6', 'fg' => '#4b5563', 'label' => 'Dihapus'],
        ];
        $cfg = $map[$status] ?? ['bg' => '#f3f4f6', 'fg' => '#4b5563', 'label' => $status];
        return _badge_html($cfg['bg'], $cfg['fg'], $cfg['label']);
    }
}

if (!function_exists('_badge_html')) {
    function _badge_html(string $bg, string $fg, string $label): string
    {
        return '<span style="display:inline-block;padding:0.25rem 0.7rem;border-radius:9999px;'
             . 'font-size:0.72rem;font-weight:500;background:' . $bg . ';color:' . $fg . ';white-space:nowrap;">'
             . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string
    {
        if (!$datetime) return '—';
        $ts = strtotime($datetime);
        if (!$ts) return '—';
        $diff = time() - $ts;
        if ($diff < 0)        return 'baru saja';
        if ($diff < 60)       return 'beberapa detik lalu';
        if ($diff < 3600)     return floor($diff / 60) . ' menit lalu';
        if ($diff < 86400)    return floor($diff / 3600) . ' jam lalu';
        if ($diff < 604800)   return floor($diff / 86400) . ' hari lalu';
        if ($diff < 2592000)  return floor($diff / 604800) . ' minggu lalu';
        return date('d M Y', $ts);
    }
}

if (!function_exists('stat_card_html')) {
    /**
     * Render stat card style baru: icon kotak warna + angka besar + label.
     *   stat_card_html('fa-user-group', 'blue', 1234, 'Total Pengguna')
     */
    function stat_card_html(string $iconClass, string $colorVariant, $number, string $label): string
    {
        return '<div class="stat-card">'
             .   '<div class="stat-icon stat-icon--' . htmlspecialchars($colorVariant, ENT_QUOTES, 'UTF-8') . '">'
             .     '<i class="fa-solid ' . htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') . '"></i>'
             .   '</div>'
             .   '<div class="stat-info">'
             .     '<div class="stat-number">' . htmlspecialchars((string) $number, ENT_QUOTES, 'UTF-8') . '</div>'
             .     '<div class="stat-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>'
             .   '</div>'
             . '</div>';
    }
}
