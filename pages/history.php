<?php
// ============================================================
//  TravelGo — Halaman Riwayat Booking (pages/history.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle  = 'Riwayat Booking';
$activeMenu = '';

// ---- Filter & pagination ----
$status_filter = isset($_GET['status']) ? clean($_GET['status']) : '';
$page          = isset($_GET['page'])   ? max(1,(int)$_GET['page']) : 1;
$perPage       = 8;
$offset        = ($page - 1) * $perPage;

$allowed_status = ['pending','confirmed','paid','cancelled','expired'];
if (!in_array($status_filter, $allowed_status)) $status_filter = '';

$uid = $_SESSION['user_id'];

// ---- Hitung total ----
$whereStatus = $status_filter ? "AND b.status = '$status_filter'" : '';
$totalRow = $conn->query(
    "SELECT COUNT(*) as n FROM bookings b WHERE b.user_id = $uid $whereStatus"
)->fetch_assoc();
$totalData = (int)($totalRow['n'] ?? 0);
$totalPage = ceil($totalData / $perPage);

// ---- Ambil data ----
$sql = "
    SELECT b.id, b.kode_booking, b.tipe_perjalanan,
           b.jml_dewasa, b.jml_anak, b.total_harga,
           b.status, b.created_at,
           j.kode_jadwal, j.jam_berangkat, j.jam_tiba,
           j.tanggal_berangkat, j.kelas,
           o.nama  AS operator_nama,
           ka.nama AS kota_asal,
           kt.nama AS kota_tujuan,
           jt.nama AS jenis_nama,
           pay.status AS pay_status, pay.metode
    FROM bookings b
    JOIN jadwal j              ON b.jadwal_id = j.id
    JOIN rute r                ON j.rute_id = r.id
    JOIN operator o            ON r.operator_id = o.id
    JOIN kota ka               ON r.kota_asal_id = ka.id
    JOIN kota kt               ON r.kota_tujuan_id = kt.id
    JOIN jenis_transportasi jt ON r.jenis_transportasi_id = jt.id
    LEFT JOIN pembayaran pay   ON b.id = pay.booking_id
    WHERE b.user_id = $uid $whereStatus
    ORDER BY b.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$bookings = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// ---- Hitung stats ----
$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status='paid') AS paid,
        SUM(status='pending') AS pending,
        SUM(status='cancelled') AS cancelled,
        SUM(total_harga) AS total_spent
    FROM bookings WHERE user_id = $uid
")->fetch_assoc();

// Helpers
$statusLabel = [
    'pending'   => ['label'=>'Menunggu Bayar', 'color'=>'var(--warning)',  'bg'=>'rgba(245,158,11,.10)',  'icon'=>'clock'],
    'confirmed' => ['label'=>'Dikonfirmasi',   'color'=>'#3B82F6',         'bg'=>'rgba(59,130,246,.10)',  'icon'=>'check-circle'],
    'paid'      => ['label'=>'Lunas',          'color'=>'var(--success)',  'bg'=>'rgba(16,185,129,.10)',  'icon'=>'check-circle-fill'],
    'cancelled' => ['label'=>'Dibatalkan',     'color'=>'var(--danger)',   'bg'=>'rgba(239,68,68,.10)',   'icon'=>'x-circle-fill'],
    'expired'   => ['label'=>'Kedaluwarsa',    'color'=>'var(--gray-400)', 'bg'=>'rgba(100,116,139,.10)', 'icon'=>'dash-circle'],
];

$jenisIkon = ['Pesawat'=>'airplane','Kereta'=>'train-front','Bus'=>'bus-front','Kapal'=>'water','Travel'=>'car-front'];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.history-page { background:var(--gray-100); min-height:100vh; padding:32px 0 64px; }

/* Page header */
.history-header {
  background:linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
  border-radius:var(--radius-lg); padding:24px 28px;
  margin-bottom:24px; position:relative; overflow:hidden;
}
.history-header::before {
  content:''; position:absolute; inset:0;
  background-image:radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
  background-size:20px 20px;
}
.history-header-content { position:relative; z-index:1; }

