<?php
// ============================================================
//  TravelGo — Admin Routes (admin/routes.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Kelola Rute';

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = clean($_POST['act'] ?? '');

    if ($act === 'tambah' || $act === 'edit') {
        $jt_id    = (int)$_POST['jenis_transportasi_id'];
        $op_id    = (int)$_POST['operator_id'];
        $asal_id  = (int)$_POST['kota_asal_id'];
        $tuj_id   = (int)$_POST['kota_tujuan_id'];
        $durasi   = (int)$_POST['durasi_menit'];
        $is_aktif = isset($_POST['is_aktif']) ? 1 : 0;

        if ($asal_id === $tuj_id) {
            $err = 'Kota asal dan tujuan tidak boleh sama.';
        } else {
            if ($act === 'tambah') {
                $stmt = $conn->prepare("INSERT INTO rute (jenis_transportasi_id,operator_id,kota_asal_id,kota_tujuan_id,durasi_menit,is_aktif) VALUES (?,?,?,?,?,?)");
                $stmt->bind_param('iiiiii',$jt_id,$op_id,$asal_id,$tuj_id,$durasi,$is_aktif);
            } else {
                $id = (int)$_POST['id'];
                $stmt = $conn->prepare("UPDATE rute SET jenis_transportasi_id=?,operator_id=?,kota_asal_id=?,kota_tujuan_id=?,durasi_menit=?,is_aktif=? WHERE id=?");
                $stmt->bind_param('iiiiiii',$jt_id,$op_id,$asal_id,$tuj_id,$durasi,$is_aktif,$id);
            }
            $stmt->execute() ? $msg = 'Rute berhasil disimpan.' : $err = 'Gagal menyimpan rute.';
        }
    }
    if ($act === 'hapus') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE rute SET is_aktif=0 WHERE id=$id");
        $msg = 'Rute berhasil dinonaktifkan.';
    }
    header('Location: ?msg='.urlencode($msg).'&err='.urlencode($err));
    exit;
}

if (isset($_GET['msg'])) $msg = clean($_GET['msg']);
if (isset($_GET['err'])) $err = clean($_GET['err']);

$page    = max(1,(int)($_GET['page']??1));
$perPage = 15; $offset = ($page-1)*$perPage;
$total   = (int)$conn->query("SELECT COUNT(*) FROM rute")->fetch_row()[0];
$totalPage = max(1,ceil($total/$perPage));

