<?php
// ============================================================
//  TravelGo — API Review (api/review.php)
//  Data dari MongoDB
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mongodb.php';

header('Content-Type: application/json');

$action = clean($_GET['action'] ?? $_POST['action'] ?? 'list');

switch ($action) {

    // ---- Ambil review per jadwal / operator ----
    case 'list':
        $jadwal_id = isset($_GET['jadwal_id']) ? (int)$_GET['jadwal_id'] : 0;
        $operator  = clean($_GET['operator'] ?? '');
        $col       = mongoCol('reviews');

        if (!$col) {
            echo json_encode(['success' => true, 'data' => [], 'avg' => 0, 'total' => 0]);
            exit;
        }

        $filter = ['is_approved' => true];
        if ($jadwal_id) $filter['jadwal_id'] = $jadwal_id;
        if ($operator)  $filter['operator']  = $operator;

        $cursor  = $col->find($filter, ['sort' => ['created_at' => -1], 'limit' => 20]);
        $reviews = [];
        $total   = 0;
        $sumRating = 0;

        foreach ($cursor as $doc) {
            $reviews[] = [
                'id'         => (string)$doc['_id'],
                'user_nama'  => $doc['user_nama'] ?? 'Anonim',
                'operator'   => $doc['operator']  ?? '',
                'rute'       => $doc['rute']       ?? '',
                'rating'     => (int)($doc['rating'] ?? 5),
                'komentar'   => $doc['komentar']   ?? '',
                'created_at' => isset($doc['created_at'])
                    ? date('d M Y', $doc['created_at']->toDateTime()->getTimestamp())
                    : '',
            ];
            $sumRating += (int)($doc['rating'] ?? 5);
            $total++;
        }

        $avg = $total > 0 ? round($sumRating / $total, 1) : 0;

        echo json_encode([
            'success' => true,
            'data'    => $reviews,
            'avg'     => $avg,
            'total'   => $total,
        ]);
        break;

    // ---- Ambil semua review (untuk homepage) ----
    case 'all':
        $col = mongoCol('reviews');
        if (!$col) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }
        $cursor  = $col->find(
            ['is_approved' => true],
            ['sort' => ['created_at' => -1], 'limit' => 6]
        );
        $reviews = [];
        foreach ($cursor as $doc) {
            $reviews[] = [
                'id'        => (string)$doc['_id'],
                'user_nama' => $doc['user_nama'] ?? 'Anonim',
                'operator'  => $doc['operator']  ?? '',
                'rute'      => $doc['rute']       ?? '',
                'rating'    => (int)($doc['rating'] ?? 5),
                'komentar'  => $doc['komentar']   ?? '',
                'created_at'=> isset($doc['created_at'])
                    ? date('d M Y', $doc['created_at']->toDateTime()->getTimestamp())
                    : '',
            ];
        }
        echo json_encode(['success' => true, 'data' => $reviews]);
        break;

    // ---- Submit review baru ----
    case 'submit':
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

        $jadwal_id = isset($_POST['jadwal_id']) ? (int)$_POST['jadwal_id'] : 0;
        $rating    = isset($_POST['rating'])    ? max(1, min(5, (int)$_POST['rating'])) : 5;
        $komentar  = clean($_POST['komentar'] ?? '');

        if (!$jadwal_id || empty($komentar)) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
            exit;
        }

        // Cek booking user untuk jadwal ini
        $booking = $conn->query(
            "SELECT b.id FROM bookings b
             WHERE b.user_id = {$_SESSION['user_id']}
               AND b.jadwal_id = $jadwal_id
               AND b.status = 'paid'
             LIMIT 1"
        )->fetch_assoc();

        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Kamu belum pernah memesan tiket ini.']);
            exit;
        }

        // Cek sudah pernah review belum
        $col = mongoCol('reviews');
        if (!$col) {
            echo json_encode(['success' => false, 'message' => 'Layanan tidak tersedia.']);
            exit;
        }

        $existing = $col->findOne([
            'user_id'   => $_SESSION['user_id'],
            'jadwal_id' => $jadwal_id,
        ]);
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Kamu sudah memberikan ulasan untuk tiket ini.']);
            exit;
        }

        // Ambil info jadwal dari MySQL
        $jadwalInfo = $conn->query(
            "SELECT o.nama AS op, ka.nama AS asal, kt.nama AS tujuan
             FROM jadwal j
             JOIN rute r ON j.rute_id = r.id
             JOIN operator o ON r.operator_id = o.id
             JOIN kota ka ON r.kota_asal_id = ka.id
             JOIN kota kt ON r.kota_tujuan_id = kt.id
             WHERE j.id = $jadwal_id LIMIT 1"
        )->fetch_assoc();

        $ok = mongoInsert('reviews', [
            'user_id'     => $_SESSION['user_id'],
            'user_nama'   => clean($_SESSION['nama'] ?? 'User'),
            'jadwal_id'   => $jadwal_id,
            'operator'    => $jadwalInfo['op']    ?? '',
            'rute'        => ($jadwalInfo['asal'] ?? '') . ' → ' . ($jadwalInfo['tujuan'] ?? ''),
            'rating'      => $rating,
            'komentar'    => $komentar,
            'is_approved' => false, // perlu approve admin dulu
        ]);

        if ($ok) {
            // Log aktivitas
            logActivity($_SESSION['user_id'], 'submit_review', [
                'jadwal_id' => $jadwal_id,
                'rating'    => $rating,
            ]);
            echo json_encode(['success' => true, 'message' => 'Ulasan berhasil dikirim, menunggu persetujuan.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ulasan.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
        break;
}