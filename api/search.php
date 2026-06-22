<?php
// ============================================================
//  TravelGo — Halaman Hasil Pencarian (pages/search.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Cari Tiket';
$activeMenu = '';

// ---- Ambil parameter dari form ----
$jenis_id       = isset($_GET['jenis'])         ? (int)$_GET['jenis']         : 1;
$asal_id        = isset($_GET['asal'])          ? (int)$_GET['asal']          : 0;
$tujuan_id      = isset($_GET['tujuan'])        ? (int)$_GET['tujuan']        : 0;
$tgl_berangkat  = isset($_GET['tgl_berangkat']) ? clean($_GET['tgl_berangkat']): '';
$tgl_kembali    = isset($_GET['tgl_kembali'])   ? clean($_GET['tgl_kembali']) : '';
$jml_dewasa     = isset($_GET['dewasa'])        ? max(1,(int)$_GET['dewasa']) : 1;
$jml_anak       = isset($_GET['anak'])          ? max(0,(int)$_GET['anak'])   : 0;
$kelas_filter   = isset($_GET['kelas'])         ? clean($_GET['kelas'])       : '';
$trip_type      = isset($_GET['trip'])          ? clean($_GET['trip'])        : 'sekali_jalan';
$sort           = isset($_GET['sort'])          ? clean($_GET['sort'])        : 'harga_asc';

// ---- Validasi tanggal ----
$tgl_valid = !empty($tgl_berangkat) && strtotime($tgl_berangkat) !== false;

// ---- Ambil data master ----
$kotaList = dbQuery($conn, "SELECT id, nama, kode FROM kota ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$jenisList = dbQuery($conn, "SELECT * FROM jenis_transportasi ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

// Nama kota asal & tujuan
$kotaAsal   = '';
$kotaTujuan = '';
foreach ($kotaList as $k) {
    if ($k['id'] == $asal_id)   $kotaAsal   = $k['nama'];
    if ($k['id'] == $tujuan_id) $kotaTujuan = $k['nama'];
}

// Nama jenis transportasi aktif
$jenisNama = '';
$jenisIkon = ['plane','train-front','bus-front','water','car-front'];
foreach ($jenisList as $j) {
    if ($j['id'] == $jenis_id) $jenisNama = $j['nama'];
}

// ---- Query hasil pencarian ----
$results = [];
$totalResults = 0;

if ($asal_id && $tujuan_id && $tgl_valid) {
    // Sorting
    $orderBy = match($sort) {
        'harga_asc'   => 'j.harga_dewasa ASC',
        'harga_desc'  => 'j.harga_dewasa DESC',
        'waktu_asc'   => 'j.jam_berangkat ASC',
        'waktu_desc'  => 'j.jam_berangkat DESC',
        'durasi_asc'  => 'r.durasi_menit ASC',
        default       => 'j.harga_dewasa ASC'
    };

    $kelasWhere = $kelas_filter ? "AND j.kelas = ?" : "";
    $sql = "
        SELECT
            j.id, j.kode_jadwal, j.tanggal_berangkat,
            j.jam_berangkat, j.jam_tiba,
            j.harga_dewasa, j.harga_anak,
            j.kapasitas, j.kursi_terisi,
            j.kelas, j.fasilitas,
            r.durasi_menit,
            o.nama AS operator_nama, o.kode AS operator_kode, o.logo AS operator_logo,
            ka.nama AS kota_asal_nama, ka.kode AS kota_asal_kode,
            kt.nama AS kota_tujuan_nama, kt.kode AS kota_tujuan_kode
        FROM jadwal j
        JOIN rute r        ON j.rute_id = r.id
        JOIN operator o    ON r.operator_id = o.id
        JOIN kota ka       ON r.kota_asal_id = ka.id
        JOIN kota kt       ON r.kota_tujuan_id = kt.id
        WHERE r.kota_asal_id = ?
          AND r.kota_tujuan_id = ?
          AND r.jenis_transportasi_id = ?
          AND j.tanggal_berangkat = ?
          AND j.is_aktif = 1
          AND r.is_aktif = 1
          AND (j.kapasitas - j.kursi_terisi) >= ?
          $kelasWhere
        ORDER BY $orderBy
    ";

    $minPenumpang = $jml_dewasa + $jml_anak;
    if ($kelas_filter) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iiisis', $asal_id, $tujuan_id, $jenis_id, $tgl_berangkat, $minPenumpang, $kelas_filter);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iiisi', $asal_id, $tujuan_id, $jenis_id, $tgl_berangkat, $minPenumpang);
    }
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $totalResults = count($results);
}

