<?php
// ============================================================
//  TravelGo — Admin Bookings (admin/bookings.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$pageTitle = 'Kelola Booking';

// ---- Filter & pagination ----
$status_filter = clean($_GET['status'] ?? '');
$search        = clean($_GET['q']      ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 15;
$offset        = ($page - 1) * $perPage;

$whereArr = [];
if ($status_filter) $whereArr[] = "b.status = '$status_filter'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $whereArr[] = "(b.kode_booking LIKE '%$s%' OR u.nama LIKE '%$s%' OR u.email LIKE '%$s%')";
}
$where = $whereArr ? 'WHERE ' . implode(' AND ', $whereArr) : '';

// Total
$total = (int)$conn->query(
    "SELECT COUNT(*) FROM bookings b JOIN users u ON b.user_id=u.id $where"
)->fetch_row()[0];
$totalPage = max(1, ceil($total / $perPage));

// Data
$bookings = $conn->query("
    SELECT b.id, b.kode_booking, b.status, b.total_harga,
           b.jml_dewasa, b.jml_anak, b.tipe_perjalanan, b.created_at,
           u.nama AS user_nama, u.email AS user_email,
           j.tanggal_berangkat, j.jam_berangkat, j.kelas,
           o.nama AS operator_nama,
           ka.nama AS kota_asal, kt.nama AS kota_tujuan,
           jt.nama AS jenis_nama,
           pay.status AS pay_status, pay.metode
    FROM bookings b
    JOIN users u               ON b.user_id = u.id
    JOIN jadwal j              ON b.jadwal_id = j.id
    JOIN rute r                ON j.rute_id = r.id
    JOIN operator o            ON r.operator_id = o.id
    JOIN kota ka               ON r.kota_asal_id = ka.id
    JOIN kota kt               ON r.kota_tujuan_id = kt.id
    JOIN jenis_transportasi jt ON r.jenis_transportasi_id = jt.id
    LEFT JOIN pembayaran pay   ON b.id = pay.booking_id
    $where
    ORDER BY b.created_at DESC
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

$statusLabel = [
    'pending'   => ['label'=>'Menunggu', 'color'=>'#F59E0B','bg'=>'rgba(245,158,11,.12)'],
    'paid'      => ['label'=>'Lunas',    'color'=>'#10B981','bg'=>'rgba(16,185,129,.12)'],
    'cancelled' => ['label'=>'Batal',    'color'=>'#EF4444','bg'=>'rgba(239,68,68,.12)'],
    'expired'   => ['label'=>'Expired',  'color'=>'#6B7280','bg'=>'rgba(107,114,128,.12)'],
    'confirmed' => ['label'=>'Konfirm',  'color'=>'#3B82F6','bg'=>'rgba(59,130,246,.12)'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0F172A; color: #E2E8F0; margin: 0; }
    .adm-sidebar {
      width: 240px; height: 100vh; position: fixed; left: 0; top: 0;
      background: #111827; border-right: 1px solid rgba(255,255,255,.06);
      display: flex; flex-direction: column; z-index: 100; overflow-y: auto;
    }
    .adm-brand { padding: 20px 20px 16px; border-bottom: 1px solid rgba(255,255,255,.06); display: flex; align-items: center; gap: 10px; }
    .adm-brand-icon { width: 36px; height: 36px; background: var(--blue); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #fff; }
    .adm-brand-name { font-size: .95rem; font-weight: 800; color: #fff; }
    .adm-brand-sub  { font-size: .65rem; color: rgba(255,255,255,.3); }
    .adm-nav { padding: 12px 10px; flex: 1; }
    .adm-nav-label { font-size: .65rem; font-weight: 700; color: rgba(255,255,255,.25); text-transform: uppercase; letter-spacing: .6px; padding: 8px 10px 4px; }
    .adm-nav-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; color: rgba(255,255,255,.55); font-size: .85rem; font-weight: 600; text-decoration: none; transition: .2s; margin-bottom: 2px; }
    .adm-nav-item:hover { background: rgba(255,255,255,.06); color: #fff; }
    .adm-nav-item.active { background: rgba(37,99,235,.2); color: #60A5FA; }
    .adm-nav-footer { padding: 12px 10px; border-top: 1px solid rgba(255,255,255,.06); }
    .adm-main { margin-left: 240px; min-height: 100vh; }
    .adm-topbar { background: #111827; border-bottom: 1px solid rgba(255,255,255,.06); padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
    .adm-topbar-title { font-size: 1rem; font-weight: 800; color: #fff; }
    .adm-avatar { width: 30px; height: 30px; border-radius: 50%; background: var(--blue); display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 800; color: #fff; }
    .adm-content { padding: 24px 28px; }

    .adm-toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .adm-search {
      display: flex; align-items: center; gap: 8px;
      background: #1E293B; border: 1px solid rgba(255,255,255,.08);
      border-radius: 8px; padding: 8px 14px; flex: 1; min-width: 220px;
    }
    .adm-search input {
      background: none; border: none; outline: none;
      color: #fff; font-size: .85rem; flex: 1;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .adm-search input::placeholder { color: rgba(255,255,255,.25); }
    .adm-search .bi { color: rgba(255,255,255,.3); }

    .adm-filter-tabs { display: flex; gap: 4px; flex-wrap: wrap; }
    .adm-ftab {
      padding: 7px 14px; border-radius: 8px; font-size: .78rem; font-weight: 700;
      border: 1px solid rgba(255,255,255,.08); color: rgba(255,255,255,.5);
      background: #1E293B; text-decoration: none; transition: .2s;
    }
    .adm-ftab:hover { color: #fff; border-color: rgba(255,255,255,.2); }
    .adm-ftab.active { background: var(--blue); color: #fff; border-color: var(--blue); }

    .adm-table-card { background: #1E293B; border: 1px solid rgba(255,255,255,.06); border-radius: 12px; overflow: hidden; }
    .adm-table { width: 100%; border-collapse: collapse; }
    .adm-table th { padding: 10px 14px; font-size: .7rem; font-weight: 700; color: rgba(255,255,255,.3); text-transform: uppercase; letter-spacing: .4px; text-align: left; background: rgba(255,255,255,.02); border-bottom: 1px solid rgba(255,255,255,.05); }
    .adm-table td { padding: 12px 14px; font-size: .82rem; color: rgba(255,255,255,.75); border-bottom: 1px solid rgba(255,255,255,.04); }
    .adm-table tr:last-child td { border-bottom: none; }
    .adm-table tr:hover td { background: rgba(255,255,255,.02); }
    .st-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 50px; font-size: .7rem; font-weight: 700; }

    .adm-action-btn {
      padding: 4px 10px; border-radius: 6px; font-size: .72rem; font-weight: 700;
      border: none; cursor: pointer; transition: .2s; text-decoration: none;
      display: inline-flex; align-items: center; gap: 4px;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .adm-action-btn.view   { background: rgba(37,99,235,.15); color: #60A5FA; }
    .adm-action-btn.confirm{ background: rgba(16,185,129,.15); color: #34D399; }
    .adm-action-btn.cancel { background: rgba(239,68,68,.12); color: #F87171; }
    .adm-action-btn:hover  { opacity: .85; }

    .pagination-wrap { display: flex; justify-content: center; gap: 5px; margin-top: 16px; flex-wrap: wrap; }
    .page-btn { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: .82rem; font-weight: 700; text-decoration: none; border: 1px solid rgba(255,255,255,.08); color: rgba(255,255,255,.5); background: #1E293B; transition: .2s; }
    .page-btn:hover  { color: #fff; border-color: rgba(255,255,255,.2); }
    .page-btn.active { background: var(--blue); color: #fff; border-color: var(--blue); }
    .page-btn.disabled { opacity: .3; pointer-events: none; }

    @media(max-width:767px) { .adm-sidebar { display: none; } .adm-main { margin-left: 0; } }
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
    <a href="<?= APP_URL ?>/admin/bookings.php"  class="adm-nav-item active"><i class="bi bi-ticket-detailed"></i> Bookings</a>
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
    <a href="<?= APP_URL ?>/api/auth.php?action=logout" class="adm-nav-item" style="color:rgba(239,68,68,.7);"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>
</aside>

<div class="adm-main">
  <div class="adm-topbar">
    <div class="adm-topbar-title">Kelola Booking</div>
    <div style="display:flex;align-items:center;gap:10px;font-size:.83rem;color:rgba(255,255,255,.5);">
      <div class="adm-avatar"><?= strtoupper(substr($_SESSION['nama'],0,1)) ?></div>
      <?= clean($_SESSION['nama']) ?>
    </div>
  </div>

  <div class="adm-content">

    <!-- Toolbar -->
    <div class="adm-toolbar">
      <form method="GET" action="" class="adm-search">
        <i class="bi bi-search"></i>
        <input type="text" name="q" placeholder="Cari kode booking, nama, email..." value="<?= $search ?>">
        <input type="hidden" name="status" value="<?= $status_filter ?>">
      </form>
      <div class="adm-filter-tabs">
        <a href="?status=" class="adm-ftab <?= $status_filter===''?'active':'' ?>">Semua (<?= $total ?>)</a>
        <?php foreach(['paid'=>'Lunas','pending'=>'Pending','cancelled'=>'Batal','expired'=>'Expired'] as $k=>$l): ?>
          <?php $cnt = $conn->query("SELECT COUNT(*) FROM bookings WHERE status='$k'")->fetch_row()[0]; ?>
          <a href="?status=<?= $k ?>" class="adm-ftab <?= $status_filter===$k?'active':'' ?>"><?= $l ?> (<?= $cnt ?>)</a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Table -->
    <div class="adm-table-card">
      <div style="overflow-x:auto;">
        <table class="adm-table">
          <thead>
            <tr>
              <th>Kode Booking</th>
              <th>Pengguna</th>
              <th>Rute</th>
              <th>Tgl Berangkat</th>
              <th>Penumpang</th>
              <th>Total</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($bookings)): ?>
              <tr><td colspan="8" style="text-align:center;padding:40px;color:rgba(255,255,255,.2);">Tidak ada data</td></tr>
            <?php else: ?>
              <?php foreach ($bookings as $bk):
                $st = $statusLabel[$bk['status']] ?? $statusLabel['pending'];
              ?>
                <tr>
                  <td style="font-family:monospace;font-weight:700;color:#fff;font-size:.78rem;"><?= clean($bk['kode_booking']) ?></td>
                  <td>
                    <div style="font-weight:600;color:#fff;"><?= clean($bk['user_nama']) ?></div>
                    <div style="font-size:.72rem;color:rgba(255,255,255,.35);"><?= clean($bk['user_email']) ?></div>
                  </td>
                  <td>
                    <div><?= clean($bk['kota_asal']) ?> → <?= clean($bk['kota_tujuan']) ?></div>
                    <div style="font-size:.72rem;color:rgba(255,255,255,.35);"><?= clean($bk['jenis_nama']) ?> · <?= clean($bk['operator_nama']) ?></div>
                  </td>
                  <td style="font-size:.78rem;"><?= date('d M Y', strtotime($bk['tanggal_berangkat'])) ?><br><span style="color:rgba(255,255,255,.35);"><?= substr($bk['jam_berangkat'],0,5) ?></span></td>
                  <td style="text-align:center;"><?= $bk['jml_dewasa'] + $bk['jml_anak'] ?></td>
                  <td style="color:#60A5FA;font-weight:700;"><?= formatRupiah($bk['total_harga']) ?></td>
                  <td><span class="st-badge" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;"><?= $st['label'] ?></span></td>
                  <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                      <a href="<?= APP_URL ?>/pages/ticket.php?booking_id=<?= $bk['id'] ?>" class="adm-action-btn view" target="_blank"><i class="bi bi-eye"></i></a>
                      <?php if ($bk['status'] === 'pending'): ?>
                        <button class="adm-action-btn confirm" onclick="confirmPay(<?= $bk['id'] ?>)"><i class="bi bi-check-lg"></i></button>
                        <button class="adm-action-btn cancel"  onclick="cancelBook(<?= $bk['id'] ?>)"><i class="bi bi-x-lg"></i></button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPage > 1): ?>
      <div class="pagination-wrap">
        <a href="?status=<?= $status_filter ?>&q=<?= $search ?>&page=<?= max(1,$page-1) ?>" class="page-btn <?= $page<=1?'disabled':'' ?>"><i class="bi bi-chevron-left"></i></a>
        <?php for($p=1;$p<=$totalPage;$p++): ?>
          <a href="?status=<?= $status_filter ?>&q=<?= $search ?>&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="?status=<?= $status_filter ?>&q=<?= $search ?>&page=<?= min($totalPage,$page+1) ?>" class="page-btn <?= $page>=$totalPage?'disabled':'' ?>"><i class="bi bi-chevron-right"></i></a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmPay(id) {
  if (!confirm('Konfirmasi pembayaran booking ini?')) return;
  fetch('<?= APP_URL ?>/api/payment.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=konfirmasi&booking_id='+id+'&kode_bayar=ADMIN'
  }).then(r=>r.json()).then(d=>{ if(d.success){location.reload();}else{alert(d.message);} });
}
function cancelBook(id) {
  if (!confirm('Batalkan booking ini?')) return;
  fetch('<?= APP_URL ?>/api/cancel.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'booking_id='+id
  }).then(r=>r.json()).then(d=>{ if(d.success){location.reload();}else{alert(d.message);} });
}
</script>
</body>
</html>