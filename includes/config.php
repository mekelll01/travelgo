<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // default XAMPP
define('DB_PASS', '');            // default XAMPP kosong
define('DB_NAME', 'travelgo');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'TravelGo');
define('APP_URL',     'http://localhost/travelgo');
define('APP_VERSION', '1.0.0');

// Durasi session login (detik) — default 2 jam
define('SESSION_LIFETIME', 7200);

// Batas waktu bayar setelah booking (menit)
define('BATAS_BAYAR_MENIT', 60);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    // Tampilkan pesan ramah ke user, bukan error teknis
    die('<div style="font-family:sans-serif;text-align:center;padding:60px;">
            <h2 style="color:#e53e3e">Koneksi database gagal</h2>
            <p>Pastikan XAMPP sudah berjalan dan database <strong>travelgo</strong> sudah diimport.</p>
            <small style="color:#999">Error: ' . htmlspecialchars($conn->connect_error) . '</small>
         </div>');
}

// Set charset ke utf8mb4 agar karakter khusus aman
$conn->set_charset(DB_CHARSET);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,   // ganti true jika pakai HTTPS
        'httponly' => true,    // cegah akses session dari JavaScript
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isLogin(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin(): void {
    if (!isLogin()) {
        header('Location: ' . APP_URL . '/pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}


function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function dbQuery(mysqli $db, string $sql, string $types = '', array $params = []): mysqli_result|bool {
    if (empty($params)) {
        return $db->query($sql);
    }
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

function generateKodeBooking(): string {
    return 'TG-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
}

function formatRupiah(float $angka): string {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function clean(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Load MongoDB
require_once __DIR__ . '/mongodb.php';