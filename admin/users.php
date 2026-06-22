<?php
// ============================================================
//  TravelGo — Admin Users (admin/users.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Kelola Users';

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = clean($_POST['act'] ?? '');

    if ($act === 'toggle_aktif') {
        $id   = (int)$_POST['id'];
        $val  = (int)$_POST['is_aktif'];
        $new  = $val ? 0 : 1;
        $conn->query("UPDATE users SET is_aktif=$new WHERE id=$id");
        $msg = 'Status user berhasil diubah.';
    }
    if ($act === 'ganti_role') {
        $id      = (int)$_POST['id'];
        $role    = in_array($_POST['role'],['user','admin']) ? clean($_POST['role']) : 'user';
        $conn->query("UPDATE users SET role='$role' WHERE id=$id");
        $msg = 'Role user berhasil diubah.';
    }
    if ($act === 'reset_pw') {
        $id   = (int)$_POST['id'];
        $hash = password_hash('travelgo123', PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hash' WHERE id=$id");
        $msg = 'Password berhasil direset ke: travelgo123';
    }
    header('Location: ?msg='.urlencode($msg));
    exit;
}

if (isset($_GET['msg'])) $msg = clean($_GET['msg']);

$page    = max(1,(int)($_GET['page']??1));
$perPage = 15; $offset = ($page-1)*$perPage;
$search  = clean($_GET['q'] ?? '');
$role_f  = clean($_GET['role'] ?? '');

$whereArr = [];
if ($search) { $s = $conn->real_escape_string($search); $whereArr[] = "(nama LIKE '%$s%' OR email LIKE '%$s%')"; }
if ($role_f) $whereArr[] = "role='$role_f'";
$where = $whereArr ? 'WHERE '.implode(' AND ',$whereArr) : '';

$total     = (int)$conn->query("SELECT COUNT(*) FROM users $where")->fetch_row()[0];
$totalPage = max(1,ceil($total/$perPage));

