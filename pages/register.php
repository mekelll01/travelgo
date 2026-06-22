<?php
// ============================================================
//  TravelGo — Halaman Register (pages/register.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';

// Kalau sudah login, redirect ke homepage
if (isLogin()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$pageTitle  = 'Daftar';
$activeMenu = '';

$error   = '';
$success = '';

// Proses form register
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = clean($_POST['nama']     ?? '');
    $email    = clean($_POST['email']    ?? '');
    $no_hp    = clean($_POST['telepon']  ?? '');
    $password = $_POST['password']  ?? '';
    $confirm  = $_POST['confirm']   ?? '';
    $setuju   = isset($_POST['setuju']);

    // Validasi
    if (empty($nama) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Semua kolom wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif (!$setuju) {
        $error = 'Kamu harus menyetujui syarat & ketentuan.';
    } else {
        // Cek email sudah terdaftar
        $cek = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $cek->bind_param('s', $email);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $error = 'Email sudah terdaftar. Silakan gunakan email lain atau masuk.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO users (nama, email, no_hp, password, role, is_aktif, created_at)
                 VALUES (?, ?, ?, ?, 'user', 1, NOW())"
            );
            $stmt->bind_param('ssss', $nama, $email, $no_hp, $hash);

            if ($stmt->execute()) {
                $_SESSION['success_msg'] = 'Akun berhasil dibuat! Silakan masuk.';
                header('Location: ' . APP_URL . '/pages/login.php');
                exit;
            } else {
                $error = 'Terjadi kesalahan. Silakan coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar — <?= APP_NAME ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">

  <style>
    .reg-page {
      min-height: 100vh;
      display: flex;
    }

    /* ---- Left panel ---- */
    .reg-left {
      width: 44%;
      background: linear-gradient(155deg, #0B1426 0%, #1E3260 50%, #1a3a7a 100%);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 48px;
    }
    .reg-left::before {
      content: '';
      position: absolute;
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(37,99,235,.20) 0%, transparent 70%);
      top: -100px; right: -100px;
    }
    .reg-left::after {
      content: '';
      position: absolute;
      width: 300px; height: 300px;
      background: radial-gradient(circle, rgba(245,158,11,.10) 0%, transparent 70%);
      bottom: 80px; left: 0;
    }
    .dot-grid {
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px);
      background-size: 28px 28px;
    }

    .reg-logo {
      position: relative; z-index: 2;
      font-size: 1.5rem; font-weight: 800;
      color: #fff; text-decoration: none;
      display: flex; align-items: center; gap: 8px;
    }
    .reg-logo span { color: #60A5FA; }

    .reg-visual {
      position: relative; z-index: 2;
      flex: 1; display: flex;
      flex-direction: column; justify-content: center;
    }
    .reg-icons-row {
      display: flex; gap: 16px;
      margin-bottom: 28px;
      flex-wrap: wrap;
    }
    .reg-icon-chip {
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 50px;
      padding: 8px 16px;
      color: rgba(255,255,255,.85);
      font-size: .85rem;
      font-weight: 600;
      display: flex; align-items: center; gap: 6px;
      animation: chipFloat 4s ease-in-out infinite;
    }
    .reg-icon-chip:nth-child(2) { animation-delay: .8s; }
    .reg-icon-chip:nth-child(3) { animation-delay: 1.6s; }
    .reg-icon-chip:nth-child(4) { animation-delay: 2.4s; }
    @keyframes chipFloat {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-6px); }
    }

    .reg-visual h2 {
      font-size: clamp(1.5rem, 2.2vw, 2.2rem);
      font-weight: 800; color: #fff;
      line-height: 1.2; letter-spacing: -.5px;
    }
    .reg-visual h2 span { color: #F59E0B; }
    .reg-visual p {
      color: rgba(255,255,255,.58);
      font-size: .92rem; margin-top: 12px; line-height: 1.7;
    }

    .reg-perks {
      position: relative; z-index: 2;
      display: flex; flex-direction: column; gap: 10px;
    }
    .reg-perk {
      display: flex; align-items: center; gap: 10px;
      color: rgba(255,255,255,.75); font-size: .85rem;
    }
    .reg-perk .bi {
      color: #34D399; font-size: 1rem; flex-shrink: 0;
    }

    /* ---- Right panel (form) ---- */
    .reg-right {
      flex: 1;
      display: flex; align-items: center; justify-content: center;
      background: var(--gray-50);
      padding: 40px 32px;
      overflow-y: auto;
    }

    .reg-card {
      width: 100%; max-width: 460px;
    }

    .reg-card h1 {
      font-size: 1.65rem; font-weight: 800;
      color: var(--navy); letter-spacing: -.5px;
      margin-bottom: 6px;
    }
    .reg-sub {
      font-size: .88rem; color: var(--gray-400); margin-bottom: 28px;
    }
    .reg-sub a { color: var(--blue); font-weight: 600; }
    .reg-sub a:hover { text-decoration: underline; }

    /* Step indicator */
    .reg-steps {
      display: flex; align-items: center; gap: 0;
      margin-bottom: 28px;
    }
    .reg-step {
      display: flex; align-items: center; gap: 8px;
      font-size: .8rem; font-weight: 600;
    }
    .reg-step-num {
      width: 26px; height: 26px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .75rem; font-weight: 700;
      background: var(--gray-200); color: var(--gray-400);
      transition: var(--transition);
    }
    .reg-step.active .reg-step-num {
      background: var(--blue); color: #fff;
    }
    .reg-step.done .reg-step-num {
      background: var(--success); color: #fff;
    }
    .reg-step-label { color: var(--gray-400); }
    .reg-step.active .reg-step-label { color: var(--navy); font-weight: 700; }
    .reg-step-line {
      flex: 1; height: 2px;
      background: var(--gray-200); margin: 0 10px;
    }

    /* Input */
    .tg-input-wrap {
      position: relative; margin-bottom: 18px;
    }
    .tg-input-wrap.has-strength {
      margin-bottom: 6px;
    }
    .tg-input-wrap label {
      display: block; font-size: .82rem;
      font-weight: 600; color: var(--gray-600); margin-bottom: 5px;
    }
    .tg-input-wrap .input-icon {
      position: absolute; left: 14px;
      top: calc(50% + 12px);
      transform: translateY(-50%);
      color: var(--gray-400); font-size: .95rem;
      pointer-events: none;
    }
    .tg-input {
      width: 100%;
      padding: 11px 14px 11px 40px;
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-md);
      font-size: .9rem;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--gray-800); background: var(--white);
      transition: var(--transition); outline: none;
    }
    .tg-input:focus {
      border-color: var(--blue);
      box-shadow: 0 0 0 3px rgba(37,99,235,.10);
    }
    .tg-input::placeholder { color: var(--gray-400); }
    .tg-input.is-invalid { border-color: var(--danger); }

    /* Password strength */
    .pwd-strength {
      display: flex; gap: 6px;
      margin-top: 8px;
    }
    .pwd-bar {
      flex: 1; height: 4px; border-radius: 4px;
      background: var(--gray-200); transition: background .3s;
    }
    .pwd-bar.weak   { background: var(--danger); }
    .pwd-bar.medium { background: var(--warning); }
    .pwd-bar.strong { background: var(--success); }
    .pwd-label {
      font-size: .75rem; color: var(--gray-400);
      margin-top: 5px;
    }

    /* Eye toggle */
    .tg-eye-btn {
      position: absolute; right: 14px;
      top: calc(50% + 12px); transform: translateY(-50%);
      background: none; border: none;
      color: var(--gray-400); cursor: pointer;
      padding: 0; font-size: 1rem;
      transition: var(--transition);
    }
    .tg-eye-btn:hover { color: var(--blue); }

    /* Checkbox */
    .reg-check {
      display: flex; align-items: flex-start; gap: 10px;
      font-size: .84rem; color: var(--gray-600);
      margin: 16px 0 20px;
    }
    .reg-check input[type=checkbox] {
      accent-color: var(--blue);
      width: 16px; height: 16px;
      flex-shrink: 0; margin-top: 2px;
    }
    .reg-check a { color: var(--blue); font-weight: 600; }

    /* Submit */
    .tg-btn-register {
      width: 100%; background: var(--blue);
      color: #fff; border: none;
      border-radius: var(--radius-md);
      padding: 13px; font-size: 1rem;
      font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;
      cursor: pointer; transition: var(--transition);
      display: flex; align-items: center;
      justify-content: center; gap: 8px;
    }
    .tg-btn-register:hover {
      background: var(--blue-light);
      box-shadow: var(--shadow-blue);
      transform: translateY(-1px);
    }

    /* Alert */
    .tg-reg-alert {
      border-radius: var(--radius-md);
      padding: 12px 16px; font-size: .86rem;
      font-weight: 500; display: flex;
      align-items: center; gap: 10px;
      margin-bottom: 20px;
    }
    .tg-reg-alert.error {
      background: rgba(239,68,68,.08); color: #991B1B;
      border: 1px solid rgba(239,68,68,.20);
    }

    /* Fade up */
    .fade-up {
      opacity: 0; transform: translateY(16px);
      animation: fadeUp .45s ease forwards;
    }
    @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }
    .fade-up:nth-child(1) { animation-delay:.05s }
    .fade-up:nth-child(2) { animation-delay:.10s }
    .fade-up:nth-child(3) { animation-delay:.15s }
    .fade-up:nth-child(4) { animation-delay:.20s }
    .fade-up:nth-child(5) { animation-delay:.25s }
    .fade-up:nth-child(6) { animation-delay:.30s }
    .fade-up:nth-child(7) { animation-delay:.35s }

    @media (max-width: 767px) {
      .reg-left  { display: none; }
      .reg-right { padding: 32px 20px; }
    }
  </style>
