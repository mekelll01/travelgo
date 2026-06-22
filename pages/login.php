<?php
// ============================================================
//  TravelGo — Halaman Login (pages/login.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';

// Kalau sudah login, redirect ke homepage
if (isLogin()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$pageTitle  = 'Masuk';
$activeMenu = '';

$error   = '';
$success = '';

// Ambil pesan dari session (misal setelah register)
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

// Proses form login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = clean($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $conn->prepare("SELECT id, nama, email, password, role, foto FROM users WHERE email = ? AND is_aktif = 1 LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['foto']    = $user['foto'] ?? 'default.png';

            // ---- Catat activity log ke MongoDB ----
            try {
                logActivity($user['id'], 'login', [
                    'email' => $user['email'],
                    'role'  => $user['role'],
                ]);
            } catch (Exception $e) { /* silent fail */ }

            $redirect = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : APP_URL . '/pages/dashboard.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Email atau password salah. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Masuk — <?= APP_NAME ?></title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">

  <style>
    /* ---- Layout split ---- */
    .login-page {
      min-height: 100vh;
      display: flex;
    }

    /* ---- Left panel (visual) ---- */
    .login-left {
      width: 52%;
      background: linear-gradient(145deg, var(--navy) 0%, var(--navy-mid) 55%, #1a3a7a 100%);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 48px;
    }

    /* Decorative blobs */
    .login-left::before {
      content: '';
      position: absolute;
      width: 480px; height: 480px;
      background: radial-gradient(circle, rgba(37,99,235,.22) 0%, transparent 70%);
      top: -80px; right: -80px;
      pointer-events: none;
    }
    .login-left::after {
      content: '';
      position: absolute;
      width: 320px; height: 320px;
      background: radial-gradient(circle, rgba(96,165,250,.14) 0%, transparent 70%);
      bottom: 60px; left: -40px;
      pointer-events: none;
    }

    /* Animated dot grid */
    .dot-grid {
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px);
      background-size: 28px 28px;
      pointer-events: none;
    }

    .login-left-logo {
      position: relative;
      z-index: 2;
      font-size: 1.5rem;
      font-weight: 800;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }
    .login-left-logo span { color: #60A5FA; }

    .login-visual {
      position: relative;
      z-index: 2;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .login-visual-icon {
      font-size: 5rem;
      margin-bottom: 24px;
      display: block;
      animation: floatIcon 3.5s ease-in-out infinite;
    }
    @keyframes floatIcon {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-12px); }
    }

    .login-visual h2 {
      font-size: clamp(1.6rem, 2.5vw, 2.4rem);
      font-weight: 800;
      color: #fff;
      line-height: 1.2;
      letter-spacing: -.5px;
    }
    .login-visual h2 span { color: #60A5FA; }
    .login-visual p {
      color: rgba(255,255,255,.60);
      font-size: .95rem;
      margin-top: 12px;
      line-height: 1.7;
    }

    .login-stats {
      position: relative;
      z-index: 2;
      display: flex;
      gap: 32px;
    }
    .login-stat-num {
      font-size: 1.4rem;
      font-weight: 800;
      color: #fff;
      line-height: 1;
    }
    .login-stat-label {
      font-size: .75rem;
      color: rgba(255,255,255,.50);
      margin-top: 2px;
    }

    /* ---- Right panel (form) ---- */
    .login-right {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--gray-50);
      padding: 48px 32px;
    }

    .login-card {
      width: 100%;
      max-width: 420px;
    }

    .login-card h1 {
      font-size: 1.7rem;
      font-weight: 800;
      color: var(--navy);
      letter-spacing: -.5px;
      margin-bottom: 6px;
    }
    .login-card .login-sub {
      font-size: .88rem;
      color: var(--gray-400);
      margin-bottom: 32px;
    }
    .login-card .login-sub a {
      color: var(--blue);
      font-weight: 600;
    }
    .login-card .login-sub a:hover { text-decoration: underline; }

    /* Input group */
    .tg-input-wrap {
      position: relative;
      margin-bottom: 16px;
    }
    .tg-input-wrap label {
      display: block;
      font-size: .82rem;
      font-weight: 600;
      color: var(--gray-600);
      margin-bottom: 6px;
    }
    .tg-input-wrap .input-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gray-400);
      font-size: 1rem;
      pointer-events: none;
    }
    /* shift icon down because of label */
    .tg-input-wrap .input-icon { top: calc(50% + 12px); }

    .tg-input {
      width: 100%;
      padding: 11px 14px 11px 40px;
      border: 1.5px solid var(--gray-200);
      border-radius: var(--radius-md);
      font-size: .9rem;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--gray-800);
      background: var(--white);
      transition: var(--transition);
      outline: none;
    }
    .tg-input:focus {
      border-color: var(--blue);
      box-shadow: 0 0 0 3px rgba(37,99,235,.10);
    }
    .tg-input::placeholder { color: var(--gray-400); }

    /* Toggle password visibility */
    .tg-eye-btn {
      position: absolute;
      right: 14px;
      top: calc(50% + 12px);
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--gray-400);
      cursor: pointer;
      padding: 0;
      font-size: 1rem;
      line-height: 1;
      transition: var(--transition);
    }
    .tg-eye-btn:hover { color: var(--blue); }

    /* Remember & forgot */
    .login-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 4px 0 22px;
      font-size: .83rem;
    }
    .login-meta label { color: var(--gray-600); cursor: pointer; }
    .login-meta a { color: var(--blue); font-weight: 600; }
    .login-meta a:hover { text-decoration: underline; }

    /* Submit button */
    .tg-btn-login {
      width: 100%;
      background: var(--blue);
      color: #fff;
      border: none;
      border-radius: var(--radius-md);
      padding: 13px;
      font-size: 1rem;
      font-weight: 700;
      font-family: 'Plus Jakarta Sans', sans-serif;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      letter-spacing: .2px;
    }
    .tg-btn-login:hover {
      background: var(--blue-light);
      box-shadow: var(--shadow-blue);
      transform: translateY(-1px);
    }
    .tg-btn-login:active { transform: translateY(0); }

    /* Divider */
    .login-divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 24px 0;
      color: var(--gray-400);
      font-size: .8rem;
    }
    .login-divider::before,
    .login-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--gray-200);
    }

    /* Alert */
    .tg-login-alert {
      border-radius: var(--radius-md);
      padding: 12px 16px;
      font-size: .86rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
      animation: tgFadeIn .3s ease;
    }
    .tg-login-alert.error   { background: rgba(239,68,68,.08);  color: #991B1B; border: 1px solid rgba(239,68,68,.20); }
    .tg-login-alert.success { background: rgba(16,185,129,.08); color: #065F46; border: 1px solid rgba(16,185,129,.20); }

    /* Fade-in animation */
    .fade-up {
      opacity: 0;
      transform: translateY(18px);
      animation: fadeUp .5s ease forwards;
    }
    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
    }
    .fade-up:nth-child(1) { animation-delay: .05s; }
    .fade-up:nth-child(2) { animation-delay: .12s; }
    .fade-up:nth-child(3) { animation-delay: .19s; }
    .fade-up:nth-child(4) { animation-delay: .26s; }
    .fade-up:nth-child(5) { animation-delay: .33s; }

    /* Responsive */
    @media (max-width: 767px) {
      .login-left { display: none; }
      .login-right { padding: 32px 20px; }
    }
  </style>
</head>
<body style="background:var(--gray-50)">

<div class="login-page">

  <!-- ======= LEFT PANEL ======= -->
  <div class="login-left">
    <div class="dot-grid"></div>

    <!-- Logo -->
    <a href="<?= APP_URL ?>/index.php" class="login-left-logo">
      <i class="bi bi-airplane-fill"></i> Travel<span>Go</span>
    </a>

    <!-- Visual tengah -->
    <div class="login-visual">
      <span class="login-visual-icon">✈️</span>
      <h2>Selamat Datang<br>Kembali di <span>TravelGo</span></h2>
      <p>Masuk untuk melihat riwayat perjalanan,<br>
         mengelola booking, dan menikmati promo eksklusif.</p>
    </div>

    <!-- Stats bawah -->
    <div class="login-stats">
      <div>
        <div class="login-stat-num">2.4 Jt+</div>
        <div class="login-stat-label">Pengguna Aktif</div>
      </div>
      <div>
        <div class="login-stat-num">850+</div>
        <div class="login-stat-label">Rute Aktif</div>
      </div>
      <div>
        <div class="login-stat-num">4.8 ★</div>
        <div class="login-stat-label">Rating App</div>
      </div>
    </div>
  </div>

  <!-- ======= RIGHT PANEL (FORM) ======= -->
  <div class="login-right">
    <div class="login-card">

      <!-- Heading -->
      <div class="fade-up">
        <h1>Masuk</h1>
        <p class="login-sub">
          Belum punya akun?
          <a href="<?= APP_URL ?>/pages/register.php">Daftar gratis</a>
        </p>
      </div>

      <!-- Alert error / success -->
      <?php if ($error): ?>
        <div class="tg-login-alert error fade-up">
          <i class="bi bi-exclamation-circle-fill"></i> <?= $error ?>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="tg-login-alert success fade-up">
          <i class="bi bi-check-circle-fill"></i> <?= $success ?>
        </div>
      <?php endif; ?>

      <!-- Form -->
      <form method="POST" action="" novalidate>

        <!-- Email -->
        <div class="tg-input-wrap fade-up">
          <label for="email">Alamat Email</label>
          <i class="bi bi-envelope input-icon"></i>
          <input
            type="email"
            id="email"
            name="email"
            class="tg-input"
            placeholder="contoh@email.com"
            value="<?= clean($_POST['email'] ?? '') ?>"
            required
            autocomplete="email"
          >
        </div>

        <!-- Password -->
        <div class="tg-input-wrap fade-up">
          <label for="password">Password</label>
          <i class="bi bi-lock input-icon"></i>
          <input
            type="password"
            id="password"
            name="password"
            class="tg-input"
            placeholder="Masukkan password"
            required
            autocomplete="current-password"
          >
          <button type="button" class="tg-eye-btn" id="togglePwd" title="Tampilkan/sembunyikan password">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>

        <!-- Remember me & lupa password -->
        <div class="login-meta fade-up">
          <label class="d-flex align-items-center gap-2">
            <input type="checkbox" name="remember" value="1"
                   style="accent-color:var(--blue);width:15px;height:15px;">
            Ingat saya
          </label>
          <a href="<?= APP_URL ?>/pages/forgot-password.php">Lupa password?</a>
        </div>

        <!-- Submit -->
        <div class="fade-up">
          <button type="submit" class="tg-btn-login" id="loginBtn">
            <i class="bi bi-box-arrow-in-right"></i>
            Masuk ke TravelGo
          </button>
        </div>

      </form>

      <!-- Divider -->
      <div class="login-divider fade-up">atau masuk dengan</div>

      <!-- Social login (placeholder) -->
      <div class="d-flex gap-3 fade-up">
        <button type="button"
          onclick="alert('Fitur Google Login segera hadir!')"
          style="flex:1;padding:10px;border:1.5px solid var(--gray-200);border-radius:var(--radius-md);
                 background:#fff;cursor:pointer;font-size:.88rem;font-weight:600;color:var(--gray-800);
                 display:flex;align-items:center;justify-content:center;gap:8px;transition:var(--transition);"
          onmouseover="this.style.borderColor='var(--blue)'"
          onmouseout="this.style.borderColor='var(--gray-200)'">
          <svg width="18" height="18" viewBox="0 0 48 48">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
          </svg>
          Google
        </button>
        <button type="button"
          onclick="alert('Fitur Facebook Login segera hadir!')"
          style="flex:1;padding:10px;border:1.5px solid var(--gray-200);border-radius:var(--radius-md);
                 background:#fff;cursor:pointer;font-size:.88rem;font-weight:600;color:var(--gray-800);
                 display:flex;align-items:center;justify-content:center;gap:8px;transition:var(--transition);"
          onmouseover="this.style.borderColor='var(--blue)'"
          onmouseout="this.style.borderColor='var(--gray-200)'">
          <i class="bi bi-facebook" style="color:#1877F2;font-size:1.1rem;"></i>
          Facebook
        </button>
      </div>

    </div>
  </div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Toggle password visibility
  const togglePwd = document.getElementById('togglePwd');
  const pwdInput  = document.getElementById('password');
  const eyeIcon   = document.getElementById('eyeIcon');

  togglePwd.addEventListener('click', () => {
    const isHidden = pwdInput.type === 'password';
    pwdInput.type  = isHidden ? 'text' : 'password';
    eyeIcon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  // Loading state on submit
  document.querySelector('form').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.innerHTML = '<span class="tg-spinner"></span> Memproses...';
    btn.disabled = true;
  });
</script>

</body>
</html>