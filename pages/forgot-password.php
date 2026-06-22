<?php
// ============================================================
//  TravelGo — Lupa Password (pages/forgot-password.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';

if (isLogin()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$pageTitle  = 'Lupa Password';
$activeMenu = '';

$step    = clean($_GET['step'] ?? 'email'); // email → reset
$error   = '';
$success = '';

// ============================================================
//  STEP 1: Kirim kode verifikasi ke email
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'email') {
    $email = clean($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        $stmt = $conn->prepare("SELECT id, nama FROM users WHERE email = ? AND is_aktif = 1 LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            // Tampilkan pesan sukses palsu supaya tidak bisa ditebak email terdaftar
            $success = 'dummy';
        } else {
            // Generate kode 6 digit & simpan di session (simulasi — tanpa kirim email asli)
            $kode = rand(100000, 999999);
            $_SESSION['fp_email']   = $email;
            $_SESSION['fp_kode']    = $kode;
            $_SESSION['fp_user_id'] = $user['id'];
            $_SESSION['fp_expired'] = time() + 600; // 10 menit

            // Di produksi, kirim email dengan kode ini menggunakan PHPMailer/SMTP
            // Untuk development, kode ditampilkan langsung di halaman
            $_SESSION['fp_dev_kode'] = $kode;

            $success = 'kode_terkirim';
        }
    }
}

// ============================================================
//  STEP 2: Verifikasi kode
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'verifikasi') {
    $kode_input = clean($_POST['kode'] ?? '');

    if (empty($kode_input)) {
        $error = 'Kode verifikasi wajib diisi.';
    } elseif (!isset($_SESSION['fp_kode']) || !isset($_SESSION['fp_expired'])) {
        $error = 'Sesi habis. Silakan ulangi dari awal.';
    } elseif (time() > $_SESSION['fp_expired']) {
        unset($_SESSION['fp_kode'], $_SESSION['fp_email'], $_SESSION['fp_user_id'], $_SESSION['fp_expired']);
        $error = 'Kode sudah kedaluwarsa. Silakan minta kode baru.';
    } elseif ((int)$kode_input !== (int)$_SESSION['fp_kode']) {
        $error = 'Kode verifikasi salah.';
    } else {
        // Kode benar, lanjut ke reset
        $_SESSION['fp_verified'] = true;
        header('Location: ?step=reset');
        exit;
    }
}

// ============================================================
//  STEP 3: Reset password baru
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    $pw_baru  = $_POST['pw_baru']    ?? '';
    $pw_conf  = $_POST['pw_konfirm'] ?? '';

    if (!isset($_SESSION['fp_verified']) || !$_SESSION['fp_verified']) {
        header('Location: ?step=email');
        exit;
    }

    if (empty($pw_baru) || empty($pw_conf)) {
        $error = 'Password wajib diisi.';
    } elseif (strlen($pw_baru) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($pw_baru !== $pw_conf) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $hash = password_hash($pw_baru, PASSWORD_DEFAULT);
        $uid  = $_SESSION['fp_user_id'];
        $conn->query("UPDATE users SET password='$hash', updated_at=NOW() WHERE id=$uid");

        // Bersihkan semua session fp_*
        foreach (['fp_email','fp_kode','fp_user_id','fp_expired','fp_verified','fp_dev_kode'] as $k) {
            unset($_SESSION[$k]);
        }

        $_SESSION['success_msg'] = 'Password berhasil direset. Silakan masuk dengan password baru.';
        header('Location: ' . APP_URL . '/pages/login.php');
        exit;
    }
}

