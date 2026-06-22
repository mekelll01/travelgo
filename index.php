<?php
// ============================================================
//  TravelGo — Homepage (index.php)
// ============================================================
require_once __DIR__ . '/includes/config.php';

$pageTitle  = 'Pesan Tiket Transportasi Online';
$activeMenu = '';

$kotaResult  = dbQuery($conn, "SELECT id, nama, kode FROM kota ORDER BY nama ASC");
$kotaList    = $kotaResult ? $kotaResult->fetch_all(MYSQLI_ASSOC) : [];

$jenisResult = dbQuery($conn, "SELECT * FROM jenis_transportasi ORDER BY id ASC");
$jenisList   = $jenisResult ? $jenisResult->fetch_all(MYSQLI_ASSOC) : [];

$activeJenis = isset($_GET['jenis']) ? (int)$_GET['jenis'] : 1;

// Data untuk dashboard user (hanya jika login)
$recentBooking = null;
$bookingStats  = null;
if (isLogin()) {
    $uid = $_SESSION['user_id'];
    $recentBooking = $conn->query("
        SELECT b.kode_booking, b.status, b.total_harga, b.created_at,
               ka.nama AS asal, kt.nama AS tujuan,
               j.tanggal_berangkat, j.jam_berangkat,
               jt.nama AS jenis_nama
        FROM bookings b
        JOIN jadwal j ON b.jadwal_id = j.id
        JOIN rute r ON j.rute_id = r.id
        JOIN kota ka ON r.kota_asal_id = ka.id
        JOIN kota kt ON r.kota_tujuan_id = kt.id
        JOIN jenis_transportasi jt ON r.jenis_transportasi_id = jt.id
        WHERE b.user_id = $uid
        ORDER BY b.created_at DESC LIMIT 3
    ")->fetch_all(MYSQLI_ASSOC);

    $bookingStats = $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(status='paid') AS paid,
            SUM(status='pending') AS pending
        FROM bookings WHERE user_id = $uid
    ")->fetch_assoc();
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ---- Destination visual cards ---- */
.dest-visual {
  border-radius: 20px; overflow: hidden;
  height: 100%; min-height: 420px;
  position: relative; display: flex;
  flex-direction: column; justify-content: flex-end;
}
.dest-bg {
  position: absolute; inset: 0;
  background: linear-gradient(160deg, #0B1426 0%, #1a3a7a 50%, #0f4c8a 100%);
}
.dest-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,.75) 0%, transparent 60%);
}
.dest-float-card {
  position: absolute; top: 20px; right: 20px;
  background: rgba(255,255,255,.12);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 14px; padding: 14px 18px;
  color: #fff; min-width: 160px;
}
.dest-float-title { font-size: .72rem; color: rgba(255,255,255,.55); font-weight: 600; margin-bottom: 6px; }
.dest-route-item {
  display: flex; align-items: center; gap: 8px;
  font-size: .82rem; font-weight: 600; color: #fff;
  padding: 5px 0; border-bottom: 1px solid rgba(255,255,255,.08);
}
.dest-route-item:last-child { border-bottom: none; }
.dest-route-item .price { margin-left: auto; color: #FCD34D; font-size: .75rem; }

/* Plane animation */
.plane-anim {
  position: absolute; font-size: 2.5rem;
  animation: flyAcross 8s ease-in-out infinite;
  filter: drop-shadow(0 4px 12px rgba(0,0,0,.3));
}
@keyframes flyAcross {
  0%   { left: 10%; top: 35%; transform: rotate(-5deg); opacity: .7; }
  50%  { left: 55%; top: 20%; transform: rotate(5deg);  opacity: 1; }
  100% { left: 10%; top: 35%; transform: rotate(-5deg); opacity: .7; }
}

/* Cloud elements */
.cloud {
  position: absolute; background: rgba(255,255,255,.06);
  border-radius: 50px; filter: blur(8px);
}

/* Dest content */
.dest-content {
  position: relative; z-index: 2; padding: 24px;
}
.dest-badge {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(251,191,36,.15); border: 1px solid rgba(251,191,36,.3);
  color: #FCD34D; font-size: .72rem; font-weight: 700;
  padding: 4px 10px; border-radius: 50px; margin-bottom: 10px;
  letter-spacing: .3px;
}
.dest-title { font-size: 1.3rem; font-weight: 800; color: #fff; margin-bottom: 6px; line-height: 1.3; }
.dest-sub   { font-size: .82rem; color: rgba(255,255,255,.6); margin-bottom: 16px; }
.dest-stats { display: flex; gap: 20px; }
.dest-stat-num   { font-size: 1.1rem; font-weight: 800; color: #fff; line-height: 1; }
.dest-stat-label { font-size: .68rem; color: rgba(255,255,255,.45); margin-top: 2px; }

/* Promo chips */
.promo-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
.promo-chip {
  background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15);
  color: #fff; font-size: .75rem; font-weight: 600;
  padding: 5px 12px; border-radius: 50px;
  display: flex; align-items: center; gap: 5px;
}
.promo-chip .disc { color: #FCD34D; }

/* ---- Dashboard user (logged in) ---- */
.dashboard-hero {
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
  padding: 32px 0 0; position: relative; overflow: hidden;
}
.dashboard-hero::before {
  content:''; position:absolute; inset:0;
  background-image: radial-gradient(rgba(255,255,255,.04) 1px, transparent 1px);
  background-size: 24px 24px;
}
.dash-greeting { position: relative; z-index: 1; margin-bottom: 24px; }
.dash-greeting h2 { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 4px; }
.dash-greeting p  { color: rgba(255,255,255,.55); font-size: .9rem; margin: 0; }

/* Quick search on dashboard */
.dash-search-bar {
  background: var(--white); border-radius: 16px 16px 0 0;
  padding: 24px 28px; position: relative; z-index: 1;
  box-shadow: 0 -8px 32px rgba(0,0,0,.15);
}
.dash-search-title {
  font-size: .82rem; font-weight: 800; color: var(--navy);
  text-transform: uppercase; letter-spacing: .5px; margin-bottom: 16px;
  display: flex; align-items: center; gap: 8px;
}

/* Stats cards */
.dash-stats { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.dash-stat {
  background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.1);
  border-radius: 12px; padding: 14px 18px; flex: 1; min-width: 120px;
  position: relative; z-index: 1;
}
.dash-stat-num   { font-size: 1.4rem; font-weight: 800; color: #fff; line-height: 1; }
.dash-stat-label { font-size: .72rem; color: rgba(255,255,255,.45); margin-top: 3px; }

/* Recent booking cards */
.recent-section { padding: 24px 0 48px; background: var(--gray-50); }
.recent-title {
  font-size: 1rem; font-weight: 800; color: var(--navy);
  margin-bottom: 16px; display: flex; align-items: center;
  justify-content: space-between;
}
.bk-mini {
  background: var(--white); border: 1px solid var(--gray-200);
  border-radius: 12px; padding: 16px 18px;
  display: flex; align-items: center; gap: 14px;
  transition: var(--transition); margin-bottom: 10px;
}
.bk-mini:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
.bk-mini-icon {
  width: 40px; height: 40px; border-radius: 10px;
  background: rgba(37,99,235,.08);
  display: flex; align-items: center; justify-content: center;
  color: var(--blue); font-size: 1.1rem; flex-shrink: 0;
}
.bk-mini-route { font-size: .9rem; font-weight: 700; color: var(--navy); }
.bk-mini-meta  { font-size: .75rem; color: var(--gray-400); margin-top: 2px; }
.bk-mini-status {
  margin-left: auto; text-align: right; flex-shrink: 0;
}
.bk-status-badge {
  font-size: .72rem; font-weight: 700; padding: 3px 9px; border-radius: 50px;
  display: inline-block;
}

/* Quick action buttons */
.quick-actions { display: grid; grid-template-columns: repeat(5,1fr); gap: 10px; margin-bottom: 24px; }
.qa-btn {
  background: var(--white); border: 1.5px solid var(--gray-200);
  border-radius: 12px; padding: 16px 8px; text-align: center;
  text-decoration: none; transition: var(--transition); cursor: pointer;
}
.qa-btn:hover { border-color: var(--blue); box-shadow: var(--shadow-md); transform: translateY(-2px); }
.qa-icon { font-size: 1.4rem; margin-bottom: 6px; display: block; }
.qa-label { font-size: .75rem; font-weight: 700; color: var(--navy); }

@media(max-width:575px) {
  .quick-actions { grid-template-columns: repeat(3,1fr); }
  .dest-float-card { display: none; }
}
</style>

<?php if (isLogin()): ?>
<!-- =========================================================
     DASHBOARD USER (LOGGED IN)
========================================================= -->
<div class="dashboard-hero">
  <div class="container">

    <!-- Greeting -->
    <div class="dash-greeting">
      <h2>
        <?php
          $jam = (int)date('H');
          if ($jam < 12) echo 'Selamat Pagi';
          elseif ($jam < 15) echo 'Selamat Siang';
          elseif ($jam < 18) echo 'Selamat Sore';
          else echo 'Selamat Malam';
        ?>, <?= clean(explode(' ', $_SESSION['nama'])[0]) ?>! 👋
      </h2>
      <p>Mau pergi ke mana hari ini?</p>
    </div>

    <!-- Stats -->
    <div class="dash-stats">
      <div class="dash-stat">
        <div class="dash-stat-num"><?= $bookingStats['total'] ?? 0 ?></div>
        <div class="dash-stat-label">Total Booking</div>
      </div>
      <div class="dash-stat">
        <div class="dash-stat-num"><?= $bookingStats['paid'] ?? 0 ?></div>
        <div class="dash-stat-label">Tiket Lunas</div>
      </div>
      <div class="dash-stat">
        <div class="dash-stat-num"><?= $bookingStats['pending'] ?? 0 ?></div>
        <div class="dash-stat-label">Menunggu Bayar</div>
      </div>
    </div>

    <!-- Quick search bar -->
    <div class="dash-search-bar">
      <div class="dash-search-title">
        <i class="bi bi-search" style="color:var(--blue);"></i> Cari Tiket Sekarang
      </div>

      <!-- Tabs jenis -->
      <div class="tg-tabs mb-3">
        <?php
          $icons = ['plane','train-front','bus-front','water','car-front'];
          foreach ($jenisList as $j):
            $icon = $icons[($j['id']-1)] ?? 'ticket';
        ?>
          <button class="tg-tab <?= $j['id']==$activeJenis?'active':'' ?>" data-jenis="<?= $j['id'] ?>">
            <i class="bi bi-<?= $icon ?>"></i> <?= clean($j['nama']) ?>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Form -->
      <form action="pages/search.php" method="GET" data-validate>
        <input type="hidden" name="jenis" id="jenis_transportasi_id" value="<?= $activeJenis ?>">
        <input type="hidden" name="trip"  id="trip_type" value="sekali_jalan">
        <div class="tg-search-row">
          <div class="tg-field">
            <label><i class="bi bi-geo-alt"></i> Dari</label>
            <select name="asal" required>
              <option value="">Pilih kota asal</option>
              <?php foreach($kotaList as $k): ?>
                <option value="<?=$k['id']?>"><?= clean($k['nama']) ?> <?= $k['kode']?'('.$k['kode'].')':'' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="button" class="tg-swap-btn" id="swap-btn"><i class="bi bi-arrow-left-right"></i></button>
          <div class="tg-field">
            <label><i class="bi bi-geo-alt-fill"></i> Ke</label>
            <select name="tujuan" required>
              <option value="">Pilih kota tujuan</option>
              <?php foreach($kotaList as $k): ?>
                <option value="<?=$k['id']?>"><?= clean($k['nama']) ?> <?= $k['kode']?'('.$k['kode'].')':'' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="tg-field">
            <label><i class="bi bi-calendar3"></i> Berangkat</label>
            <input type="date" name="tgl_berangkat" required min="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-sm-4">
            <div class="tg-field">
              <label><i class="bi bi-people"></i> Dewasa</label>
              <select name="dewasa"><?php for($i=1;$i<=9;$i++): ?><option value="<?=$i?>"><?=$i?> Orang</option><?php endfor; ?></select>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="tg-field">
              <label><i class="bi bi-person-hearts"></i> Anak</label>
              <select name="anak"><?php for($i=0;$i<=6;$i++): ?><option value="<?=$i?>"><?=$i?> Anak</option><?php endfor; ?></select>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="tg-field">
              <label><i class="bi bi-star"></i> Kelas</label>
              <select name="kelas">
                <option value="">Semua Kelas</option>
                <option>Ekonomi</option><option>Bisnis</option><option>Eksekutif</option>
              </select>
            </div>
          </div>
        </div>
        <div class="mt-3">
          <button type="submit" class="tg-btn-search w-100" style="font-size:1rem;padding:14px;">
            <i class="bi bi-search"></i> Cari Tiket Sekarang
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Recent booking + quick actions -->
<div class="recent-section">
  <div class="container">

    <!-- Quick actions -->
    <div class="quick-actions mb-4">
      <?php
        $qa = [
          ['icon'=>'airplane',    'label'=>'Pesawat',  'url'=>'pages/search.php?jenis=1'],
          ['icon'=>'train-front', 'label'=>'Kereta',   'url'=>'pages/search.php?jenis=2'],
          ['icon'=>'bus-front',   'label'=>'Bus',      'url'=>'pages/search.php?jenis=3'],
          ['icon'=>'water',       'label'=>'Kapal',    'url'=>'pages/search.php?jenis=4'],
          ['icon'=>'car-front',   'label'=>'Travel',   'url'=>'pages/search.php?jenis=5'],
        ];
        foreach($qa as $q):
      ?>
        <a href="<?= APP_URL ?>/<?= $q['url'] ?>" class="qa-btn">
          <span class="qa-icon"><i class="bi bi-<?= $q['icon'] ?>"></i></span>
          <div class="qa-label"><?= $q['label'] ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Recent bookings -->
    <?php if (!empty($recentBooking)): ?>
    <div class="recent-title">
      <span><i class="bi bi-clock-history" style="color:var(--blue);"></i> Perjalanan Terakhir</span>
      <a href="<?= APP_URL ?>/pages/history.php" style="font-size:.82rem;color:var(--blue);font-weight:600;text-decoration:none;">
        Lihat Semua <i class="bi bi-arrow-right"></i>
      </a>
    </div>
    <?php
      $stColor = [
        'paid'      => ['bg'=>'rgba(16,185,129,.1)', 'color'=>'#065F46', 'label'=>'Lunas'],
        'pending'   => ['bg'=>'rgba(245,158,11,.1)', 'color'=>'#92400E', 'label'=>'Menunggu Bayar'],
        'cancelled' => ['bg'=>'rgba(239,68,68,.1)',  'color'=>'#991B1B', 'label'=>'Dibatalkan'],
        'expired'   => ['bg'=>'rgba(107,114,128,.1)','color'=>'#374151', 'label'=>'Kedaluwarsa'],
      ];
      $jenisIcon = ['Pesawat'=>'airplane','Kereta'=>'train-front','Bus'=>'bus-front','Kapal'=>'water','Travel'=>'car-front'];
      foreach($recentBooking as $bk):
        $st = $stColor[$bk['status']] ?? $stColor['pending'];
        $icon = $jenisIcon[$bk['jenis_nama']] ?? 'ticket-detailed';
    ?>
      <a href="<?= APP_URL ?>/pages/<?= $bk['status']==='paid'?'ticket':'payment' ?>.php?booking_id=<?= /* id tidak ada di query, pakai kode */ 0 ?>"
         style="text-decoration:none;">
        <div class="bk-mini">
          <div class="bk-mini-icon"><i class="bi bi-<?= $icon ?>"></i></div>
          <div>
            <div class="bk-mini-route">
              <?= clean($bk['asal']) ?> <i class="bi bi-arrow-right" style="font-size:.7rem;color:var(--gray-400);"></i> <?= clean($bk['tujuan']) ?>
            </div>
            <div class="bk-mini-meta">
              <?= date('d M Y', strtotime($bk['tanggal_berangkat'])) ?> · <?= substr($bk['jam_berangkat'],0,5) ?> ·
              <?= clean($bk['kode_booking']) ?>
            </div>
          </div>
          <div class="bk-mini-status">
            <span class="bk-status-badge" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;">
              <?= $st['label'] ?>
            </span>
            <div style="font-size:.75rem;color:var(--blue);font-weight:700;margin-top:4px;">
              <?= formatRupiah($bk['total_harga']) ?>
            </div>
          </div>
        </div>
      </a>
    <?php endforeach; ?>

    <?php else: ?>
    <!-- Belum ada booking -->
    <div style="text-align:center;padding:40px 20px;background:var(--white);border-radius:16px;border:1.5px dashed var(--gray-200);">
      <div style="font-size:3rem;margin-bottom:12px;">🎫</div>
      <div style="font-size:1rem;font-weight:800;color:var(--navy);margin-bottom:6px;">Belum Ada Perjalanan</div>
      <p style="font-size:.85rem;color:var(--gray-400);margin-bottom:16px;">Yuk, pesan tiket pertamamu sekarang!</p>
      <a href="<?= APP_URL ?>/pages/search.php?jenis=1"
         style="display:inline-flex;align-items:center;gap:6px;background:var(--blue);color:#fff;
                padding:10px 24px;border-radius:10px;font-weight:700;text-decoration:none;font-size:.88rem;">
        <i class="bi bi-search"></i> Cari Tiket Sekarang
      </a>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php else: ?>
<!-- =========================================================
     HERO SECTION — GUEST (BELUM LOGIN)
========================================================= -->
<section class="tg-hero">
  <div class="container">
    <div class="row align-items-center gy-5">

      <!-- Teks kiri -->
      <div class="col-lg-5 pb-5">
        <div class="tg-fade-in">
          <span class="badge mb-3"
            style="background:rgba(96,165,250,.15);color:#93C5FD;border:1px solid rgba(96,165,250,.25);
                   font-size:.78rem;font-weight:600;padding:6px 14px;border-radius:50px;letter-spacing:.5px;">
            ✈ Platform Tiket #1 Indonesia
          </span>
          <h1 class="tg-hero-title">
            Perjalanan Impian<br>
            <span>Dimulai di Sini</span>
          </h1>
          <p class="tg-hero-sub">
            Pesan tiket pesawat, kereta, bus, kapal & travel<br>
            dengan harga terbaik — cepat, mudah, terpercaya.
          </p>
          <div class="d-flex gap-3 flex-wrap mt-4">
            <a href="<?= APP_URL ?>/pages/register.php" class="tg-btn-primary" style="font-size:.95rem;padding:12px 28px;">
              <i class="bi bi-person-plus"></i> Daftar Gratis
            </a>
            <a href="<?= APP_URL ?>/pages/login.php" class="tg-btn-outline" style="font-size:.95rem;padding:12px 28px;color:#fff;border-color:rgba(255,255,255,.3);">
              <i class="bi bi-box-arrow-in-right"></i> Masuk
            </a>
          </div>
          <div class="tg-hero-stats mt-4">
            <div class="tg-stat-item"><div class="tg-stat-num">2.4 Jt+</div><div class="tg-stat-label">Pengguna</div></div>
            <div class="tg-stat-item"><div class="tg-stat-num">850+</div><div class="tg-stat-label">Rute Aktif</div></div>
            <div class="tg-stat-item"><div class="tg-stat-num">4.8 ★</div><div class="tg-stat-label">Rating</div></div>
            <div class="tg-stat-item"><div class="tg-stat-num">24/7</div><div class="tg-stat-label">Support</div></div>
          </div>
        </div>
      </div>

      <!-- Visual kanan (ganti kotak search) -->
      <div class="col-lg-7 tg-fade-in" style="animation-delay:.15s;">
        <div class="dest-visual">
          <div class="dest-bg"></div>
          <div class="dest-overlay"></div>

          <!-- Clouds -->
          <div class="cloud" style="width:200px;height:60px;top:15%;left:5%;"></div>
          <div class="cloud" style="width:140px;height:45px;top:30%;right:15%;"></div>
          <div class="cloud" style="width:100px;height:35px;top:50%;left:20%;"></div>

          <!-- Plane animation -->
          <div class="plane-anim">✈️</div>

          <!-- Float card: rute populer -->
          <div class="dest-float-card">
            <div class="dest-float-title">🔥 Rute Populer Hari Ini</div>
            <div class="dest-route-item">
              <i class="bi bi-airplane" style="color:#60A5FA;font-size:.8rem;"></i>
              JKT → DPS
              <span class="price">ab Rp 399rb</span>
            </div>
            <div class="dest-route-item">
              <i class="bi bi-train-front" style="color:#34D399;font-size:.8rem;"></i>
              JKT → YOG
              <span class="price">ab Rp 180rb</span>
            </div>
            <div class="dest-route-item">
              <i class="bi bi-bus-front" style="color:#FBBF24;font-size:.8rem;"></i>
              JKT → BDO
              <span class="price">ab Rp 75rb</span>
            </div>
            <div class="dest-route-item">
              <i class="bi bi-water" style="color:#F87171;font-size:.8rem;"></i>
              JKT → MKS
              <span class="price">ab Rp 450rb</span>
            </div>
          </div>

          <!-- Bottom content -->
          <div class="dest-content">
            <div class="dest-badge">
              <i class="bi bi-lightning-charge-fill"></i> Promo Aktif
            </div>
            <div class="dest-title">850+ Rute Tersedia<br>ke Seluruh Indonesia</div>
            <div class="dest-sub">Pesawat, kereta, bus, kapal & travel — semua ada di sini</div>
            <div class="dest-stats">
              <div>
                <div class="dest-stat-num">50%</div>
                <div class="dest-stat-label">Diskon Maks</div>
              </div>
              <div>
                <div class="dest-stat-num">10+</div>
                <div class="dest-stat-label">Kota Tujuan</div>
              </div>
              <div>
                <div class="dest-stat-num">11+</div>
                <div class="dest-stat-label">Operator</div>
              </div>
            </div>
            <div class="promo-chips">
              <div class="promo-chip"><span class="disc">50%</span> Flash Sale Pesawat</div>
              <div class="promo-chip"><span class="disc">30%</span> Kereta Eksekutif</div>
              <div class="promo-chip"><span class="disc">20%</span> Bus Malam</div>
            </div>
            <div class="mt-4">
              <a href="<?= APP_URL ?>/pages/login.php"
                 style="display:inline-flex;align-items:center;gap:8px;
                        background:#2563EB;color:#fff;padding:12px 24px;
                        border-radius:10px;font-weight:700;font-size:.9rem;
                        text-decoration:none;transition:.2s;"
                 onmouseover="this.style.background='#1D4ED8'"
                 onmouseout="this.style.background='#2563EB'">
                <i class="bi bi-search"></i> Masuk & Cari Tiket
              </a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!isLogin()): ?>
<!-- =========================================================
     SECTION: JENIS TRANSPORTASI (hanya guest)
========================================================= -->
<section class="tg-section bg-white">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="tg-section-title">Pilih Transportasi</h2>
      <p class="tg-section-sub">Dari udara, darat, hingga laut — semua tersedia di TravelGo</p>
    </div>
    <div class="row g-4">
      <?php
        $transportData = [
          ['icon'=>'airplane',    'nama'=>'Pesawat', 'desc'=>'Terbang ke seluruh Indonesia & mancanegara dengan maskapai terpercaya.','harga'=>'350.000','jenis'=>1],
          ['icon'=>'train-front', 'nama'=>'Kereta',  'desc'=>'Perjalanan nyaman lintas kota dengan KAI — kelas ekonomi, bisnis & eksekutif.','harga'=>'80.000','jenis'=>2],
          ['icon'=>'bus-front',   'nama'=>'Bus',     'desc'=>'Bus AKAP & DAMRI antar kota antar provinsi dengan tarif terjangkau.','harga'=>'50.000','jenis'=>3],
          ['icon'=>'water',       'nama'=>'Kapal',   'desc'=>'Layari kepulauan Indonesia bersama PELNI dan kapal feri reguler.','harga'=>'200.000','jenis'=>4],
          ['icon'=>'car-front',   'nama'=>'Travel',  'desc'=>'Door-to-door travel antar kota dengan kendaraan nyaman & tepat waktu.','harga'=>'75.000','jenis'=>5],
        ];
      ?>
      <?php foreach ($transportData as $t): ?>
        <div class="col-6 col-md-4 col-lg">
          <a href="<?= APP_URL ?>/pages/login.php" class="text-decoration-none">
            <div class="tg-transport-card">
              <div class="tg-transport-icon"><i class="bi bi-<?= $t['icon'] ?>"></i></div>
              <h5><?= $t['nama'] ?></h5>
              <p><?= $t['desc'] ?></p>
              <div class="tg-price-tag">Mulai <span>Rp <?= $t['harga'] ?></span></div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- SECTION: PROMO -->
<section class="tg-section" style="background:var(--gray-100)">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
      <div>
        <h2 class="tg-section-title">Promo Spesial</h2>
        <p class="tg-section-sub">Hemat lebih banyak dengan promo eksklusif hari ini</p>
      </div>
      <a href="<?= APP_URL ?>/pages/login.php" class="tg-btn-primary">Lihat Semua <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="tg-promo-card" style="height:240px;">
          <div class="tg-promo-bg" style="background:linear-gradient(135deg,#0B1426 0%,#1E3260 50%,#1a3a7a 100%);"></div>
          <div class="tg-promo-overlay"></div>
          <div class="tg-promo-content">
            <span class="tg-promo-badge">Flash Sale 50%</span>
            <div class="tg-promo-title">Tiket Pesawat Jakarta–Bali<br>Mulai Rp 399.000</div>
            <div class="mt-2" style="color:rgba(255,255,255,.70);font-size:.82rem;">
              Berlaku hingga 30 Juni 2026 · Kode: <strong style="color:#FCD34D">TGFLASH50</strong>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="row g-3 h-100">
          <?php foreach ([['label'=>'Diskon 30%','title'=>'Kereta Eksekutif Jakarta–Yogyakarta','color'=>'#132040'],['label'=>'Cashback 20%','title'=>'Bus Malam Surabaya–Semarang','color'=>'#1a2f5e']] as $p): ?>
            <div class="col-12">
              <div class="tg-promo-card" style="height:104px;">
                <div class="tg-promo-bg" style="background:<?= $p['color'] ?>;"></div>
                <div class="tg-promo-overlay"></div>
                <div class="tg-promo-content">
                  <span class="tg-promo-badge"><?= $p['label'] ?></span>
                  <div class="tg-promo-title"><?= $p['title'] ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- SECTION: CARA PESAN -->
<section class="tg-section bg-white">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="tg-section-title">Cara Pesan Tiket</h2>
      <p class="tg-section-sub">Mudah, cepat, dan aman dalam 4 langkah</p>
    </div>
    <div class="row g-4">
      <?php foreach ([
        ['num'=>'1','icon'=>'search','title'=>'Cari Tiket','desc'=>'Pilih jenis transportasi, isi asal tujuan, tanggal, dan jumlah penumpang.'],
        ['num'=>'2','icon'=>'list-ul','title'=>'Pilih Jadwal','desc'=>'Bandingkan jadwal, harga, dan fasilitas. Pilih yang paling sesuai.'],
        ['num'=>'3','icon'=>'person-lines-fill','title'=>'Isi Data','desc'=>'Masukkan data penumpang dan pilih nomor kursi.'],
        ['num'=>'4','icon'=>'credit-card','title'=>'Bayar & Selesai','desc'=>'Bayar dengan metode favorit. E-tiket langsung dikirim ke email.'],
      ] as $s): ?>
        <div class="col-6 col-md-3">
          <div class="tg-how-step">
            <div class="tg-step-num"><?= $s['num'] ?></div>
            <h5><?= $s['title'] ?></h5>
            <p><?= $s['desc'] ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- SECTION: KENAPA TRAVELGO -->
<section class="tg-section" style="background:var(--navy)">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="tg-section-title" style="color:#fff">Kenapa Pilih TravelGo?</h2>
      <p style="color:rgba(255,255,255,.60)">Jutaan pengguna mempercayai TravelGo untuk perjalanan mereka</p>
    </div>
    <div class="row g-4">
      <?php foreach ([
        ['icon'=>'shield-check','title'=>'Aman & Terpercaya','desc'=>'Transaksi diproteksi enkripsi SSL. Data kamu aman bersama kami.'],
        ['icon'=>'lightning-charge','title'=>'Pesan Super Cepat','desc'=>'Proses booking kurang dari 3 menit. E-tiket langsung ke email.'],
        ['icon'=>'tag','title'=>'Harga Terbaik','desc'=>'Garansi harga terbaik. Temukan lebih murah? Kami kembalikan selisihnya.'],
        ['icon'=>'headset','title'=>'Support 24/7','desc'=>'Tim kami siap membantu kapan saja via live chat, telepon, atau email.'],
      ] as $f): ?>
        <div class="col-6 col-md-3">
          <div class="text-center p-3">
            <div class="mx-auto mb-3" style="width:56px;height:56px;background:rgba(255,255,255,.08);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#60A5FA;">
              <i class="bi bi-<?= $f['icon'] ?>"></i>
            </div>
            <h5 style="color:#fff;font-size:.95rem;font-weight:700;margin-bottom:8px;"><?= $f['title'] ?></h5>
            <p style="color:rgba(255,255,255,.55);font-size:.84rem;line-height:1.6;"><?= $f['desc'] ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>