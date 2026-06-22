<?php
// ============================================================
//  TravelGo — Halaman E-Tiket (pages/ticket.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle  = 'E-Tiket';
$activeMenu = '';

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$booking_id) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

// ---- Ambil data booking lengkap ----
$sql = "
    SELECT b.*,
           j.kode_jadwal, j.jam_berangkat, j.jam_tiba, j.kelas,
           j.harga_dewasa, j.harga_anak, j.tanggal_berangkat, j.fasilitas,
           r.durasi_menit,
           o.nama AS operator_nama, o.kode AS operator_kode,
           ka.nama AS kota_asal,   ka.kode AS kota_asal_kode,
           kt.nama AS kota_tujuan, kt.kode AS kota_tujuan_kode,
           jt.nama AS jenis_nama,
           u.nama AS user_nama, u.email AS user_email, u.no_hp AS user_hp,
           pay.metode, pay.bank_atau_dompet, pay.kode_bayar,
           pay.jumlah AS jumlah_bayar, pay.waktu_bayar, pay.status AS pay_status
    FROM bookings b
    JOIN jadwal j              ON b.jadwal_id = j.id
    JOIN rute r                ON j.rute_id = r.id
    JOIN operator o            ON r.operator_id = o.id
    JOIN kota ka               ON r.kota_asal_id = ka.id
    JOIN kota kt               ON r.kota_tujuan_id = kt.id
    JOIN jenis_transportasi jt ON r.jenis_transportasi_id = jt.id
    JOIN users u               ON b.user_id = u.id
    LEFT JOIN pembayaran pay   ON b.id = pay.booking_id
    WHERE b.id = ? AND b.user_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

// ---- Ambil daftar penumpang ----
$penumpang = $conn->query(
    "SELECT * FROM penumpang WHERE booking_id = $booking_id ORDER BY tipe DESC, id ASC"
)->fetch_all(MYSQLI_ASSOC);

// Helper
function formatDurasi(int $m): string {
    return intdiv($m,60).'j '.($m%60>0?$m%60.:'');
}

// Ikon jenis transportasi
$jenisIkon = ['Pesawat'=>'airplane','Kereta'=>'train-front','Bus'=>'bus-front','Kapal'=>'water','Travel'=>'car-front'];
$ikon = $jenisIkon[$booking['jenis_nama']] ?? 'ticket-detailed';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.ticket-page { background:var(--gray-100); min-height:100vh; padding:32px 0 64px; }