// Cek apakah step valid
if ($step === 'verifikasi' && !isset($_SESSION['fp_email'])) {
    header('Location: ?step=email');
    exit;
}
if ($step === 'reset' && (!isset($_SESSION['fp_verified']) || !$_SESSION['fp_verified'])) {
    header('Location: ?step=email');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lupa Password — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    body {
      min-height: 100vh; background: var(--gray-50);
      display: flex; align-items: center; justify-content: center;
      padding: 32px 16px;
    }

    .fp-card {
      width: 100%; max-width: 420px;
      background: var(--white);
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-xl);
      padding: 36px 32px;
      box-shadow: var(--shadow-lg);
    }

    /* Back link */
    .fp-back {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: .8rem; font-weight: 600; color: var(--gray-400);
      text-decoration: none; margin-bottom: 24px;
      transition: var(--transition);
    }
    .fp-back:hover { color: var(--blue); }

    /* Steps indicator */
    .fp-steps {
      display: flex; align-items: center; gap: 0;
      margin-bottom: 28px;
    }
    .fp-step {
      display: flex; flex-direction: column; align-items: center; gap: 4px;
      flex: 1;
    }
    .fp-step-dot {
      width: 28px; height: 28px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .75rem; font-weight: 800;
      background: var(--gray-200); color: var(--gray-400);
      transition: var(--transition);
    }
    .fp-step.active .fp-step-dot { background: var(--blue); color: #fff; }
    .fp-step.done .fp-step-dot   { background: var(--success); color: #fff; }
    .fp-step-label { font-size: .68rem; color: var(--gray-400); font-weight: 600; text-align: center; }
    .fp-step.active .fp-step-label { color: var(--navy); }
    .fp-step-line  { flex: 1; height: 2px; background: var(--gray-200); margin: 0 4px; margin-bottom: 16px; }
    .fp-step-line.done { background: var(--success); }

    /* Heading */
    .fp-icon {
      width: 52px; height: 52px; border-radius: var(--radius-lg);
      background: rgba(37,99,235,.08);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem; margin-bottom: 16px;
    }
    .fp-title { font-size: 1.4rem; font-weight: 800; color: var(--navy); margin-bottom: 6px; }
    .fp-sub   { font-size: .85rem; color: var(--gray-400); margin-bottom: 24px; line-height: 1.6; }

    /* Input */
    .fp-field { margin-bottom: 16px; }
    .fp-field label { display: block; font-size: .82rem; font-weight: 600; color: var(--gray-600); margin-bottom: 5px; }
    .fp-input {
      width: 100%; padding: 11px 14px;
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-md);
      font-size: .9rem; font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--gray-800); background: var(--white);
      transition: var(--transition); outline: none;
    }
    .fp-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.08); }
    .fp-input::placeholder { color: var(--gray-400); }

    /* OTP input */
    .otp-wrap { display: flex; gap: 8px; justify-content: center; margin: 8px 0 4px; }
    .otp-input {
      width: 48px; height: 56px; text-align: center;
      font-size: 1.4rem; font-weight: 800; color: var(--navy);
      border: 2px solid var(--gray-200); border-radius: var(--radius-md);
      font-family: monospace; outline: none; transition: var(--transition);
      background: var(--white);
    }
    .otp-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.08); }

    /* Eye toggle */
    .fp-pw-wrap { position: relative; }
    .fp-eye {
      position: absolute; right: 12px; top: 50%;
      transform: translateY(-50%); background: none; border: none;
      color: var(--gray-400); cursor: pointer; font-size: 1rem; padding: 0;
    }
    .fp-eye:hover { color: var(--blue); }

    /* Password strength */
    .pwd-strength { display: flex; gap: 5px; margin-top: 8px; }
    .pwd-bar { flex: 1; height: 3px; border-radius: 4px; background: var(--gray-200); transition: background .3s; }
    .pwd-bar.weak   { background: var(--danger); }
    .pwd-bar.medium { background: var(--warning); }
    .pwd-bar.strong { background: var(--success); }
    .pwd-label { font-size: .72rem; margin-top: 4px; color: var(--gray-400); }

    /* Alert */
    .fp-alert {
      border-radius: var(--radius-md); padding: 12px 14px;
      font-size: .84rem; font-weight: 500;
      display: flex; align-items: flex-start; gap: 8px; margin-bottom: 16px;
    }
    .fp-alert.error   { background: rgba(239,68,68,.08); color: #991B1B; border: 1px solid rgba(239,68,68,.2); }
    .fp-alert.success { background: rgba(16,185,129,.08); color: #065F46; border: 1px solid rgba(16,185,129,.2); }
    .fp-alert.info    { background: rgba(37,99,235,.06); color: var(--blue); border: 1px solid rgba(37,99,235,.15); }

    /* Dev box */
    .dev-box {
      background: rgba(245,158,11,.06); border: 1px solid rgba(245,158,11,.2);
      border-radius: var(--radius-md); padding: 12px 14px;
      margin-bottom: 16px; font-size: .82rem;
    }
    .dev-kode {
      font-size: 1.8rem; font-weight: 800; color: var(--navy);
      letter-spacing: 6px; font-family: monospace;
      text-align: center; margin: 6px 0;
    }

    /* Submit btn */
    .fp-btn {
      width: 100%; background: var(--blue); color: #fff; border: none;
      border-radius: var(--radius-md); padding: 13px;
      font-size: .95rem; font-weight: 700;
      font-family: 'Plus Jakarta Sans', sans-serif;
      cursor: pointer; transition: var(--transition);
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .fp-btn:hover { background: var(--blue-light); box-shadow: var(--shadow-blue); transform: translateY(-1px); }

    /* Resend */
    .fp-resend { text-align: center; font-size: .8rem; color: var(--gray-400); margin-top: 14px; }
    .fp-resend a { color: var(--blue); font-weight: 600; cursor: pointer; }

    /* Fade up */
    .fade-up { opacity: 0; transform: translateY(14px); animation: fuAnim .4s ease forwards; }
    @keyframes fuAnim { to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body>

<div class="fp-card fade-up">

  <!-- Back -->
  <a href="<?= APP_URL ?>/pages/login.php" class="fp-back">
    <i class="bi bi-arrow-left"></i> Kembali ke login
  </a>

  <!-- Steps -->
  <?php
    $steps = ['email'=>1, 'verifikasi'=>2, 'reset'=>3];
    $currentStep = $steps[$step] ?? 1;
  ?>
  <div class="fp-steps">
    <?php
      $stepLabels = [1=>'Email', 2=>'Verifikasi', 3=>'Reset'];
      for ($i = 1; $i <= 3; $i++):
        $cls = $i < $currentStep ? 'done' : ($i === $currentStep ? 'active' : '');
    ?>
      <div class="fp-step <?= $cls ?>">
        <div class="fp-step-dot">
          <?= $i < $currentStep ? '<i class="bi bi-check"></i>' : $i ?>
        </div>
        <div class="fp-step-label"><?= $stepLabels[$i] ?></div>
      </div>
      <?php if ($i < 3): ?>
        <div class="fp-step-line <?= $i < $currentStep ? 'done' : '' ?>"></div>
      <?php endif; ?>
    <?php endfor; ?>
  </div>

  <?php
  // ============================================================
  //  RENDER STEP 1: Input email
  // ============================================================
  if ($step === 'email'):
  ?>
    <div class="fp-icon"><i class="bi bi-envelope-open" style="color:var(--blue);"></i></div>
    <div class="fp-title">Lupa Password?</div>
    <div class="fp-sub">Masukkan email yang terdaftar. Kami akan mengirim kode verifikasi.</div>

    <?php if ($error): ?>
      <div class="fp-alert error"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
    <?php endif; ?>

    <?php if ($success === 'dummy' || $success === 'kode_terkirim'): ?>
      <div class="fp-alert success">
        <i class="bi bi-check-circle-fill"></i>
        Kalau email terdaftar, kode verifikasi sudah dikirim. Cek inbox atau spam.
      </div>
      <?php if ($success === 'kode_terkirim'): ?>
        <a href="?step=verifikasi" class="fp-btn" style="text-decoration:none;margin-top:4px;">
          <i class="bi bi-arrow-right"></i> Masukkan Kode Verifikasi
        </a>
      <?php endif; ?>
    <?php else: ?>
      <form method="POST" action="?step=email">
        <div class="fp-field">
          <label for="email">Alamat Email</label>
          <input type="email" id="email" name="email" class="fp-input"
                 placeholder="contoh@email.com"
                 value="<?= clean($_POST['email'] ?? '') ?>" required>
        </div>
        <button type="submit" class="fp-btn">
          <i class="bi bi-send"></i> Kirim Kode Verifikasi
        </button>
      </form>
    <?php endif; ?>

  <?php
  // ============================================================
  //  RENDER STEP 2: Verifikasi kode
  // ============================================================
  elseif ($step === 'verifikasi'):
  ?>
    <div class="fp-icon"><i class="bi bi-shield-lock" style="color:var(--blue);"></i></div>
    <div class="fp-title">Masukkan Kode</div>
    <div class="fp-sub">
      Kode 6 digit telah dikirim ke<br>
      <strong><?= clean($_SESSION['fp_email'] ?? '') ?></strong>
    </div>

    <?php if ($error): ?>
      <div class="fp-alert error"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
    <?php endif; ?>

    <!-- DEV MODE: tampilkan kode langsung -->
    <?php if (isset($_SESSION['fp_dev_kode'])): ?>
      <div class="dev-box">
        <div style="font-size:.75rem;color:#92400E;font-weight:700;">
          <i class="bi bi-code-slash"></i> Mode Development — Kode Verifikasi:
        </div>
        <div class="dev-kode"><?= $_SESSION['fp_dev_kode'] ?></div>
        <div style="font-size:.72rem;color:rgba(0,0,0,.4);text-align:center;">
          Di produksi, kode ini dikirim via email
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" action="?step=verifikasi" id="otpForm">
      <div class="fp-field">
        <label style="text-align:center;display:block;">Kode Verifikasi</label>
        <!-- OTP inputs visual -->
        <div class="otp-wrap">
          <input type="text" class="otp-input" maxlength="1" id="otp0" inputmode="numeric">
          <input type="text" class="otp-input" maxlength="1" id="otp1" inputmode="numeric">
          <input type="text" class="otp-input" maxlength="1" id="otp2" inputmode="numeric">
          <input type="text" class="otp-input" maxlength="1" id="otp3" inputmode="numeric">
          <input type="text" class="otp-input" maxlength="1" id="otp4" inputmode="numeric">
          <input type="text" class="otp-input" maxlength="1" id="otp5" inputmode="numeric">
        </div>
        <!-- Hidden input yang dikirim ke server -->
        <input type="hidden" name="kode" id="kodeHidden">
      </div>

      <!-- Countdown -->
      <?php
        $sisaDetik = isset($_SESSION['fp_expired']) ? max(0, $_SESSION['fp_expired'] - time()) : 0;
      ?>
      <div style="text-align:center;font-size:.78rem;color:var(--gray-400);margin-bottom:16px;">
        Kode berlaku selama <span id="countdown" style="font-weight:700;color:var(--navy);">--:--</span>
      </div>

      <button type="submit" class="fp-btn" id="btnVerif">
        <i class="bi bi-shield-check"></i> Verifikasi Kode
      </button>
    </form>

    <div class="fp-resend">
      Tidak menerima kode?
      <a href="?step=email">Kirim ulang</a>
    </div>

  <?php
  // ============================================================
  //  RENDER STEP 3: Reset password
  // ============================================================
  elseif ($step === 'reset'):
  ?>
    <div class="fp-icon"><i class="bi bi-lock-fill" style="color:var(--blue);"></i></div>
    <div class="fp-title">Buat Password Baru</div>
    <div class="fp-sub">Masukkan password baru untuk akun kamu.</div>

    <?php if ($error): ?>
      <div class="fp-alert error"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="?step=reset" id="resetForm">
      <!-- Password baru -->
      <div class="fp-field">
        <label>Password Baru <span style="color:var(--danger)">*</span></label>
        <div class="fp-pw-wrap">
          <input type="password" name="pw_baru" id="pwBaru" class="fp-input"
                 placeholder="Minimal 8 karakter"
                 style="padding-right:40px;" required
                 oninput="checkStrength(this.value)">
          <button type="button" class="fp-eye" onclick="togglePw('pwBaru','eye1')">
            <i class="bi bi-eye" id="eye1"></i>
          </button>
        </div>
        <div class="pwd-strength">
          <div class="pwd-bar" id="bar1"></div>
          <div class="pwd-bar" id="bar2"></div>
          <div class="pwd-bar" id="bar3"></div>
          <div class="pwd-bar" id="bar4"></div>
        </div>
        <div class="pwd-label" id="strengthLabel">Masukkan password baru</div>
      </div>

      <!-- Konfirmasi -->
      <div class="fp-field">
        <label>Konfirmasi Password <span style="color:var(--danger)">*</span></label>
        <div class="fp-pw-wrap">
          <input type="password" name="pw_konfirm" id="pwKonfirm" class="fp-input"
                 placeholder="Ulangi password baru"
                 style="padding-right:40px;" required
                 oninput="checkMatch()">
          <button type="button" class="fp-eye" onclick="togglePw('pwKonfirm','eye2')">
            <i class="bi bi-eye" id="eye2"></i>
          </button>
        </div>
        <div id="matchMsg" style="font-size:.75rem;margin-top:4px;display:none;"></div>
      </div>

      <button type="submit" class="fp-btn" id="btnReset">
        <i class="bi bi-check-circle"></i> Reset Password
      </button>
    </form>

  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ---- OTP input navigation ----
const otpInputs = document.querySelectorAll('.otp-input');
otpInputs.forEach((inp, i) => {
  inp.addEventListener('input', () => {
    inp.value = inp.value.replace(/\D/g,'');
    if (inp.value && i < 5) otpInputs[i+1].focus();
    updateKode();
  });
  inp.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !inp.value && i > 0) {
      otpInputs[i-1].focus();
    }
  });
  inp.addEventListener('paste', e => {
    const text = e.clipboardData.getData('text').replace(/\D/g,'').slice(0,6);
    text.split('').forEach((ch, j) => {
      if (otpInputs[j]) otpInputs[j].value = ch;
    });
    updateKode();
    e.preventDefault();
  });
});

function updateKode() {
  const h = document.getElementById('kodeHidden');
  if (h) h.value = Array.from(otpInputs).map(i => i.value).join('');
}

// ---- Countdown ----
let sisa = <?= $sisaDetik ?? 0 ?>;
const cdEl = document.getElementById('countdown');
if (cdEl) {
  function tick() {
    if (sisa <= 0) { cdEl.textContent = 'Habis'; cdEl.style.color='var(--danger)'; return; }
    const m = String(Math.floor(sisa/60)).padStart(2,'0');
    const s = String(sisa%60).padStart(2,'0');
    cdEl.textContent = m+':'+s;
    if (sisa <= 60) cdEl.style.color='var(--danger)';
    sisa--;
    setTimeout(tick, 1000);
  }
  tick();
}

// ---- OTP form submit ----
const otpForm = document.getElementById('otpForm');
if (otpForm) {
  otpForm.addEventListener('submit', function(e) {
    updateKode();
    const kode = document.getElementById('kodeHidden').value;
    if (kode.length < 6) { e.preventDefault(); alert('Masukkan semua 6 digit kode.'); return; }
    const btn = document.getElementById('btnVerif');
    btn.innerHTML = '<span class="tg-spinner"></span> Memverifikasi...';
    btn.disabled = true;
  });
}

// ---- Toggle password ----
function togglePw(inputId, iconId) {
  const inp  = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  inp.type   = inp.type === 'password' ? 'text' : 'password';
  icon.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

// ---- Password strength ----
function checkStrength(val) {
  const bars  = [1,2,3,4].map(i => document.getElementById('bar'+i));
  const label = document.getElementById('strengthLabel');
  let score = 0;
  if (val.length >= 8)           score++;
  if (/[A-Z]/.test(val))         score++;
  if (/[0-9]/.test(val))         score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = ['','weak','medium','medium','strong'];
  const labels = ['Masukkan password baru','Lemah','Cukup','Bagus','Kuat 🔒'];
  const colors = ['','#EF4444','#F59E0B','#F59E0B','#10B981'];
  bars.forEach((b,i) => { b.className = 'pwd-bar' + (i < score ? ' '+levels[score] : ''); });
  label.textContent = labels[score];
  label.style.color = colors[score];
}

// ---- Confirm match ----
function checkMatch() {
  const msg  = document.getElementById('matchMsg');
  const baru = document.getElementById('pwBaru')?.value;
  const conf = document.getElementById('pwKonfirm')?.value;
  if (!msg || !baru) return;
  msg.style.display = 'block';
  if (conf === baru && baru.length > 0) {
    msg.textContent = '✓ Password cocok';
    msg.style.color = '#10B981';
  } else {
    msg.textContent = '✗ Password tidak cocok';
    msg.style.color = '#EF4444';
  }
}

// ---- Loading state reset form ----
const resetForm = document.getElementById('resetForm');
if (resetForm) {
  resetForm.addEventListener('submit', function(e) {
    const baru = document.getElementById('pwBaru').value;
    const conf = document.getElementById('pwKonfirm').value;
    if (baru !== conf) { e.preventDefault(); return; }
    const btn = document.getElementById('btnReset');
    btn.innerHTML = '<span class="tg-spinner"></span> Menyimpan...';
    btn.disabled = true;
  });
}
</script>
</body>
</html>