<?php
// ============================================================
//  TravelGo — Admin Reviews (admin/reviews.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mongodb.php';
requireAdmin();

$pageTitle = 'Kelola Ulasan';
$msg = ''; $err = '';

// ---- Proses approve/reject ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act      = clean($_POST['act'] ?? '');
    $reviewId = clean($_POST['review_id'] ?? '');

    $col = mongoCol('reviews');
    if ($col && $reviewId) {
        try {
            $oid = new MongoDB\BSON\ObjectId($reviewId);
            if ($act === 'approve') {
                $col->updateOne(['_id' => $oid], ['$set' => ['is_approved' => true]]);
                $msg = 'Ulasan berhasil disetujui.';
            } elseif ($act === 'reject') {
                $col->deleteOne(['_id' => $oid]);
                $msg = 'Ulasan berhasil dihapus.';
            }
        } catch (Exception $e) {
            $err = 'Gagal memproses ulasan.';
        }
    }
    header('Location: ?msg='.urlencode($msg).'&err='.urlencode($err));
    exit;
}

if (isset($_GET['msg'])) $msg = clean($_GET['msg']);
if (isset($_GET['err'])) $err = clean($_GET['err']);

// ---- Filter ----
$filter_status = clean($_GET['status'] ?? 'pending');

// ---- Ambil data reviews dari MongoDB ----
$reviews   = [];
$totalAll  = 0;
$totalPend = 0;
$totalAppr = 0;

