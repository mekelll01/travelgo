<?php
// ============================================================
//  TravelGo — Dashboard User (pages/dashboard.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle  = 'Dashboard';
$activeMenu = '';

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['nama'] ?? 'Pengguna';

$kotaResult  = dbQuery($conn, "SELECT id, nama, kode FROM kota ORDER BY nama ASC");
$kotaList    = $kotaResult ? $kotaResult->fetch_all(MYSQLI_ASSOC) : [];

$jenisResult = dbQuery($conn, "SELECT * FROM jenis_transportasi ORDER BY id ASC");
$jenisList   = $jenisResult ? $jenisResult->fetch_all(MYSQLI_ASSOC) : [];

$riwayatResult = dbQuery($conn,
    "SELECT b.id, b.kode_booking, b.total_harga, b.status, b.created_at,
            j.tanggal_berangkat, j.jam_berangkat, j.jam_tiba, j.kelas,
            ka.nama AS kota_asal, kt.nama AS kota_tujuan,
            o.nama AS operator_nama, jt.nama AS jenis_nama
     FROM bookings b
     JOIN jadwal j ON b.jadwal_id = j.id
     JOIN rute r ON j.rute_id = r.id
     JOIN kota ka ON r.kota_asal_id = ka.id
     JOIN kota kt ON r.kota_tujuan_id = kt.id
     JOIN operator o ON r.operator_id = o.id
     JOIN jenis_transportasi jt ON r.jenis_transportasi_id = jt.id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC LIMIT 4",
    "i", [$userId]
);
$riwayatList = $riwayatResult ? $riwayatResult->fetch_all(MYSQLI_ASSOC) : [];

$statsRes = dbQuery($conn,
    "SELECT COUNT(*) as total, SUM(status='paid') as paid, SUM(status='pending') as pending,
            COALESCE(SUM(CASE WHEN status='paid' THEN total_harga END),0) as spend
     FROM bookings WHERE user_id = ?", "i", [$userId]
);
$stats = $statsRes ? $statsRes->fetch_assoc() : ['total'=>0,'paid'=>0,'pending'=>0,'spend'=>0];

// Jam untuk sapaan
$jam = (int)date('H');
$sapaan = $jam < 11 ? 'Selamat Pagi' : ($jam < 15 ? 'Selamat Siang' : ($jam < 18 ? 'Selamat Sore' : 'Selamat Malam'));
$namaDepan = explode(' ', $userName)[0];

// ---- Load promo dari MongoDB ----
$promoList = [];
try {
    $col = mongoCol('promos');
    if ($col) {
        $now    = new MongoDB\BSON\UTCDateTime();
        $cursor = $col->find(
            ['is_aktif' => true, 'berlaku_hingga' => ['$gte' => $now]],
            ['sort' => ['urutan' => 1], 'limit' => 4]
        );
        foreach ($cursor as $doc) {
            $promoList[] = [
                'judul'      => $doc['judul'] ?? '',
                'deskripsi'  => $doc['deskripsi'] ?? '',
                'kode'       => $doc['kode'] ?? '',
                'diskon_pct' => (int)($doc['diskon_pct'] ?? 0),
                'jenis'      => $doc['jenis'] ?? '',
                'warna'      => $doc['warna'] ?? '#1a3a7a',
                'berlaku'    => isset($doc['berlaku_hingga'])
                    ? date('d M Y', $doc['berlaku_hingga']->toDateTime()->getTimestamp())
                    : '',
            ];
        }
    }
} catch (Exception $e) {
    $promoList = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================================
   DASHBOARD PAGE
============================================================ */
.db-page { background: #F0F4F8; min-height: 100vh; }

/* ---- Top bar ---- */
.db-topbar {
  background: linear-gradient(135deg, #0B1426 0%, #1a3a7a 100%);
  padding: 28px 0 100px; position: relative; overflow: hidden;
}
.db-topbar::before {
  content:''; position:absolute; inset:0;
  background-image: radial-gradient(rgba(255,255,255,.04) 1px, transparent 1px);
  background-size: 24px 24px;
}
.db-topbar-blob1 {
  position:absolute; width:400px; height:400px;
  background:radial-gradient(circle, rgba(37,99,235,.25) 0%, transparent 70%);
  top:-100px; right:-50px; pointer-events:none;
}
.db-topbar-blob2 {
  position:absolute; width:250px; height:250px;
  background:radial-gradient(circle, rgba(96,165,250,.12) 0%, transparent 70%);
  bottom:-50px; left:5%; pointer-events:none;
}
.db-greeting { position:relative; z-index:1; }
.db-greeting-name {
  font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 4px;
}
.db-greeting-sub { font-size: .88rem; color: rgba(255,255,255,.5); margin: 0; }

/* Stats row */
.db-stats {
  display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;
  position: relative; z-index: 1;
}
.db-stat {
  background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.1);
  border-radius: 12px; padding: 12px 18px; flex: 1; min-width: 100px;
}
.db-stat-num   { font-size: 1.3rem; font-weight: 800; color: #fff; line-height: 1; }
.db-stat-label { font-size: .7rem; color: rgba(255,255,255,.4); margin-top: 3px; }

/* ---- Transport cards (pilih jenis) ---- */
.transport-section {
  margin-top: -72px; position: relative; z-index: 10;
  padding-bottom: 12px;
}
.transport-title {
  font-size: .78rem; font-weight: 800; color: rgba(255,255,255,.6);
  text-transform: uppercase; letter-spacing: .6px;
  margin-bottom: 12px;
}
.transport-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
}
.transport-card {
  border-radius: 16px; overflow: hidden; cursor: pointer;
  position: relative; height: 160px;
  transition: transform .25s, box-shadow .25s;
  border: 2px solid transparent;
}
.transport-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,.25); }
.transport-card.selected {
  border-color: #FCD34D;
  box-shadow: 0 0 0 3px rgba(252,211,77,.3), 0 16px 40px rgba(0,0,0,.25);
  transform: translateY(-4px);
}