// ---- Helper: format durasi ----
function formatDurasi(int $menit): string {
    $j = intdiv($menit, 60);
    $m = $menit % 60;
    return $j > 0 ? "{$j}j " . ($m > 0 ? "{$m}m" : '') : "{$m}m";
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ---- Search page layout ---- */
.search-header {
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
  padding: 28px 0 0;
  position: relative;
  overflow: hidden;
}
.search-header::before {
  content:'';
  position:absolute; inset:0;
  background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
  background-size: 24px 24px;
}

/* Mini search form di atas */
.search-bar-mini {
  background: var(--white);
  border-radius: var(--radius-xl);
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  box-shadow: var(--shadow-lg);
  position: relative;
  z-index: 2;
  margin-bottom: -20px;
}
.search-bar-mini .mini-field {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 14px;
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-md);
  font-size: .85rem; font-weight: 500;
  color: var(--gray-800); cursor: pointer;
  transition: var(--transition); flex: 1; min-width: 120px;
}
.search-bar-mini .mini-field:hover { border-color: var(--blue); }
.search-bar-mini .mini-field .bi { color: var(--blue); }
.search-bar-mini .mini-sep {
  color: var(--gray-300); font-size: 1.2rem; flex-shrink: 0;
}
.search-bar-mini .mini-search-btn {
  background: var(--blue); color: #fff;
  border: none; border-radius: var(--radius-md);
  padding: 10px 20px; font-size: .88rem; font-weight: 700;
  cursor: pointer; transition: var(--transition);
  display: flex; align-items: center; gap: 6px; white-space: nowrap;
}
.search-bar-mini .mini-search-btn:hover {
  background: var(--blue-light); box-shadow: var(--shadow-blue);
}

/* Result info bar */
.result-info-bar {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 12px;
  padding: 20px 0 12px;
}
.result-route {
  font-size: 1.1rem; font-weight: 800;
  color: var(--navy); display: flex; align-items: center; gap: 10px;
}
.result-route .bi { color: var(--blue); }
.result-count {
  font-size: .85rem; color: var(--gray-400);
  background: var(--gray-100); padding: 4px 12px;
  border-radius: 50px; font-weight: 600;
}

/* Sort & filter bar */
.filter-sort-bar {
  display: flex; align-items: center; gap: 8px;
  flex-wrap: wrap; margin-bottom: 20px;
}
.sort-btn {
  padding: 7px 14px; border: 1.5px solid var(--gray-200);
  border-radius: 50px; font-size: .82rem; font-weight: 600;
  color: var(--gray-600); background: var(--white);
  cursor: pointer; transition: var(--transition);
  display: flex; align-items: center; gap: 5px;
}
.sort-btn:hover { border-color: var(--blue); color: var(--blue); }
.sort-btn.active {
  background: var(--blue); color: #fff;
  border-color: var(--blue);
}

/* Kelas filter chips */
.kelas-chip {
  padding: 6px 14px; border: 1.5px solid var(--gray-200);
  border-radius: 50px; font-size: .82rem; font-weight: 600;
  color: var(--gray-600); background: var(--white);
  cursor: pointer; transition: var(--transition); text-decoration: none;
}
.kelas-chip:hover { border-color: var(--blue); color: var(--blue); }
.kelas-chip.active {
  background: var(--navy); color: #fff; border-color: var(--navy);
}

/* Result card baru dengan gambar */
.result-card {
  background: var(--white);
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-lg);
  margin-bottom: 14px; overflow: hidden;
  transition: var(--transition);
  animation: slideIn .35s ease forwards; opacity: 0;
  position: relative;
}
@keyframes slideIn {
  from { opacity:0; transform:translateY(10px); }
  to   { opacity:1; transform:translateY(0); }
}
.result-card:nth-child(1){animation-delay:.05s}.result-card:nth-child(2){animation-delay:.10s}
.result-card:nth-child(3){animation-delay:.15s}.result-card:nth-child(4){animation-delay:.20s}
.result-card:nth-child(5){animation-delay:.25s}
.result-card:hover { border-color:var(--blue-light); box-shadow:var(--shadow-md); transform:translateY(-2px); }
.result-card.cheapest { border-color:rgba(16,185,129,.35); }