$col = mongoCol('reviews');
if ($col) {
    try {
        $filterQuery = $filter_status === 'approved'
            ? ['is_approved' => true]
            : ['is_approved' => false];

        $cursor = $col->find($filterQuery, ['sort' => ['created_at' => -1]]);
        foreach ($cursor as $doc) {
            $reviews[] = [
                'id'          => (string)$doc['_id'],
                'user_nama'   => $doc['user_nama']  ?? 'Anonim',
                'operator'    => $doc['operator']   ?? '-',
                'rute'        => $doc['rute']        ?? '-',
                'rating'      => (int)($doc['rating'] ?? 5),
                'komentar'    => $doc['komentar']    ?? '',
                'is_approved' => $doc['is_approved'] ?? false,
                'created_at'  => isset($doc['created_at'])
                    ? date('d M Y H:i', $doc['created_at']->toDateTime()->getTimestamp())
                    : '-',
            ];
        }
        $totalPend = $col->countDocuments(['is_approved' => false]);
        $totalAppr = $col->countDocuments(['is_approved' => true]);
        $totalAll  = $totalPend + $totalAppr;
    } catch (Exception $e) {
        $err = 'Gagal memuat data dari MongoDB.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
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
    .adm-main{margin-left:240px;min-height:100vh}
    .adm-topbar{background:#111827;border-bottom:1px solid rgba(255,255,255,.06);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
    .adm-topbar-title{font-size:1rem;font-weight:800;color:#fff}
    .adm-avatar{width:30px;height:30px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff}
    .adm-content{padding:24px 28px}
    .adm-msg{padding:10px 14px;border-radius:8px;font-size:.82rem;font-weight:500;margin-bottom:14px}
    .adm-msg.ok{background:rgba(16,185,129,.1);color:#34D399;border:1px solid rgba(16,185,129,.2)}
    .adm-msg.er{background:rgba(239,68,68,.1);color:#F87171;border:1px solid rgba(239,68,68,.2)}

    /* Stats */
    .rv-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px}
    .rv-stat{background:#1E293B;border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:18px 20px;display:flex;align-items:center;gap:14px}
    .rv-stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
    .rv-stat-num{font-size:1.4rem;font-weight:800;color:#fff;line-height:1}
    .rv-stat-label{font-size:.72rem;color:rgba(255,255,255,.4);margin-top:3px}

    /* Filter tabs */
    .rv-tabs{display:flex;gap:6px;margin-bottom:16px}
    .rv-tab{padding:7px 16px;border-radius:8px;font-size:.82rem;font-weight:700;border:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.5);background:#1E293B;text-decoration:none;transition:.2s}
    .rv-tab:hover{color:#fff}.rv-tab.active{background:var(--blue);color:#fff;border-color:var(--blue)}

    /* Review cards */
    .rv-card{background:#1E293B;border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:18px 20px;margin-bottom:12px;transition:.2s}
    .rv-card:hover{border-color:rgba(255,255,255,.12)}
    .rv-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px}
    .rv-user{display:flex;align-items:center;gap:10px}
    .rv-avatar{width:36px;height:36px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:#fff;flex-shrink:0}
    .rv-name{font-size:.9rem;font-weight:700;color:#fff}
    .rv-meta{font-size:.72rem;color:rgba(255,255,255,.4);margin-top:1px}
    .rv-stars{display:flex;gap:2px}
    .rv-star{color:#FCD34D;font-size:.9rem}
    .rv-star.empty{color:rgba(255,255,255,.15)}
    .rv-komentar{font-size:.85rem;color:rgba(255,255,255,.7);line-height:1.6;background:rgba(255,255,255,.03);border-radius:8px;padding:12px 14px;margin:10px 0;font-style:italic}
    .rv-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
    .rv-badge{padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700}
    .rv-badge.pending{background:rgba(245,158,11,.12);color:#FBBF24}
    .rv-badge.approved{background:rgba(16,185,129,.12);color:#34D399}
    .rv-actions{display:flex;gap:8px}
    .rv-btn{padding:6px 14px;border-radius:8px;font-size:.78rem;font-weight:700;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:.2s;display:flex;align-items:center;gap:5px}
    .rv-btn.approve{background:rgba(16,185,129,.15);color:#34D399}
    .rv-btn.approve:hover{background:rgba(16,185,129,.25)}
    .rv-btn.reject{background:rgba(239,68,68,.12);color:#F87171}
    .rv-btn.reject:hover{background:rgba(239,68,68,.2)}

    .empty-state{text-align:center;padding:60px 20px;color:rgba(255,255,255,.3)}
    .empty-state .icon{font-size:3rem;margin-bottom:12px}

    @media(max-width:767px){.adm-sidebar{display:none}.adm-main{margin-left:0}.rv-stats{grid-template-columns:1fr 1fr}}
  </style>
</head>
<body>

<aside class="adm-sidebar">
  <div class="adm-brand">
    <div class="adm-brand-icon"><i class="bi bi-airplane-fill"></i></div>
    <div><div class="adm-brand-name">TravelGo</div><div class="adm-brand-sub">Admin Panel</div></div>
  </div>
  <nav class="adm-nav">
    <div class="adm-nav-label">Utama</div>
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="adm-nav-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="<?= APP_URL ?>/admin/bookings.php"  class="adm-nav-item"><i class="bi bi-ticket-detailed"></i> Bookings</a>
    <div class="adm-nav-label" style="margin-top:8px;">Master Data</div>
    <a href="<?= APP_URL ?>/admin/jadwal.php"    class="adm-nav-item"><i class="bi bi-calendar3"></i> Jadwal</a>
    <a href="<?= APP_URL ?>/admin/routes.php"    class="adm-nav-item"><i class="bi bi-signpost-split"></i> Rute</a>
    <a href="<?= APP_URL ?>/admin/users.php"     class="adm-nav-item"><i class="bi bi-people"></i> Users</a>
    </a>
     <a href="<?= APP_URL ?>/admin/reviews.php" class="adm-nav-item">
      <i class="bi bi-star"></i> Reviews
    </a>
    <div class="adm-nav-label" style="margin-top:8px;">Lainnya</div>
    <a href="<?= APP_URL ?>/index.php"           class="adm-nav-item"><i class="bi bi-globe"></i> Lihat Website</a>
  </nav>
  <div class="adm-nav-footer">
    <a href="<?= APP_URL ?>/api/auth.php?action=logout" class="adm-nav-item" style="color:rgba(239,68,68,.7);">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </div>
</aside>

<div class="adm-main">
  <div class="adm-topbar">
    <div class="adm-topbar-title">Kelola Ulasan</div>
    <div style="display:flex;align-items:center;gap:10px;font-size:.83rem;color:rgba(255,255,255,.5);">
      <div class="adm-avatar"><?= strtoupper(substr($_SESSION['nama'],0,1)) ?></div>
      <?= clean($_SESSION['nama']) ?>
    </div>
  </div>

  <div class="adm-content">

    <?php if($msg): ?><div class="adm-msg ok"><i class="bi bi-check-circle"></i> <?= $msg ?></div><?php endif; ?>
    <?php if($err): ?><div class="adm-msg er"><i class="bi bi-x-circle"></i> <?= $err ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="rv-stats">
      <div class="rv-stat">
        <div class="rv-stat-icon" style="background:rgba(37,99,235,.15);">
          <i class="bi bi-star-fill" style="color:#60A5FA;"></i>
        </div>
        <div>
          <div class="rv-stat-num"><?= $totalAll ?></div>
          <div class="rv-stat-label">Total Ulasan</div>
        </div>
      </div>
      <div class="rv-stat">
        <div class="rv-stat-icon" style="background:rgba(245,158,11,.15);">
          <i class="bi bi-clock" style="color:#FBBF24;"></i>
        </div>
        <div>
          <div class="rv-stat-num"><?= $totalPend ?></div>
          <div class="rv-stat-label">Menunggu Review</div>
        </div>
      </div>
      <div class="rv-stat">
        <div class="rv-stat-icon" style="background:rgba(16,185,129,.15);">
          <i class="bi bi-check-circle-fill" style="color:#34D399;"></i>
        </div>
        <div>
          <div class="rv-stat-num"><?= $totalAppr ?></div>
          <div class="rv-stat-label">Disetujui</div>
        </div>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="rv-tabs">
      <a href="?status=pending"  class="rv-tab <?= $filter_status==='pending'?'active':'' ?>">
        Menunggu <?php if($totalPend > 0): ?><span style="background:rgba(245,158,11,.2);color:#FBBF24;padding:1px 7px;border-radius:50px;font-size:.7rem;margin-left:4px;"><?= $totalPend ?></span><?php endif; ?>
      </a>
      <a href="?status=approved" class="rv-tab <?= $filter_status==='approved'?'active':'' ?>">
        Disetujui <span style="background:rgba(255,255,255,.08);padding:1px 7px;border-radius:50px;font-size:.7rem;margin-left:4px;"><?= $totalAppr ?></span>
      </a>
    </div>

    <!-- Review list -->
    <?php if (empty($reviews)): ?>
      <div class="empty-state">
        <div class="icon">⭐</div>
        <div style="font-weight:700;font-size:1rem;color:rgba(255,255,255,.5);margin-bottom:6px;">
          Tidak Ada Ulasan
        </div>
        <div style="font-size:.82rem;">
          <?= $filter_status === 'pending' ? 'Belum ada ulasan yang menunggu persetujuan.' : 'Belum ada ulasan yang disetujui.' ?>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($reviews as $rv): ?>
        <div class="rv-card">
          <div class="rv-card-header">
            <div class="rv-user">
              <div class="rv-avatar"><?= strtoupper(substr($rv['user_nama'],0,1)) ?></div>
              <div>
                <div class="rv-name"><?= clean($rv['user_nama']) ?></div>
                <div class="rv-meta">
                  <?= clean($rv['operator']) ?> · <?= clean($rv['rute']) ?>
                </div>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
              <div class="rv-stars">
                <?php for($i=1;$i<=5;$i++): ?>
                  <span class="rv-star <?= $i > $rv['rating'] ? 'empty' : '' ?>">★</span>
                <?php endfor; ?>
              </div>
              <span style="font-size:.8rem;color:rgba(255,255,255,.4);"><?= $rv['created_at'] ?></span>
            </div>
          </div>

          <div class="rv-komentar">"<?= clean($rv['komentar']) ?>"</div>

          <div class="rv-footer">
            <span class="rv-badge <?= $rv['is_approved'] ? 'approved' : 'pending' ?>">
              <i class="bi bi-<?= $rv['is_approved'] ? 'check-circle-fill' : 'clock' ?>"></i>
              <?= $rv['is_approved'] ? 'Disetujui' : 'Menunggu' ?>
            </span>
            <div class="rv-actions">
              <?php if (!$rv['is_approved']): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="act" value="approve">
                  <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
                  <button type="submit" class="rv-btn approve">
                    <i class="bi bi-check-lg"></i> Setujui
                  </button>
                </form>
              <?php endif; ?>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Hapus ulasan ini?')">
                <input type="hidden" name="act" value="reject">
                <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
                <button type="submit" class="rv-btn reject">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>