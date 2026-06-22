<?php
// ============================================================
//  TravelGo — Admin Login (admin/login.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';

// Kalau sudah login sebagai admin, langsung ke dashboard
if (isLogin() && isAdmin()) {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = clean($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, nama, email, password, role FROM users
             WHERE email = ? AND role = 'admin' AND is_aktif = 1 LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            header('Location: ' . APP_URL . '/admin/dashboard.php');
            exit;
        } else {
            $error = 'Email atau password salah, atau akun bukan admin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    body { background: #0B1426; min-height: 100vh;
           display: flex; align-items: center; justify-content: center;
           font-family: 'Plus Jakarta Sans', sans-serif; }

    .admin-login-card {
      width: 100%; max-width: 400px;
      background: #111827;
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 16px; padding: 36px 32px;
      box-shadow: 0 24px 80px rgba(0,0,0,.5);
    }

    .admin-logo {
      text-align: center; margin-bottom: 28px;
    }
    .admin-logo .icon-wrap {
      width: 56px; height: 56px; background: var(--blue);
      border-radius: 14px; display: flex; align-items: center;
      justify-content: center; margin: 0 auto 12px;
      font-size: 1.4rem; color: #fff;
    }
    .admin-logo h1 { font-size: 1.2rem; font-weight: 800; color: #fff; margin: 0; }
    .admin-logo p  { font-size: .78rem; color: rgba(255,255,255,.4); margin: 4px 0 0; }

    .al-label { font-size: .8rem; font-weight: 600; color: rgba(255,255,255,.6); margin-bottom: 6px; display: block; }
    .al-input {
      width: 100%; padding: 11px 14px;
      background: rgba(255,255,255,.06);
      border: 1.5px solid rgba(255,255,255,.1);
      border-radius: 10px; color: #fff;
      font-size: .9rem; font-family: 'Plus Jakarta Sans', sans-serif;
      outline: none; transition: .2s; margin-bottom: 14px;
    }
    .al-input:focus { border-color: var(--blue); background: rgba(37,99,235,.08); }
    .al-input::placeholder { color: rgba(255,255,255,.25); }

    .al-btn {
      width: 100%; background: var(--blue); color: #fff; border: none;
      border-radius: 10px; padding: 13px; font-size: .95rem; font-weight: 700;
      cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif;
      transition: .2s; display: flex; align-items: center; justify-content: center; gap: 7px;
    }
    .al-btn:hover { background: #1D4ED8; box-shadow: 0 4px 20px rgba(37,99,235,.4); }

    .al-alert {
      background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.25);
      color: #FCA5A5; border-radius: 10px; padding: 11px 14px;
      font-size: .83rem; font-weight: 500; margin-bottom: 16px;
      display: flex; align-items: center; gap: 8px;
    }

    .al-back { text-align: center; margin-top: 20px; font-size: .8rem; color: rgba(255,255,255,.3); }
    .al-back a { color: rgba(255,255,255,.5); text-decoration: none; font-weight: 600; }
    .al-back a:hover { color: #fff; }

    /* Eye toggle */
    .al-pw-wrap { position: relative; }
    .al-eye {
      position: absolute; right: 12px; top: 50%; transform: translateY(-60%);
      background: none; border: none; color: rgba(255,255,255,.3);
      cursor: pointer; font-size: .95rem; padding: 0;
    }
    .al-eye:hover { color: rgba(255,255,255,.7); }
  </style>
</head>
<body>

<div class="admin-login-card">
  <div class="admin-logo">
    <div class="icon-wrap"><i class="bi bi-shield-lock-fill"></i></div>
    <h1>Admin Panel</h1>
    <p><?= APP_NAME ?> — Akses Terbatas</p>
  </div>

  <?php if ($error): ?>
    <div class="al-alert"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?></div>
  <?php endif; ?>

  <form method="POST" action="">
    <label class="al-label">Email Admin</label>
    <input type="email" name="email" class="al-input"
           placeholder="admin@travelgo.com"
           value="<?= clean($_POST['email'] ?? '') ?>" required>

    <label class="al-label">Password</label>
    <div class="al-pw-wrap">
      <input type="password" name="password" id="pwInput" class="al-input"
             placeholder="Masukkan password" required style="padding-right:40px;">
      <button type="button" class="al-eye" onclick="togglePw()">
        <i class="bi bi-eye" id="eyeIcon"></i>
      </button>
    </div>

    <button type="submit" class="al-btn" id="loginBtn">
      <i class="bi bi-shield-check"></i> Masuk ke Admin Panel
    </button>
  </form>

  <div class="al-back">
    <a href="<?= APP_URL ?>/index.php"><i class="bi bi-arrow-left"></i> Kembali ke website</a>
  </div>
</div>

<script>
function togglePw() {
  const i = document.getElementById('pwInput');
  const e = document.getElementById('eyeIcon');
  i.type = i.type === 'password' ? 'text' : 'password';
  e.className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
document.querySelector('form').addEventListener('submit', function() {
  const btn = document.getElementById('loginBtn');
  btn.innerHTML = '<span class="tg-spinner"></span> Memverifikasi...';
  btn.disabled = true;
});
</script>
</body>
</html>