$users = $conn->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM bookings WHERE user_id=u.id) AS jml_booking,
           (SELECT COUNT(*) FROM bookings WHERE user_id=u.id AND status='paid') AS jml_paid
    FROM users u $where
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
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
.adm-table td{padding:12px 14px;font-size:.82rem;color:rgba(255,255,255,.75);border-bottom:1px solid rgba(255,255,255,.04)}
.adm-table tr:last-child td{border-bottom:none}.adm-table tr:hover td{background:rgba(255,255,255,.02)}
.adm-search{display:flex;align-items:center;gap:8px;background:#1E293B;border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:8px 14px;flex:1;min-width:200px}
.adm-search input{background:none;border:none;outline:none;color:#fff;font-size:.85rem;flex:1;font-family:'Plus Jakarta Sans',sans-serif}
.adm-search input::placeholder{color:rgba(255,255,255,.25)}
.adm-ftab{padding:7px 14px;border-radius:8px;font-size:.78rem;font-weight:700;border:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.5);background:#1E293B;text-decoration:none;transition:.2s}
.adm-ftab:hover{color:#fff}.adm-ftab.active{background:var(--blue);color:#fff;border-color:var(--blue)}
.adm-btn{padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:700;border:none;cursor:pointer;transition:.2s;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:4px}
.adm-btn.sm-edit{background:rgba(99,102,241,.15);color:#A5B4FC}
.adm-btn.sm-warn{background:rgba(245,158,11,.12);color:#FBBF24}
.adm-btn.sm-danger{background:rgba(239,68,68,.12);color:#F87171}
.adm-msg{padding:10px 14px;border-radius:8px;font-size:.82rem;font-weight:500;margin-bottom:14px}
.adm-msg.ok{background:rgba(16,185,129,.1);color:#34D399;border:1px solid rgba(16,185,129,.2)}
.user-avatar{width:34px;height:34px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;color:#fff;flex-shrink:0}
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
    <a href="<?= APP_URL ?>/admin/routes.php"    class="adm-nav-item"><i class="bi bi-signpost-split"></i> Rute</a>
    <a href="<?= APP_URL ?>/admin/users.php"     class="adm-nav-item active"><i class="bi bi-people"></i> Users</a>
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
    <div class="adm-topbar-title">Kelola Users</div>
    <div style="display:flex;align-items:center;gap:10px;font-size:.83rem;color:rgba(255,255,255,.5);">
      <div class="adm-avatar"><?= strtoupper(substr($_SESSION['nama'],0,1)) ?></div><?= clean($_SESSION['nama']) ?>
    </div>
  </div>
  <div class="adm-content">
    <?php if($msg): ?><div class="adm-msg ok"><i class="bi bi-check-circle"></i> <?= $msg ?></div><?php endif; ?>

    <!-- Toolbar -->
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
      <form method="GET" class="adm-search">
        <i class="bi bi-search" style="color:rgba(255,255,255,.3);"></i>
        <input type="text" name="q" placeholder="Cari nama atau email..." value="<?= $search ?>">
        <input type="hidden" name="role" value="<?= $role_f ?>">
      </form>
      <div style="display:flex;gap:4px;">
        <a href="?role=" class="adm-ftab <?= $role_f===''?'active':'' ?>">Semua</a>
        <a href="?role=user" class="adm-ftab <?= $role_f==='user'?'active':'' ?>">Member</a>
        <a href="?role=admin" class="adm-ftab <?= $role_f==='admin'?'active':'' ?>">Admin</a>
      </div>
    </div>

    <!-- Table -->
    <div class="adm-table-card">
      <div style="overflow-x:auto;">
        <table class="adm-table">
          <thead><tr>
            <th>Pengguna</th><th>No. HP</th><th>Role</th>
            <th>Booking</th><th>Status</th><th>Bergabung</th><th>Aksi</th>
          </tr></thead>
          <tbody>
            <?php if(empty($users)): ?>
              <tr><td colspan="7" style="text-align:center;padding:40px;color:rgba(255,255,255,.2);">Tidak ada data</td></tr>
            <?php else: foreach($users as $u): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div class="user-avatar"><?= strtoupper(substr($u['nama'],0,1)) ?></div>
                    <div>
                      <div style="font-weight:700;color:#fff;"><?= clean($u['nama']) ?></div>
                      <div style="font-size:.72rem;color:rgba(255,255,255,.35);"><?= clean($u['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="color:rgba(255,255,255,.5);"><?= clean($u['no_hp'] ?? '-') ?></td>
                <td>
                  <span style="padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:700;background:<?= $u['role']==='admin'?'rgba(99,102,241,.15)':'rgba(37,99,235,.12)' ?>;color:<?= $u['role']==='admin'?'#A5B4FC':'#60A5FA' ?>;">
                    <?= $u['role']==='admin'?'Admin':'Member' ?>
                  </span>
                </td>
                <td>
                  <span style="color:#fff;font-weight:700;"><?= $u['jml_booking'] ?></span>
                  <span style="color:rgba(255,255,255,.3);font-size:.72rem;"> / <?= $u['jml_paid'] ?> lunas</span>
                </td>
                <td>
                  <span style="padding:3px 8px;border-radius:50px;font-size:.7rem;font-weight:700;background:<?= $u['is_aktif']?'rgba(16,185,129,.12)':'rgba(239,68,68,.12)' ?>;color:<?= $u['is_aktif']?'#34D399':'#F87171' ?>;">
                    <?= $u['is_aktif']?'Aktif':'Nonaktif' ?>
                  </span>
                </td>
                <td style="font-size:.75rem;color:rgba(255,255,255,.4);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                  <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <!-- Toggle aktif -->
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="act" value="toggle_aktif">
                      <input type="hidden" name="id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="is_aktif" value="<?= $u['is_aktif'] ?>">
                      <button type="submit" class="adm-btn <?= $u['is_aktif']?'sm-danger':'sm-edit' ?>"
                              onclick="return confirm('<?= $u['is_aktif']?'Nonaktifkan':'Aktifkan' ?> user ini?')">
                        <i class="bi bi-<?= $u['is_aktif']?'pause-circle':'play-circle' ?>"></i>
                      </button>
                    </form>
                    <!-- Toggle role (jangan ubah diri sendiri) -->
                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="act" value="ganti_role">
                      <input type="hidden" name="id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="role" value="<?= $u['role']==='admin'?'user':'admin' ?>">
                      <button type="submit" class="adm-btn sm-warn"
                              onclick="return confirm('Ubah role jadi <?= $u['role']==='admin'?'Member':'Admin' ?>?')">
                        <i class="bi bi-arrow-repeat"></i>
                      </button>
                    </form>
                    <!-- Reset password -->
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="act" value="reset_pw">
                      <input type="hidden" name="id" value="<?= $u['id'] ?>">
                      <button type="submit" class="adm-btn sm-warn"
                              onclick="return confirm('Reset password ke: travelgo123?')">
                        <i class="bi bi-key"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if($totalPage>1): ?>
      <div class="pagination-wrap">
        <a href="?q=<?=$search?>&role=<?=$role_f?>&page=<?=max(1,$page-1)?>" class="page-btn <?=$page<=1?'disabled':''?>"><i class="bi bi-chevron-left"></i></a>
        <?php for($p=1;$p<=$totalPage;$p++): ?><a href="?q=<?=$search?>&role=<?=$role_f?>&page=<?=$p?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a><?php endfor; ?>
        <a href="?q=<?=$search?>&role=<?=$role_f?>&page=<?=min($totalPage,$page+1)?>" class="page-btn <?=$page>=$totalPage?'disabled':''?>"><i class="bi bi-chevron-right"></i></a>
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>