$routes = $conn->query("
    SELECT r.*, jt.nama AS jenis, o.nama AS op_nama,
           ka.nama AS asal, kt.nama AS tujuan
    FROM rute r
    JOIN jenis_transportasi jt ON r.jenis_transportasi_id=jt.id
    JOIN operator o            ON r.operator_id=o.id
    JOIN kota ka               ON r.kota_asal_id=ka.id
    JOIN kota kt               ON r.kota_tujuan_id=kt.id
    ORDER BY jt.id, ka.nama
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

$jenisList = $conn->query("SELECT * FROM jenis_transportasi ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$kotaList  = $conn->query("SELECT * FROM kota ORDER BY nama")->fetch_all(MYSQLI_ASSOC);
$opList    = $conn->query("SELECT o.*, jt.nama AS jenis FROM operator o JOIN jenis_transportasi jt ON o.jenis_transportasi_id=jt.id ORDER BY jt.id, o.nama")->fetch_all(MYSQLI_ASSOC);
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
.adm-modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;display:none;align-items:center;justify-content:center}
.adm-modal-bg.open{display:flex}
.adm-modal{background:#1E293B;border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:28px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto}
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
    <a href="<?= APP_URL ?>/admin/jadwal.php"    class="adm-nav-item"><i class="bi bi-calendar3"></i> Jadwal</a>
    <a href="<?= APP_URL ?>/admin/routes.php"    class="adm-nav-item active"><i class="bi bi-signpost-split"></i> Rute</a>
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
    <div class="adm-topbar-title">Kelola Rute</div>
    <div style="display:flex;align-items:center;gap:10px;font-size:.83rem;color:rgba(255,255,255,.5);">
      <div class="adm-avatar"><?= strtoupper(substr($_SESSION['nama'],0,1)) ?></div><?= clean($_SESSION['nama']) ?>
    </div>
  </div>
  <div class="adm-content">
    <?php if($msg): ?><div class="adm-msg ok"><i class="bi bi-check-circle"></i> <?= $msg ?></div><?php endif; ?>
    <?php if($err): ?><div class="adm-msg er"><i class="bi bi-x-circle"></i> <?= $err ?></div><?php endif; ?>

    <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
      <button class="adm-btn primary" onclick="openModal('modalTambah')">
        <i class="bi bi-plus-lg"></i> Tambah Rute
      </button>
    </div>

    <div class="adm-table-card">
      <div style="overflow-x:auto;">
        <table class="adm-table">
          <thead><tr><th>#</th><th>Jenis</th><th>Operator</th><th>Asal</th><th>Tujuan</th><th>Durasi</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php if(empty($routes)): ?>
              <tr><td colspan="8" style="text-align:center;padding:40px;color:rgba(255,255,255,.2);">Tidak ada data</td></tr>
            <?php else: foreach($routes as $r): ?>
              <tr>
                <td style="color:rgba(255,255,255,.3);"><?= $r['id'] ?></td>
                <td><span style="padding:3px 8px;border-radius:4px;font-size:.72rem;font-weight:700;background:rgba(37,99,235,.12);color:#60A5FA;"><?= clean($r['jenis']) ?></span></td>
                <td style="font-weight:600;color:#fff;"><?= clean($r['op_nama']) ?></td>
                <td><?= clean($r['asal']) ?></td>
                <td><?= clean($r['tujuan']) ?></td>
              <td>
<?= intdiv($r['durasi_menit'],60) ?>j 
<?= ($r['durasi_menit'] % 60 > 0 ? ($r['durasi_menit'] % 60) . 'm' : '') ?>
</td>
                <td><span style="padding:3px 8px;border-radius:50px;font-size:.7rem;font-weight:700;background:<?= $r['is_aktif']?'rgba(16,185,129,.12)':'rgba(107,114,128,.12)' ?>;color:<?= $r['is_aktif']?'#34D399':'#9CA3AF' ?>;"><?= $r['is_aktif']?'Aktif':'Nonaktif' ?></span></td>
                <td>
                  <button class="adm-btn edit sm" onclick="editRute(<?= htmlspecialchars(json_encode($r),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Nonaktifkan rute ini?')">
                    <input type="hidden" name="act" value="hapus">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
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
        <a href="?page=<?=max(1,$page-1)?>" class="page-btn <?=$page<=1?'disabled':''?>"><i class="bi bi-chevron-left"></i></a>
        <?php for($p=1;$p<=$totalPage;$p++): ?><a href="?page=<?=$p?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a><?php endfor; ?>
        <a href="?page=<?=min($totalPage,$page+1)?>" class="page-btn <?=$page>=$totalPage?'disabled':''?>"><i class="bi bi-chevron-right"></i></a>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Tambah -->
<div class="adm-modal-bg" id="modalTambah">
  <div class="adm-modal">
    <h3><i class="bi bi-plus-circle"></i> Tambah Rute</h3>
    <form method="POST">
      <input type="hidden" name="act" value="tambah">
      <div class="mb-3">
        <label class="adm-label">Jenis Transportasi *</label>
        <select name="jenis_transportasi_id" id="jtSelect" class="adm-input" required onchange="filterOp(this.value,'opSelect')">
          <option value="">-- Pilih Jenis --</option>
          <?php foreach($jenisList as $jt): ?><option value="<?=$jt['id']?>"><?= clean($jt['nama']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="adm-label">Operator *</label>
        <select name="operator_id" id="opSelect" class="adm-input" required>
          <option value="">-- Pilih Jenis dulu --</option>
        </select>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="adm-label">Kota Asal *</label>
          <select name="kota_asal_id" class="adm-input" required>
            <option value="">-- Pilih Kota --</option>
            <?php foreach($kotaList as $k): ?><option value="<?=$k['id']?>"><?= clean($k['nama']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="adm-label">Kota Tujuan *</label>
          <select name="kota_tujuan_id" class="adm-input" required>
            <option value="">-- Pilih Kota --</option>
            <?php foreach($kotaList as $k): ?><option value="<?=$k['id']?>"><?= clean($k['nama']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label class="adm-label">Durasi (menit) *</label>
        <input type="number" name="durasi_menit" class="adm-input" placeholder="90" min="1" required>
      </div>
      <div class="mb-3">
        <label style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:rgba(255,255,255,.7);cursor:pointer;">
          <input type="checkbox" name="is_aktif" value="1" checked style="accent-color:var(--blue);"> Rute Aktif
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
    <h3><i class="bi bi-pencil"></i> Edit Rute</h3>
    <form method="POST">
      <input type="hidden" name="act" value="edit">
      <input type="hidden" name="id" id="eId">
      <div class="mb-3">
        <label class="adm-label">Jenis Transportasi *</label>
        <select name="jenis_transportasi_id" id="eJt" class="adm-input" required onchange="filterOp(this.value,'eOp')">
          <?php foreach($jenisList as $jt): ?><option value="<?=$jt['id']?>"><?= clean($jt['nama']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="adm-label">Operator *</label>
        <select name="operator_id" id="eOp" class="adm-input" required></select>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="adm-label">Kota Asal *</label>
          <select name="kota_asal_id" id="eAsal" class="adm-input" required>
            <?php foreach($kotaList as $k): ?><option value="<?=$k['id']?>"><?= clean($k['nama']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="adm-label">Kota Tujuan *</label>
          <select name="kota_tujuan_id" id="eTuj" class="adm-input" required>
            <?php foreach($kotaList as $k): ?><option value="<?=$k['id']?>"><?= clean($k['nama']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label class="adm-label">Durasi (menit) *</label>
        <input type="number" name="durasi_menit" id="eDurasi" class="adm-input" min="1" required>
      </div>
      <div class="mb-3">
        <label style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:rgba(255,255,255,.7);cursor:pointer;">
          <input type="checkbox" name="is_aktif" id="eAktif" value="1" style="accent-color:var(--blue);"> Rute Aktif
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
const allOp = <?= json_encode($opList) ?>;

function filterOp(jtId, selectId) {
  const sel = document.getElementById(selectId);
  sel.innerHTML = '<option value="">-- Pilih Operator --</option>';
  allOp.filter(o => o.jenis_transportasi_id == jtId).forEach(o => {
    sel.innerHTML += `<option value="${o.id}">${o.nama}</option>`;
  });
}

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.adm-modal-bg').forEach(m => {
  m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); });
});

function editRute(r) {
  document.getElementById('eId').value = r.id;
  document.getElementById('eJt').value = r.jenis_transportasi_id;
  filterOp(r.jenis_transportasi_id, 'eOp');
  setTimeout(() => { document.getElementById('eOp').value = r.operator_id; }, 50);
  document.getElementById('eAsal').value   = r.kota_asal_id;
  document.getElementById('eTuj').value    = r.kota_tujuan_id;
  document.getElementById('eDurasi').value = r.durasi_menit;
  document.getElementById('eAktif').checked = r.is_aktif == 1;
  openModal('modalEdit');
}
</script>
</body></html>