.cheapest-badge {
  display:inline-flex; align-items:center; gap:4px;
  background:var(--success); color:#fff;
  font-size:.68rem; font-weight:800;
  padding:3px 10px; border-radius:0 0 8px 0;
  position:absolute; top:0; left:0; z-index:5; letter-spacing:.3px;
}

/* Image strip */
.rc-image-strip {
  position:relative; height:110px; overflow:hidden;
  background:linear-gradient(135deg, #0B1426 0%, #1a3a7a 100%);
}
.rc-image-strip img { width:100%; height:100%; object-fit:cover; opacity:.65; transition:.3s; }
.result-card:hover .rc-image-strip img { opacity:.8; }
.rc-img-overlay {
  position:absolute; inset:0;
  background:linear-gradient(to right, rgba(0,0,0,.65) 0%, rgba(0,0,0,.1) 55%, transparent 100%);
}
.rc-img-fallback { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:3.2rem; }
.rc-img-info { position:absolute; inset:0; z-index:2; padding:12px 18px; display:flex; align-items:flex-end; justify-content:space-between; }
.rc-op-name { font-size:.95rem; font-weight:800; color:#fff; }
.rc-op-kode { font-size:.7rem; color:rgba(255,255,255,.55); }
.rc-kelas-pill {
  padding:4px 12px; border-radius:50px; font-size:.75rem; font-weight:800;
  border:1.5px solid rgba(255,255,255,.3); color:#fff;
  backdrop-filter:blur(8px); background:rgba(255,255,255,.12);
}
.rc-kelas-pill.ekonomi  { border-color:rgba(96,165,250,.6);  color:#93C5FD; }
.rc-kelas-pill.bisnis   { border-color:rgba(251,191,36,.6);  color:#FDE68A; }
.rc-kelas-pill.eksekutif{ border-color:rgba(52,211,153,.6);  color:#6EE7B7; }
.rc-kelas-pill.reguler  { border-color:rgba(167,139,250,.6); color:#C4B5FD; }

/* Card body */
.rc-body { padding:14px 18px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.rc-times { display:flex; align-items:center; gap:10px; flex:1; min-width:180px; }
.rc-time-block { text-align:center; }
.rc-time { font-size:1.35rem; font-weight:800; color:var(--navy); line-height:1; }
.rc-city { font-size:.7rem; color:var(--gray-400); margin-top:2px; font-weight:600; }
.rc-arrow { flex:1; display:flex; flex-direction:column; align-items:center; gap:3px; }
.rc-arrow-line { width:100%; height:1px; background:var(--gray-200); position:relative; }
.rc-arrow-line::after { content:'›'; position:absolute; right:-5px; top:50%; transform:translateY(-50%); color:var(--gray-300); font-size:.9rem; }
.rc-durasi { font-size:.7rem; color:var(--gray-400); font-weight:600; }
.rc-langsung { font-size:.65rem; font-weight:700; color:var(--success); background:rgba(16,185,129,.1); padding:1px 7px; border-radius:50px; }

/* Fasilitas */
.rc-fasilitas { display:flex; gap:5px; flex-wrap:wrap; margin-top:6px; }
.rc-fas-chip { font-size:.65rem; font-weight:600; color:var(--gray-500); background:var(--gray-50); border:1px solid var(--gray-200); padding:2px 7px; border-radius:4px; display:flex; align-items:center; gap:3px; }

/* Kanan: harga */
.rc-right { text-align:right; flex-shrink:0; min-width:140px; }
.rc-seat { font-size:.72rem; font-weight:600; margin-bottom:6px; display:flex; align-items:center; justify-content:flex-end; gap:4px; }
.rc-seat.low { color:var(--danger); }.rc-seat.ok { color:var(--gray-400); }
.rc-price-label { font-size:.68rem; color:var(--gray-400); }
.rc-price { font-size:1.25rem; font-weight:800; color:var(--blue); line-height:1; }
.rc-price-sub { font-size:.68rem; color:var(--gray-400); margin-bottom:8px; }
.rc-total { font-size:.72rem; color:var(--gray-600); margin-bottom:8px; font-weight:600; }
.btn-pilih {
  display:inline-flex; align-items:center; gap:6px;
  background:var(--blue); color:#fff; border:none;
  border-radius:var(--radius-md); padding:9px 18px;
  font-size:.85rem; font-weight:700; cursor:pointer;
  transition:var(--transition); text-decoration:none; white-space:nowrap;
}
.btn-pilih:hover { background:var(--blue-light); box-shadow:var(--shadow-blue); color:#fff; transform:translateY(-1px); }

/* Kelas lain row */
.rc-other-kelas {
  border-top:1px solid var(--gray-100); padding:10px 18px;
  display:flex; align-items:center; gap:8px; flex-wrap:wrap; background:var(--gray-50);
}
.rc-other-label { font-size:.7rem; color:var(--gray-400); font-weight:700; margin-right:4px; text-transform:uppercase; letter-spacing:.3px; }
.rc-kelas-option {
  display:flex; align-items:center; gap:6px; padding:5px 12px;
  border-radius:var(--radius-md); border:1.5px solid var(--gray-200);
  background:var(--white); font-size:.78rem; font-weight:700; color:var(--navy);
  text-decoration:none; transition:var(--transition);
}
.rc-kelas-option:hover { border-color:var(--blue); color:var(--blue); }
.rc-kelas-option .price { color:var(--blue); font-size:.7rem; }

/* Empty state */
.empty-state {
  text-align: center; padding: 64px 20px;
  background: var(--white); border-radius: var(--radius-lg);
  border: 1.5px dashed var(--gray-200);
}
.empty-state .icon { font-size: 3.5rem; margin-bottom: 16px; }
.empty-state h3 { color: var(--navy); font-weight: 800; }
.empty-state p  { color: var(--gray-400); font-size: .9rem; }

/* Sidebar filter */
.filter-sidebar {
  background: var(--white); border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-lg); padding: 20px;
  position: sticky; top: 80px;
}
.filter-title {
  font-size: .82rem; font-weight: 800; color: var(--navy);
  text-transform: uppercase; letter-spacing: .5px;
  margin-bottom: 12px; padding-bottom: 8px;
  border-bottom: 1px solid var(--gray-100);
}
.filter-option {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 0; font-size: .85rem; color: var(--gray-600);
  cursor: pointer;
}
.filter-option input { accent-color: var(--blue); }

/* Tanggal navigator */
.date-nav {
  display: flex; gap: 4px; overflow-x: auto;
  scrollbar-width: none; margin-bottom: 16px;
}
.date-nav::-webkit-scrollbar { display: none; }
.date-nav-item {
  flex-shrink: 0; padding: 8px 14px;
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-md);
  text-align: center; cursor: pointer;
  transition: var(--transition); text-decoration: none;
  background: var(--white);
}
.date-nav-item:hover { border-color: var(--blue); }
.date-nav-item.active {
  background: var(--blue); border-color: var(--blue); color: #fff;
}
.date-nav-item .dn-day { font-size: .7rem; color: inherit; opacity: .8; }
.date-nav-item .dn-date { font-size: .9rem; font-weight: 700; }
.date-nav-item .dn-price { font-size: .68rem; color: var(--success); }
.date-nav-item.active .dn-price { color: rgba(255,255,255,.8); }
</style>

<!-- =========================================================
     SEARCH HEADER
========================================================= -->
<div class="search-header">
  <div class="container pb-5">
    <div class="d-flex align-items-center gap-3 mb-4" style="position:relative;z-index:2;">
      <a href="<?= APP_URL ?>/index.php" style="color:rgba(255,255,255,.6);font-size:.85rem;text-decoration:none;">
        <i class="bi bi-house"></i> Beranda
      </a>
      <i class="bi bi-chevron-right" style="color:rgba(255,255,255,.3);font-size:.7rem;"></i>
      <span style="color:#fff;font-size:.85rem;font-weight:600;">Cari Tiket <?= clean($jenisNama) ?></span>
    </div>

    <!-- Mini search bar -->
    <form method="GET" action="" class="search-bar-mini" id="searchForm">
      <input type="hidden" name="jenis" value="<?= $jenis_id ?>">
      <input type="hidden" name="trip"  value="<?= clean($trip_type) ?>">

      <!-- Jenis transportasi pills -->
      <div class="d-flex gap-1 flex-wrap" style="width:100%;margin-bottom:4px;">
        <?php foreach ($jenisList as $j): ?>
          <?php $icons = ['plane','train-front','bus-front','water','car-front']; ?>
          <a href="?jenis=<?= $j['id'] ?>&asal=<?= $asal_id ?>&tujuan=<?= $tujuan_id ?>&tgl_berangkat=<?= $tgl_berangkat ?>&dewasa=<?= $jml_dewasa ?>&anak=<?= $jml_anak ?>"
             class="kelas-chip <?= $j['id'] == $jenis_id ? 'active' : '' ?>"
             style="font-size:.78rem;padding:4px 12px;">
            <i class="bi bi-<?= $icons[$j['id']-1] ?? 'ticket' ?>"></i>
            <?= clean($j['nama']) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Asal -->
      <div class="mini-field">
        <i class="bi bi-geo-alt"></i>
        <select name="asal" style="border:none;outline:none;font-size:.85rem;font-weight:500;background:transparent;flex:1;">
          <option value="">Asal</option>
          <?php foreach ($kotaList as $k): ?>
            <option value="<?= $k['id'] ?>" <?= $k['id']==$asal_id?'selected':'' ?>>
              <?= clean($k['nama']) ?> <?= $k['kode']?'('.$k['kode'].')':'' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <span class="mini-sep"><i class="bi bi-arrow-left-right" style="color:var(--blue)"></i></span>

      <!-- Tujuan -->
      <div class="mini-field">
        <i class="bi bi-geo-alt-fill"></i>
        <select name="tujuan" style="border:none;outline:none;font-size:.85rem;font-weight:500;background:transparent;flex:1;">
          <option value="">Tujuan</option>
          <?php foreach ($kotaList as $k): ?>
            <option value="<?= $k['id'] ?>" <?= $k['id']==$tujuan_id?'selected':'' ?>>
              <?= clean($k['nama']) ?> <?= $k['kode']?'('.$k['kode'].')':'' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Tanggal -->
      <div class="mini-field">
        <i class="bi bi-calendar3"></i>
        <input type="date" name="tgl_berangkat"
               value="<?= $tgl_berangkat ?>"
               style="border:none;outline:none;font-size:.85rem;font-weight:500;background:transparent;">
      </div>

      <!-- Penumpang -->
      <div class="mini-field" style="white-space:nowrap;">
        <i class="bi bi-people"></i>
        <select name="dewasa" style="border:none;outline:none;font-size:.85rem;background:transparent;">
          <?php for($i=1;$i<=9;$i++): ?>
            <option value="<?=$i?>" <?=$i==$jml_dewasa?'selected':''?>><?=$i?> Dewasa</option>
          <?php endfor; ?>
        </select>
      </div>

      <button type="submit" class="mini-search-btn">
        <i class="bi bi-search"></i> Cari
      </button>
    </form>
  </div>
</div>

<!-- =========================================================
     MAIN CONTENT
========================================================= -->
<div class="container py-4">
  <div class="row g-4">

    <!-- ===== SIDEBAR FILTER ===== -->
    <div class="col-lg-3 d-none d-lg-block">
      <div class="filter-sidebar">
        <div class="filter-title"><i class="bi bi-sliders"></i> Filter</div>

        <!-- Kelas -->
        <div class="mb-4">
          <div style="font-size:.82rem;font-weight:700;color:var(--gray-600);margin-bottom:8px;">Kelas</div>
          <?php foreach(['','Ekonomi','Bisnis','Eksekutif','Reguler'] as $k): ?>
            <label class="filter-option">
              <input type="radio" name="kelas_filter" value="<?= $k ?>"
                     <?= $kelas_filter===$k?'checked':'' ?>
                     onchange="applyFilter('kelas','<?= $k ?>')">
              <?= $k ?: 'Semua Kelas' ?>
            </label>
          <?php endforeach; ?>
        </div>

        <!-- Waktu Berangkat -->
        <div class="mb-4">
          <div style="font-size:.82rem;font-weight:700;color:var(--gray-600);margin-bottom:8px;">Waktu Berangkat</div>
          <?php
            $waktuOptions = [
              ['label'=>'Dini hari (00–06)', 'val'=>'00-06'],
              ['label'=>'Pagi (06–12)',       'val'=>'06-12'],
              ['label'=>'Siang (12–18)',      'val'=>'12-18'],
              ['label'=>'Malam (18–24)',      'val'=>'18-24'],
            ];
          ?>
          <?php foreach($waktuOptions as $w): ?>
            <label class="filter-option">
              <input type="checkbox" value="<?= $w['val'] ?>">
              <?= $w['label'] ?>
            </label>
          <?php endforeach; ?>
        </div>

        <!-- Maskapai / Operator -->
        <?php if(!empty($results)): ?>
        <div>
          <div style="font-size:.82rem;font-weight:700;color:var(--gray-600);margin-bottom:8px;">Operator</div>
          <?php
            $ops = array_unique(array_column($results, 'operator_nama'));
            foreach($ops as $op):
          ?>
            <label class="filter-option">
              <input type="checkbox" value="<?= clean($op) ?>" checked>
              <?= clean($op) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ===== HASIL PENCARIAN ===== -->
    <div class="col-lg-9">

      <?php if ($asal_id && $tujuan_id && $tgl_valid): ?>

        <!-- Info rute -->
        <div class="result-info-bar">
          <div>
            <div class="result-route">
              <i class="bi bi-<?= $jenisIkon[$jenis_id-1] ?? 'ticket' ?>"></i>
              <?= clean($kotaAsal) ?>
              <i class="bi bi-arrow-right" style="color:var(--gray-300)"></i>
              <?= clean($kotaTujuan) ?>
            </div>
            <div style="font-size:.82rem;color:var(--gray-400);margin-top:4px;">
              <?= date('l, d F Y', strtotime($tgl_berangkat)) ?> ·
              <?= $jml_dewasa ?> Dewasa<?= $jml_anak ? ', '.$jml_anak.' Anak' : '' ?>
            </div>
          </div>
          <span class="result-count"><?= $totalResults ?> jadwal ditemukan</span>
        </div>

        <!-- Date navigator (±3 hari) -->
        <div class="date-nav">
          <?php
            $tglBase = strtotime($tgl_berangkat);
            for ($d = -3; $d <= 3; $d++):
              $tglLoop   = date('Y-m-d', strtotime("$d days", $tglBase));
              $isActive  = $tglLoop === $tgl_berangkat;
              $dayName   = date('D', strtotime($tglLoop));
              $dayNum    = date('d M', strtotime($tglLoop));
              $urlLoop   = '?' . http_build_query(array_merge($_GET, ['tgl_berangkat' => $tglLoop]));
          ?>
            <a href="<?= $urlLoop ?>" class="date-nav-item <?= $isActive ? 'active' : '' ?>">
              <div class="dn-day"><?= $dayName ?></div>
              <div class="dn-date"><?= $dayNum ?></div>
            </a>
          <?php endfor; ?>
        </div>

        <!-- Sort bar -->
        <div class="filter-sort-bar">
          <span style="font-size:.82rem;font-weight:600;color:var(--gray-400);">Urutkan:</span>
          <?php
            $sorts = [
              'harga_asc'  => ['label'=>'Harga Termurah',  'icon'=>'arrow-up'],
              'harga_desc' => ['label'=>'Harga Tertinggi', 'icon'=>'arrow-down'],
              'waktu_asc'  => ['label'=>'Berangkat Awal',  'icon'=>'clock'],
              'durasi_asc' => ['label'=>'Tercepat',        'icon'=>'lightning-charge'],
            ];
            foreach($sorts as $key => $s):
              $url = '?' . http_build_query(array_merge($_GET, ['sort'=>$key]));
          ?>
            <a href="<?= $url ?>" class="sort-btn <?= $sort===$key?'active':'' ?>">
              <i class="bi bi-<?= $s['icon'] ?>"></i> <?= $s['label'] ?>
            </a>
          <?php endforeach; ?>

          <!-- Filter kelas chips -->
          <div class="ms-auto d-flex gap-2 flex-wrap">
            <?php foreach([''=>'Semua','Ekonomi'=>'Ekonomi','Bisnis'=>'Bisnis','Eksekutif'=>'Eksekutif'] as $kv => $kl): ?>
              <a href="?<?= http_build_query(array_merge($_GET, ['kelas'=>$kv])) ?>"
                 class="kelas-chip <?= $kelas_filter===$kv?'active':'' ?>">
                <?= $kl ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Result cards -->
        <?php if (empty($results)): ?>
          <div class="empty-state">
            <div class="icon">✈️</div>
            <h3>Tidak Ada Jadwal</h3>
            <p>
              Tidak ada jadwal <?= clean($jenisNama) ?> dari <strong><?= clean($kotaAsal) ?></strong>
              ke <strong><?= clean($kotaTujuan) ?></strong><br>
              pada tanggal <strong><?= date('d F Y', strtotime($tgl_berangkat)) ?></strong>.
            </p>
            <p class="mt-2">Coba pilih tanggal lain atau ubah rute.</p>
          </div>

        <?php else: ?>
          <?php
            $minHarga = min(array_column($results, 'harga_dewasa'));
          ?>
          <?php foreach ($results as $i => $r):
            $kursiSisa  = $r['kapasitas'] - $r['kursi_terisi'];
            $isCheapest = ($r['harga_dewasa'] == $minHarga && $i == array_search($minHarga, array_column($results,'harga_dewasa')));
            $totalHarga = ($r['harga_dewasa'] * $jml_dewasa) + ($r['harga_anak'] * $jml_anak);

            // Format jam
            $jamBrgkt = substr($r['jam_berangkat'], 0, 5);
            $jamTiba  = substr($r['jam_tiba'], 0, 5);

            // Build booking URL
            $bookingUrl = APP_URL . '/pages/booking.php?' . http_build_query([
              'jadwal_id'     => $r['id'],
              'dewasa'        => $jml_dewasa,
              'anak'          => $jml_anak,
              'tgl_berangkat' => $tgl_berangkat,
              'trip'          => $trip_type,
            ]);
          ?>
            <div class="result-card <?= $isCheapest ? 'cheapest' : '' ?>">

              <!-- Logo operator -->
              <div class="op-logo">
                <?= strtoupper(substr($r['operator_kode'] ?? $r['operator_nama'], 0, 3)) ?>
              </div>

              <!-- Nama operator & kode -->
              <div style="min-width:90px;">
                <div style="font-size:.88rem;font-weight:700;color:var(--navy);">
                  <?= clean($r['operator_nama']) ?>
                </div>
                <div style="font-size:.75rem;color:var(--gray-400);"><?= clean($r['kode_jadwal']) ?></div>
                <span class="result-kelas-badge mt-1 d-inline-block"><?= clean($r['kelas']) ?></span>
              </div>

              <!-- Waktu -->
              <div class="result-times">
                <div class="result-time-block">
                  <div class="result-time"><?= $jamBrgkt ?></div>
                  <div class="result-city"><?= clean($r['kota_asal_kode'] ?: substr($r['kota_asal_nama'],0,3)) ?></div>
                </div>
                <div class="result-arrow">
                  <div class="result-arrow-line"></div>
                  <div class="result-durasi"><?= formatDurasi($r['durasi_menit']) ?></div>
                </div>
                <div class="result-time-block">
                  <div class="result-time"><?= $jamTiba ?></div>
                  <div class="result-city"><?= clean($r['kota_tujuan_kode'] ?: substr($r['kota_tujuan_nama'],0,3)) ?></div>
                </div>
              </div>

              <!-- Kursi sisa -->
              <div style="min-width:80px;text-align:center;">
                <div class="result-seat <?= $kursiSisa <= 10 ? 'low' : '' ?>">
                  <i class="bi bi-person-seat"></i>
                  <?= $kursiSisa ?> kursi
                </div>
                <?php if($kursiSisa <= 10 && $kursiSisa > 0): ?>
                  <div style="font-size:.68rem;color:var(--danger);font-weight:600;">Hampir habis!</div>
                <?php endif; ?>
              </div>

              <!-- Harga & tombol -->
              <div class="result-price-block">
                <div class="result-price-label">Mulai dari</div>
                <div class="result-price"><?= formatRupiah($r['harga_dewasa']) ?></div>
                <div class="result-price-sub">/orang</div>
                <?php if($jml_dewasa + $jml_anak > 1): ?>
                  <div style="font-size:.72rem;color:var(--gray-600);margin-top:2px;">
                    Total: <?= formatRupiah($totalHarga) ?>
                  </div>
                <?php endif; ?>
                <a href="<?= $bookingUrl ?>" class="btn-pilih">
                  Pilih <i class="bi bi-arrow-right"></i>
                </a>
              </div>

            </div>
          <?php endforeach; ?>

        <?php endif; ?>

      <?php else: ?>
        <!-- Belum ada pencarian -->
        <div class="empty-state">
          <div class="icon">🔍</div>
          <h3>Cari Tiket Dulu Yuk!</h3>
          <p>Pilih kota asal, tujuan, dan tanggal keberangkatan<br>di form pencarian di atas.</p>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
// Apply filter via URL
function applyFilter(key, val) {
  const url = new URL(window.location.href);
  url.searchParams.set(key, val);
  window.location.href = url.toString();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>