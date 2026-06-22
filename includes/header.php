<?php
date_default_timezone_set('Asia/Jakarta');
// Header ini di-include di semua halaman pages/
// Pastikan $conn dan session sudah diset via config.php sebelum include ini
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/config.php';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? clean($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
  <meta name="description" content="<?= APP_NAME ?> — Pesan tiket pesawat, kereta, bus, kapal & travel murah dan mudah.">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link href="/assets/css/style.css" rel="stylesheet">

  <!-- Notifikasi styles -->
  <style>
    .notif-wrap { position:relative; }
    .notif-bell {
      width:38px; height:38px; border-radius:50%;
      background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2);
      display:flex; align-items:center; justify-content:center;
      color:#fff; font-size:1rem; cursor:pointer; transition:.2s; position:relative;
    }
    .notif-bell:hover { background:rgba(255,255,255,.18); }
    .notif-badge {
      position:absolute; top:-4px; right:-4px;
      background:#EF4444; color:#fff; font-size:.6rem; font-weight:800;
      min-width:18px; height:18px; border-radius:50%;
      display:none; align-items:center; justify-content:center;
      border:2px solid #0B1426;
    }
    .notif-badge.show { display:flex; }
    .notif-dropdown {
      position:absolute; top:calc(100% + 10px); right:0;
      width:320px; background:#1E293B;
      border:1px solid rgba(255,255,255,.1);
      border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.4);
      z-index:9999; display:none; overflow:hidden;
    }
    .notif-dropdown.open { display:block; animation:notifFade .2s ease; }
    @keyframes notifFade { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
    .notif-header {
      padding:14px 16px; border-bottom:1px solid rgba(255,255,255,.08);
      display:flex; align-items:center; justify-content:space-between;
    }
    .notif-header-title { font-size:.85rem; font-weight:800; color:#fff; }
    .notif-read-all { font-size:.72rem; color:#60A5FA; font-weight:600; cursor:pointer; background:none; border:none; padding:0; }
    .notif-list { max-height:300px; overflow-y:auto; scrollbar-width:thin; }
    .notif-item {
      padding:12px 16px; border-bottom:1px solid rgba(255,255,255,.05);
      cursor:pointer; transition:.15s; display:flex; gap:10px;
    }
    .notif-item:hover { background:rgba(255,255,255,.04); }
    .notif-item.unread { background:rgba(37,99,235,.08); }
    .notif-item-title { font-size:.82rem; font-weight:700; color:#fff; margin-bottom:3px; }
    .notif-item-pesan { font-size:.75rem; color:rgba(255,255,255,.5); line-height:1.4; }
    .notif-item-time  { font-size:.68rem; color:rgba(255,255,255,.3); margin-top:4px; }
    .notif-tipe-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:5px; }
    .notif-empty { padding:32px 16px; text-align:center; color:rgba(255,255,255,.3); font-size:.82rem; }
  </style>

  <?= isset($extraHead) ? $extraHead : '' ?>
</head>
<body>

<!-- =========================================================
     NAVBAR
========================================================= -->
<nav class="navbar navbar-expand-lg tg-navbar sticky-top">
  <div class="container">

    <!-- Logo -->
    <a class="navbar-brand tg-logo" href="<?= APP_URL ?>/index.php">
      <i class="bi bi-airplane-fill"></i> Travel<span>Go</span>
    </a>

    <!-- Hamburger mobile -->
    <button class="navbar-toggler border-0" type="button"
            data-bs-toggle="collapse" data-bs-target="#navMenu">
      <i class="bi bi-list fs-4 text-white"></i>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">

      <!-- Menu transportasi -->
      <ul class="navbar-nav mx-auto gap-1">
        <?php
          $menuItems = [
            ['key'=>'pesawat','icon'=>'airplane',    'label'=>'Pesawat', 'jenis'=>1],
            ['key'=>'kereta', 'icon'=>'train-front', 'label'=>'Kereta',  'jenis'=>2],
            ['key'=>'bus',    'icon'=>'bus-front',   'label'=>'Bus',     'jenis'=>3],
            ['key'=>'kapal',  'icon'=>'water',       'label'=>'Kapal',   'jenis'=>4],
            ['key'=>'travel', 'icon'=>'car-front',   'label'=>'Travel',  'jenis'=>5],
          ];
          foreach ($menuItems as $m):
            $href = isLogin()
              ? APP_URL . '/pages/search.php?jenis=' . $m['jenis']
              : APP_URL . '/pages/login.php';
        ?>
          <li class="nav-item">
            <a class="nav-link tg-nav-link <?= (isset($activeMenu) && $activeMenu === $m['key']) ? 'active' : '' ?>"
               href="<?= $href ?>">
              <i class="bi bi-<?= $m['icon'] ?>"></i> <?= $m['label'] ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <!-- Tombol login / profil -->
      <div class="d-flex align-items-center gap-2">
        <?php if (isLogin()): ?>

          <!-- Bell notifikasi -->
          <div class="notif-wrap" id="notifWrap">
            <div class="notif-bell" id="notifBell" onclick="toggleNotif(event)">
              <i class="bi bi-bell"></i>
              <span class="notif-badge" id="notifBadge"></span>
            </div>
            <div class="notif-dropdown" id="notifDropdown">
              <div class="notif-header">
                <span class="notif-header-title">🔔 Notifikasi</span>
                <button class="notif-read-all" onclick="readAllNotif()">Tandai semua dibaca</button>
              </div>
              <div class="notif-list" id="notifList">
                <div class="notif-empty">Memuat...</div>
              </div>
            </div>
          </div>
          <!-- Dropdown profil -->
          <div class="dropdown">
            <button class="btn tg-btn-outline dropdown-toggle d-flex align-items-center gap-2"
                    data-bs-toggle="dropdown">
              <div style="width:28px;height:28px;border-radius:50%;background:var(--blue);
                          display:flex;align-items:center;justify-content:center;
                          font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0;">
                <?= strtoupper(substr($_SESSION['nama'] ?? 'U', 0, 1)) ?>
              </div>
              <?= clean($_SESSION['nama'] ?? 'User') ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end tg-dropdown">
              <li>
                <a class="dropdown-item" href="<?= APP_URL ?>/pages/dashboard.php">
                  <i class="bi bi-speedometer2"></i> Dashboard
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="<?= APP_URL ?>/pages/profile.php">
                  <i class="bi bi-person"></i> Profil Saya
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="<?= APP_URL ?>/pages/history.php">
                  <i class="bi bi-clock-history"></i> Riwayat Booking
                </a>
              </li>
              <?php if (isAdmin()): ?>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item text-warning" href="<?= APP_URL ?>/admin/dashboard.php">
                  <i class="bi bi-shield-fill"></i> Admin Panel
                </a>
              </li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item text-danger" href="<?= APP_URL ?>/api/auth.php?action=logout">
                  <i class="bi bi-box-arrow-right"></i> Logout
                </a>
              </li>
            </ul>
          </div>

        <?php else: ?>
          <!-- Belum login -->
          <a href="<?= APP_URL ?>/pages/login.php" class="btn tg-btn-outline">
            <i class="bi bi-person"></i> Masuk
          </a>
          <a href="<?= APP_URL ?>/pages/register.php" class="btn tg-btn-primary">
            Daftar Gratis
          </a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</nav>
<!-- /NAVBAR -->
<?php if (isLogin()): ?>
<script>
// ============================================================
//  Notifikasi — load dari MongoDB via API
// ============================================================
function toggleNotif(e) {
  e.stopPropagation();
  const dd = document.getElementById('notifDropdown');
  dd.classList.toggle('open');
  if (dd.classList.contains('open')) loadNotif();
}

document.addEventListener('click', () => {
  document.getElementById('notifDropdown')?.classList.remove('open');
});

function loadNotif() {
  fetch('<?= APP_URL ?>/api/notification.php?action=list')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;

      // Update badge
      const badge = document.getElementById('notifBadge');
      if (data.unread > 0) {
        badge.textContent = data.unread > 9 ? '9+' : data.unread;
        badge.classList.add('show');
      } else {
        badge.classList.remove('show');
      }

      // Render list
      const list = document.getElementById('notifList');
      if (!data.data || data.data.length === 0) {
        list.innerHTML = '<div class="notif-empty">Tidak ada notifikasi</div>';
        return;
      }

      const tipeColor = { info:'#60A5FA', promo:'#FBBF24', warning:'#F87171', success:'#34D399' };
      list.innerHTML = data.data.map(n => `
        <div class="notif-item ${!n.is_read ? 'unread' : ''}"
             onclick="readNotif('${n.id}', this)">
          <div class="notif-tipe-dot" style="background:${tipeColor[n.tipe]||'#60A5FA'};"></div>
          <div style="flex:1;">
            <div class="notif-item-title">${n.judul}</div>
            <div class="notif-item-pesan">${n.pesan}</div>
            <div class="notif-item-time">${n.created_at}</div>
          </div>
        </div>
      `).join('');
    })
    .catch(() => {});
}

function readNotif(id, el) {
  el.classList.remove('unread');
  fetch('<?= APP_URL ?>/api/notification.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=read&notif_id=' + id
  });
  // Update badge count
  const badge = document.getElementById('notifBadge');
  const cur   = parseInt(badge.textContent) || 0;
  if (cur <= 1) badge.classList.remove('show');
  else badge.textContent = cur - 1;
}

function readAllNotif() {
  document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
  document.getElementById('notifBadge').classList.remove('show');
  fetch('<?= APP_URL ?>/api/notification.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=read_all'
  });
}

// Load badge count saat halaman dibuka
window.addEventListener('DOMContentLoaded', () => {
  fetch('<?= APP_URL ?>/api/notification.php?action=list')
    .then(r => r.json())
    .then(data => {
      if (data.success && data.unread > 0) {
        const badge = document.getElementById('notifBadge');
        badge.textContent = data.unread > 9 ? '9+' : data.unread;
        badge.classList.add('show');
      }
    }).catch(() => {});
});
</script>
<?php endif; ?>