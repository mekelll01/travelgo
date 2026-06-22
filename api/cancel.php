<?php
// ============================================================
//  TravelGo — API Cancel Booking (api/cancel.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (!isLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$uid        = $_SESSION['user_id'];

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Booking ID tidak valid.']);
    exit;
}

// ---- Ambil booking ----
$stmt = $conn->prepare(
    "SELECT id, status, jadwal_id, jml_dewasa, jml_anak
     FROM bookings WHERE id = ? AND user_id = ? LIMIT 1"
);
$stmt->bind_param('ii', $booking_id, $uid);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking tidak ditemukan.']);
    exit;
}

// ---- Validasi status ----
$bisa_cancel = ['pending', 'confirmed'];
if (!in_array($booking['status'], $bisa_cancel)) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking dengan status "' . $booking['status'] . '" tidak dapat dibatalkan.'
    ]);
    exit;
}

// ---- Proses cancel ----
$conn->begin_transaction();
try {
    $status_lama = $booking['status'];
    $jml         = $booking['jml_dewasa'] + $booking['jml_anak'];

    // 1. Update status booking
    $conn->query("UPDATE bookings SET status='cancelled', updated_at=NOW() WHERE id=$booking_id");

    // 2. Kembalikan kursi ke jadwal
    $conn->query(
        "UPDATE jadwal
         SET kursi_terisi = GREATEST(0, kursi_terisi - $jml)
         WHERE id = {$booking['jadwal_id']}"
    );

    // 3. Update pembayaran jika ada yang menunggu
    $conn->query(
        "UPDATE pembayaran
         SET status = 'refund'
         WHERE booking_id = $booking_id AND status = 'menunggu'"
    );

    // 4. Catat log riwayat
    $keterangan = 'Dibatalkan oleh pengguna';
    $conn->query(
        "INSERT INTO riwayat_status
             (booking_id, status_lama, status_baru, keterangan, created_at)
         VALUES ($booking_id, '$status_lama', 'cancelled', '$keterangan', NOW())"
    );

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Booking berhasil dibatalkan.',
        'data'    => ['booking_id' => $booking_id, 'status' => 'cancelled']
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan. Silakan coba lagi.']);
}