</head>
<body style="background:var(--gray-50)">

<div class="reg-page">

  <!-- ======= LEFT PANEL ======= -->
  <div class="reg-left">
    <div class="dot-grid"></div>

    <a href="<?= APP_URL ?>/index.php" class="reg-logo">
      <i class="bi bi-airplane-fill"></i> Travel<span>Go</span>
    </a>

    <div class="reg-visual">
      <div class="reg-icons-row">
        <div class="reg-icon-chip"><i class="bi bi-airplane"></i> Pesawat</div>
        <div class="reg-icon-chip"><i class="bi bi-train-front"></i> Kereta</div>
        <div class="reg-icon-chip"><i class="bi bi-bus-front"></i> Bus</div>
        <div class="reg-icon-chip"><i class="bi bi-water"></i> Kapal</div>
      </div>
      <h2>Satu Akun untuk<br>Semua <span>Perjalananmu</span></h2>
      <p>Daftar gratis dan nikmati kemudahan pesan tiket<br>
         pesawat, kereta, bus, kapal, dan travel.</p>
    </div>

    <div class="reg-perks">
      <div class="reg-perk"><i class="bi bi-check-circle-fill"></i> Gratis daftar, tanpa biaya apapun</div>
      <div class="reg-perk"><i class="bi bi-check-circle-fill"></i> Promo eksklusif untuk member baru</div>
      <div class="reg-perk"><i class="bi bi-check-circle-fill"></i> E-tiket langsung ke email kamu</div>
      <div class="reg-perk"><i class="bi bi-check-circle-fill"></i> Riwayat booking tersimpan aman</div>
    </div>
  </div>

  <!-- ======= RIGHT PANEL (FORM) ======= -->
  <div class="reg-right">
    <div class="reg-card">

      <!-- Heading -->
      <div class="fade-up">
        <h1>Buat Akun Baru</h1>
        <p class="reg-sub">
          Sudah punya akun?
          <a href="<?= APP_URL ?>/pages/login.php">Masuk di sini</a>
        </p>
      </div>

      <!-- Step indicator -->
      <div class="reg-steps fade-up">
        <div class="reg-step active">
          <div class="reg-step-num">1</div>
          <div class="reg-step-label">Data Diri</div>
        </div>
        <div class="reg-step-line"></div>
        <div class="reg-step" id="step2">
          <div class="reg-step-num">2</div>
          <div class="reg-step-label">Password</div>
        </div>
        <div class="reg-step-line"></div>
        <div class="reg-step" id="step3">
          <div class="reg-step-num">3</div>
          <div class="reg-step-label">Selesai</div>
        </div>
      </div>

      <!-- Alert error -->
      <?php if ($error): ?>
        <div class="tg-reg-alert error fade-up">
          <i class="bi bi-exclamation-circle-fill"></i> <?= $error ?>
        </div>
      <?php endif; ?>

      <!-- Form -->
      <form method="POST" action="" id="regForm" novalidate>

        <!-- Nama Lengkap -->
        <div class="tg-input-wrap fade-up">
          <label for="nama">Nama Lengkap</label>
          <i class="bi bi-person input-icon"></i>
          <input type="text" id="nama" name="nama" class="tg-input"
                 placeholder="Masukkan nama lengkap"
                 value="<?= clean($_POST['nama'] ?? '') ?>"
                 required autocomplete="name">
        </div>

        <!-- Email -->
        <div class="tg-input-wrap fade-up">
          <label for="email">Alamat Email</label>
          <i class="bi bi-envelope input-icon"></i>
          <input type="email" id="email" name="email" class="tg-input"
                 placeholder="contoh@email.com"
                 value="<?= clean($_POST['email'] ?? '') ?>"
                 required autocomplete="email">
        </div>

        <!-- Nomor Telepon -->
        <div class="tg-input-wrap fade-up">
          <label for="telepon">Nomor Telepon <span style="color:var(--gray-400);font-weight:400"></span></label>
          <i class="bi bi-telephone input-icon"></i>
          <input type="tel" id="telepon" name="telepon" class="tg-input"
                 placeholder="08xxxxxxxxxx"
                 value="<?= clean($_POST['telepon'] ?? '') ?>"
                 autocomplete="tel">
        </div>

        <!-- Password -->
        <div class="tg-input-wrap has-strength fade-up">
          <label for="password">Password</label>
          <i class="bi bi-lock input-icon"></i>
          <input type="password" id="password" name="password" class="tg-input"
                 placeholder="Minimal 8 karakter"
                 required autocomplete="new-password"
                 oninput="checkStrength(this.value)">
          <button type="button" class="tg-eye-btn" id="togglePwd">
            <i class="bi bi-eye" id="eyeIcon1"></i>
          </button>
        </div>
        <!-- Strength bar di luar kotak input -->
        <div class="pwd-strength" id="strengthBars">
          <div class="pwd-bar" id="bar1"></div>
          <div class="pwd-bar" id="bar2"></div>
          <div class="pwd-bar" id="bar3"></div>
          <div class="pwd-bar" id="bar4"></div>
        </div>
        <div class="pwd-label" id="strengthLabel">Masukkan password</div>

        <!-- Konfirmasi Password -->
        <div class="tg-input-wrap fade-up">
          <label for="confirm">Konfirmasi Password</label>
          <i class="bi bi-lock-fill input-icon"></i>
          <input type="password" id="confirm" name="confirm" class="tg-input"
                 placeholder="Ulangi password"
                 required autocomplete="new-password">
          <button type="button" class="tg-eye-btn" id="toggleConfirm">
            <i class="bi bi-eye" id="eyeIcon2"></i>
          </button>
          <div id="matchMsg" style="font-size:.75rem;margin-top:4px;display:none;"></div>
        </div>

        <!-- Setuju syarat -->
        <div class="reg-check fade-up">
          <input type="checkbox" id="setuju" name="setuju" <?= isset($_POST['setuju']) ? 'checked' : '' ?>>
          <label for="setuju">
            Saya menyetujui
            <a href="#" target="_blank">Syarat & Ketentuan</a>
            dan
            <a href="#" target="_blank">Kebijakan Privasi</a>
            TravelGo
          </label>
        </div>

        <!-- Submit -->
        <div class="fade-up">
          <button type="submit" class="tg-btn-register" id="regBtn">
            <i class="bi bi-person-plus"></i>
            Buat Akun Gratis
          </button>
        </div>

      </form>

    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Toggle password
  document.getElementById('togglePwd').addEventListener('click', () => {
    const i = document.getElementById('password');
    const e = document.getElementById('eyeIcon1');
    i.type = i.type === 'password' ? 'text' : 'password';
    e.className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  });
  document.getElementById('toggleConfirm').addEventListener('click', () => {
    const i = document.getElementById('confirm');
    const e = document.getElementById('eyeIcon2');
    i.type = i.type === 'password' ? 'text' : 'password';
    e.className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  });

  // Password strength checker
  function checkStrength(val) {
    const bars   = [document.getElementById('bar1'), document.getElementById('bar2'),
                    document.getElementById('bar3'), document.getElementById('bar4')];
    const label  = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 8)                      score++;
    if (/[A-Z]/.test(val))                    score++;
    if (/[0-9]/.test(val))                    score++;
    if (/[^A-Za-z0-9]/.test(val))            score++;

    const levels = ['', 'weak', 'medium', 'medium', 'strong'];
    const labels = ['Masukkan password', 'Lemah', 'Cukup', 'Bagus', 'Kuat 🔒'];
    const colors = ['', '#EF4444', '#F59E0B', '#F59E0B', '#10B981'];

    bars.forEach((b, i) => {
      b.className = 'pwd-bar' + (i < score ? ' ' + levels[score] : '');
    });
    label.textContent  = labels[score];
    label.style.color  = colors[score];

    // Update step indicator
    if (score >= 2) {
      document.getElementById('step2').classList.add('active');
    }
  }

  // Confirm password match
  document.getElementById('confirm').addEventListener('input', function() {
    const msg = document.getElementById('matchMsg');
    const pwd = document.getElementById('password').value;
    msg.style.display = 'block';
    if (this.value === pwd && pwd.length > 0) {
      msg.textContent = '✓ Password cocok';
      msg.style.color = '#10B981';
      document.getElementById('step3').classList.add('active');
    } else {
      msg.textContent = '✗ Password tidak cocok';
      msg.style.color = '#EF4444';
    }
  });

  // Loading state on submit
  document.getElementById('regForm').addEventListener('submit', function(e) {
    const pwd  = document.getElementById('password').value;
    const conf = document.getElementById('confirm').value;
    if (pwd !== conf) { e.preventDefault(); return; }
    const btn = document.getElementById('regBtn');
    btn.innerHTML = '<span class="tg-spinner"></span> Membuat akun...';
    btn.disabled = true;
  });
</script>

</body>
</html>