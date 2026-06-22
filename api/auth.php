<?php
// ============================================================
//  TravelGo — API Auth (api/auth.php)
//  Handles: logout
// ============================================================
require_once __DIR__ . '/../includes/config.php';

$action = clean($_GET['action'] ?? $_POST['action'] ?? '');

header('Content-Type: application/json');

switch ($action) {

    // ---- Logout ----
    case 'logout':
        session_destroy();
        // Kalau dipanggil via browser (bukan AJAX), redirect
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Logout berhasil.']);
        break;

    // ---- Cek status login (AJAX) ----
    case 'check':
        echo json_encode([
            'logged_in' => isLogin(),
            'user'      => isLogin() ? [
                'id'    => $_SESSION['user_id'],
                'nama'  => $_SESSION['nama'],
                'role'  => $_SESSION['role'],
            ] : null,
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
        break;
}