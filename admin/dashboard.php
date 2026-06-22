<?php
// ============================================================
//  TravelGo — Admin Dashboard (admin/dashboard.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle = 'Dashboard Admin';

// ---- Statistik utama ----
$stats = [
    'total_users'    => $conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetch_row()[0],
    'total_bookings' => $conn->query("SELECT COUNT(*) FROM bookings")->fetch_row()[0],
    'total_paid'     => $conn->query("SELECT COUNT(*) FROM bookings WHERE status='paid'")->fetch_row()[0],
    'total_pending'  => $conn->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetch_row()[0],
    'revenue'        => $conn->query("SELECT COALESCE(SUM(jumlah),0) FROM pembayaran WHERE status='berhasil'")->fetch_row()[0],
    'total_jadwal'   => $conn->query("SELECT COUNT(*) FROM jadwal WHERE is_aktif=1")->fetch_row()[0],
];

// ---- Booking terbaru (10) ----
$recentBookings = $conn->query("
    SELECT b.kode_booking, b.status, b.total_harga, b.created_at,
           u.nama AS user_nama,
           ka.nama AS kota_asal, kt.nama AS kota_tujuan,
           jt.nama AS jenis_nama
    FROM bookings b
    JOIN users u               ON b.user_id = u.id
    JOIN jadwal j              ON b.jadwal_id = j.id
    JOIN rute r                ON j.rute_id = r.id
    JOIN kota ka               ON r.kota_asal_id = ka.id
    JOIN kota kt               ON r.kota_tujuan_id = kt.id
    JOIN jenis_transportasi jt ON r.jenis_transportasi_id = jt.id
    ORDER BY b.created_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ---- Revenue 7 hari terakhir ----
$revenueChart = $conn->query("
    SELECT DATE(waktu_bayar) AS tgl, SUM(jumlah) AS total
    FROM pembayaran
    WHERE status = 'berhasil'
      AND waktu_bayar >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(waktu_bayar)
    ORDER BY tgl ASC
")->fetch_all(MYSQLI_ASSOC);

// ---- Booking per jenis transportasi ----
$bookingByJenis = $conn->query("
    SELECT jt.nama, COUNT(b.id) AS total
    FROM bookings b
    JOIN jadwal j              ON b.jadwal_id = j.id
    JOIN rute r                ON j.rute_id = r.id
    JOIN jenis_transportasi jt ON r.jenis_transportasi_id = jt.id
    GROUP BY jt.id, jt.nama
    ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

$statusLabel = [
    'pending'   => ['label'=>'Menunggu',   'color'=>'#F59E0B', 'bg'=>'rgba(245,158,11,.12)'],
    'paid'      => ['label'=>'Lunas',      'color'=>'#10B981', 'bg'=>'rgba(16,185,129,.12)'],
    'cancelled' => ['label'=>'Batal',      'color'=>'#EF4444', 'bg'=>'rgba(239,68,68,.12)'],
    'expired'   => ['label'=>'Expired',    'color'=>'#6B7280', 'bg'=>'rgba(107,114,128,.12)'],
    'confirmed' => ['label'=>'Konfirmasi', 'color'=>'#3B82F6', 'bg'=>'rgba(59,130,246,.12)'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0F172A; color: #E2E8F0; margin: 0; }

    /* ---- Sidebar ---- */
    .adm-sidebar {
      width: 240px; height: 100vh; position: fixed; left: 0; top: 0;
      background: #111827; border-right: 1px solid rgba(255,255,255,.06);
      display: flex; flex-direction: column; z-index: 100;
      overflow-y: auto;
    }
    .adm-brand {
      padding: 20px 20px 16px;
      border-bottom: 1px solid rgba(255,255,255,.06);
      display: flex; align-items: center; gap: 10px;
    }
    .adm-brand-icon {
      width: 36px; height: 36px; background: var(--blue);
      border-radius: 10px; display: flex; align-items: center;
      justify-content: center; font-size: 1rem; color: #fff; flex-shrink: 0;
    }
    .adm-brand-name { font-size: .95rem; font-weight: 800; color: #fff; }
    .adm-brand-sub  { font-size: .65rem; color: rgba(255,255,255,.3); }

    .adm-nav { padding: 12px 10px; flex: 1; }
    .adm-nav-label {
      font-size: .65rem; font-weight: 700; color: rgba(255,255,255,.25);
      text-transform: uppercase; letter-spacing: .6px;
      padding: 8px 10px 4px;
    }
    .adm-nav-item {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 12px; border-radius: 8px;
      color: rgba(255,255,255,.55); font-size: .85rem; font-weight: 600;
      text-decoration: none; transition: .2s; margin-bottom: 2px;
    }
    .adm-nav-item:hover { background: rgba(255,255,255,.06); color: #fff; }
    .adm-nav-item.active { background: rgba(37,99,235,.2); color: #60A5FA; }
    .adm-nav-item .bi { font-size: 1rem; width: 18px; text-align: center; }

    .adm-nav-footer {
      padding: 12px 10px;
      border-top: 1px solid rgba(255,255,255,.06);
    }

    /* ---- Main ---- */
    .adm-main { margin-left: 240px; min-height: 100vh; }

    .adm-topbar {
      background: #111827; border-bottom: 1px solid rgba(255,255,255,.06);
      padding: 14px 28px; display: flex; align-items: center;
      justify-content: space-between; position: sticky; top: 0; z-index: 50;
    }
    .adm-topbar-title { font-size: 1rem; font-weight: 800; color: #fff; }
    .adm-topbar-user {
      display: flex; align-items: center; gap: 10px;
      font-size: .83rem; color: rgba(255,255,255,.6);
    }
    .adm-avatar {
      width: 30px; height: 30px; border-radius: 50%;
      background: var(--blue); display: flex; align-items: center;
      justify-content: center; font-size: .75rem; font-weight: 800; color: #fff;
    }

    .adm-content { padding: 24px 28px; }

    /* ---- Stat cards ---- */
    .stat-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 24px; }
    .stat-card {
      background: #1E293B; border: 1px solid rgba(255,255,255,.06);
      border-radius: 12px; padding: 18px 20px;
      display: flex; align-items: center; gap: 14px;
      transition: .2s;
    }
    .stat-card:hover { border-color: rgba(37,99,235,.3); transform: translateY(-1px); }
    .stat-icon {
      width: 44px; height: 44px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; flex-shrink: 0;
    }
    .stat-num   { font-size: 1.5rem; font-weight: 800; color: #fff; line-height: 1; }
    .stat-label { font-size: .73rem; color: rgba(255,255,255,.4); margin-top: 3px; }
    .stat-trend { font-size: .72rem; color: #34D399; margin-top: 4px; }

    /* ---- Chart area ---- */
    .chart-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 24px; }
    .chart-card {
      background: #1E293B; border: 1px solid rgba(255,255,255,.06);
      border-radius: 12px; padding: 20px 22px;
    }
    .chart-title {
      font-size: .85rem; font-weight: 800; color: #fff;
      margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
    }

    /* Bar chart manual */
    .bar-chart { display: flex; align-items: flex-end; gap: 8px; height: 120px; }
    .bar-item  { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
    .bar       { width: 100%; background: var(--blue); border-radius: 4px 4px 0 0;
                 opacity: .85; transition: opacity .2s; min-height: 4px; }
    .bar:hover { opacity: 1; }
    .bar-label { font-size: .62rem; color: rgba(255,255,255,.35); }
    .bar-val   { font-size: .62rem; color: rgba(255,255,255,.5); }

    /* Donut chart manual */
    .donut-list { display: flex; flex-direction: column; gap: 10px; }
    .donut-item { display: flex; align-items: center; gap: 10px; }
    .donut-dot  { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .donut-name { font-size: .82rem; color: rgba(255,255,255,.7); flex: 1; }
    .donut-val  { font-size: .82rem; font-weight: 700; color: #fff; }
    .donut-bar-wrap { flex: 1; height: 4px; background: rgba(255,255,255,.08); border-radius: 2px; }
    .donut-bar-fill { height: 100%; border-radius: 2px; }

    /* ---- Table ---- */
    .adm-table-card {
      background: #1E293B; border: 1px solid rgba(255,255,255,.06);
      border-radius: 12px; overflow: hidden;
    }
    .adm-table-head {
      padding: 16px 20px; display: flex; align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .adm-table-title { font-size: .88rem; font-weight: 800; color: #fff; }
    .adm-table { width: 100%; border-collapse: collapse; }
    .adm-table th {
      padding: 10px 16px; font-size: .72rem; font-weight: 700;
      color: rgba(255,255,255,.3); text-transform: uppercase;
      letter-spacing: .4px; text-align: left;
      background: rgba(255,255,255,.02);
      border-bottom: 1px solid rgba(255,255,255,.05);
    }
    .adm-table td {
      padding: 12px 16px; font-size: .83rem; color: rgba(255,255,255,.75);
      border-bottom: 1px solid rgba(255,255,255,.04);
    }
    .adm-table tr:last-child td { border-bottom: none; }
    .adm-table tr:hover td { background: rgba(255,255,255,.02); }

    .st-badge {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 9px; border-radius: 50px;
      font-size: .72rem; font-weight: 700;
    }

    @media(max-width:991px) {
      .adm-sidebar { width: 200px; }
      .adm-main    { margin-left: 200px; }
      .stat-grid   { grid-template-columns: repeat(2,1fr); }
      .chart-grid  { grid-template-columns: 1fr; }
    }
    @media(max-width:767px) {
      .adm-sidebar { display: none; }
      .adm-main    { margin-left: 0; }
      .stat-grid   { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="adm-sidebar">
  <div class="adm-brand">
    <div class="adm-brand-icon"><i class="bi bi-airplane-fill"></i></div>
    <div>
      <div class="adm-brand-name">TravelGo</div>
      <div class="adm-brand-sub">Admin Panel</div>
    </div>
  </div>

  <nav class="adm-nav">
    <div class="adm-nav-label">Utama</div>
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="adm-nav-item active">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="<?= APP_URL ?>/admin/bookings.php" class="adm-nav-item">
      <i class="bi bi-ticket-detailed"></i> Bookings
    </a>

    <div class="adm-nav-label" style="margin-top:8px;">Master Data</div>
    <a href="<?= APP_URL ?>/admin/jadwal.php" class="adm-nav-item">
      <i class="bi bi-calendar3"></i> Jadwal
    </a>
    <a href="<?= APP_URL ?>/admin/routes.php" class="adm-nav-item">
      <i class="bi bi-signpost-split"></i> Rute
    </a>
    <a href="<?= APP_URL ?>/admin/users.php" class="adm-nav-item">
      <i class="bi bi-people"></i> Users
    </a>
     <a href="<?= APP_URL ?>/admin/reviews.php" class="adm-nav-item">
      <i class="bi bi-star"></i> Reviews
    </a>

    <div class="adm-nav-label" style="margin-top:8px;">Lainnya</div>
    <a href="<?= APP_URL ?>/index.php" class="adm-nav-item">
      <i class="bi bi-globe"></i> Lihat Website
    </a>
  </nav>

  <div class="adm-nav-footer">
    <a href="<?= APP_URL ?>/api/auth.php?action=logout" class="adm-nav-item" style="color:rgba(239,68,68,.7);">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<div class="adm-main">

  <!-- Topbar -->
  <div class="adm-topbar">
    <div class="adm-topbar-title">Dashboard</div>
    <div class="adm-topbar-user">
      <div class="adm-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
      <?= clean($_SESSION['nama']) ?>
    </div>
  </div>

  <div class="adm-content">

    <!-- Stat cards -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(37,99,235,.15);">
          <i class="bi bi-people-fill" style="color:#60A5FA;"></i>
        </div>
        <div>
          <div class="stat-num"><?= number_format($stats['total_users']) ?></div>
          <div class="stat-label">Total Pengguna</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16,185,129,.15);">
          <i class="bi bi-ticket-detailed-fill" style="color:#34D399;"></i>
        </div>
        <div>
          <div class="stat-num"><?= number_format($stats['total_bookings']) ?></div>
          <div class="stat-label">Total Booking</div>
          <div class="stat-trend"><i class="bi bi-check-circle"></i> <?= $stats['total_paid'] ?> lunas</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245,158,11,.15);">
          <i class="bi bi-clock-fill" style="color:#FBBF24;"></i>
        </div>
        <div>
          <div class="stat-num"><?= number_format($stats['total_pending']) ?></div>
          <div class="stat-label">Menunggu Bayar</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16,185,129,.15);">
          <i class="bi bi-wallet2" style="color:#34D399;"></i>
        </div>
        <div>
          <div class="stat-num" style="font-size:1.1rem;">
            <?= formatRupiah((float)$stats['revenue']) ?>
          </div>
          <div class="stat-label">Total Pendapatan</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(99,102,241,.15);">
          <i class="bi bi-calendar3" style="color:#A5B4FC;"></i>
        </div>
        <div>
          <div class="stat-num"><?= number_format($stats['total_jadwal']) ?></div>
          <div class="stat-label">Jadwal Aktif</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(244,63,94,.15);">
          <i class="bi bi-graph-up-arrow" style="color:#FB7185;"></i>
        </div>
        <div>
          <?php
            $conv = $stats['total_bookings'] > 0
                ? round(($stats['total_paid'] / $stats['total_bookings']) * 100, 1)
                : 0;
          ?>
          <div class="stat-num"><?= $conv ?>%</div>
          <div class="stat-label">Konversi Booking</div>
        </div>
      </div>
    </div>

    <!-- Charts -->
    <div class="chart-grid">

      <!-- Revenue 7 hari -->
      <div class="chart-card">
        <div class="chart-title">
          <i class="bi bi-bar-chart-fill" style="color:#60A5FA;"></i>
          Pendapatan 7 Hari Terakhir
        </div>
        <?php
          $maxRev = 1;
          foreach ($revenueChart as $rc) { $maxRev = max($maxRev, (float)$rc['total']); }
        ?>
        <?php if (empty($revenueChart)): ?>
          <div style="text-align:center;padding:40px 0;color:rgba(255,255,255,.2);font-size:.82rem;">
            Belum ada data pembayaran
          </div>
        <?php else: ?>
          <div class="bar-chart">
            <?php foreach ($revenueChart as $rc): ?>
              <?php $h = max(4, round(($rc['total'] / $maxRev) * 110)); ?>
              <div class="bar-item">
                <div class="bar-val" style="font-size:.6rem;color:rgba(255,255,255,.4);">
                  <?= 'Rp'.number_format($rc['total']/1000000, 1).'jt' ?>
                </div>
                <div class="bar" style="height:<?= $h ?>px;"></div>
                <div class="bar-label"><?= date('d/m', strtotime($rc['tgl'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Booking by jenis -->
      <div class="chart-card">
        <div class="chart-title">
          <i class="bi bi-pie-chart-fill" style="color:#A78BFA;"></i>
          Booking per Transportasi
        </div>
        <?php
          $colors = ['#60A5FA','#34D399','#FBBF24','#F87171','#A78BFA'];
          $maxJ   = max(1, max(array_column($bookingByJenis, 'total') ?: [1]));
        ?>
        <?php if (empty($bookingByJenis)): ?>
          <div style="text-align:center;padding:40px 0;color:rgba(255,255,255,.2);font-size:.82rem;">
            Belum ada data booking
          </div>
        <?php else: ?>
          <div class="donut-list">
            <?php foreach ($bookingByJenis as $i => $bj): ?>
              <div class="donut-item">
                <div class="donut-dot" style="background:<?= $colors[$i % 5] ?>;"></div>
                <div class="donut-name"><?= clean($bj['nama']) ?></div>
                <div class="donut-bar-wrap">
                  <div class="donut-bar-fill"
                       style="width:<?= round($bj['total']/$maxJ*100) ?>%;background:<?= $colors[$i%5] ?>;"></div>
                </div>
                <div class="donut-val"><?= $bj['total'] ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent bookings table -->
    <div class="adm-table-card">
      <div class="adm-table-head">
        <div class="adm-table-title">Booking Terbaru</div>
        <a href="<?= APP_URL ?>/admin/bookings.php"
           style="font-size:.78rem;color:#60A5FA;text-decoration:none;font-weight:600;">
          Lihat Semua <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <div style="overflow-x:auto;">
        <table class="adm-table">
          <thead>
            <tr>
              <th>Kode Booking</th>
              <th>Pengguna</th>
              <th>Rute</th>
              <th>Jenis</th>
              <th>Total</th>
              <th>Status</th>
              <th>Tanggal</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentBookings)): ?>
              <tr><td colspan="7" style="text-align:center;color:rgba(255,255,255,.2);padding:32px;">Belum ada data</td></tr>
            <?php else: ?>
              <?php foreach ($recentBookings as $bk):
                $st = $statusLabel[$bk['status']] ?? $statusLabel['pending'];
              ?>
                <tr>
                  <td style="font-family:monospace;font-weight:700;color:#fff;">
                    <?= clean($bk['kode_booking']) ?>
                  </td>
                  <td><?= clean($bk['user_nama']) ?></td>
                  <td>
                    <?= clean($bk['kota_asal']) ?>
                    <span style="color:rgba(255,255,255,.3);">→</span>
                    <?= clean($bk['kota_tujuan']) ?>
                  </td>
                  <td><?= clean($bk['jenis_nama']) ?></td>
                  <td style="color:#60A5FA;font-weight:700;"><?= formatRupiah($bk['total_harga']) ?></td>
                  <td>
                    <span class="st-badge"
                          style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;">
                      <?= $st['label'] ?>
                    </span>
                  </td>
                  <td style="color:rgba(255,255,255,.4);font-size:.75rem;">
                    <?= date('d M Y', strtotime($bk['created_at'])) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>