/* Stats cards */
.stats-grid {
  display:grid; grid-template-columns:repeat(4,1fr); gap:12px;
  margin-bottom:20px;
}
.stat-card {
  background:var(--white); border:1px solid var(--gray-200);
  border-radius:var(--radius-lg); padding:16px 18px;
  display:flex; align-items:center; gap:12px;
  transition:var(--transition);
}
.stat-card:hover { box-shadow:var(--shadow-md); transform:translateY(-1px); }
.stat-icon {
  width:40px; height:40px; border-radius:var(--radius-md);
  display:flex; align-items:center; justify-content:center;
  font-size:1.1rem; flex-shrink:0;
}
.stat-num { font-size:1.3rem; font-weight:800; color:var(--navy); line-height:1; }
.stat-label { font-size:.75rem; color:var(--gray-400); margin-top:2px; }

/* Filter tabs */
.filter-tabs {
  display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px;
}
.filter-tab {
  padding:7px 16px; border-radius:50px;
  font-size:.82rem; font-weight:600;
  border:1.5px solid var(--gray-200);
  color:var(--gray-600); background:var(--white);
  text-decoration:none; transition:var(--transition);
  display:flex; align-items:center; gap:5px;
}
.filter-tab:hover { border-color:var(--blue); color:var(--blue); }
.filter-tab.active { background:var(--blue); color:#fff; border-color:var(--blue); }

/* Booking card */
.booking-card {
  background:var(--white); border:1.5px solid var(--gray-200);
  border-radius:var(--radius-lg); margin-bottom:12px;
  overflow:hidden; transition:var(--transition);
  animation:slideIn .35s ease forwards; opacity:0;
}
.booking-card:hover { box-shadow:var(--shadow-md); border-color:var(--blue-light); }
@keyframes slideIn {
  from { opacity:0; transform:translateY(8px); }
  to   { opacity:1; transform:translateY(0); }
}
.booking-card:nth-child(1){animation-delay:.04s}
.booking-card:nth-child(2){animation-delay:.08s}
.booking-card:nth-child(3){animation-delay:.12s}
.booking-card:nth-child(4){animation-delay:.16s}
.booking-card:nth-child(5){animation-delay:.20s}

/* Card header stripe */
.bc-header {
  padding:12px 20px;
  display:flex; align-items:center; justify-content:space-between;
  border-bottom:1px solid var(--gray-100);
  background:var(--gray-50);
}
.bc-kode {
  font-size:.82rem; font-weight:800; color:var(--navy);
  font-family:monospace; letter-spacing:.5px;
}
.bc-date { font-size:.75rem; color:var(--gray-400); }
.status-badge {
  display:inline-flex; align-items:center; gap:5px;
  padding:4px 10px; border-radius:50px;
  font-size:.75rem; font-weight:700;
}

/* Card body */
.bc-body {
  padding:16px 20px;
  display:flex; align-items:center; gap:16px; flex-wrap:wrap;
}
.bc-jenis {
  width:44px; height:44px; border-radius:var(--radius-md);
  background:rgba(37,99,235,.08);
  display:flex; align-items:center; justify-content:center;
  color:var(--blue); font-size:1.2rem; flex-shrink:0;
}
.bc-route {
  flex:1; min-width:200px;
}
.bc-route-main {
  font-size:1rem; font-weight:800; color:var(--navy);
  display:flex; align-items:center; gap:8px;
}
.bc-route-main .bi-arrow-right { color:var(--blue); font-size:.85rem; }
.bc-route-sub { font-size:.78rem; color:var(--gray-400); margin-top:3px; }

.bc-info {
  display:flex; gap:20px; flex-wrap:wrap;
}
.bc-info-item .label {
  font-size:.7rem; color:var(--gray-400); font-weight:600;
  text-transform:uppercase; letter-spacing:.3px;
}
.bc-info-item .value {
  font-size:.88rem; font-weight:700; color:var(--navy); margin-top:1px;
}

.bc-price {
  text-align:right; flex-shrink:0;
}
.bc-price-num {
  font-size:1.1rem; font-weight:800; color:var(--blue);
}
.bc-price-sub { font-size:.72rem; color:var(--gray-400); }

/* Card footer actions */
.bc-footer {
  padding:10px 20px;
  border-top:1px solid var(--gray-100);
  display:flex; align-items:center; justify-content:space-between;
  gap:10px; flex-wrap:wrap;
}
.bc-footer-left { font-size:.75rem; color:var(--gray-400); }
.bc-actions { display:flex; gap:8px; }
.bc-btn {
  padding:6px 14px; border-radius:var(--radius-md);
  font-size:.8rem; font-weight:700; cursor:pointer;
  transition:var(--transition); text-decoration:none;
  display:flex; align-items:center; gap:5px; border:none;
  font-family:'Plus Jakarta Sans',sans-serif;
}
.bc-btn.primary { background:var(--blue); color:#fff; }
.bc-btn.primary:hover { background:var(--blue-light); }
.bc-btn.outline {
  background:var(--white); color:var(--navy);
  border:1.5px solid var(--gray-200);
}
.bc-btn.outline:hover { border-color:var(--blue); color:var(--blue); }
.bc-btn.danger {
  background:rgba(239,68,68,.08); color:var(--danger);
  border:1.5px solid rgba(239,68,68,.2);
}
.bc-btn.danger:hover { background:rgba(239,68,68,.15); }

/* Pagination */
.pagination-wrap {
  display:flex; justify-content:center; gap:6px;
  margin-top:20px; flex-wrap:wrap;
}
.page-btn {
  width:36px; height:36px; border-radius:var(--radius-md);
  display:flex; align-items:center; justify-content:center;
  font-size:.85rem; font-weight:700; text-decoration:none;
  border:1.5px solid var(--gray-200); color:var(--gray-600);
  background:var(--white); transition:var(--transition);
}
.page-btn:hover { border-color:var(--blue); color:var(--blue); }
.page-btn.active { background:var(--blue); color:#fff; border-color:var(--blue); }
.page-btn.disabled { opacity:.4; pointer-events:none; }

/* Empty state */
.empty-state {
  text-align:center; padding:64px 20px;
  background:var(--white); border-radius:var(--radius-lg);
  border:1.5px dashed var(--gray-200);
}
.empty-state .icon { font-size:3.5rem; margin-bottom:16px; }

.fade-up { opacity:0; transform:translateY(12px); animation:fuAnim .4s ease forwards; }
@keyframes fuAnim { to { opacity:1; transform:translateY(0); } }

@media(max-width:767px) {
  .stats-grid { grid-template-columns:repeat(2,1fr); }
  .bc-body    { gap:12px; }
  .bc-info    { gap:12px; }
}
@media(max-width:480px) {
  .stats-grid { grid-template-columns:1fr 1fr; }
}
</style>

<div class="history-page">
  <div class="container">

    <!-- Page header -->
    <div class="history-header fade-up">
      <div class="history-header-content">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
          <div>
            <h1 style="color:#fff;font-size:1.4rem;font-weight:800;margin:0 0 4px;">
              Riwayat Booking
            </h1>
            <p style="color:rgba(255,255,255,.6);font-size:.85rem;margin:0;">
              Semua pemesanan tiket kamu ada di sini
            </p>
          </div>
          <a href="<?= APP_URL ?>/index.php"
             style="background:rgba(255,255,255,.12);color:#fff;padding:9px 18px;
                    border-radius:var(--radius-md);font-size:.85rem;font-weight:700;
                    text-decoration:none;display:flex;align-items:center;gap:6px;
                    border:1px solid rgba(255,255,255,.2);">
            <i class="bi bi-plus-circle"></i> Pesan Tiket Baru
          </a>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid fade-up">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(37,99,235,.08);">
          <i class="bi bi-ticket-detailed" style="color:var(--blue);"></i>
        </div>
        <div>
          <div class="stat-num"><?= $stats['total'] ?? 0 ?></div>
          <div class="stat-label">Total Booking</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16,185,129,.08);">
          <i class="bi bi-check-circle-fill" style="color:var(--success);"></i>
        </div>
        <div>
          <div class="stat-num"><?= $stats['paid'] ?? 0 ?></div>
          <div class="stat-label">Booking Lunas</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245,158,11,.08);">
          <i class="bi bi-clock" style="color:var(--warning);"></i>
        </div>
        <div>
          <div class="stat-num"><?= $stats['pending'] ?? 0 ?></div>
          <div class="stat-label">Menunggu Bayar</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(37,99,235,.06);">
          <i class="bi bi-wallet2" style="color:var(--blue);"></i>
        </div>
        <div>
          <div class="stat-num" style="font-size:1rem;">
            <?= formatRupiah((float)($stats['total_spent'] ?? 0)) ?>
          </div>
          <div class="stat-label">Total Pengeluaran</div>
        </div>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="filter-tabs fade-up">
      <a href="?status=" class="filter-tab <?= $status_filter===''?'active':'' ?>">
        Semua <span style="background:rgba(255,255,255,.2);padding:1px 7px;border-radius:50px;font-size:.72rem;">
          <?= $stats['total'] ?? 0 ?>
        </span>
      </a>
      <?php
        $tabList = [
          'paid'      => ['label'=>'Lunas',           'icon'=>'check-circle-fill'],
          'pending'   => ['label'=>'Menunggu Bayar',  'icon'=>'clock'],
          'cancelled' => ['label'=>'Dibatalkan',      'icon'=>'x-circle'],
          'expired'   => ['label'=>'Kedaluwarsa',     'icon'=>'dash-circle'],
        ];
        foreach($tabList as $key => $t):
          $cnt = $conn->query("SELECT COUNT(*) as n FROM bookings WHERE user_id=$uid AND status='$key'")->fetch_assoc()['n'] ?? 0;
      ?>
        <a href="?status=<?= $key ?>" class="filter-tab <?= $status_filter===$key?'active':'' ?>">
          <i class="bi bi-<?= $t['icon'] ?>"></i> <?= $t['label'] ?>
          <?php if($cnt > 0): ?>
            <span style="background:rgba(255,255,255,.25);padding:1px 7px;border-radius:50px;font-size:.72rem;"><?= $cnt ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Booking list -->
    <?php if (empty($bookings)): ?>
      <div class="empty-state fade-up">
        <div class="icon">🎫</div>
        <h3 style="color:var(--navy);font-weight:800;">Belum Ada Booking</h3>
        <p style="color:var(--gray-400);font-size:.9rem;">
          <?= $status_filter ? 'Tidak ada booking dengan status "'.($statusLabel[$status_filter]['label']??$status_filter).'".' : 'Kamu belum pernah memesan tiket.' ?>
        </p>
        <a href="<?= APP_URL ?>/index.php"
           style="display:inline-flex;align-items:center;gap:6px;margin-top:16px;
                  background:var(--blue);color:#fff;padding:10px 24px;
                  border-radius:var(--radius-md);font-weight:700;text-decoration:none;font-size:.9rem;">
          <i class="bi bi-search"></i> Cari Tiket Sekarang
        </a>
      </div>

    <?php else: ?>
      <?php foreach ($bookings as $bk):
        $st  = $statusLabel[$bk['status']] ?? $statusLabel['pending'];
        $ikon = $jenisIkon[$bk['jenis_nama']] ?? 'ticket-detailed';
        $jmlPenumpang = $bk['jml_dewasa'] + $bk['jml_anak'];
      ?>
        <div class="booking-card">

          <!-- Card header -->
          <div class="bc-header">
            <div>
              <div class="bc-kode"><?= clean($bk['kode_booking']) ?></div>
              <div class="bc-date">
                Dipesan: <?= date('d M Y, H:i', strtotime($bk['created_at'])) ?>
              </div>
            </div>
            <div class="status-badge"
                 style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;">
              <i class="bi bi-<?= $st['icon'] ?>"></i>
              <?= $st['label'] ?>
            </div>
          </div>

          <!-- Card body -->
          <div class="bc-body">

            <!-- Ikon jenis -->
            <div class="bc-jenis">
              <i class="bi bi-<?= $ikon ?>"></i>
            </div>

            <!-- Rute -->
            <div class="bc-route">
              <div class="bc-route-main">
                <?= clean($bk['kota_asal']) ?>
                <i class="bi bi-arrow-right"></i>
                <?= clean($bk['kota_tujuan']) ?>
              </div>
              <div class="bc-route-sub">
                <?= clean($bk['jenis_nama']) ?> · <?= clean($bk['operator_nama']) ?> · <?= clean($bk['kelas']) ?>
              </div>
            </div>

            <!-- Info detail -->
            <div class="bc-info">
              <div class="bc-info-item">
                <div class="label">Tanggal</div>
                <div class="value"><?= date('d M Y', strtotime($bk['tanggal_berangkat'])) ?></div>
              </div>
              <div class="bc-info-item">
                <div class="label">Jam</div>
                <div class="value"><?= substr($bk['jam_berangkat'],0,5) ?> → <?= substr($bk['jam_tiba'],0,5) ?></div>
              </div>
              <div class="bc-info-item">
                <div class="label">Penumpang</div>
                <div class="value"><?= $jmlPenumpang ?> Orang</div>
              </div>
            </div>

            <!-- Harga -->
            <div class="bc-price">
              <div class="bc-price-num"><?= formatRupiah($bk['total_harga']) ?></div>
              <div class="bc-price-sub"><?= $jmlPenumpang ?> penumpang</div>
            </div>

          </div>

          <!-- Card footer -->
          <div class="bc-footer">
            <div class="bc-footer-left">
              <?= clean($bk['jenis_nama']) ?> ·
              <?= clean($bk['kode_jadwal']) ?> ·
              <?= $bk['tipe_perjalanan']==='pulang_pergi' ? '↔ Pulang Pergi' : '→ Sekali Jalan' ?>
            </div>
            <div class="bc-actions">
              <?php if ($bk['status'] === 'paid'): ?>
                <a href="<?= APP_URL ?>/pages/ticket.php?booking_id=<?= $bk['id'] ?>" class="bc-btn primary">
                  <i class="bi bi-ticket-detailed"></i> Lihat Tiket
                </a>
              <?php elseif ($bk['status'] === 'pending'): ?>
                <a href="<?= APP_URL ?>/pages/payment.php?booking_id=<?= $bk['id'] ?>" class="bc-btn primary">
                  <i class="bi bi-credit-card"></i> Bayar Sekarang
                </a>
                <button class="bc-btn danger"
                        onclick="cancelBooking(<?= $bk['id'] ?>, '<?= clean($bk['kode_booking']) ?>')">
                  <i class="bi bi-x-circle"></i> Batalkan
                </button>
              <?php else: ?>
                <button class="bc-btn outline" disabled style="opacity:.5;cursor:not-allowed;">
                  <i class="bi bi-archive"></i> <?= $st['label'] ?>
                </button>
              <?php endif; ?>
            </div>
          </div>

        </div>
      <?php endforeach; ?>

      <!-- Pagination -->
      <?php if ($totalPage > 1): ?>
        <div class="pagination-wrap fade-up">
          <a href="?status=<?= $status_filter ?>&page=<?= max(1,$page-1) ?>"
             class="page-btn <?= $page<=1?'disabled':'' ?>">
            <i class="bi bi-chevron-left"></i>
          </a>
          <?php for($p=1;$p<=$totalPage;$p++): ?>
            <a href="?status=<?= $status_filter ?>&page=<?= $p ?>"
               class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
          <?php endfor; ?>
          <a href="?status=<?= $status_filter ?>&page=<?= min($totalPage,$page+1) ?>"
             class="page-btn <?= $page>=$totalPage?'disabled':'' ?>">
            <i class="bi bi-chevron-right"></i>
          </a>
        </div>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<script>
function cancelBooking(id, kode) {
  if (!confirm('Yakin ingin membatalkan booking ' + kode + '?\nBooking yang dibatalkan tidak dapat dipulihkan.')) return;

  fetch('<?= APP_URL ?>/api/booking.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=cancel&booking_id=' + id
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('Booking berhasil dibatalkan.');
      location.reload();
    } else {
      alert('Gagal: ' + (data.message || 'Terjadi kesalahan.'));
    }
  })
  .catch(() => alert('Terjadi kesalahan jaringan.'));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>