<?php
// ============================================================
//  TravelGo — API Seat / Kursi (api/seat.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

$action    = clean($_GET['action'] ?? 'cek');
$jadwal_id = isset($_GET['jadwal_id']) ? (int)$_GET['jadwal_id'] : 0;

if (!$jadwal_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Jadwal ID tidak valid.']);
    exit;
}

switch ($action) {

    // ---- Cek sisa kursi ----
    case 'cek':
        $row = $conn->query(
            "SELECT j.kapasitas, j.kursi_terisi,
                    (j.kapasitas - j.kursi_terisi) AS sisa,
                    j.kelas
             FROM jadwal j WHERE j.id = $jadwal_id AND j.is_aktif = 1"
        )->fetch_assoc();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data'    => [
                'kapasitas'    => (int)$row['kapasitas'],
                'kursi_terisi' => (int)$row['kursi_terisi'],
                'sisa'         => (int)$row['sisa'],
                'kelas'        => $row['kelas'],
                'hampir_habis' => (int)$row['sisa'] <= 10,
                'penuh'        => (int)$row['sisa'] <= 0,
            ]
        ]);
        break;

    // ---- Kursi yang sudah terpakai (untuk seat map) ----
    case 'terpakai':
        // Ambil nomor kursi yang sudah dipesan pada jadwal ini
        $sql = "
            SELECT p.no_kursi
            FROM penumpang p
            JOIN bookings b ON p.booking_id = b.id
            WHERE b.jadwal_id = $jadwal_id
              AND b.status NOT IN ('cancelled','expired')
              AND p.no_kursi IS NOT NULL
              AND p.no_kursi != ''
        ";
        $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        $kursi_terpakai = array_column($rows, 'no_kursi');

        echo json_encode([
            'success'        => true,
            'kursi_terpakai' => $kursi_terpakai,
            'jumlah'         => count($kursi_terpakai),
        ]);
        break;

    // ---- Validasi kursi sebelum booking ----
    case 'validasi':
        $no_kursi = clean($_GET['no_kursi'] ?? '');

        if (empty($no_kursi)) {
            echo json_encode(['success' => false, 'message' => 'Nomor kursi tidak boleh kosong.']);
            exit;
        }

        // Cek apakah kursi sudah dipakai
        $sql = "
            SELECT COUNT(*) AS n
            FROM penumpang p
            JOIN bookings b ON p.booking_id = b.id
            WHERE b.jadwal_id = $jadwal_id
              AND b.status NOT IN ('cancelled','expired')
              AND p.no_kursi = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $no_kursi);
        $stmt->execute();
        $n = (int)$stmt->get_result()->fetch_assoc()['n'];

        if ($n > 0) {
            echo json_encode([
                'success'    => false,
                'tersedia'   => false,
                'message'    => "Kursi $no_kursi sudah dipesan.",
            ]);
        } else {
            echo json_encode([
                'success'  => true,
                'tersedia' => true,
                'message'  => "Kursi $no_kursi tersedia.",
            ]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
        break;
}