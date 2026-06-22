<?php
// ============================================================
//  TravelGo — Admin Jadwal (admin/jadwal.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Kelola Jadwal';

$msg = ''; $err = '';

// ---- Proses aksi ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = clean($_POST['act'] ?? '');

    if ($act === 'tambah' || $act === 'edit') {
        $rute_id   = (int)$_POST['rute_id'];
        $kode      = clean($_POST['kode_jadwal']);
        $tgl       = clean($_POST['tanggal_berangkat']);
        $jam_brkt  = clean($_POST['jam_berangkat']);
        $jam_tiba  = clean($_POST['jam_tiba']);
        $h_dew     = (float)$_POST['harga_dewasa'];
        $h_anak    = (float)$_POST['harga_anak'];
        $kap       = (int)$_POST['kapasitas'];
        $kelas     = clean($_POST['kelas']);
        $is_aktif  = isset($_POST['is_aktif']) ? 1 : 0;

        if ($act === 'tambah') {
            $hari_op = 0; // default setiap hari
            $stmt = $conn->prepare("INSERT INTO jadwal (rute_id,kode_jadwal,tanggal_berangkat,jam_berangkat,jam_tiba,harga_dewasa,harga_anak,kapasitas,kelas,is_aktif,hari_operasi) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('issssddssii',$rute_id,$kode,$tgl,$jam_brkt,$jam_tiba,$h_dew,$h_anak,$kap,$kelas,$is_aktif,$hari_op);
        } else {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE jadwal SET rute_id=?,kode_jadwal=?,tanggal_berangkat=?,jam_berangkat=?,jam_tiba=?,harga_dewasa=?,harga_anak=?,kapasitas=?,kelas=?,is_aktif=? WHERE id=?");
            $stmt->bind_param('issssddssii',$rute_id,$kode,$tgl,$jam_brkt,$jam_tiba,$h_dew,$h_anak,$kap,$kelas,$is_aktif,$id);
        }
        $stmt->execute() ? $msg = 'Jadwal berhasil disimpan.' : $err = 'Gagal menyimpan jadwal.';
    }

    if ($act === 'hapus') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE jadwal SET is_aktif=0 WHERE id=$id");
        $msg = 'Jadwal berhasil dinonaktifkan.';
    }
    header('Location: ?msg='.urlencode($msg).'&err='.urlencode($err));
    exit;
}

if (isset($_GET['msg'])) $msg = clean($_GET['msg']);
if (isset($_GET['err'])) $err = clean($_GET['err']);

// ---- Data ----
$page    = max(1,(int)($_GET['page']??1));
$perPage = 15; $offset = ($page-1)*$perPage;
$search  = clean($_GET['q']??'');
$sWhere  = $search ? "WHERE (j.kode_jadwal LIKE '%$search%' OR ka.nama LIKE '%$search%' OR kt.nama LIKE '%$search%')" : '';
$total   = (int)$conn->query("SELECT COUNT(*) FROM jadwal j JOIN rute r ON j.rute_id=r.id JOIN kota ka ON r.kota_asal_id=ka.id JOIN kota kt ON r.kota_tujuan_id=kt.id $sWhere")->fetch_row()[0];
$totalPage = max(1,ceil($total/$perPage));

