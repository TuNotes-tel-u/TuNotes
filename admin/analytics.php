<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../helpers/admin_view.php';

// =============================================================================
// Query semua stat + data chart.
// =============================================================================

// Stat utama (4 kartu)
$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE last_active_at >= NOW() - INTERVAL 1 DAY");
$dau = (int) mysqli_fetch_assoc($rs)['c'];
$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE last_active_at >= NOW() - INTERVAL 30 DAY");
$mau = (int) mysqli_fetch_assoc($rs)['c'];
$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM notes WHERE status='active'");
$totalNotes = (int) mysqli_fetch_assoc($rs)['c'];
// Retention sederhana: persentase user (id > seed admin) yang aktif dalam 30 hari.
$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE id > 1");
$totalNonAdminUsers = max(1, (int) mysqli_fetch_assoc($rs)['c']);
$retention = round($mau / $totalNonAdminUsers * 100);

// DAU 7 hari terakhir (untuk bar chart)
$dauSeries = [];
$rs = mysqli_query($conn, "SELECT DATE(last_active_at) AS d, COUNT(DISTINCT id) AS c
                           FROM users WHERE last_active_at >= DATE(NOW()) - INTERVAL 6 DAY
                           GROUP BY DATE(last_active_at)");
$dauMap = [];
while ($r = mysqli_fetch_assoc($rs)) $dauMap[$r['d']] = (int) $r['c'];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime('-' . $i . ' days'));
    $dauSeries[] = ['label' => date('D', strtotime($day)), 'value' => $dauMap[$day] ?? 0];
}

// Tren pembuatan catatan 6 bulan terakhir
$notesTrend = [];
$rs = mysqli_query($conn, "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c
                           FROM notes WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') - INTERVAL 5 MONTH
                             AND status='active'
                           GROUP BY DATE_FORMAT(created_at, '%Y-%m')");
$notesMap = [];
while ($r = mysqli_fetch_assoc($rs)) $notesMap[$r['m']] = (int) $r['c'];
$bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime('first day of -' . $i . ' months');
    $key = date('Y-m', $ts);
    $notesTrend[] = ['label' => $bulan[(int) date('n', $ts) - 1], 'value' => $notesMap[$key] ?? 0];
}

// MAU 6 bulan terakhir
$mauSeries = [];
$rs = mysqli_query($conn, "SELECT DATE_FORMAT(last_active_at, '%Y-%m') AS m, COUNT(DISTINCT id) AS c
                           FROM users WHERE last_active_at >= DATE_FORMAT(NOW(), '%Y-%m-01') - INTERVAL 5 MONTH
                           GROUP BY DATE_FORMAT(last_active_at, '%Y-%m')");
$mauMap = [];
while ($r = mysqli_fetch_assoc($rs)) $mauMap[$r['m']] = (int) $r['c'];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime('first day of -' . $i . ' months');
    $key = date('Y-m', $ts);
    $mauSeries[] = ['label' => $bulan[(int) date('n', $ts) - 1], 'value' => $mauMap[$key] ?? 0];
}

// Top 5 mata kuliah (group by course_name)
$topCourses = [];
$rs = mysqli_query($conn, "SELECT course_name,
                                  COUNT(*) AS notes_count,
                                  COUNT(DISTINCT user_id) AS user_count
                           FROM notes WHERE status='active' AND course_name IS NOT NULL AND course_name <> ''
                           GROUP BY course_name ORDER BY notes_count DESC LIMIT 5");
while ($r = mysqli_fetch_assoc($rs)) $topCourses[] = $r;

$page_title   = 'Analitik';
$current_page = 'analytics';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="admin-main">
    <div class="page-header">
        <div>
            <h1>Analitik</h1>
            <p class="page-subtitle">Statistik penggunaan TuNotes — read-only, di-update real-time dari DB.</p>
        </div>
        <span class="page-badge"><i class="fa-solid fa-eye"></i> Read-Only</span>
    </div>

    <div class="stats-grid">
        <?= stat_card_html('fa-user-group',     'blue',   $dau,        'Aktif Harian (DAU)') ?>
        <?= stat_card_html('fa-wave-square',    'green',  $mau,        'Aktif Bulanan (MAU)') ?>
        <?= stat_card_html('fa-file-lines',     'purple', $totalNotes, 'Total Catatan') ?>
        <?= stat_card_html('fa-arrow-trend-up', 'yellow', $retention . '%', 'Retention Rate') ?>
    </div>

    <div class="two-col">
        <div class="admin-section">
            <h2><i class="fa-solid fa-chart-column"></i>Pengguna Aktif Harian (7 hari)</h2>
            <div class="chart-container"><canvas id="dauChart"></canvas></div>
        </div>
        <div class="admin-section">
            <h2><i class="fa-solid fa-chart-line"></i>Tren Pembuatan Catatan (6 bulan)</h2>
            <div class="chart-container"><canvas id="notesChart"></canvas></div>
        </div>
    </div>

    <div class="two-col">
        <div class="admin-section">
            <h2><i class="fa-solid fa-chart-bar"></i>Pengguna Aktif Bulanan</h2>
            <div class="chart-container"><canvas id="mauChart"></canvas></div>
        </div>
        <div class="admin-section">
            <h2><i class="fa-solid fa-trophy"></i>Mata Kuliah Terpopuler</h2>
            <table class="admin-table" style="box-shadow:none;">
                <thead>
                    <tr><th>Mata Kuliah</th><th style="text-align:right;">Catatan</th><th style="text-align:right;">Pengguna</th></tr>
                </thead>
                <tbody>
                <?php if (empty($topCourses)): ?>
                    <tr><td colspan="3" style="text-align:center;color:var(--text-secondary);padding:1rem;">Belum ada data.</td></tr>
                <?php else: foreach ($topCourses as $c): ?>
                    <tr>
                        <td><?= h($c['course_name']) ?></td>
                        <td style="text-align:right;font-weight:600;color:var(--primary);"><?= (int) $c['notes_count'] ?></td>
                        <td style="text-align:right;"><?= (int) $c['user_count'] ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
const DAU_DATA   = <?= json_encode($dauSeries) ?>;
const NOTES_DATA = <?= json_encode($notesTrend) ?>;
const MAU_DATA   = <?= json_encode($mauSeries) ?>;

function renderChart(canvasId, type, data, color) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
        type: type,
        data: {
            labels: data.map(d => d.label),
            datasets: [{
                label: '',
                data: data.map(d => d.value),
                backgroundColor: type === 'line' ? color + '33' : color,
                borderColor: color,
                borderWidth: 2,
                fill: type === 'line',
                tension: 0.3,
                borderRadius: 6,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f3f4f6' } },
                x: { grid: { display: false } },
            },
        },
    });
}

renderChart('dauChart',   'bar',  DAU_DATA,   '#2563eb');
renderChart('notesChart', 'line', NOTES_DATA, '#10b981');
renderChart('mauChart',   'bar',  MAU_DATA,   '#8b5cf6');
</script>
