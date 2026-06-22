<?php
// ============================================================
//  TravelGo — Halaman Profil (pages/profile.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle  = 'Profil Saya';
$activeMenu = '';

$uid = $_SESSION['user_id'];

// ---- Ambil data user ----
$user = $conn->query("SELECT * FROM users WHERE id = $uid LIMIT 1")->fetch_assoc();

$error   = '';
$success = '';

// ---- Proses update profil ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean($_POST['action'] ?? '');

    // ---- Update data diri ----
    if ($action === 'update_profil') {
        $nama    = clean($_POST['nama']    ?? '');
        $no_hp   = clean($_POST['no_hp']   ?? '');

        if (empty($nama)) {
            $error = 'Nama tidak boleh kosong.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET nama = ?, no_hp = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('ssi', $nama, $no_hp, $uid);
            if ($stmt->execute()) {
                $_SESSION['nama'] = $nama;
                $success = 'Profil berhasil diperbarui.';
                $user['nama']  = $nama;
                $user['no_hp'] = $no_hp;
            } else {
                $error = 'Gagal memperbarui profil. Coba lagi.';
            }
        }
    }

    // ---- Ganti password ----
    if ($action === 'ganti_password') {
        $pw_lama  = $_POST['pw_lama']   ?? '';
        $pw_baru  = $_POST['pw_baru']   ?? '';
        $pw_conf  = $_POST['pw_konfirm'] ?? '';

        if (empty($pw_lama) || empty($pw_baru) || empty($pw_conf)) {
            $error = 'Semua kolom password wajib diisi.';
        } elseif (!password_verify($pw_lama, $user['password'])) {
            $error = 'Password lama tidak sesuai.';
        } elseif (strlen($pw_baru) < 8) {
            $error = 'Password baru minimal 8 karakter.';
        } elseif ($pw_baru !== $pw_conf) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $hash = password_hash($pw_baru, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $hash, $uid);
            if ($stmt->execute()) {
                $success = 'Password berhasil diubah.';
                $user['password'] = $hash;
            } else {
                $error = 'Gagal mengubah password. Coba lagi.';
            }
        }
    }
}

// ---- Statistik user ----
$statUser = $conn->query("
    SELECT
        COUNT(*) AS total_booking,
        SUM(status='paid') AS total_paid,
        SUM(total_harga) AS total_spent,
        MIN(created_at) AS member_since
    FROM bookings WHERE user_id = $uid
")->fetch_assoc();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.profile-page { background:var(--gray-100); min-height:100vh; padding:32px 0 64px; }

/* Hero */
.profile-hero {
  background:linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
  border-radius:var(--radius-lg); padding:28px 32px;
  margin-bottom:24px; position:relative; overflow:hidden;
  display:flex; align-items:center; gap:24px;
}
.profile-hero::before {
  content:''; position:absolute; inset:0;
  background-image:radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
  background-size:20px 20px;
}
.profile-avatar-big {
  width:72px; height:72px; border-radius:50%;
  background:var(--blue); display:flex; align-items:center;
  justify-content:center; font-size:1.8rem; font-weight:800;
  color:#fff; flex-shrink:0; position:relative; z-index:1;
  border:3px solid rgba(255,255,255,.2);
}
.profile-hero-info { position:relative; z-index:1; flex:1; }
.profile-hero-name {
  font-size:1.3rem; font-weight:800; color:#fff; margin:0 0 4px;
}
.profile-hero-email { font-size:.85rem; color:rgba(255,255,255,.6); }
.profile-hero-badge {
  display:inline-flex; align-items:center; gap:5px;
  background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2);
  color:rgba(255,255,255,.85); padding:3px 10px;
  border-radius:50px; font-size:.72rem; font-weight:700;
  margin-top:6px;
}

