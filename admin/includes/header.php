<?php
/**
 * Partial header: <head> + opening admin layout.
 * Wajib set $page_title sebelum include.
 */
$_pt = isset($page_title) ? (string) $page_title : 'Admin';
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($_pt, ENT_QUOTES, 'UTF-8') ?> — TuNotes Admin</title>
    <link rel="stylesheet" href="../assets/css/Admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
         * Admin panel styles (inline, override Admin.css existing kalau bentrok).
         * Inline supaya cepat tweak per halaman.
         * ============================================================ */
        :root { --bg-page: #f9fafb; --border: #e5e7eb; --border-light: #f3f4f6;
                --text-primary: #111827; --text-secondary: #6b7280; --text-muted: #9ca3af;
                --primary: #2563eb; --primary-bg: #dbeafe; --primary-text: #1e40af; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; margin: 0;
               background: var(--bg-page); color: var(--text-primary); font-size: 14px; line-height: 1.5; }
        a { color: inherit; }

        /* ===== Layout ===== */
        .admin-layout { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
        .admin-main { padding: 1.75rem 2rem; overflow-x: auto; min-width: 0; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.75rem; flex-wrap: wrap; }
        .page-header h1 { margin: 0 0 0.25rem 0; font-size: 1.65rem; font-weight: 700; color: var(--text-primary); }
        .page-subtitle { color: var(--text-secondary); margin: 0; font-size: 0.95rem; }
        .page-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.8rem;
                      background: #f3f4f6; color: #6b7280; border-radius: 6px; font-size: 0.75rem; font-weight: 600;
                      text-transform: uppercase; letter-spacing: 0.05em; }

        /* ===== Sidebar ===== */
        .admin-sidebar { background: #fff; border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        .admin-sidebar__header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-light); }
        .admin-sidebar__brand { display: flex; align-items: center; gap: 0.7rem; text-decoration: none; color: var(--text-primary); }
        .admin-sidebar__brand img { width: 36px; height: 36px; border-radius: 8px; }
        .brand-title { font-weight: 700; font-size: 1.1rem; display: block; line-height: 1.1; }
        .brand-badge { display: inline-block; background: #fee2e2; color: #991b1b; font-size: 0.6rem;
                       padding: 0.1rem 0.4rem; border-radius: 3px; font-weight: 700;
                       letter-spacing: 0.06em; margin-top: 0.15rem; }
        .admin-sidebar__nav { padding: 0.5rem 0; flex: 1; overflow-y: auto; }
        .nav-section-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em;
                             color: var(--text-muted); padding: 1rem 1.5rem 0.5rem; font-weight: 600; }
        .nav-section-label:first-child { padding-top: 0.5rem; }
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1.5rem;
                    text-decoration: none; color: #374151; font-size: 0.9rem; transition: background 0.15s;
                    border-left: 3px solid transparent; padding-left: calc(1.5rem - 3px); }
        .nav-item:hover { background: var(--bg-page); }
        .nav-item--active { background: var(--primary-bg); color: var(--primary-text); font-weight: 600; border-left-color: var(--primary); }
        .nav-item--active i { color: var(--primary); }
        .nav-item i { width: 18px; text-align: center; font-size: 0.95rem; color: var(--text-secondary); }
        .admin-sidebar__footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-light); }
        .profile-mini { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.6rem; }
        .profile-mini__info { flex: 1; min-width: 0; }
        .profile-mini__name { font-weight: 600; font-size: 0.85rem; color: var(--text-primary);
                              white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .profile-mini__role { font-size: 0.68rem; color: var(--text-secondary);
                              text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.1rem; }
        .logout-link { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0;
                       color: #ef4444; text-decoration: none; font-size: 0.85rem; font-weight: 500; }
        .logout-link:hover { text-decoration: underline; }

        /* ===== Flash ===== */
        .admin-flash { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .admin-flash--success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .admin-flash--error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .admin-flash--info    { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }

        /* ===== Stat cards (new design: icon box + number + label) ===== */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
        .stat-card { display: flex; align-items: center; gap: 1rem; padding: 1.25rem 1.5rem;
                     background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
                     font-size: 1.25rem; flex-shrink: 0; }
        .stat-icon--blue   { background: #dbeafe; color: #2563eb; }
        .stat-icon--green  { background: #dcfce7; color: #16a34a; }
        .stat-icon--yellow { background: #fef3c7; color: #d97706; }
        .stat-icon--red    { background: #fee2e2; color: #dc2626; }
        .stat-icon--purple { background: #ede9fe; color: #7c3aed; }
        .stat-info { min-width: 0; }
        .stat-number { font-size: 2rem; font-weight: 700; line-height: 1; color: var(--text-primary); }
        .stat-label { font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem; }

        /* ===== Filter bar ===== */
        .filter-bar { display: flex; gap: 0.75rem; margin-bottom: 1.25rem; flex-wrap: wrap; align-items: center; }
        .search-input { padding: 0.55rem 0.9rem; border: 1px solid var(--border); border-radius: 8px;
                        font: inherit; min-width: 240px; background: #fff; }
        .search-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .filter-tabs { display: flex; gap: 0.25rem; background: #f3f4f6; padding: 0.25rem; border-radius: 8px; }
        .filter-tab { padding: 0.45rem 1rem; background: transparent; border: none; border-radius: 6px;
                      font-size: 0.85rem; cursor: pointer; color: var(--text-secondary); font-family: inherit;
                      text-decoration: none; display: inline-flex; align-items: center; }
        .filter-tab:hover { color: var(--text-primary); }
        .filter-tab--active { background: var(--primary); color: #fff; font-weight: 500; }
        .filter-tab--active:hover { color: #fff; }

        /* ===== Buttons ===== */
        .btn-admin { padding: 0.55rem 1.1rem; border-radius: 8px; border: none; cursor: pointer;
                     font-weight: 500; font-family: inherit; font-size: 0.9rem;
                     text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;
                     transition: filter 0.15s; }
        .btn-admin:hover { filter: brightness(0.93); }
        .btn-admin.primary { background: var(--primary); color: #fff; }
        .btn-admin.danger  { background: #ef4444; color: #fff; }
        .btn-admin.outline { background: #fff; color: var(--text-primary); border: 1px solid var(--border); }
        .btn-admin.green   { background: #10b981; color: #fff; }

        /* ===== Table ===== */
        .admin-table { width: 100%; background: #fff; border-collapse: separate; border-spacing: 0;
                       border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .admin-table th { background: var(--bg-page); color: var(--text-secondary); font-weight: 600;
                          font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em;
                          padding: 0.85rem 1rem; text-align: left; border-bottom: 1px solid var(--border-light); }
        .admin-table td { padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-light);
                          font-size: 0.9rem; vertical-align: middle; }
        .admin-table tr:hover td { background: var(--bg-page); }
        .admin-table tr:last-child td { border-bottom: none; }
        .table-user-cell { display: flex; align-items: center; gap: 0.75rem; }

        /* ===== Action icons (3 icon per row) ===== */
        .action-icons { display: flex; gap: 0.4rem; }
        .action-icon { width: 34px; height: 34px; border-radius: 7px; display: inline-flex;
                       align-items: center; justify-content: center; border: none; cursor: pointer;
                       font-size: 0.85rem; text-decoration: none; transition: filter 0.15s; }
        .action-icon:hover { filter: brightness(0.92); }
        .action-icon--view   { background: #dbeafe; color: #2563eb; }
        .action-icon--edit   { background: #fef3c7; color: #d97706; }
        .action-icon--delete { background: #fee2e2; color: #dc2626; }

        /* ===== Content card list (for content.php & comments.php) ===== */
        .content-list { display: flex; flex-direction: column; gap: 0.75rem; }
        .content-card { background: #fff; border-radius: 12px; padding: 1rem 1.25rem;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; gap: 1rem; align-items: center; }
        .content-icon { width: 48px; height: 48px; border-radius: 10px; background: #dbeafe; color: #2563eb;
                        display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
        .content-body { flex: 1; min-width: 0; }
        .content-title { font-weight: 600; font-size: 0.95rem; color: var(--text-primary); margin: 0 0 0.2rem 0; }
        .content-meta  { color: var(--text-secondary); font-size: 0.82rem; }
        .content-meta-strong { color: var(--text-primary); }
        .content-stats { display: flex; gap: 1rem; align-items: center; color: var(--text-secondary); font-size: 0.85rem; flex-shrink: 0; }
        .content-stats .star { color: #f59e0b; }
        .content-stats .dl   { color: #6366f1; }

        /* ===== Sections (analytics, dashboard) ===== */
        .admin-section { background: #fff; padding: 1.5rem; border-radius: 12px;
                         box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .admin-section h2 { margin: 0 0 1rem 0; font-size: 1.05rem; font-weight: 600; }
        .admin-section h2 i { color: var(--primary); margin-right: 0.4rem; }
        .two-col { display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.25rem; }

        /* ===== Activity list (Dashboard) ===== */
        .activity-list { list-style: none; padding: 0; margin: 0; }
        .activity-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 0;
                         border-bottom: 1px solid var(--border-light); font-size: 0.9rem; }
        .activity-item:last-child { border-bottom: none; }
        .activity-item__body { flex: 1; min-width: 0; }
        .activity-item__title { font-weight: 500; color: var(--text-primary); }
        .activity-item__meta { color: var(--text-secondary); font-size: 0.78rem; margin-top: 0.1rem; }

        /* ===== Quick access cards (Dashboard) ===== */
        .quick-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
        .quick-card { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding: 1.25rem 1rem;
                      background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                      text-decoration: none; color: var(--text-primary); transition: transform 0.15s, box-shadow 0.15s; }
        .quick-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .quick-card i { font-size: 1.75rem; color: var(--primary); }
        .quick-card span { font-weight: 500; font-size: 0.9rem; text-align: center; }

        /* ===== Chart container ===== */
        .chart-container { position: relative; height: 280px; }

        /* ===== Notifications 2-column ===== */
        .notif-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1.25rem; }
        @media (max-width: 900px) { .notif-grid { grid-template-columns: 1fr; } }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.35rem; font-size: 0.85rem; color: var(--text-primary); }
        .form-input, .form-select, .form-textarea {
            width: 100%; padding: 0.55rem 0.9rem; border: 1px solid var(--border); border-radius: 8px;
            font: inherit; background: #fff; box-sizing: border-box;
        }
        .form-textarea { resize: vertical; min-height: 100px; }
        .notif-history-list { max-height: 600px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.75rem; }
        .notif-history-item { background: #f9fafb; border: 1px solid var(--border-light); border-radius: 10px;
                              padding: 0.9rem 1rem; }
        .notif-history-item__header { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.35rem; }
        .notif-history-item__title { font-weight: 600; font-size: 0.9rem; flex: 1; min-width: 0; }
        .notif-history-item__meta { color: var(--text-secondary); font-size: 0.78rem; margin-bottom: 0.5rem; }
        .notif-history-item__body { font-size: 0.85rem; color: #374151; margin-bottom: 0.5rem; }
        .notif-history-item__actions a { color: var(--primary); font-size: 0.8rem; text-decoration: none; margin-right: 0.75rem; cursor: pointer; }
        .notif-history-item__actions a.danger { color: #ef4444; }

        /* ===== Pagination ===== */
        .pagination { display: flex; gap: 0.4rem; justify-content: center; margin-top: 1.5rem; }
        .pagination a { padding: 0.45rem 0.85rem; border-radius: 8px; text-decoration: none; font-size: 0.85rem;
                        background: #fff; border: 1px solid var(--border); color: var(--text-primary); }
        .pagination a.active { background: var(--primary); color: #fff; border-color: var(--primary); font-weight: 600; }
        .pagination a:hover { background: var(--bg-page); }
    </style>
</head>
<body>
<div class="admin-layout">