/* Steps */
.booking-steps { display:flex; align-items:center; gap:0; margin-bottom:28px; }
.bstep { display:flex; align-items:center; gap:8px; font-size:.82rem; font-weight:600; }
.bstep-num {
  width:28px; height:28px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:.78rem; font-weight:800;
  background:var(--gray-200); color:var(--gray-400); flex-shrink:0;
}
.bstep.done .bstep-num  { background:var(--success); color:#fff; }
.bstep-label { color:var(--gray-400); }
.bstep.done .bstep-label { color:var(--navy); font-weight:700; }
.bstep-line { flex:1; height:2px; background:var(--gray-200); margin:0 10px; }
.bstep-line.done { background:var(--success); }

/* Success banner */
.success-banner {
  background:linear-gradient(135deg, #065F46 0%, #059669 100%);
  border-radius:var(--radius-lg); padding:24px 28px;
  display:flex; align-items:center; gap:20px; margin-bottom:20px;
  position:relative; overflow:hidden;
}
.success-banner::before {
  content:''; position:absolute; inset:0;
  background-image:radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
  background-size:20px 20px;
}
.success-icon {
  width:56px; height:56px; background:rgba(255,255,255,.15);
  border-radius:50%; display:flex; align-items:center; justify-content:center;
  font-size:1.6rem; flex-shrink:0; position:relative; z-index:1;
}
.success-text { position:relative; z-index:1; }
.success-text h2 { color:#fff; font-size:1.2rem; font-weight:800; margin:0 0 4px; }
.success-text p  { color:rgba(255,255,255,.7); font-size:.85rem; margin:0; }

/* E-Tiket card */
.eticket {
  background:var(--white); border-radius:var(--radius-lg);
  overflow:hidden; box-shadow:var(--shadow-md);
  margin-bottom:16px; border:1px solid var(--gray-200);
}

/* Tiket header */
.eticket-header {
  background:linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
  padding:20px 28px; position:relative; overflow:hidden;
  display:flex; align-items:center; justify-content:space-between;
}
.eticket-header::before {
  content:''; position:absolute; inset:0;
  background-image:radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
  background-size:20px 20px;
}
.et-header-left { position:relative; z-index:1; }
.et-logo { font-size:1.2rem; font-weight:800; color:#fff; display:flex; align-items:center; gap:6px; }
.et-logo span { color:#60A5FA; }
.et-kode { font-size:.72rem; color:rgba(255,255,255,.5); margin-top:2px; font-family:monospace; letter-spacing:1px; }
.et-header-right { position:relative; z-index:1; text-align:right; }
.et-status-badge {
  display:inline-flex; align-items:center; gap:6px;
  padding:5px 12px; border-radius:50px;
  font-size:.75rem; font-weight:700;
}
.et-status-badge.paid  { background:rgba(16,185,129,.2); color:#34D399; }
.et-status-badge.pending { background:rgba(245,158,11,.2); color:#FBBF24; }

/* Tiket body */
.eticket-body { padding:24px 28px; }

/* Rute besar */
.et-route {
  display:flex; align-items:center; gap:0; margin-bottom:24px;
}
.et-city-block { flex:1; }
.et-city-block.right { text-align:right; }
.et-time { font-size:2.2rem; font-weight:800; color:var(--navy); line-height:1; }
.et-city-name { font-size:.88rem; font-weight:700; color:var(--gray-600); margin-top:4px; }
.et-city-code { font-size:.72rem; color:var(--gray-400); }
.et-middle {
  flex:0 0 160px; display:flex; flex-direction:column;
  align-items:center; gap:4px; padding:0 16px;
}
.et-plane-icon { font-size:1.3rem; color:var(--blue); }
.et-line {
  width:100%; height:1px; background:var(--gray-200);
  position:relative;
}
.et-line::before, .et-line::after {
  content:''; position:absolute; top:50%;
  transform:translateY(-50%);
  width:8px; height:8px; border-radius:50%;
  background:var(--gray-300);
}
.et-line::before { left:-4px; }
.et-line::after  { right:-4px; }
.et-dur { font-size:.75rem; color:var(--gray-400); font-weight:600; }

/* Perforasi tiket */
.et-perforation {
  margin:0 -1px; height:0;
  border-top:2px dashed var(--gray-200);
  position:relative;
}
.et-perforation::before, .et-perforation::after {
  content:''; position:absolute; top:50%;
  transform:translateY(-50%);
  width:24px; height:24px; border-radius:50%;
  background:var(--gray-100);
}
.et-perforation::before { left:-12px; border:1px solid var(--gray-200); }
.et-perforation::after  { right:-12px; border:1px solid var(--gray-200); }

/* Detail info grid */
.et-info-grid {
  display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
  gap:16px; padding:20px 28px;
  background:var(--gray-50);
}
.et-info-item .label {
  font-size:.7rem; color:var(--gray-400); font-weight:600;
  text-transform:uppercase; letter-spacing:.4px; margin-bottom:3px;
}
.et-info-item .value {
  font-size:.9rem; font-weight:700; color:var(--navy);
}

/* Penumpang list */
.et-passengers { padding:20px 28px; border-top:1px solid var(--gray-100); }
.et-pass-title {
  font-size:.82rem; font-weight:800; color:var(--gray-500);
  text-transform:uppercase; letter-spacing:.4px; margin-bottom:12px;
}
.et-pass-row {
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 14px; border-radius:var(--radius-md);
  background:var(--gray-50); margin-bottom:8px;
}
.et-pass-name { font-size:.9rem; font-weight:700; color:var(--navy); }
.et-pass-meta { font-size:.75rem; color:var(--gray-400); margin-top:2px; }
.et-pass-kursi {
  font-size:.85rem; font-weight:800; color:var(--blue);
  background:rgba(37,99,235,.08);
  padding:4px 10px; border-radius:6px;
}

/* QR placeholder */
.et-qr {
  padding:20px 28px; border-top:1px solid var(--gray-100);
  display:flex; align-items:center; gap:20px;
}
.qr-box {
  width:100px; height:100px; border:2px solid var(--gray-200);
  border-radius:var(--radius-md);
  display:flex; align-items:center; justify-content:center;
  flex-shrink:0; background:var(--white);
  font-size:.6rem; color:var(--gray-300); text-align:center;
}
.qr-info { font-size:.82rem; color:var(--gray-600); line-height:1.6; }
.qr-info strong { color:var(--navy); }

/* Action buttons */
.ticket-actions { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.btn-action {
  display:flex; align-items:center; gap:7px;
  padding:10px 20px; border-radius:var(--radius-md);
  font-size:.88rem; font-weight:700; cursor:pointer;
  transition:var(--transition); text-decoration:none; border:none;
  font-family:'Plus Jakarta Sans',sans-serif;
}
.btn-action.primary { background:var(--blue); color:#fff; }
.btn-action.primary:hover { background:var(--blue-light); box-shadow:var(--shadow-blue); }
.btn-action.outline {
  background:var(--white); color:var(--navy);
  border:1.5px solid var(--gray-200);
}
.btn-action.outline:hover { border-color:var(--blue); color:var(--blue); }

/* Sidebar info */
.info-card {
  background:var(--white); border:1px solid var(--gray-200);
  border-radius:var(--radius-lg); padding:20px 22px;
  margin-bottom:14px;
}
.info-card-title {
  font-size:.82rem; font-weight:800; color:var(--navy);
  margin-bottom:14px; padding-bottom:8px;
  border-bottom:1px solid var(--gray-100);
  display:flex; align-items:center; gap:6px;
}

.fade-up { opacity:0; transform:translateY(12px); animation:fuAnim .4s ease forwards; }
.fade-up:nth-child(1){animation-delay:.05s}
.fade-up:nth-child(2){animation-delay:.12s}
.fade-up:nth-child(3){animation-delay:.19s}
@keyframes fuAnim { to { opacity:1; transform:translateY(0); } }

/* Rating modal */
.rating-modal-bg {
  position:fixed; inset:0; background:rgba(0,0,0,.6);
  z-index:9999; display:flex; align-items:center; justify-content:center;
  animation:fadeIn .3s ease;
}
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
.rating-modal {
  background:var(--white); border-radius:20px;
  padding:32px 28px; width:100%; max-width:420px;
  text-align:center; position:relative;
  box-shadow:0 24px 80px rgba(0,0,0,.3);
  animation:slideUp .3s ease;
}
@keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
.rating-modal-close {
  position:absolute; top:14px; right:16px;
  background:none; border:none; font-size:1.2rem;
  color:var(--gray-400); cursor:pointer;
}
.rating-modal-close:hover { color:var(--navy); }
.rating-emoji { font-size:3rem; margin-bottom:12px; display:block; }
.rating-modal h3 { font-size:1.15rem; font-weight:800; color:var(--navy); margin-bottom:6px; }
.rating-modal p  { font-size:.85rem; color:var(--gray-400); margin-bottom:20px; }
.rating-stars-big { display:flex; justify-content:center; gap:10px; margin-bottom:20px; }
.rating-star-big {
  font-size:2.4rem; cursor:pointer; color:var(--gray-200);
  transition:.15s; line-height:1;
}
.rating-star-big.active, .rating-star-big:hover { color:#FCD34D; transform:scale(1.1); }
.rating-textarea {
  width:100%; padding:10px 14px; border:1.5px solid var(--gray-200);
  border-radius:var(--radius-md); font-size:.88rem; resize:none;
  font-family:'Plus Jakarta Sans',sans-serif; outline:none;
  transition:var(--transition); margin-bottom:14px;
}
.rating-textarea:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(37,99,235,.08); }
.btn-submit-rating {
  width:100%; background:var(--blue); color:#fff; border:none;
  border-radius:var(--radius-md); padding:12px; font-size:.95rem;
  font-weight:700; cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif;
  display:flex; align-items:center; justify-content:center; gap:7px;
  transition:var(--transition);
}
.btn-submit-rating:hover { background:var(--blue-light); box-shadow:var(--shadow-blue); }
.btn-skip-rating {
  display:block; margin-top:10px; font-size:.78rem;
  color:var(--gray-400); cursor:pointer; background:none; border:none;
  font-family:'Plus Jakarta Sans',sans-serif;
}
.btn-skip-rating:hover { color:var(--navy); }
  .eticket { box-shadow:none; border:1px solid #ddd; }
  body { background:#fff; }
}

@media(max-width:575px) {
  .et-time  { font-size:1.6rem; }
  .et-middle { flex:0 0 80px; padding:0 8px; }
  .et-info-grid { grid-template-columns:1fr 1fr; }
}
</style>

<div class="ticket-page">
  <div class="container">

    <!-- Steps -->
    <div class="booking-steps mb-4 fade-up no-print">
      <?php
        $steps = ['Pilih Jadwal','Isi Data','Pembayaran','E-Tiket'];
        foreach($steps as $i => $s):
      ?>
        <div class="bstep done">
          <div class="bstep-num"><i class="bi bi-check"></i></div>
          <div class="bstep-label"><?= $s ?></div>
        </div>
        <?php if($i < count($steps)-1): ?>
          <div class="bstep-line done"></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- Success banner -->
    <?php if($booking['status'] === 'paid'): ?>
    <div class="success-banner fade-up no-print">
      <div class="success-icon">✅</div>
      <div class="success-text">
        <h2>Pembayaran Berhasil! Selamat Jalan 🎉</h2>
        <p>E-tiket sudah dikirim ke <strong><?= clean($booking['user_email']) ?></strong>. Tunjukkan QR code saat check-in.</p>
      </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">

      <!-- ===== E-TIKET UTAMA ===== -->
      <div class="col-lg-8">

        <!-- Action buttons -->
        <div class="ticket-actions fade-up no-print">
          <button class="btn-action primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak Tiket
          </button>
          <a href="<?= APP_URL ?>/pages/history.php" class="btn-action outline">
            <i class="bi bi-clock-history"></i> Riwayat Booking
          </a>
          <a href="<?= APP_URL ?>/index.php" class="btn-action outline">
            <i class="bi bi-house"></i> Beranda
          </a>
        </div>

        <!-- E-Tiket Card -->
        <div class="eticket fade-up">

          <!-- Header -->
          <div class="eticket-header">
            <div class="et-header-left">
              <div class="et-logo">
                <i class="bi bi-airplane-fill"></i> Travel<span>Go</span>
              </div>
              <div class="et-kode">BOOKING: <?= clean($booking['kode_booking']) ?></div>
            </div>
            <div class="et-header-right">
              <div class="et-status-badge <?= $booking['status']==='paid'?'paid':'pending' ?>">
                <i class="bi bi-<?= $booking['status']==='paid'?'check-circle-fill':'clock' ?>"></i>
                <?= $booking['status']==='paid' ? 'LUNAS' : strtoupper($booking['status']) ?>
              </div>
              <div style="color:rgba(255,255,255,.5);font-size:.72rem;margin-top:4px;">
                <?= clean($booking['jenis_nama']) ?> · <?= clean($booking['kelas']) ?>
              </div>
            </div>
          </div>

          <!-- Rute besar -->
          <div class="eticket-body">
            <div class="et-route">
              <!-- Asal -->
              <div class="et-city-block">
                <div class="et-time"><?= substr($booking['jam_berangkat'],0,5) ?></div>
                <div class="et-city-name"><?= clean($booking['kota_asal']) ?></div>
                <div class="et-city-code"><?= clean($booking['kota_asal_kode']) ?></div>
              </div>

              <!-- Tengah -->
              <div class="et-middle">
                <i class="bi bi-<?= $ikon ?> et-plane-icon"></i>
                <div class="et-line"></div>
                <div class="et-dur"><?= formatDurasi($booking['durasi_menit']) ?></div>
              </div>

              <!-- Tujuan -->
              <div class="et-city-block right">
                <div class="et-time"><?= substr($booking['jam_tiba'],0,5) ?></div>
                <div class="et-city-name"><?= clean($booking['kota_tujuan']) ?></div>
                <div class="et-city-code"><?= clean($booking['kota_tujuan_kode']) ?></div>
              </div>
            </div>
          </div>

          <!-- Perforasi -->
          <div class="et-perforation"></div>

          <!-- Info detail -->
          <div class="et-info-grid">
            <div class="et-info-item">
              <div class="label">Tanggal</div>
              <div class="value"><?= date('d M Y', strtotime($booking['tanggal_berangkat'])) ?></div>
            </div>
            <div class="et-info-item">
              <div class="label">Operator</div>
              <div class="value"><?= clean($booking['operator_nama']) ?></div>
            </div>
            <div class="et-info-item">
              <div class="label">No. Tiket</div>
              <div class="value" style="font-family:monospace;font-size:.82rem;">
                <?= clean($booking['kode_jadwal']) ?>
              </div>
            </div>
            <div class="et-info-item">
              <div class="label">Kelas</div>
              <div class="value"><?= clean($booking['kelas']) ?></div>
            </div>
            <div class="et-info-item">
              <div class="label">Penumpang</div>
              <div class="value">
                <?= $booking['jml_dewasa'] ?> Dewasa
                <?= $booking['jml_anak'] ? '+ '.$booking['jml_anak'].' Anak' : '' ?>
              </div>
            </div>
            <div class="et-info-item">
              <div class="label">Total Bayar</div>
              <div class="value" style="color:var(--blue);">
                <?= formatRupiah($booking['total_harga']) ?>
              </div>
            </div>
          </div>

          <!-- Perforasi 2 -->
          <div class="et-perforation"></div>

          <!-- Daftar penumpang -->
          <div class="et-passengers">
            <div class="et-pass-title">
              <i class="bi bi-people"></i> Daftar Penumpang
            </div>
            <?php foreach ($penumpang as $p): ?>
              <div class="et-pass-row">
                <div>
                  <div class="et-pass-name"><?= clean($p['nama']) ?></div>
                  <div class="et-pass-meta">
                    <?= $p['tipe'] === 'dewasa' ? '👤 Dewasa' : '👶 Anak' ?>
                    <?= $p['no_identitas'] ? ' · NIK/Paspor: ' . clean($p['no_identitas']) : '' ?>
                  </div>
                </div>
                <div class="et-pass-kursi">
                  <?= $p['no_kursi'] ? '🪑 ' . clean($p['no_kursi']) : 'Kursi bebas' ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Perforasi 3 -->
          <div class="et-perforation"></div>

          <!-- QR Code + instruksi check-in -->
          <div class="et-qr">
            <div class="qr-box">
              <!-- QR Code placeholder (implementasi pakai library qrcode.js) -->
              <div style="text-align:center;">
                <i class="bi bi-qr-code" style="font-size:2.5rem;color:var(--navy);display:block;"></i>
                <div style="font-size:.6rem;color:var(--gray-400);margin-top:4px;">QR Code</div>
              </div>
            </div>
            <div class="qr-info">
              <strong>Cara Check-in:</strong><br>
              1. Tunjukkan QR code ini kepada petugas.<br>
              2. Hadir <strong>30–60 menit</strong> sebelum keberangkatan.<br>
              3. Bawa identitas asli sesuai data tiket.<br>
              <span style="color:var(--gray-400);font-size:.75rem;">
                Kode booking: <strong><?= clean($booking['kode_booking']) ?></strong>
              </span>
            </div>
          </div>

        </div>
        <!-- /E-Tiket Card -->

      </div>

      <!-- ===== SIDEBAR KANAN ===== -->
      <div class="col-lg-4 no-print">

        <!-- Info Pembayaran -->
        <?php if($booking['pay_status']): ?>
        <div class="info-card fade-up">
          <div class="info-card-title">
            <i class="bi bi-receipt" style="color:var(--blue)"></i>
            Info Pembayaran
          </div>
          <div style="font-size:.85rem;">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;color:var(--gray-600);">
              <span>Metode</span>
              <strong style="color:var(--navy);">
                <?= ucwords(str_replace('_',' ', $booking['metode'] ?? '-')) ?>
                <?= $booking['bank_atau_dompet'] ? '('.$booking['bank_atau_dompet'].')' : '' ?>
              </strong>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;color:var(--gray-600);">
              <span>Jumlah</span>
              <strong style="color:var(--blue);"><?= formatRupiah($booking['jumlah_bayar'] ?? 0) ?></strong>
            </div>
            <?php if($booking['waktu_bayar']): ?>
            <div style="display:flex;justify-content:space-between;color:var(--gray-600);">
              <span>Waktu Bayar</span>
              <strong style="color:var(--navy);"><?= date('d M Y H:i', strtotime($booking['waktu_bayar'])) ?></strong>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Info penumpang -->
        <div class="info-card fade-up">
          <div class="info-card-title">
            <i class="bi bi-person-badge" style="color:var(--blue)"></i>
            Info Pemesan
          </div>
          <div style="font-size:.85rem;">
            <div style="margin-bottom:6px;">
              <div style="font-size:.72rem;color:var(--gray-400);font-weight:600;">Nama</div>
              <div style="font-weight:700;color:var(--navy);"><?= clean($booking['user_nama']) ?></div>
            </div>
            <div style="margin-bottom:6px;">
              <div style="font-size:.72rem;color:var(--gray-400);font-weight:600;">Email</div>
              <div style="font-weight:600;color:var(--gray-600);"><?= clean($booking['user_email']) ?></div>
            </div>
            <?php if($booking['user_hp']): ?>
            <div>
              <div style="font-size:.72rem;color:var(--gray-400);font-weight:600;">No. HP</div>
              <div style="font-weight:600;color:var(--gray-600);"><?= clean($booking['user_hp']) ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Kebijakan -->
        <div class="info-card fade-up">
          <div class="info-card-title">
            <i class="bi bi-info-circle" style="color:var(--blue)"></i>
            Kebijakan Tiket
          </div>
          <ul style="font-size:.8rem;color:var(--gray-600);padding-left:16px;margin:0;line-height:1.8;">
            <li>Tiket berlaku sesuai tanggal & jadwal tertera</li>
            <li>Perubahan jadwal dikenakan biaya admin</li>
            <li>Refund maksimal H-3 sebelum keberangkatan</li>
            <li>Bawa identitas asli saat check-in</li>
            <li>TravelGo tidak bertanggung jawab atas keterlambatan operator</li>
          </ul>
        </div>

        <!-- Pesan lagi -->
        <div class="fade-up">
          <a href="<?= APP_URL ?>/index.php"
             style="display:block;text-align:center;background:var(--blue);color:#fff;
                    padding:12px;border-radius:var(--radius-md);font-weight:700;
                    text-decoration:none;font-size:.9rem;transition:var(--transition);"
             onmouseover="this.style.background='var(--blue-light)'"
             onmouseout="this.style.background='var(--blue)'">
            <i class="bi bi-plus-circle"></i> Pesan Tiket Lagi
          </a>
        </div>

      </div>
    </div>
  </div>
</div>

<?php if($booking['status'] === 'paid'): ?>
<!-- =========================================================
     MODAL RATING (muncul otomatis setelah payment)
========================================================= -->
<div class="rating-modal-bg" id="ratingModal" style="display:none;">
  <div class="rating-modal">
    <button class="rating-modal-close" onclick="closeRatingModal()">
      <i class="bi bi-x-lg"></i>
    </button>
    <span class="rating-emoji">🎉</span>
    <h3>Perjalananmu Berhasil Dipesan!</h3>
    <p>
      Bagaimana pengalamanmu memesan tiket<br>
      <strong><?= clean($booking['operator_nama']) ?></strong>
      rute <?= clean($booking['kota_asal']) ?> → <?= clean($booking['kota_tujuan']) ?>?
    </p>

    <!-- Bintang rating -->
    <div class="rating-stars-big" id="ratingStars">
      <?php for($i=1;$i<=5;$i++): ?>
        <span class="rating-star-big" data-val="<?=$i?>" onclick="setRating(<?=$i?>)">★</span>
      <?php endfor; ?>
    </div>
    <div style="font-size:.8rem;color:var(--gray-400);margin-bottom:14px;" id="ratingLabel">
      Klik bintang untuk memberi nilai
    </div>

    <textarea id="ratingKomentar" class="rating-textarea" rows="3"
              placeholder="Ceritakan pengalamanmu..."></textarea>

    <button class="btn-submit-rating" id="btnSubmitRating" onclick="submitRating()">
      <i class="bi bi-send"></i> Kirim Ulasan
    </button>
    <button class="btn-skip-rating" onclick="closeRatingModal()">
      Lewati, mungkin nanti
    </button>
  </div>
</div>

<script>
// ---- Rating modal ----
let selectedRating = 0;
const jadwalId = <?= $booking['jadwal_id'] ?? 0 ?>;
const ratingLabels = ['','Buruk 😞','Kurang 😐','Cukup 🙂','Bagus 😊','Sempurna 🤩'];

// Tampilkan modal setelah 1.5 detik kalau belum pernah rating
window.addEventListener('DOMContentLoaded', () => {
  const alreadyRated = localStorage.getItem('rated_<?= $booking_id ?>');
  if (!alreadyRated) {
    setTimeout(() => {
      document.getElementById('ratingModal').style.display = 'flex';
    }, 1500);
  }
});

function setRating(val) {
  selectedRating = val;
  document.querySelectorAll('.rating-star-big').forEach((s, i) => {
    s.classList.toggle('active', i < val);
  });
  document.getElementById('ratingLabel').textContent = ratingLabels[val] || '';
}

function closeRatingModal() {
  document.getElementById('ratingModal').style.display = 'none';
  localStorage.setItem('rated_<?= $booking_id ?>', '1');
}

function submitRating() {
  if (selectedRating === 0) {
    document.getElementById('ratingLabel').textContent = '⚠️ Pilih rating dulu!';
    document.getElementById('ratingLabel').style.color = 'var(--danger)';
    return;
  }
  const komentar = document.getElementById('ratingKomentar').value.trim();
  const btn = document.getElementById('btnSubmitRating');
  btn.innerHTML = '<span class="tg-spinner"></span> Mengirim...';
  btn.disabled = true;

  fetch('<?= APP_URL ?>/api/review.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=submit&jadwal_id=${jadwalId}&rating=${selectedRating}&komentar=${encodeURIComponent(komentar)}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      document.querySelector('.rating-modal').innerHTML = `
        <div style="padding:20px;">
          <div style="font-size:3rem;margin-bottom:12px;">✅</div>
          <h3 style="color:var(--navy);font-weight:800;margin-bottom:8px;">Terima Kasih!</h3>
          <p style="color:var(--gray-400);font-size:.88rem;">
            Ulasanmu sudah dikirim dan akan ditampilkan setelah diverifikasi admin.
          </p>
          <button onclick="closeRatingModal()"
                  style="margin-top:16px;background:var(--blue);color:#fff;border:none;
                         border-radius:10px;padding:10px 24px;font-weight:700;cursor:pointer;
                         font-family:'Plus Jakarta Sans',sans-serif;">
            Tutup
          </button>
        </div>
      `;
      localStorage.setItem('rated_<?= $booking_id ?>', '1');
    } else {
      btn.innerHTML = '<i class="bi bi-send"></i> Kirim Ulasan';
      btn.disabled = false;
      document.getElementById('ratingLabel').textContent = data.message;
      document.getElementById('ratingLabel').style.color = 'var(--danger)';
    }
  })
  .catch(() => {
    btn.innerHTML = '<i class="bi bi-send"></i> Kirim Ulasan';
    btn.disabled = false;
  });
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>