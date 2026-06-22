<?php
// ============================================================
//  TravelGo — MongoDB Seeder
//  Jalankan sekali: php seeder_mongo.php
// ============================================================
require_once __DIR__ . '/includes/mongodb.php';

if (!$mdb) {
    die("MongoDB tidak terkoneksi!\n");
}

echo "=== TravelGo MongoDB Seeder ===\n\n";

// ============================================================
//  1. PROMOS
// ============================================================
echo "Seeding promos...\n";
$mdb->promos->drop();
$mdb->promos->insertMany([
    [
        'judul'       => 'Flash Sale Pesawat 50%',
        'deskripsi'   => 'Terbang ke Bali mulai Rp 399.000. Promo terbatas!',
        'kode'        => 'TGFLASH50',
        'diskon_pct'  => 50,
        'jenis'       => 'pesawat',
        'gambar'      => 'promo-pesawat.jpg',
        'warna'       => '#1a3a7a',
        'is_aktif'    => true,
        'urutan'      => 1,
        'berlaku_hingga' => new MongoDB\BSON\UTCDateTime(strtotime('+30 days') * 1000),
        'created_at'  => new MongoDB\BSON\UTCDateTime(),
    ],
    [
        'judul'       => 'Diskon Kereta Eksekutif 30%',
        'deskripsi'   => 'Jakarta–Yogyakarta kelas eksekutif lebih hemat.',
        'kode'        => 'KAIEKS30',
        'diskon_pct'  => 30,
        'jenis'       => 'kereta',
        'gambar'      => 'promo-kereta.jpg',
        'warna'       => '#1a4020',
        'is_aktif'    => true,
        'urutan'      => 2,
        'berlaku_hingga' => new MongoDB\BSON\UTCDateTime(strtotime('+15 days') * 1000),
        'created_at'  => new MongoDB\BSON\UTCDateTime(),
    ],
    [
        'judul'       => 'Cashback Bus Malam 20%',
        'deskripsi'   => 'Naik bus malam, cashback langsung ke dompet.',
        'kode'        => 'BUSMALAM20',
        'diskon_pct'  => 20,
        'jenis'       => 'bus',
        'gambar'      => 'promo-bus.jpg',
        'warna'       => '#7c4a00',
        'is_aktif'    => true,
        'urutan'      => 3,
        'berlaku_hingga' => new MongoDB\BSON\UTCDateTime(strtotime('+20 days') * 1000),
        'created_at'  => new MongoDB\BSON\UTCDateTime(),
    ],
    [
        'judul'       => 'Promo Kapal PELNI 25%',
        'deskripsi'   => 'Jelajahi kepulauan Indonesia lebih hemat.',
        'kode'        => 'PELNI25',
        'diskon_pct'  => 25,
        'jenis'       => 'kapal',
        'gambar'      => 'promo-kapal.jpg',
        'warna'       => '#004a7a',
        'is_aktif'    => true,
        'urutan'      => 4,
        'berlaku_hingga' => new MongoDB\BSON\UTCDateTime(strtotime('+25 days') * 1000),
        'created_at'  => new MongoDB\BSON\UTCDateTime(),
    ],
]);
echo "  ✓ 4 promos inserted\n";

// ============================================================
//  2. NOTIFICATIONS (contoh)
// ============================================================
echo "Seeding notifications...\n";
$mdb->notifications->drop();
$mdb->notifications->insertMany([
    [
        'user_id'    => 0, // 0 = broadcast ke semua user
        'judul'      => 'Selamat Datang di TravelGo! 🎉',
        'pesan'      => 'Daftar sekarang dan nikmati promo eksklusif member baru.',
        'tipe'       => 'info',
        'is_read'    => false,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
    ],
    [
        'user_id'    => 0,
        'judul'      => 'Flash Sale Hari Ini!',
        'pesan'      => 'Tiket pesawat diskon 50% hanya hari ini. Gunakan kode TGFLASH50.',
        'tipe'       => 'promo',
        'is_read'    => false,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
    ],
]);
echo "  ✓ 2 notifications inserted\n";

// ============================================================
//  3. REVIEWS (contoh)
// ============================================================
echo "Seeding reviews...\n";
$mdb->reviews->drop();
$mdb->reviews->insertMany([
    [
        'user_id'      => 1,
        'user_nama'    => 'Budi Santoso',
        'jadwal_id'    => 1,
        'operator'     => 'Garuda Indonesia',
        'rute'         => 'Jakarta → Surabaya',
        'rating'       => 5,
        'komentar'     => 'Pelayanan sangat memuaskan, tepat waktu!',
        'is_approved'  => true,
        'created_at'   => new MongoDB\BSON\UTCDateTime(),
    ],
    [
        'user_id'      => 2,
        'user_nama'    => 'Siti Rahayu',
        'jadwal_id'    => 4,
        'operator'     => 'KAI Eksekutif',
        'rute'         => 'Jakarta → Yogyakarta',
        'rating'       => 4,
        'komentar'     => 'Kursi nyaman, makanan enak. Sedikit telat tapi oke.',
        'is_approved'  => true,
        'created_at'   => new MongoDB\BSON\UTCDateTime(),
    ],
    [
        'user_id'      => 3,
        'user_nama'    => 'Ahmad Fauzi',
        'jadwal_id'    => 2,
        'operator'     => 'Lion Air',
        'rute'         => 'Jakarta → Bali',
        'rating'       => 4,
        'komentar'     => 'Harga terjangkau, pelayanan cukup baik.',
        'is_approved'  => true,
        'created_at'   => new MongoDB\BSON\UTCDateTime(),
    ],
    [
        'user_id'      => 4,
        'user_nama'    => 'Dewi Lestari',
        'jadwal_id'    => 6,
        'operator'     => 'DAMRI',
        'rute'         => 'Jakarta → Bandung',
        'rating'       => 5,
        'komentar'     => 'Murah meriah, bis bersih dan nyaman!',
        'is_approved'  => true,
        'created_at'   => new MongoDB\BSON\UTCDateTime(),
    ],
]);
echo "  ✓ 4 reviews inserted\n";

// ============================================================
//  4. Buat index untuk performa
// ============================================================
echo "Creating indexes...\n";
$mdb->promos->createIndex(['is_aktif' => 1, 'urutan' => 1]);
$mdb->notifications->createIndex(['user_id' => 1, 'is_read' => 1]);
$mdb->reviews->createIndex(['jadwal_id' => 1, 'is_approved' => 1]);
$mdb->reviews->createIndex(['operator' => 1]);
$mdb->search_logs->createIndex(['user_id' => 1, 'created_at' => -1]);
$mdb->activity_logs->createIndex(['user_id' => 1, 'created_at' => -1]);
echo "  ✓ Indexes created\n";

echo "\n=== Seeder selesai! ===\n";
echo "Collections: promos, notifications, reviews, search_logs, activity_logs\n";