/* Gradient backgrounds per jenis */
.transport-card[data-jenis="1"] .tc-bg { background: linear-gradient(160deg, #0c1e3c 0%, #1a4080 60%, #2563EB 100%); }
.transport-card[data-jenis="2"] .tc-bg { background: linear-gradient(160deg, #0a2010 0%, #1a5c2a 60%, #16A34A 100%); }
.transport-card[data-jenis="3"] .tc-bg { background: linear-gradient(160deg, #2d1a00 0%, #7c4a00 60%, #D97706 100%); }
.transport-card[data-jenis="4"] .tc-bg { background: linear-gradient(160deg, #001a2d 0%, #004a7a 60%, #0284C7 100%); }
.transport-card[data-jenis="5"] .tc-bg { background: linear-gradient(160deg, #1a0030 0%, #4a0080 60%, #7C3AED 100%); }

.tc-bg {
  position: absolute; inset: 0;
}
.tc-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,.6) 0%, rgba(0,0,0,.1) 60%);
}
.tc-content {
  position: absolute; inset: 0; z-index: 2;
  display: flex; flex-direction: column;
  justify-content: space-between; padding: 14px;
}
.tc-icon {
  width: 40px; height: 40px; border-radius: 10px;
  background: rgba(255,255,255,.15);
  backdrop-filter: blur(8px);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; color: #fff;
  transition: transform .2s;
}
.transport-card:hover .tc-icon { transform: scale(1.1); }
.transport-card.selected .tc-icon {
  background: rgba(252,211,77,.25);
  color: #FCD34D;
}
.tc-bottom {}
.tc-name {
  font-size: .9rem; font-weight: 800; color: #fff;
  margin-bottom: 2px; line-height: 1.2;
}
.tc-price {
  font-size: .7rem; color: rgba(255,255,255,.55); font-weight: 500;
}
.tc-selected-badge {
  position: absolute; top: 10px; right: 10px;
  background: #FCD34D; color: #1a1a1a;
  font-size: .65rem; font-weight: 800;
  padding: 2px 8px; border-radius: 50px;
  display: none; z-index: 3;
}
.transport-card.selected .tc-selected-badge { display: block; }

/* Dot grid pattern on card */
.tc-dots {
  position: absolute; inset: 0; z-index: 1;
  background-image: radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px);
  background-size: 16px 16px;
}

/* ---- Search form panel ---- */
.search-panel-wrapper {
  max-height: 0; overflow: hidden;
  transition: max-height .45s cubic-bezier(.4,0,.2,1), opacity .3s ease;
  opacity: 0;
}
.search-panel-wrapper.open {
  max-height: 600px; opacity: 1;
}
.search-panel-inner {
  background: var(--white);
  border-radius: 0 0 20px 20px;
  padding: 24px 28px 28px;
  border: 2px solid #FCD34D;
  border-top: none;
  box-shadow: 0 12px 40px rgba(0,0,0,.12);
}
.spanel-title {
  font-size: .82rem; font-weight: 800; color: var(--navy);
  text-transform: uppercase; letter-spacing: .5px;
  margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
}
.spanel-title .jenis-badge {
  background: var(--navy); color: #fff;
  font-size: .72rem; padding: 3px 10px; border-radius: 50px;
  font-weight: 700;
}

/* ---- Content grid ---- */
.db-content {
  padding: 20px 0 60px;
}
.db-content-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 20px;
  align-items: start;
}

/* ---- Riwayat ---- */
.section-title {
  font-size: .95rem; font-weight: 800; color: var(--navy);
  margin-bottom: 14px; display: flex; align-items: center;
  justify-content: space-between;
}
.section-title a { font-size: .78rem; color: var(--blue); font-weight: 600; text-decoration: none; }
.section-title a:hover { text-decoration: underline; }

.riwayat-card {
  background: var(--white); border: 1.5px solid var(--gray-200);
  border-radius: 14px; padding: 16px 18px;
  display: flex; align-items: center; gap: 14px;
  margin-bottom: 10px; transition: var(--transition);
  text-decoration: none;
}
.riwayat-card:hover { box-shadow: var(--shadow-md); border-color: var(--blue-light); transform: translateY(-1px); }
.rw-icon {
  width: 42px; height: 42px; border-radius: 10px;
  background: rgba(37,99,235,.08);
  display: flex; align-items: center; justify-content: center;
  color: var(--blue); font-size: 1.1rem; flex-shrink: 0;
}
.rw-route { font-size: .9rem; font-weight: 700; color: var(--navy); }
.rw-meta  { font-size: .75rem; color: var(--gray-400); margin-top: 2px; }
.rw-right { margin-left: auto; text-align: right; flex-shrink: 0; }
.rw-price { font-size: .9rem; font-weight: 800; color: var(--blue); }
.rw-status {
  display: inline-block; font-size: .7rem; font-weight: 700;
  padding: 2px 8px; border-radius: 50px; margin-top: 3px;
}

/* ---- Quick menu sidebar ---- */
.quick-menu-card {
  background: var(--white); border: 1.5px solid var(--gray-200);
  border-radius: 16px; padding: 18px 20px;
  position: sticky; top: 80px;
}
.qm-title {
  font-size: .82rem; font-weight: 800; color: var(--navy);
  margin-bottom: 12px; text-transform: uppercase; letter-spacing: .4px;
}
.qm-item {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px; border-radius: 10px;
  text-decoration: none; transition: var(--transition);
  margin-bottom: 4px;
}
.qm-item:hover { background: var(--gray-50); }
.qm-icon {
  width: 36px; height: 36px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: .95rem; flex-shrink: 0;
}
.qm-label { font-size: .85rem; font-weight: 700; color: var(--navy); }
.qm-sub   { font-size: .72rem; color: var(--gray-400); }

/* Pending alert */
.pending-alert {
  background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.25);
  border-radius: 12px; padding: 12px 16px; margin-bottom: 14px;
  font-size: .82rem; color: #92400E;
  display: flex; align-items: center; gap: 10px;
}
.pending-alert a { color: #D97706; font-weight: 700; }

/* Empty state */
.empty-booking {
  text-align: center; padding: 40px 20px;
  background: var(--white); border: 1.5px dashed var(--gray-200);
  border-radius: 14px;
}

@media(max-width:991px) {
  .transport-grid { grid-template-columns: repeat(3,1fr); }
  .db-content-grid { grid-template-columns: 1fr; }
  .quick-menu-card { position: static; }
}
@media(max-width:575px) {
  .transport-grid { grid-template-columns: repeat(2,1fr); }
  .transport-card { height: 130px; }
}
</style>

<div class="db-page">

  <!-- Top bar greeting -->
  <div class="db-topbar">
    <div class="db-topbar-blob1"></div>
    <div class="db-topbar-blob2"></div>
    <div class="container">
      <div class="row align-items-center gy-3">
        <div class="col-lg-7">
          <div class="db-greeting">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
              <div style="width:44px;height:44px;border-radius:50%;background:var(--blue);
                          display:flex;align-items:center;justify-content:center;
                          font-size:1.2rem;font-weight:800;color:#fff;
                          border:2px solid rgba(255,255,255,.2);flex-shrink:0;">
                <?= strtoupper(substr($namaDepan, 0, 1)) ?>
              </div>
              <div>
                <div style="font-size:.78rem;color:rgba(255,255,255,.45);font-weight:500;"><?= $sapaan ?> 👋</div>
                <div class="db-greeting-name"><?= clean($userName) ?></div>
              </div>
            </div>
            <p class="db-greeting-sub">Pilih jenis transportasi dan temukan tiket terbaik untukmu.</p>
            <div class="db-stats">
              <div class="db-stat">
                <div class="db-stat-num"><?= $stats['total'] ?></div>
                <div class="db-stat-label">Total Booking</div>
              </div>
              <div class="db-stat">
                <div class="db-stat-num"><?= $stats['paid'] ?></div>
                <div class="db-stat-label">Tiket Lunas</div>
              </div>
              <div class="db-stat">
                <div class="db-stat-num" style="font-size:.95rem;"><?= formatRupiah((float)$stats['spend']) ?></div>
                <div class="db-stat-label">Total Belanja</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Transport cards + search panel -->
  <div class="container transport-section">

    <div class="transport-title">Pilih Jenis Transportasi</div>

    <!-- Transport cards grid -->
    <div class="transport-grid" id="transportGrid">
      <?php
        $tcData = [
          1 => ['icon'=>'airplane',    'nama'=>'Pesawat', 'price'=>'ab Rp 350.000'],
          2 => ['icon'=>'train-front', 'nama'=>'Kereta',  'price'=>'ab Rp 80.000'],
          3 => ['icon'=>'bus-front',   'nama'=>'Bus',     'price'=>'ab Rp 50.000'],
          4 => ['icon'=>'water',       'nama'=>'Kapal',   'price'=>'ab Rp 200.000'],
          5 => ['icon'=>'car-front',   'nama'=>'Travel',  'price'=>'ab Rp 75.000'],
        ];
        foreach ($jenisList as $j):
          $tc = $tcData[$j['id']] ?? ['icon'=>'ticket','nama'=>$j['nama'],'price'=>''];
      ?>
        <div class="transport-card" data-jenis="<?= $j['id'] ?>"
             onclick="selectTransport(<?= $j['id'] ?>, '<?= clean($j['nama']) ?>')">
          <div class="tc-bg"></div>
          <div class="tc-dots"></div>
          <div class="tc-overlay"></div>
          <div class="tc-selected-badge">✓ Dipilih</div>
          <div class="tc-content">
            <div class="tc-icon">
              <i class="bi bi-<?= $tc['icon'] ?>"></i>
            </div>
            <div class="tc-bottom">
              <div class="tc-name"><?= clean($j['nama']) ?></div>
              <div class="tc-price"><?= $tc['price'] ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Search form panel (muncul setelah klik transport) -->
    <div class="search-panel-wrapper" id="searchPanelWrapper">
      <div class="search-panel-inner">
        <div class="spanel-title">
          <i class="bi bi-search" style="color:var(--blue);"></i>
          Cari Tiket
          <span class="jenis-badge" id="spJenisBadge">Pesawat</span>
        </div>

        <!-- Toggle sekali jalan / pulang pergi -->
        <div class="tg-trip-toggle mb-3">
          <button type="button" class="tg-trip-btn active" data-trip="sekali_jalan">Sekali Jalan</button>
          <button type="button" class="tg-trip-btn" data-trip="pulang_pergi">Pulang Pergi</button>
        </div>

        <form action="<?= APP_URL ?>/pages/search.php" method="GET" data-validate>
          <input type="hidden" name="jenis" id="sp_jenis" value="1">
          <input type="hidden" name="trip"  id="sp_trip"  value="sekali_jalan">

          <div class="tg-search-row">
            <!-- Asal -->
            <div class="tg-field">
              <label><i class="bi bi-geo-alt"></i> Dari</label>
              <select name="asal" id="sp_asal" required>
                <option value="">Pilih kota asal</option>
                <?php foreach ($kotaList as $k): ?>
                  <option value="<?= $k['id'] ?>"><?= clean($k['nama']) ?><?= $k['kode'] ? ' ('.$k['kode'].')' : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Swap -->
            <button type="button" class="tg-swap-btn" id="sp_swap">
              <i class="bi bi-arrow-left-right"></i>
            </button>

            <!-- Tujuan -->
            <div class="tg-field">
              <label><i class="bi bi-geo-alt-fill"></i> Ke</label>
              <select name="tujuan" id="sp_tujuan" required>
                <option value="">Pilih kota tujuan</option>
                <?php foreach ($kotaList as $k): ?>
                  <option value="<?= $k['id'] ?>"><?= clean($k['nama']) ?><?= $k['kode'] ? ' ('.$k['kode'].')' : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Tanggal -->
            <div class="tg-field">
              <label><i class="bi bi-calendar3"></i> Berangkat</label>
              <input type="date" name="tgl_berangkat" id="sp_tgl" required min="<?= date('Y-m-d') ?>">
            </div>

            <!-- Return date (pulang pergi) -->
            <div class="tg-field tg-return-field" id="sp_return_wrap" style="display:none;">
              <label><i class="bi bi-calendar3"></i> Kembali</label>
              <input type="date" name="tgl_kembali" id="sp_tgl_kembali">
            </div>
          </div>

          <!-- Row 2: penumpang & kelas -->
          <div class="row g-3 mt-1">
            <div class="col-sm-4">
              <div class="tg-field">
                <label><i class="bi bi-people"></i> Dewasa</label>
                <select name="dewasa">
                  <?php for($i=1;$i<=9;$i++): ?><option value="<?=$i?>"><?=$i?> Orang</option><?php endfor; ?>
                </select>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="tg-field">
                <label><i class="bi bi-person-hearts"></i> Anak (2–11 thn)</label>
                <select name="anak">
                  <?php for($i=0;$i<=6;$i++): ?><option value="<?=$i?>"><?=$i?> Anak</option><?php endfor; ?>
                </select>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="tg-field">
                <label><i class="bi bi-star"></i> Kelas</label>
                <select name="kelas">
                  <option value="">Semua Kelas</option>
                  <option>Ekonomi</option>
                  <option>Bisnis</option>
                  <option>Eksekutif</option>
                  <option>Reguler</option>
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

  <!-- Content: riwayat + quick menu -->
  <div class="container db-content">

    <!-- Alert pending bayar -->
    <?php if ($stats['pending'] > 0): ?>
      <div class="pending-alert">
        <i class="bi bi-clock-fill" style="color:var(--warning);font-size:1.1rem;flex-shrink:0;"></i>
        <div>
          Kamu punya <strong><?= $stats['pending'] ?> booking</strong> yang belum dibayar.
          <a href="<?= APP_URL ?>/pages/history.php?status=pending">Bayar sekarang →</a>
        </div>
      </div>
    <?php endif; ?>

    <div class="db-content-grid">

      <!-- Riwayat booking -->
      <div>
        <div class="section-title">
          <span><i class="bi bi-clock-history" style="color:var(--blue);"></i> Perjalanan Terakhir</span>
          <a href="<?= APP_URL ?>/pages/history.php">Lihat Semua <i class="bi bi-arrow-right"></i></a>
        </div>

        <?php if (empty($riwayatList)): ?>
          <div class="empty-booking">
            <div style="font-size:3rem;margin-bottom:12px;">🎫</div>
            <div style="font-weight:800;color:var(--navy);margin-bottom:6px;">Belum Ada Perjalanan</div>
            <p style="font-size:.85rem;color:var(--gray-400);">Pilih transportasi di atas dan mulai pesan tiket!</p>
          </div>
        <?php else: ?>
          <?php
            $stMap = [
              'paid'      => ['label'=>'Lunas',          'bg'=>'rgba(16,185,129,.1)', 'color'=>'#065F46'],
              'pending'   => ['label'=>'Menunggu Bayar', 'bg'=>'rgba(245,158,11,.1)', 'color'=>'#92400E'],
              'cancelled' => ['label'=>'Dibatalkan',     'bg'=>'rgba(239,68,68,.1)',  'color'=>'#991B1B'],
              'expired'   => ['label'=>'Kedaluwarsa',    'bg'=>'rgba(107,114,128,.1)','color'=>'#374151'],
            ];
            $jiMap = ['Pesawat'=>'airplane','Kereta'=>'train-front','Bus'=>'bus-front','Kapal'=>'water','Travel'=>'car-front'];
            foreach ($riwayatList as $r):
              $st   = $stMap[$r['status']] ?? $stMap['pending'];
              $icon = $jiMap[$r['jenis_nama']] ?? 'ticket-detailed';
              $link = $r['status']==='paid'
                    ? APP_URL.'/pages/ticket.php?booking_id='.$r['id']
                    : APP_URL.'/pages/payment.php?booking_id='.$r['id'];
          ?>
            <a href="<?= $link ?>" class="riwayat-card">
              <div class="rw-icon"><i class="bi bi-<?= $icon ?>"></i></div>
              <div style="flex:1;min-width:0;">
                <div class="rw-route">
                  <?= clean($r['kota_asal']) ?>
                  <i class="bi bi-arrow-right" style="font-size:.7rem;color:var(--gray-400);"></i>
                  <?= clean($r['kota_tujuan']) ?>
                </div>
                <div class="rw-meta">
                  <?= date('d M Y', strtotime($r['tanggal_berangkat'])) ?> ·
                  <?= substr($r['jam_berangkat'],0,5) ?> –
                  <?= substr($r['jam_tiba'],0,5) ?> ·
                  <?= clean($r['operator_nama']) ?>
                </div>
                <div style="font-size:.72rem;color:var(--gray-400);margin-top:1px;">
                  <?= clean($r['kode_booking']) ?> · <?= clean($r['kelas']) ?>
                </div>
              </div>
              <div class="rw-right">
                <div class="rw-price"><?= formatRupiah($r['total_harga']) ?></div>
                <span class="rw-status" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;">
                  <?= $st['label'] ?>
                </span>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Quick menu sidebar -->
      <div>
        <div class="quick-menu-card">
          <div class="qm-title">Menu Cepat</div>

          <a href="<?= APP_URL ?>/pages/history.php" class="qm-item">
            <div class="qm-icon" style="background:rgba(37,99,235,.1);color:var(--blue);">
              <i class="bi bi-clock-history"></i>
            </div>
            <div>
              <div class="qm-label">Riwayat Booking</div>
              <div class="qm-sub">Semua perjalanan kamu</div>
            </div>
          </a>

          <a href="<?= APP_URL ?>/pages/profile.php" class="qm-item">
            <div class="qm-icon" style="background:rgba(16,185,129,.1);color:var(--success);">
              <i class="bi bi-person-circle"></i>
            </div>
            <div>
              <div class="qm-label">Profil Saya</div>
              <div class="qm-sub">Edit data & password</div>
            </div>
          </a>

          <?php if ($stats['pending'] > 0): ?>
          <a href="<?= APP_URL ?>/pages/history.php?status=pending" class="qm-item">
            <div class="qm-icon" style="background:rgba(245,158,11,.1);color:var(--warning);">
              <i class="bi bi-credit-card"></i>
            </div>
            <div>
              <div class="qm-label">Bayar Sekarang</div>
              <div class="qm-sub"><?= $stats['pending'] ?> booking menunggu</div>
            </div>
          </a>
          <?php endif; ?>

          <a href="<?= APP_URL ?>/api/auth.php?action=logout" class="qm-item"
             onclick="return confirm('Yakin mau logout?')">
            <div class="qm-icon" style="background:rgba(239,68,68,.1);color:var(--danger);">
              <i class="bi bi-box-arrow-right"></i>
            </div>
            <div>
              <div class="qm-label">Logout</div>
              <div class="qm-sub">Keluar dari akun</div>
            </div>
          </a>

        </div>
      </div>

    </div>
  </div>

</div>

<!-- =========================================================
     SECTION PROMO (dari MongoDB)
========================================================= -->
<?php if (!empty($promoList)): ?>
<div style="background:var(--white);padding:32px 0 48px;border-top:1px solid var(--gray-100);">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
      <div>
        <h2 style="font-size:1.1rem;font-weight:800;color:var(--navy);margin:0 0 4px;">
          🎉 Promo Spesial Untukmu
        </h2>
        <p style="font-size:.82rem;color:var(--gray-400);margin:0;">
          Gunakan kode promo saat pembayaran untuk hemat lebih banyak
        </p>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
      <?php foreach ($promoList as $p): ?>
        <div style="background:linear-gradient(135deg, <?= clean($p['warna']) ?> 0%, <?= clean($p['warna']) ?>cc 100%);
                    border-radius:16px;padding:20px;position:relative;overflow:hidden;cursor:pointer;"
             onclick="copyKodePromo('<?= clean($p['kode']) ?>', this)">

          <!-- Dot pattern -->
          <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.06) 1px,transparent 1px);background-size:16px 16px;pointer-events:none;"></div>

          <!-- Diskon badge -->
          <div style="position:absolute;top:14px;right:14px;background:rgba(252,211,77,.2);
                      border:1px solid rgba(252,211,77,.4);color:#FCD34D;
                      font-size:.75rem;font-weight:800;padding:3px 10px;border-radius:50px;">
            <?= $p['diskon_pct'] ?>% OFF
          </div>

          <div style="position:relative;z-index:1;">
            <div style="font-size:1rem;font-weight:800;color:#fff;margin-bottom:6px;padding-right:60px;">
              <?= clean($p['judul']) ?>
            </div>
            <div style="font-size:.78rem;color:rgba(255,255,255,.65);margin-bottom:14px;line-height:1.5;">
              <?= clean($p['deskripsi']) ?>
            </div>

            <!-- Kode promo box -->
            <div style="background:rgba(255,255,255,.12);border:1.5px dashed rgba(255,255,255,.3);
                        border-radius:10px;padding:10px 14px;
                        display:flex;align-items:center;justify-content:space-between;">
              <div>
                <div style="font-size:.65rem;color:rgba(255,255,255,.5);font-weight:600;margin-bottom:2px;">KODE PROMO</div>
                <div style="font-size:1.1rem;font-weight:800;color:#fff;letter-spacing:2px;font-family:monospace;">
                  <?= clean($p['kode']) ?>
                </div>
              </div>
              <div style="background:rgba(255,255,255,.15);border-radius:8px;padding:6px 10px;
                          font-size:.72rem;font-weight:700;color:#fff;">
                <i class="bi bi-clipboard"></i> Salin
              </div>
            </div>

            <div style="font-size:.68rem;color:rgba(255,255,255,.4);margin-top:8px;">
              Berlaku hingga <?= clean($p['berlaku']) ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function copyKodePromo(kode, el) {
  navigator.clipboard.writeText(kode).then(() => {
    const btn = el.querySelector('div[style*="Salin"]');
    if (btn) {
      const orig = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-check-lg"></i> Disalin!';
      btn.style.background = 'rgba(52,211,153,.3)';
      setTimeout(() => {
        btn.innerHTML = orig;
        btn.style.background = 'rgba(255,255,255,.15)';
      }, 2000);
    }
  }).catch(() => {
    alert('Kode promo: ' + kode);
  });
}
</script>

<script>
// ---- Pilih transportasi & munculkan form ----
let selectedJenis = null;

function selectTransport(jenisId, jenisNama) {
  const cards  = document.querySelectorAll('.transport-card');
  const panel  = document.getElementById('searchPanelWrapper');
  const badge  = document.getElementById('spJenisBadge');
  const input  = document.getElementById('sp_jenis');

  // Highlight card yang dipilih
  cards.forEach(c => c.classList.remove('selected'));
  const clicked = document.querySelector(`.transport-card[data-jenis="${jenisId}"]`);
  if (clicked) clicked.classList.add('selected');

  // Update badge & hidden input
  badge.textContent = jenisNama;
  input.value = jenisId;

  // Buka atau tutup panel (toggle kalau klik sama)
  if (selectedJenis === jenisId && panel.classList.contains('open')) {
    panel.classList.remove('open');
    selectedJenis = null;
    clicked.classList.remove('selected');
  } else {
    panel.classList.add('open');
    selectedJenis = jenisId;
    // Scroll smooth ke form
    setTimeout(() => {
      panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
  }
}

// ---- Toggle sekali jalan / pulang pergi ----
document.querySelectorAll('.tg-trip-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tg-trip-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('sp_trip').value = btn.dataset.trip;
    const returnWrap = document.getElementById('sp_return_wrap');
    if (returnWrap) {
      returnWrap.style.display = btn.dataset.trip === 'pulang_pergi' ? '' : 'none';
    }
  });
});

// ---- Swap asal & tujuan ----
document.getElementById('sp_swap').addEventListener('click', () => {
  const asal   = document.getElementById('sp_asal');
  const tujuan = document.getElementById('sp_tujuan');
  const tmp    = asal.value;
  asal.value   = tujuan.value;
  tujuan.value = tmp;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>