<?php
// ============================================================
//  TravelGo — API Promo (api/promo.php)
//  Data dari MongoDB
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mongodb.php';

header('Content-Type: application/json');

$action = clean($_GET['action'] ?? 'list');

switch ($action) {

    // ---- Ambil semua promo aktif ----
    case 'list':
        $col = mongoCol('promos');
        if (!$col) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $now    = new MongoDB\BSON\UTCDateTime();
        $cursor = $col->find(
            [
                'is_aktif'        => true,
                'berlaku_hingga'  => ['$gte' => $now],
            ],
            ['sort' => ['urutan' => 1]]
        );

        $promos = [];
        foreach ($cursor as $doc) {
            $promos[] = [
                'id'          => (string)$doc['_id'],
                'judul'       => $doc['judul']      ?? '',
                'deskripsi'   => $doc['deskripsi']  ?? '',
                'kode'        => $doc['kode']        ?? '',
                'diskon_pct'  => (int)($doc['diskon_pct'] ?? 0),
                'jenis'       => $doc['jenis']       ?? '',
                'warna'       => $doc['warna']       ?? '#1a3a7a',
                'gambar'      => $doc['gambar']      ?? '',
                'berlaku_hingga' => isset($doc['berlaku_hingga'])
                    ? date('d M Y', $doc['berlaku_hingga']->toDateTime()->getTimestamp())
                    : '',
            ];
        }

        echo json_encode(['success' => true, 'data' => $promos]);
        break;

    // ---- Validasi kode promo ----
    case 'validasi':
        $kode  = strtoupper(clean($_GET['kode'] ?? ''));
        $total = isset($_GET['total']) ? (float)$_GET['total'] : 0;

        if (empty($kode)) {
            echo json_encode(['success' => false, 'message' => 'Kode promo tidak boleh kosong.']);
            exit;
        }

        $col = mongoCol('promos');
        if (!$col) {
            echo json_encode(['success' => false, 'message' => 'Layanan tidak tersedia.']);
            exit;
        }

        $now   = new MongoDB\BSON\UTCDateTime();
        $promo = $col->findOne([
            'kode'           => $kode,
            'is_aktif'       => true,
            'berlaku_hingga' => ['$gte' => $now],
        ]);

        if (!$promo) {
            echo json_encode(['success' => false, 'message' => 'Kode promo tidak valid atau sudah kedaluwarsa.']);
            exit;
        }

        $diskon_pct = (int)($promo['diskon_pct'] ?? 0);
        $diskon_rp  = round($total * $diskon_pct / 100);
        $total_baru = max(0, $total - $diskon_rp);

        echo json_encode([
            'success'     => true,
            'kode'        => $kode,
            'judul'       => $promo['judul'] ?? '',
            'diskon_pct'  => $diskon_pct,
            'diskon_rp'   => $diskon_rp,
            'diskon_fmt'  => 'Rp ' . number_format($diskon_rp, 0, ',', '.'),
            'total_baru'  => $total_baru,
            'total_fmt'   => 'Rp ' . number_format($total_baru, 0, ',', '.'),
            'message'     => "Promo berhasil! Hemat {$diskon_pct}% (Rp " . number_format($diskon_rp, 0, ',', '.') . ")",
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
        break;
}