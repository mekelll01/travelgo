<?php
// ============================================================
//  TravelGo — API Notifikasi (api/notification.php)
//  Data dari MongoDB
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mongodb.php';

header('Content-Type: application/json');

if (!isLogin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$action = clean($_GET['action'] ?? $_POST['action'] ?? 'list');
$uid    = $_SESSION['user_id'];

switch ($action) {

    // ---- Ambil notifikasi user ----
    case 'list':
        $col = mongoCol('notifications');
        if (!$col) {
            echo json_encode(['success' => false, 'data' => [], 'unread' => 0]);
            exit;
        }

        // Ambil notif broadcast (user_id=0) + notif khusus user ini
        $cursor = $col->find(
            ['$or' => [
                ['user_id' => 0],
                ['user_id' => $uid],
            ]],
            [
                'sort'  => ['created_at' => -1],
                'limit' => 10,
            ]
        );

        $notifs = [];
        foreach ($cursor as $doc) {
            $notifs[] = [
                'id'         => (string)$doc['_id'],
                'judul'      => $doc['judul'] ?? '',
                'pesan'      => $doc['pesan'] ?? '',
                'tipe'       => $doc['tipe'] ?? 'info',
                'is_read'    => $doc['is_read'] ?? false,
                'created_at' => isset($doc['created_at'])
                    ? date('d M Y H:i', $doc['created_at']->toDateTime()->getTimestamp())
                    : '',
            ];
        }

        // Hitung unread
        $unread = $col->countDocuments([
            '$or'     => [['user_id' => 0], ['user_id' => $uid]],
            'is_read' => false,
        ]);

        echo json_encode([
            'success' => true,
            'data'    => $notifs,
            'unread'  => (int)$unread,
        ]);
        break;

    // ---- Tandai sudah dibaca ----
    case 'read':
        $notif_id = clean($_POST['notif_id'] ?? '');
        $col      = mongoCol('notifications');

        if (!$col || empty($notif_id)) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
            exit;
        }

        try {
            $col->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($notif_id)],
                ['$set' => ['is_read' => true]]
            );
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal update.']);
        }
        break;

    // ---- Tandai semua sudah dibaca ----
    case 'read_all':
        $col = mongoCol('notifications');
        if (!$col) {
            echo json_encode(['success' => false]);
            exit;
        }
        $col->updateMany(
            ['$or' => [['user_id' => 0], ['user_id' => $uid]]],
            ['$set' => ['is_read' => true]]
        );
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
        break;
}