/* Stats row */
.profile-stats {
  display:flex; gap:0; position:relative; z-index:1;
  flex-shrink:0;
}
.profile-stat {
  text-align:center; padding:0 20px;
  border-left:1px solid rgba(255,255,255,.1);
}
.profile-stat:first-child { border-left:none; padding-left:0; }
.profile-stat-num { font-size:1.4rem; font-weight:800; color:#fff; line-height:1; }
.profile-stat-label { font-size:.72rem; color:rgba(255,255,255,.5); margin-top:2px; }

/* Tab nav */
.profile-tabs {
  display:flex; gap:0; background:var(--white);
  border:1px solid var(--gray-200); border-radius:var(--radius-lg);
  padding:6px; margin-bottom:20px; overflow-x:auto;
}
.profile-tab {
  flex:1; padding:10px 16px; border-radius:var(--radius-md);
  font-size:.85rem; font-weight:600; color:var(--gray-500);
  cursor:pointer; transition:var(--transition);
  display:flex; align-items:center; justify-content:center;
  gap:6px; white-space:nowrap; border:none; background:none;
  font-family:'Plus Jakarta Sans',sans-serif;
}
.profile-tab:hover { color:var(--blue); }
.profile-tab.active {
  background:var(--blue); color:#fff;
  box-shadow:var(--shadow-sm);
}

/* Tab content */
.tab-content-panel { display:none; }
.tab-content-panel.active { display:block; }

/* Form card */
.form-card {
  background:var(--white); border:1px solid var(--gray-200);
  border-radius:var(--radius-lg); padding:24px 28px;
  margin-bottom:16px;
}
.form-card-title {
  font-size:1rem; font-weight:800; color:var(--navy);
  margin-bottom:20px; padding-bottom:12px;
  border-bottom:1px solid var(--gray-100);
  display:flex; align-items:center; gap:10px;
}
.form-card-title .bi {
  width:32px; height:32px; background:rgba(37,99,235,.08);
  border-radius:8px; display:flex; align-items:center;
  justify-content:center; color:var(--blue); font-size:1rem;
}

/* Input */
.pf-field { margin-bottom:16px; }
.pf-field label {
  display:block; font-size:.82rem; font-weight:600;
  color:var(--gray-600); margin-bottom:5px;
}
.pf-input {
  width:100%; padding:11px 14px;
  border:1.5px solid var(--gray-200);
  border-radius:var(--radius-md);
  font-size:.9rem; font-family:'Plus Jakarta Sans',sans-serif;
  color:var(--gray-800); background:var(--white);
  transition:var(--transition); outline:none;
}
.pf-input:focus {
  border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(37,99,235,.08);
}
.pf-input:read-only {
  background:var(--gray-50); color:var(--gray-400); cursor:not-allowed;
}

/* Eye toggle */
.pf-input-wrap { position:relative; }
.pf-eye {
  position:absolute; right:12px; top:50%;
  transform:translateY(-50%); background:none;
  border:none; color:var(--gray-400); cursor:pointer;
  font-size:1rem; padding:0;
}
.pf-eye:hover { color:var(--blue); }

/* Submit btn */
.btn-save {
  background:var(--blue); color:#fff; border:none;
  border-radius:var(--radius-md); padding:12px 28px;
  font-size:.92rem; font-weight:700; cursor:pointer;
  font-family:'Plus Jakarta Sans',sans-serif;
  transition:var(--transition);
  display:inline-flex; align-items:center; gap:7px;
}
.btn-save:hover { background:var(--blue-light); box-shadow:var(--shadow-blue); transform:translateY(-1px); }

/* Alert */
.pf-alert {
  border-radius:var(--radius-md); padding:12px 16px;
  font-size:.86rem; font-weight:500;
  display:flex; align-items:center; gap:10px; margin-bottom:18px;
}
.pf-alert.error   { background:rgba(239,68,68,.08); color:#991B1B; border:1px solid rgba(239,68,68,.20); }
.pf-alert.success { background:rgba(16,185,129,.08); color:#065F46; border:1px solid rgba(16,185,129,.20); }

/* Info row */
.info-row {
  display:flex; align-items:center; justify-content:space-between;
  padding:12px 0; border-bottom:1px solid var(--gray-100);
  font-size:.88rem;
}
.info-row:last-child { border-bottom:none; }
.info-row .info-label { color:var(--gray-400); font-weight:600; min-width:140px; }
.info-row .info-value { color:var(--navy); font-weight:700; text-align:right; }

/* Password strength */
.pwd-strength { display:flex; gap:5px; margin-top:8px; }
.pwd-bar { flex:1; height:3px; border-radius:4px; background:var(--gray-200); transition:background .3s; }
.pwd-bar.weak   { background:var(--danger); }
.pwd-bar.medium { background:var(--warning); }
.pwd-bar.strong { background:var(--success); }
.pwd-label { font-size:.72rem; margin-top:4px; color:var(--gray-400); }

/* Danger zone */
.danger-zone {
  background:rgba(239,68,68,.04); border:1px solid rgba(239,68,68,.15);
  border-radius:var(--radius-lg); padding:20px 24px;
}
.danger-zone-title {
  font-size:.9rem; font-weight:800; color:var(--danger);
  margin-bottom:8px; display:flex; align-items:center; gap:6px;
}

.fade-up { opacity:0; transform:translateY(12px); animation:fuAnim .4s ease forwards; }
@keyframes fuAnim { to { opacity:1; transform:translateY(0); } }

@media(max-width:767px) {
  .profile-hero   { flex-direction:column; text-align:center; }
  .profile-stats  { justify-content:center; }
  .profile-stat   { padding:0 14px; }
}
</style>

<div class="profile-page">
  <div class="container" style="max-width:860px;">

    <!-- Hero -->
    <div class="profile-hero fade-up">
      <div class="profile-avatar-big">
        <?= strtoupper(substr($user['nama'], 0, 1)) ?>
      </div>
      <div class="profile-hero-info">
        <div class="profile-hero-name"><?= clean($user['nama']) ?></div>
        <div class="profile-hero-email"><?= clean($user['email']) ?></div>
        <div class="profile-hero-badge">
          <i class="bi bi-<?= $user['role']==='admin' ? 'shield-fill' : 'person-check-fill' ?>"></i>
          <?= $user['role'] === 'admin' ? 'Administrator' : 'Member' ?>
        </div>
      </div>
      <div class="profile-stats">
        <div class="profile-stat">
          <div class="profile-stat-num"><?= $statUser['total_booking'] ?? 0 ?></div>
          <div class="profile-stat-label">Booking</div>
        </div>
        <div class="profile-stat">
          <div class="profile-stat-num"><?= $statUser['total_paid'] ?? 0 ?></div>
          <div class="profile-stat-label">Lunas</div>
        </div>
        <div class="profile-stat">
          <div class="profile-stat-num" style="font-size:.95rem;">
            <?= formatRupiah((float)($statUser['total_spent'] ?? 0)) ?>
          </div>
          <div class="profile-stat-label">Total Belanja</div>
        </div>
      </div>
    </div>

    <!-- Alert -->
    <?php if ($error): ?>
      <div class="pf-alert error fade-up"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="pf-alert success fade-up"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="profile-tabs fade-up">
      <button class="profile-tab active" onclick="switchTab('info')">
        <i class="bi bi-person"></i> Data Diri
      </button>
      <button class="profile-tab" onclick="switchTab('password')">
        <i class="bi bi-lock"></i> Ubah Password
      </button>
      <button class="profile-tab" onclick="switchTab('akun')">
        <i class="bi bi-gear"></i> Info Akun
      </button>
    </div>

    <!-- ===== TAB: DATA DIRI ===== -->
    <div class="tab-content-panel active" id="tab-info">
      <div class="form-card fade-up">
        <div class="form-card-title">
          <i class="bi bi-person-fill"></i>
          Informasi Pribadi
        </div>
        <form method="POST" action="">
          <input type="hidden" name="action" value="update_profil">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="pf-field">
                <label>Nama Lengkap <span style="color:var(--danger)">*</span></label>
                <input type="text" name="nama" class="pf-input"
                       value="<?= clean($user['nama']) ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="pf-field">
                <label>Alamat Email</label>
                <input type="email" class="pf-input" value="<?= clean($user['email']) ?>" readonly>
                <div style="font-size:.72rem;color:var(--gray-400);margin-top:4px;">
                  <i class="bi bi-lock-fill"></i> Email tidak dapat diubah
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="pf-field">
                <label>No. Telepon / WhatsApp</label>
                <input type="tel" name="no_hp" class="pf-input"
                       placeholder="08xxxxxxxxxx"
                       value="<?= clean($user['no_hp'] ?? '') ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="pf-field">
                <label>Role Akun</label>
                <input type="text" class="pf-input"
                       value="<?= $user['role']==='admin' ? 'Administrator' : 'Member' ?>" readonly>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn-save">
              <i class="bi bi-check-lg"></i> Simpan Perubahan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ===== TAB: PASSWORD ===== -->
    <div class="tab-content-panel" id="tab-password">
      <div class="form-card fade-up">
        <div class="form-card-title">
          <i class="bi bi-lock-fill"></i>
          Ubah Password
        </div>
        <form method="POST" action="" id="pwForm">
          <input type="hidden" name="action" value="ganti_password">
          <div style="max-width:420px;">

            <!-- Password lama -->
            <div class="pf-field">
              <label>Password Lama <span style="color:var(--danger)">*</span></label>
              <div class="pf-input-wrap">
                <input type="password" name="pw_lama" id="pwLama" class="pf-input"
                       placeholder="Masukkan password saat ini" required
                       style="padding-right:40px;">
                <button type="button" class="pf-eye" onclick="togglePw('pwLama','eyeLama')">
                  <i class="bi bi-eye" id="eyeLama"></i>
                </button>
              </div>
            </div>

            <!-- Password baru -->
            <div class="pf-field">
              <label>Password Baru <span style="color:var(--danger)">*</span></label>
              <div class="pf-input-wrap">
                <input type="password" name="pw_baru" id="pwBaru" class="pf-input"
                       placeholder="Minimal 8 karakter" required
                       style="padding-right:40px;"
                       oninput="checkStrength(this.value)">
                <button type="button" class="pf-eye" onclick="togglePw('pwBaru','eyeBaru')">
                  <i class="bi bi-eye" id="eyeBaru"></i>
                </button>
              </div>
              <div class="pwd-strength" id="strengthBars">
                <div class="pwd-bar" id="bar1"></div>
                <div class="pwd-bar" id="bar2"></div>
                <div class="pwd-bar" id="bar3"></div>
                <div class="pwd-bar" id="bar4"></div>
              </div>
              <div class="pwd-label" id="strengthLabel">Masukkan password baru</div>
            </div>

            <!-- Konfirmasi -->
            <div class="pf-field">
              <label>Konfirmasi Password Baru <span style="color:var(--danger)">*</span></label>
              <div class="pf-input-wrap">
                <input type="password" name="pw_konfirm" id="pwKonfirm" class="pf-input"
                       placeholder="Ulangi password baru" required
                       style="padding-right:40px;"
                       oninput="checkMatch()">
                <button type="button" class="pf-eye" onclick="togglePw('pwKonfirm','eyeKonfirm')">
                  <i class="bi bi-eye" id="eyeKonfirm"></i>
                </button>
              </div>
              <div id="matchMsg" style="font-size:.75rem;margin-top:4px;display:none;"></div>
            </div>

            <button type="submit" class="btn-save" id="btnSavePw">
              <i class="bi bi-shield-check"></i> Ubah Password
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ===== TAB: INFO AKUN ===== -->
    <div class="tab-content-panel" id="tab-akun">
      <div class="form-card fade-up">
        <div class="form-card-title">
          <i class="bi bi-info-circle-fill"></i>
          Informasi Akun
        </div>
        <div class="info-row">
          <span class="info-label">ID Pengguna</span>
          <span class="info-value" style="font-family:monospace;">#<?= $user['id'] ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Email</span>
          <span class="info-value"><?= clean($user['email']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Status Akun</span>
          <span class="info-value">
            <span style="display:inline-flex;align-items:center;gap:5px;
                         color:var(--success);background:rgba(16,185,129,.08);
                         padding:3px 10px;border-radius:50px;font-size:.8rem;">
              <i class="bi bi-check-circle-fill"></i>
              <?= $user['is_aktif'] ? 'Aktif' : 'Nonaktif' ?>
            </span>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Bergabung Sejak</span>
          <span class="info-value"><?= date('d F Y', strtotime($user['created_at'])) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Terakhir Diperbarui</span>
          <span class="info-value"><?= date('d F Y, H:i', strtotime($user['updated_at'])) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Total Booking</span>
          <span class="info-value"><?= $statUser['total_booking'] ?? 0 ?> kali</span>
        </div>
        <div class="info-row">
          <span class="info-label">Total Pengeluaran</span>
          <span class="info-value" style="color:var(--blue);">
            <?= formatRupiah((float)($statUser['total_spent'] ?? 0)) ?>
          </span>
        </div>
        <?php if($statUser['member_since']): ?>
        <div class="info-row">
          <span class="info-label">Booking Pertama</span>
          <span class="info-value"><?= date('d F Y', strtotime($statUser['member_since'])) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Danger zone -->
      <div class="danger-zone fade-up">
        <div class="danger-zone-title">
          <i class="bi bi-exclamation-triangle-fill"></i> Zona Bahaya
        </div>
        <p style="font-size:.84rem;color:var(--gray-600);margin-bottom:14px;">
          Logout dari semua perangkat atau hapus akun kamu.
          Tindakan ini tidak dapat dibatalkan.
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="<?= APP_URL ?>/api/auth.php?action=logout"
             style="display:inline-flex;align-items:center;gap:6px;
                    padding:9px 18px;border-radius:var(--radius-md);
                    background:rgba(239,68,68,.08);color:var(--danger);
                    border:1.5px solid rgba(239,68,68,.2);
                    font-size:.85rem;font-weight:700;text-decoration:none;
                    transition:var(--transition);"
             onmouseover="this.style.background='rgba(239,68,68,.15)'"
             onmouseout="this.style.background='rgba(239,68,68,.08)'">
            <i class="bi bi-box-arrow-right"></i> Logout
          </a>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
// ---- Tab switch ----
function switchTab(tab) {
  document.querySelectorAll('.tab-content-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  event.currentTarget.classList.add('active');
}

// ---- Toggle password visibility ----
function togglePw(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon  = document.getElementById(iconId);
  input.type  = input.type === 'password' ? 'text' : 'password';
  icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

// ---- Password strength ----
function checkStrength(val) {
  const bars  = ['bar1','bar2','bar3','bar4'].map(id => document.getElementById(id));
  const label = document.getElementById('strengthLabel');
  let score   = 0;
  if (val.length >= 8)           score++;
  if (/[A-Z]/.test(val))         score++;
  if (/[0-9]/.test(val))         score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const levels = ['','weak','medium','medium','strong'];
  const labels = ['Masukkan password baru','Lemah','Cukup','Bagus','Kuat 🔒'];
  const colors = ['','#EF4444','#F59E0B','#F59E0B','#10B981'];

  bars.forEach((b,i) => {
    b.className = 'pwd-bar' + (i < score ? ' ' + levels[score] : '');
  });
  label.textContent = labels[score];
  label.style.color = colors[score];
}

// ---- Confirm match ----
function checkMatch() {
  const msg  = document.getElementById('matchMsg');
  const baru = document.getElementById('pwBaru').value;
  const conf = document.getElementById('pwKonfirm').value;
  msg.style.display = 'block';
  if (conf === baru && baru.length > 0) {
    msg.textContent = '✓ Password cocok';
    msg.style.color = '#10B981';
  } else {
    msg.textContent = '✗ Password tidak cocok';
    msg.style.color = '#EF4444';
  }
}

// ---- Loading state ----
document.getElementById('pwForm').addEventListener('submit', function(e) {
  const baru = document.getElementById('pwBaru').value;
  const conf = document.getElementById('pwKonfirm').value;
  if (baru !== conf) { e.preventDefault(); return; }
  const btn = document.getElementById('btnSavePw');
  btn.innerHTML = '<span class="tg-spinner"></span> Menyimpan...';
  btn.disabled = true;
});

// ---- Buka tab sesuai hash URL ----
const hash = window.location.hash.replace('#','');
if (['info','password','akun'].includes(hash)) {
  document.querySelector('.profile-tab.active').classList.remove('active');
  document.querySelector('.tab-content-panel.active').classList.remove('active');
  document.getElementById('tab-'+hash).classList.add('active');
  document.querySelectorAll('.profile-tab')[['info','password','akun'].indexOf(hash)].classList.add('active');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>