$jadwals = $conn->query("
    SELECT j.*, r.durasi_menit,
           o.nama AS op_nama,
           ka.nama AS asal, kt.nama AS tujuan,
           jt.nama AS jenis
    FROM jadwal j
    JOIN rute r ON j.rute_id=r.id
    JOIN operator o ON r.operator_id=o.id
    JOIN kota ka ON r.kota_asal_id=ka.id
    JOIN kota kt ON r.kota_tujuan_id=kt.id
    JOIN jenis_transportasi jt ON r.jenis_transportasi_id=jt.id
    $sWhere
    ORDER BY j.tanggal_berangkat DESC, j.jam_berangkat ASC
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

$ruteList = $conn->query("
    SELECT r.id, ka.nama AS asal, kt.nama AS tujuan, o.nama AS op, jt.nama AS jenis
    FROM rute r
    JOIN kota ka ON r.kota_asal_id=ka.id
    JOIN kota kt ON r.kota_tujuan_id=kt.id
    JOIN operator o ON r.operator_id=o.id
    JOIN jenis_transportasi jt ON r.jenis_transportasi_id=jt.id
    WHERE r.is_aktif=1 ORDER BY jt.id, ka.nama
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $pageTitle ?> — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
<style>
*{box-sizing:border-box}body{font-family:'Plus Jakarta Sans',sans-serif;background:#0F172A;color:#E2E8F0;margin:0}
.adm-sidebar{width:240px;height:100vh;position:fixed;left:0;top:0;background:#111827;border-right:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;z-index:100;overflow-y:auto}
.adm-brand{padding:20px 20px 16px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:10px}
.adm-brand-icon{width:36px;height:36px;background:var(--blue);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#fff}
.adm-brand-name{font-size:.95rem;font-weight:800;color:#fff}.adm-brand-sub{font-size:.65rem;color:rgba(255,255,255,.3)}
.adm-nav{padding:12px 10px;flex:1}.adm-nav-label{font-size:.65rem;font-weight:700;color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:.6px;padding:8px 10px 4px}
.adm-nav-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;color:rgba(255,255,255,.55);font-size:.85rem;font-weight:600;text-decoration:none;transition:.2s;margin-bottom:2px}
.adm-nav-item:hover{background:rgba(255,255,255,.06);color:#fff}.adm-nav-item.active{background:rgba(37,99,235,.2);color:#60A5FA}
.adm-nav-footer{padding:12px 10px;border-top:1px solid rgba(255,255,255,.06)}
.adm-main{margin-left:240px;min-height:100vh}.adm-topbar{background:#111827;border-bottom:1px solid rgba(255,255,255,.06);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.adm-topbar-title{font-size:1rem;font-weight:800;color:#fff}.adm-avatar{width:30px;height:30px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff}
.adm-content{padding:24px 28px}
.adm-table-card{background:#1E293B;border:1px solid rgba(255,255,255,.06);border-radius:12px;overflow:hidden;margin-top:16px}
.adm-table{width:100%;border-collapse:collapse}
.adm-table th{padding:10px 14px;font-size:.7rem;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.4px;text-align:left;background:rgba(255,255,255,.02);border-bottom:1px solid rgba(255,255,255,.05)}
.adm-table td{padding:11px 14px;font-size:.82rem;color:rgba(255,255,255,.75);border-bottom:1px solid rgba(255,255,255,.04)}
.adm-table tr:last-child td{border-bottom:none}.adm-table tr:hover td{background:rgba(255,255,255,.02)}
.adm-btn{padding:7px 16px;border-radius:8px;font-size:.82rem;font-weight:700;border:none;cursor:pointer;transition:.2s;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:5px}
.adm-btn.primary{background:var(--blue);color:#fff}.adm-btn.primary:hover{background:#1D4ED8}
.adm-btn.sm{padding:4px 10px;font-size:.72rem}
.adm-btn.edit{background:rgba(99,102,241,.15);color:#A5B4FC}.adm-btn.danger{background:rgba(239,68,68,.12);color:#F87171}
.adm-input{width:100%;padding:9px 12px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.1);border-radius:8px;color:#fff;font-size:.85rem;font-family:'Plus Jakarta Sans',sans-serif;outline:none;transition:.2s}
.adm-input:focus{border-color:var(--blue)}.adm-input::placeholder{color:rgba(255,255,255,.25)}
.adm-input option{background:#1E293B;color:#E2E8F0;}
.adm-input option:hover,.adm-input option:checked{background:#2563EB;color:#fff;}
.adm-label{font-size:.78rem;font-weight:600;color:rgba(255,255,255,.5);margin-bottom:5px;display:block}
.adm-search{display:flex;align-items:center;gap:8px;background:#1E293B;border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:8px 14px;flex:1;min-width:200px}
.adm-search input{background:none;border:none;outline:none;color:#fff;font-size:.85rem;flex:1;font-family:'Plus Jakarta Sans',sans-serif}
.adm-search input::placeholder{color:rgba(255,255,255,.25)}
.adm-modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;display:none;align-items:center;justify-content:center}
.adm-modal-bg.open{display:flex}
.adm-modal{background:#1E293B;border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:28px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto}
.adm-modal h3{font-size:1rem;font-weight:800;color:#fff;margin:0 0 20px}
.adm-msg{padding:10px 14px;border-radius:8px;font-size:.82rem;font-weight:500;margin-bottom:14px}
.adm-msg.ok{background:rgba(16,185,129,.1);color:#34D399;border:1px solid rgba(16,185,129,.2)}
.adm-msg.er{background:rgba(239,68,68,.1);color:#F87171;border:1px solid rgba(239,68,68,.2)}
.pagination-wrap{display:flex;justify-content:center;gap:5px;margin-top:16px}
.page-btn{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;text-decoration:none;border:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.5);background:#1E293B;transition:.2s}
.page-btn:hover{color:#fff}.page-btn.active{background:var(--blue);color:#fff;border-color:var(--blue)}.page-btn.disabled{opacity:.3;pointer-events:none}
@media(max-width:767px){.adm-sidebar{display:none}.adm-main{margin-left:0}}
</style></head><body>

<aside class="adm-sidebar">
  <div class="adm-brand"><div class="adm-brand-icon"><i class="bi bi-airplane-fill"></i></div><div><div class="adm-brand-name">TravelGo</div><div class="adm-brand-sub">Admin Panel</div></div></div>
  <nav class="adm-nav">
    <div class="adm-nav-label">Utama</div>
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="adm-nav-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="<?= APP_URL ?>/admin/bookings.php"  class="adm-nav-item"><i class="bi bi-ticket-detailed"></i> Bookings</a>
    <div class="adm-nav-label" style="margin-top:8px;">Master Data</div>
    <a href="<?= APP_URL ?>/admin/jadwal.php"    class="adm-nav-item active"><i class="bi bi-calendar3"></i> Jadwal</a>
    <a href="<?= APP_URL ?>/admin/routes.php"    class="adm-nav-item"><i class="bi bi-signpost-split"></i> Rute</a>
    <a href="<?= APP_URL ?>/admin/users.php"     class="adm-nav-item"><i class="bi bi-people"></i> Users</a>
    </a>
     <a href="<?= APP_URL ?>/admin/reviews.php" class="adm-nav-item">
      <i class="bi bi-star"></i> Reviews
    </a>
    <div class="adm-nav-label" style="margin-top:8px;">Lainnya</div>
    <a href="<?= APP_URL ?>/index.php"           class="adm-nav-item"><i class="bi bi-globe"></i> Lihat Website</a>
  </nav>
  <div class="adm-nav-footer"><a href="<?= APP_URL ?>/api/auth.php?action=logout" class="adm-nav-item" style="color:rgba(239,68,68,.7);"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</aside>

<div class="adm-main">
  <div class="adm-topbar">
    <div class="adm-topbar-title">Kelola Jadwal</div>
    <div style="display:flex;align-items:center;gap:10px;font-size:.83rem;color:rgba(255,255,255,.5);">
      <div class="adm-avatar"><?= strtoupper(substr($_SESSION['nama'],0,1)) ?></div><?= clean($_SESSION['nama']) ?>
    </div>
  </div>
  <div class="adm-content">
    <?php if($msg): ?><div class="adm-msg ok"><i class="bi bi-check-circle"></i> <?= $msg ?></div><?php endif; ?>
    <?php if($err): ?><div class="adm-msg er"><i class="bi bi-x-circle"></i> <?= $err ?></div><?php endif; ?>

    <!-- Toolbar -->
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
      <form method="GET" class="adm-search">
        <i class="bi bi-search" style="color:rgba(255,255,255,.3);"></i>
        <input type="text" name="q" placeholder="Cari kode, rute..." value="<?= $search ?>">
      </form>
      <button class="adm-btn primary" onclick="openModal('modalTambah')">
        <i class="bi bi-plus-lg"></i> Tambah Jadwal
      </button>
    </div>

    <!-- Table -->
    <div class="adm-table-card">
      <div style="overflow-x:auto;">
        <table class="adm-table">
          <thead><tr>
            <th>Kode</th><th>Rute</th><th>Tgl Berangkat</th>
            <th>Jam</th><th>Kelas</th><th>Harga Dewasa</th>
            <th>Kursi Sisa</th><th>Status</th><th>Aksi</th>
          </tr></thead>
          <tbody>
            <?php if(empty($jadwals)): ?>
              <tr><td colspan="9" style="text-align:center;padding:40px;color:rgba(255,255,255,.2);">Tidak ada data</td></tr>
            <?php else: foreach($jadwals as $j): $sisa = $j['kapasitas']-$j['kursi_terisi']; ?>
              <tr>
                <td style="font-family:monospace;color:#fff;font-weight:700;"><?= clean($j['kode_jadwal']) ?></td>
                <td><div style="font-weight:600;"><?= clean($j['asal']) ?> → <?= clean($j['tujuan']) ?></div><div style="font-size:.72rem;color:rgba(255,255,255,.35);"><?= clean($j['jenis']) ?> · <?= clean($j['op_nama']) ?></div></td>
                <td><?= date('d M Y', strtotime($j['tanggal_berangkat'])) ?></td>
                <td style="font-family:monospace;"><?= substr($j['jam_berangkat'],0,5) ?> – <?= substr($j['jam_tiba'],0,5) ?></td>
                <td><?= clean($j['kelas']) ?></td>
                <td style="color:#60A5FA;font-weight:700;"><?= formatRupiah($j['harga_dewasa']) ?></td>
                <td><span style="color:<?= $sisa<=10?'#F87171':'#34D399' ?>;font-weight:700;"><?= $sisa ?></span></td>
                <td><span style="padding:3px 8px;border-radius:50px;font-size:.7rem;font-weight:700;background:<?= $j['is_aktif']?'rgba(16,185,129,.12)':'rgba(107,114,128,.12)' ?>;color:<?= $j['is_aktif']?'#34D399':'#9CA3AF' ?>;"><?= $j['is_aktif']?'Aktif':'Nonaktif' ?></span></td>
                <td>
                  <button class="adm-btn edit sm" onclick="editJadwal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Nonaktifkan jadwal ini?')">
                    <input type="hidden" name="act" value="hapus">
                    <input type="hidden" name="id" value="<?= $j['id'] ?>">
                    <button type="submit" class="adm-btn danger sm"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if($totalPage>1): ?>
      <div class="pagination-wrap">
        <a href="?q=<?=$search?>&page=<?=max(1,$page-1)?>" class="page-btn <?=$page<=1?'disabled':''?>"><i class="bi bi-chevron-left"></i></a>
        <?php for($p=1;$p<=$totalPage;$p++): ?><a href="?q=<?=$search?>&page=<?=$p?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a><?php endfor; ?>
        <a href="?q=<?=$search?>&page=<?=min($totalPage,$page+1)?>" class="page-btn <?=$page>=$totalPage?'disabled':''?>"><i class="bi bi-chevron-right"></i></a>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Tambah -->
<div class="adm-modal-bg" id="modalTambah">
  <div class="adm-modal">
    <h3><i class="bi bi-plus-circle"></i> Tambah Jadwal</h3>
    <form method="POST">
      <input type="hidden" name="act" value="tambah">
      <div class="mb-3">
        <label class="adm-label">Rute *</label>
        <select name="rute_id" class="adm-input" required>
          <option value="">-- Pilih Rute --</option>
          <?php foreach($ruteList as $r): ?>
            <option value="<?=$r['id']?>"><?= clean($r['jenis'].' | '.$r['asal'].' → '.$r['tujuan'].' ('.$r['op'].')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6"><label class="adm-label">Kode Jadwal *</label><input type="text" name="kode_jadwal" class="adm-input" placeholder="GA-101" required></div>
        <div class="col-6"><label class="adm-label">Kelas *</label>
          <select name="kelas" class="adm-input">
            <option value="Ekonomi">Ekonomi</option>
            <option value="Bisnis">Bisnis</option>
            <option value="Eksekutif">Eksekutif</option>
            <option value="Reguler">Reguler</option>
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label class="adm-label">Operasi Jadwal *</label>
        <select name="hari_operasi" class="adm-input">
          <option value="0">Setiap Hari</option>
          <option value="1">Senin</option>
          <option value="2">Selasa</option>
          <option value="3">Rabu</option>
          <option value="4">Kamis</option>
          <option value="5">Jumat</option>
          <option value="6">Sabtu</option>
          <option value="7">Minggu</option>
        </select>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-12"><label class="adm-label">Tanggal Berangkat *</label><input type="date" name="tanggal_berangkat" class="adm-input" required></div>
        <div class="col-6"><label class="adm-label">Jam Berangkat *</label><input type="time" name="jam_berangkat" class="adm-input" required></div>
        <div class="col-6"><label class="adm-label">Jam Tiba *</label><input type="time" name="jam_tiba" class="adm-input" required></div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-4"><label class="adm-label">Harga Dewasa (Rp)</label><input type="number" name="harga_dewasa" class="adm-input" placeholder="500000" required></div>
        <div class="col-4"><label class="adm-label">Harga Anak (Rp)</label><input type="number" name="harga_anak" class="adm-input" placeholder="400000" required></div>
        <div class="col-4"><label class="adm-label">Kapasitas</label><input type="number" name="kapasitas" class="adm-input" value="100" required></div>
      </div>
      <div class="mb-3">
        <label style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:rgba(255,255,255,.7);cursor:pointer;">
          <input type="checkbox" name="is_aktif" value="1" checked style="accent-color:var(--blue);"> Jadwal Aktif
        </label>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="adm-btn primary"><i class="bi bi-check-lg"></i> Simpan</button>
        <button type="button" class="adm-btn" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.6);" onclick="closeModal('modalTambah')">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div class="adm-modal-bg" id="modalEdit">
  <div class="adm-modal">
    <h3><i class="bi bi-pencil"></i> Edit Jadwal</h3>
    <form method="POST" id="formEdit">
      <input type="hidden" name="act" value="edit">
      <input type="hidden" name="id" id="editId">
      <div class="mb-3">
        <label class="adm-label">Rute *</label>
        <select name="rute_id" id="editRute" class="adm-input" required>
          <?php foreach($ruteList as $r): ?>
            <option value="<?=$r['id']?>"><?= clean($r['jenis'].' | '.$r['asal'].' → '.$r['tujuan'].' ('.$r['op'].')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6"><label class="adm-label">Kode Jadwal *</label><input type="text" name="kode_jadwal" id="editKode" class="adm-input" required></div>
        <div class="col-6"><label class="adm-label">Kelas *</label>
          <select name="kelas" id="editKelas" class="adm-input">
            <option value="Ekonomi">Ekonomi</option>
            <option value="Bisnis">Bisnis</option>
            <option value="Eksekutif">Eksekutif</option>
            <option value="Reguler">Reguler</option>
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label class="adm-label">Operasi Jadwal *</label>
        <select name="hari_operasi" id="editHariOp" class="adm-input">
          <option value="0">Setiap Hari</option>
          <option value="1">Senin</option>
          <option value="2">Selasa</option>
          <option value="3">Rabu</option>
          <option value="4">Kamis</option>
          <option value="5">Jumat</option>
          <option value="6">Sabtu</option>
          <option value="7">Minggu</option>
        </select>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-12"><label class="adm-label">Tanggal Berangkat *</label><input type="date" name="tanggal_berangkat" id="editTgl" class="adm-input" required></div>
        <div class="col-6"><label class="adm-label">Jam Berangkat *</label><input type="time" name="jam_berangkat" id="editJamB" class="adm-input" required></div>
        <div class="col-6"><label class="adm-label">Jam Tiba *</label><input type="time" name="jam_tiba" id="editJamT" class="adm-input" required></div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-4"><label class="adm-label">Harga Dewasa</label><input type="number" name="harga_dewasa" id="editHD" class="adm-input" required></div>
        <div class="col-4"><label class="adm-label">Harga Anak</label><input type="number" name="harga_anak" id="editHA" class="adm-input" required></div>
        <div class="col-4"><label class="adm-label">Kapasitas</label><input type="number" name="kapasitas" id="editKap" class="adm-input" required></div>
      </div>
      <div class="mb-3">
        <label style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:rgba(255,255,255,.7);cursor:pointer;">
          <input type="checkbox" name="is_aktif" id="editAktif" value="1" style="accent-color:var(--blue);"> Jadwal Aktif
        </label>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="adm-btn primary"><i class="bi bi-check-lg"></i> Update</button>
        <button type="button" class="adm-btn" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.6);" onclick="closeModal('modalEdit')">Batal</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.adm-modal-bg').forEach(m => {
  m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); });
});
function editJadwal(j) {
  document.getElementById('editId').value       = j.id;
  document.getElementById('editRute').value     = j.rute_id;
  document.getElementById('editKode').value     = j.kode_jadwal;
  document.getElementById('editTgl').value      = j.tanggal_berangkat;
  document.getElementById('editJamB').value     = j.jam_berangkat.substr(0,5);
  document.getElementById('editJamT').value     = j.jam_tiba.substr(0,5);
  document.getElementById('editHD').value       = j.harga_dewasa;
  document.getElementById('editHA').value       = j.harga_anak;
  document.getElementById('editKap').value      = j.kapasitas;
  document.getElementById('editKelas').value    = j.kelas;
  document.getElementById('editHariOp').value   = j.hari_operasi ?? 0;
  document.getElementById('editAktif').checked  = j.is_aktif == 1;
  openModal('modalEdit');
}
</script>
</body></html>