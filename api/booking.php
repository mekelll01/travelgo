<?php
// ============================================================
//  TravelGo — API Booking (api/booking.php)
//  Handles: cancel
// ============================================================
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Wajib login
if (!isLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$action     = clean($_POST['action'] ?? $_GET['action'] ?? '');
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$uid        = $_SESSION['user_id'];

switch ($action) {

    // ---- Cancel booking ----
    case 'cancel':
        if (!$booking_id) {
            echo json_encode(['success' => false, 'message' => 'Booking ID tidak valid.']);
            exit;
        }

        // Ambil booking, pastikan milik user ini dan status masih pending
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

        if (!in_array($booking['status'], ['pending', 'confirmed'])) {
            echo json_encode(['success' => false, 'message' => 'Booking dengan status "' . $booking['status'] . '" tidak dapat dibatalkan.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            // Update status booking
            $conn->query("UPDATE bookings SET status='cancelled', updated_at=NOW() WHERE id=$booking_id");

            // Kembalikan kursi
            $jml = $booking['jml_dewasa'] + $booking['jml_anak'];
            $conn->query("UPDATE jadwal SET kursi_terisi = GREATEST(0, kursi_terisi - $jml) WHERE id = {$booking['jadwal_id']}");

            // Update status pembayaran jika ada
            $conn->query("UPDATE pembayaran SET status='refund' WHERE booking_id=$booking_id AND status='menunggu'");

            // Log riwayat
            $conn->query("INSERT INTO riwayat_status (booking_id, status_lama, status_baru, keterangan, created_at)
                          VALUES ($booking_id, '{$booking['status']}', 'cancelled', 'Dibatalkan oleh user', NOW())");

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Booking berhasil dibatalkan.']);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan. Silakan coba lagi.']);
        }
        break;

    // ---- Detail booking (AJAX) ----
    case 'detail':
        if (!$booking_id) {
            echo json_encode(['success' => false, 'message' => 'Booking ID tidak valid.']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT b.kode_booking, b.status, b.total_harga, b.created_at,
                   ka.nama AS kota_asal, kt.nama AS kota_tujuan,
                   j.jam_berangkat, j.jam_tiba, j.tanggal_berangkat
            FROM bookings b
            JOIN jadwal j    ON b.jadwal_id = j.id
            JOIN rute r      ON j.rute_id = r.id
            JOIN kota ka     ON r.kota_asal_id = ka.id
            JOIN kota kt     ON r.kota_tujuan_id = kt.id
            WHERE b.id = ? AND b.user_id = ?
        ");
        $stmt->bind_param('ii', $booking_id, $uid);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();

        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Booking tidak ditemukan.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
        break;
}