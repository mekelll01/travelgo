<?php
// ============================================================
//  TravelGo — Koneksi MongoDB (includes/mongodb.php)
// ============================================================

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;

// ---- Konfigurasi MongoDB ----
define('MONGO_HOST', 'mongodb://mongo:27017');
define('MONGO_DB',   'travelgo');

// ---- Buat koneksi ----
try {
    $mongo = new Client(MONGO_HOST);
    $mdb   = $mongo->selectDatabase(MONGO_DB);
} catch (Exception $e) {
    // Kalau MongoDB tidak jalan, jangan crash — cukup log
    $mongo = null;
    $mdb   = null;
    error_log('MongoDB connection failed: ' . $e->getMessage());
}

// ============================================================
//  Collections yang dipakai TravelGo:
//
//  $mdb->reviews       — ulasan & rating tiket
//  $mdb->notifications — notifikasi user
//  $mdb->promos        — promo & banner dinamis
//  $mdb->search_logs   — log pencarian user
//  $mdb->activity_logs — log aktivitas (login, booking, dll)
// ============================================================

// ---- Helper: ambil collection ----
function mongoCol(string $name): ?\MongoDB\Collection {
    global $mdb;
    if (!$mdb) return null;
    return $mdb->selectCollection($name);
}

// ---- Helper: insert dokumen ----
function mongoInsert(string $collection, array $data): bool {
    $col = mongoCol($collection);
    if (!$col) return false;
    try {
        $data['created_at'] = new \MongoDB\BSON\UTCDateTime();
        $col->insertOne($data);
        return true;
    } catch (Exception $e) {
        error_log('MongoDB insert error: ' . $e->getMessage());
        return false;
    }
}

// ---- Helper: log aktivitas user ----
function logActivity(int $userId, string $action, array $detail = []): void {
    mongoInsert('activity_logs', [
        'user_id' => $userId,
        'action'  => $action,
        'detail'  => $detail,
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
        'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);
}

// ---- Helper: log pencarian ----
function logSearch(int $userId, int $jenisId, int $asalId, int $tujuanId, string $tanggal): void {
    mongoInsert('search_logs', [
        'user_id'   => $userId,
        'jenis_id'  => $jenisId,
        'asal_id'   => $asalId,
        'tujuan_id' => $tujuanId,
        'tanggal'   => $tanggal,
    ]);
}