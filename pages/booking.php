<?php
// ============================================================
//  TravelGo — Halaman Booking (pages/booking.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle  = 'Isi Data Pemesanan';
$activeMenu = '';

// ---- Ambil parameter ----
$jadwal_id     = isset($_GET['jadwal_id'])     ? (int)$_GET['jadwal_id']     : 0;
$jml_dewasa    = isset($_GET['dewasa'])        ? max(1,(int)$_GET['dewasa']) : 1;
$jml_anak      = isset($_GET['anak'])          ? max(0,(int)$_GET['anak'])   : 0;
$tgl_berangkat = isset($_GET['tgl_berangkat']) ? clean($_GET['tgl_berangkat']): '';
$trip_type     = isset($_GET['trip'])          ? clean($_GET['trip'])        : 'sekali_jalan';

if (!$jadwal_id) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

// ---- Ambil detail jadwal ----
$sql = "
    SELECT j.*, r.durasi_menit,
           o.nama AS operator_nama, o.kode AS operator_kode,
           ka.nama AS kota_asal, ka.kode AS kota_asal_kode,
           kt.nama AS kota_tujuan, kt.kode AS kota_tujuan_kode,
           jt.nama AS jenis_nama
    FROM jadwal j
    JOIN rute r       ON j.rute_id = r.id
    JOIN operator o   ON r.operator_id = o.id
    JOIN kota ka      ON r.kota_asal_id = ka.id
    JOIN kota kt      ON r.kota_tujuan_id = kt.id
    JOIN jenis_transportasi jt ON r.jenis_transportasi_id = jt.id
    WHERE j.id = ? AND j.is_aktif = 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $jadwal_id);
$stmt->execute();
$jadwal = $stmt->get_result()->fetch_assoc();

if (!$jadwal) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

// ---- Hitung total harga ----
$total_harga = ($jadwal['harga_dewasa'] * $jml_dewasa) + ($jadwal['harga_anak'] * $jml_anak);

// ---- Proses submit booking ----
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data penumpang dari POST
    $penumpangData = [];
    $valid = true;

    for ($i = 0; $i < $jml_dewasa; $i++) {
        $nama_p = clean($_POST['dewasa_nama'][$i] ?? '');
        $nik    = clean($_POST['dewasa_nik'][$i]  ?? '');
        $kursi  = clean($_POST['dewasa_kursi'][$i] ?? '');
        if (empty($nama_p)) { $valid = false; break; }
        $penumpangData[] = ['nama'=>$nama_p,'tipe'=>'dewasa','no_identitas'=>$nik,'no_kursi'=>$kursi];
    }

    for ($i = 0; $i < $jml_anak; $i++) {
        $nama_p = clean($_POST['anak_nama'][$i] ?? '');
        $kursi  = clean($_POST['anak_kursi'][$i] ?? '');
        if (empty($nama_p)) { $valid = false; break; }
        $penumpangData[] = ['nama'=>$nama_p,'tipe'=>'anak','no_identitas'=>'','no_kursi'=>$kursi];
    }

    if (!$valid) {
        $error = 'Nama penumpang wajib diisi semua.';
    } else {
        // Cek kursi masih tersedia
        $sisa = $jadwal['kapasitas'] - $jadwal['kursi_terisi'];
        if ($sisa < ($jml_dewasa + $jml_anak)) {
            $error = 'Maaf, kursi tidak cukup. Silakan pilih jadwal lain.';
        } else {
            // Buat booking
            $kode_booking = generateKodeBooking();

            $conn->begin_transaction();
            try {
                $uid   = $_SESSION['user_id'];
                $jid   = $jadwal_id;
                $kode  = $kode_booking;
                $trip  = $trip_type;
                $ndew  = $jml_dewasa;
                $nanak = $jml_anak;
                $tot   = (float)$total_harga;

                $stmtB = $conn->prepare(
                    "INSERT INTO bookings (user_id, jadwal_id, kode_booking, tipe_perjalanan,
                     jml_dewasa, jml_anak, total_harga, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
                );
                $stmtB->bind_param('iissiid', $uid, $jid, $kode, $trip, $ndew, $nanak, $tot);
                $stmtB->execute();
                $booking_id = $conn->insert_id;

                // Insert penumpang
                $stmtP = $conn->prepare(
                    "INSERT INTO penumpang (booking_id, nama, tipe, no_identitas, no_kursi)
                     VALUES (?, ?, ?, ?, ?)"
                );
                foreach ($penumpangData as $p) {
                    $stmtP->bind_param('issss',
                        $booking_id, $p['nama'], $p['tipe'], $p['no_identitas'], $p['no_kursi']
                    );
                    $stmtP->execute();
                }

                // Update kursi terisi
                $conn->query("UPDATE jadwal SET kursi_terisi = kursi_terisi + " . ($jml_dewasa + $jml_anak) . " WHERE id = $jadwal_id");

                // Log riwayat status
                $conn->query("INSERT INTO riwayat_status (booking_id, status_lama, status_baru, keterangan, created_at)
                              VALUES ($booking_id, NULL, 'pending', 'Booking dibuat', NOW())");

                $conn->commit();

                // ---- Catat activity log ke MongoDB ----
                try {
                    logActivity($_SESSION['user_id'], 'create_booking', [
                        'booking_id'   => $booking_id,
                        'kode_booking' => $kode_booking,
                        'jadwal_id'    => $jadwal_id,
                        'total_harga'  => $total_harga,
                        'jml_dewasa'   => $jml_dewasa,
                        'jml_anak'     => $jml_anak,
                    ]);
                } catch (Exception $e) { /* silent fail */ }

                // Redirect ke payment
                header('Location: ' . APP_URL . '/pages/payment.php?booking_id=' . $booking_id);
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Terjadi kesalahan. Silakan coba lagi.';
            }
        }
    }
}

// Helper format durasi
function formatDurasi(int $m): string {
    return intdiv($m,60).'j '.($m%60>0?$m%60.:'');
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.booking-page { background: var(--gray-100); min-height: 100vh; padding: 32px 0 64px; }

/* Breadcrumb */
.booking-steps {
  display: flex; align-items: center; gap: 0;
  margin-bottom: 28px;
}
.bstep {
  display: flex; align-items: center; gap: 8px;
  font-size: .82rem; font-weight: 600;
}
.bstep-num {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .78rem; font-weight: 800;
  background: var(--gray-200); color: var(--gray-400);
  flex-shrink: 0;
}
.bstep.active .bstep-num { background: var(--blue); color: #fff; }
.bstep.done .bstep-num   { background: var(--success); color: #fff; }
.bstep-label { color: var(--gray-400); }
.bstep.active .bstep-label { color: var(--navy); font-weight: 700; }
.bstep-line { flex: 1; height: 2px; background: var(--gray-200); margin: 0 10px; }

/* Section card */
.booking-section {
  background: var(--white);
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  padding: 24px 28px;
  margin-bottom: 16px;
}
.booking-section-title {
  font-size: 1rem; font-weight: 800; color: var(--navy);
  margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
  padding-bottom: 12px; border-bottom: 1px solid var(--gray-100);
}
.booking-section-title .bi {
  width: 32px; height: 32px; background: rgba(37,99,235,.08);
  border-radius: 8px; display: flex; align-items: center;
  justify-content: center; color: var(--blue); font-size: 1rem;
}

/* Flight summary card */
.flight-summary {
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
  border-radius: var(--radius-lg); padding: 20px 24px; color: #fff;
  margin-bottom: 16px; position: relative; overflow: hidden;
}
.flight-summary::before {
  content:''; position:absolute; inset:0;
  background-image: radial-gradient(rgba(255,255,255,.04) 1px, transparent 1px);
  background-size:20px 20px;
}
.fs-content { position: relative; z-index: 1; }
.fs-route {
  display: flex; align-items: center; gap: 16px;
  margin-bottom: 16px;
}
.fs-city { text-align: center; }
.fs-time { font-size: 1.8rem; font-weight: 800; line-height: 1; }
.fs-code { font-size: .78rem; color: rgba(255,255,255,.55); margin-top: 2px; }
.fs-middle {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; gap: 4px;
}
.fs-line {
  width: 100%; height: 1px; background: rgba(255,255,255,.2);
  position: relative;
}
.fs-line::after {
  content: '›'; position: absolute; right: -4px; top: 50%;
  transform: translateY(-50%); color: rgba(255,255,255,.4);
}
.fs-dur { font-size: .75rem; color: rgba(255,255,255,.55); }
.fs-meta {
  display: flex; gap: 20px; flex-wrap: wrap;
  padding-top: 14px; border-top: 1px solid rgba(255,255,255,.10);
}
.fs-meta-item { font-size: .8rem; color: rgba(255,255,255,.65); }
.fs-meta-item strong { color: #fff; display: block; font-size: .88rem; }

/* Penumpang form */
.penumpang-card {
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-md);
  padding: 18px 20px; margin-bottom: 12px;
  transition: var(--transition);
}
.penumpang-card:focus-within { border-color: var(--blue); }
.penumpang-label {
  font-size: .8rem; font-weight: 800; color: var(--blue);
  text-transform: uppercase; letter-spacing: .4px;
  margin-bottom: 14px; display: flex; align-items: center; gap: 6px;
}
.penumpang-label.anak { color: var(--warning); }

/* Input field */
.bk-field { margin-bottom: 14px; }
.bk-field label {
  display: block; font-size: .8rem; font-weight: 600;
  color: var(--gray-600); margin-bottom: 5px;
}
.bk-input {
  width: 100%; padding: 10px 14px;
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-md);
  font-size: .88rem; font-family: 'Plus Jakarta Sans', sans-serif;
  color: var(--gray-800); background: var(--white);
  transition: var(--transition); outline: none;
}
.bk-input:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(37,99,235,.08);
}

/* Order summary sidebar */
.order-summary {
  background: var(--white);
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  padding: 20px 22px;
  position: sticky; top: 80px;
}
.os-title {
  font-size: .88rem; font-weight: 800; color: var(--navy);
  margin-bottom: 16px; padding-bottom: 10px;
  border-bottom: 1px solid var(--gray-100);
}
.os-row {
  display: flex; justify-content: space-between;
  font-size: .85rem; margin-bottom: 8px; color: var(--gray-600);
}
.os-row.total {
  font-weight: 800; color: var(--navy);
  font-size: 1rem; padding-top: 10px;
  border-top: 1px solid var(--gray-200); margin-top: 6px;
}
.os-row.total span:last-child { color: var(--blue); }

.btn-book {
  width: 100%; background: var(--blue);
  color: #fff; border: none; border-radius: var(--radius-md);
  padding: 14px; font-size: 1rem; font-weight: 700;
  font-family: 'Plus Jakarta Sans', sans-serif;
  cursor: pointer; transition: var(--transition);
  display: flex; align-items: center;
  justify-content: center; gap: 8px; margin-top: 16px;
}
.btn-book:hover {
  background: var(--blue-light);
  box-shadow: var(--shadow-blue);
  transform: translateY(-1px);
}

/* Alert */
.bk-alert {
  background: rgba(239,68,68,.08); color: #991B1B;
  border: 1px solid rgba(239,68,68,.20);
  border-radius: var(--radius-md); padding: 12px 16px;
  font-size: .86rem; font-weight: 500;
  display: flex; align-items: center; gap: 10px; margin-bottom: 16px;
}

/* Kontak darurat */
.contact-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media(max-width:575px) { .contact-row { grid-template-columns: 1fr; } }

/* Fade in */
.fade-up { opacity:0; transform:translateY(12px); animation: fuAnim .4s ease forwards; }
@keyframes fuAnim { to { opacity:1; transform:translateY(0); } }
</style>

<div class="booking-page">
  <div class="container">

    <!-- Breadcrumb steps -->
    <div class="booking-steps mb-4 fade-up">
      <div class="bstep done">
        <div class="bstep-num"><i class="bi bi-check"></i></div>
        <div class="bstep-label">Pilih Jadwal</div>
      </div>
      <div class="bstep-line"></div>
      <div class="bstep active">
        <div class="bstep-num">2</div>
        <div class="bstep-label">Isi Data</div>
      </div>
      <div class="bstep-line"></div>
      <div class="bstep">
        <div class="bstep-num">3</div>
        <div class="bstep-label">Pembayaran</div>
      </div>
      <div class="bstep-line"></div>
      <div class="bstep">
        <div class="bstep-num">4</div>
        <div class="bstep-label">E-Tiket</div>
      </div>
    </div>

    <div class="row g-4">

      <!-- ===== FORM KIRI ===== -->
      <div class="col-lg-8">

        <?php if ($error): ?>
          <div class="bk-alert"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
        <?php endif; ?>

        <!-- Detail Penerbangan -->
        <div class="fade-up">
          <div class="flight-summary">
            <div class="fs-content">
              <div style="font-size:.75rem;color:rgba(255,255,255,.5);margin-bottom:12px;font-weight:600;letter-spacing:.4px;">
                <?= strtoupper(clean($jadwal['jenis_nama'])) ?> · <?= clean($jadwal['kode_jadwal']) ?> · <?= clean($jadwal['kelas']) ?>
              </div>
              <div class="fs-route">
                <div class="fs-city">
                  <div class="fs-time"><?= substr($jadwal['jam_berangkat'],0,5) ?></div>
                  <div class="fs-code"><?= clean($jadwal['kota_asal_kode'] ?: $jadwal['kota_asal']) ?></div>
                </div>
                <div class="fs-middle">
                  <div class="fs-line"></div>
                  <div class="fs-dur"><?= formatDurasi($jadwal['durasi_menit']) ?></div>
                </div>
                <div class="fs-city">
                  <div class="fs-time"><?= substr($jadwal['jam_tiba'],0,5) ?></div>
                  <div class="fs-code"><?= clean($jadwal['kota_tujuan_kode'] ?: $jadwal['kota_tujuan']) ?></div>
                </div>
              </div>
              <div class="fs-meta">
                <div class="fs-meta-item">
                  <strong><?= clean($jadwal['operator_nama']) ?></strong>
                  Operator
                </div>
                <div class="fs-meta-item">
                  <strong><?= date('d M Y', strtotime($tgl_berangkat ?: $jadwal['tanggal_berangkat'])) ?></strong>
                  Tanggal
                </div>
                <div class="fs-meta-item">
                  <strong><?= $jml_dewasa ?> Dewasa<?= $jml_anak ? ', '.$jml_anak.' Anak' : '' ?></strong>
                  Penumpang
                </div>
                <div class="fs-meta-item">
                  <strong><?= $jadwal['kapasitas'] - $jadwal['kursi_terisi'] ?> Kursi</strong>
                  Tersisa
                </div>
              </div>
            </div>
          </div>
        </div>

        <form method="POST" action="" id="bookingForm">

          <!-- Data Penumpang Dewasa -->
          <?php if ($jml_dewasa > 0): ?>
          <div class="booking-section fade-up">
            <div class="booking-section-title">
              <i class="bi bi-person-fill"></i>
              Data Penumpang Dewasa
            </div>
            <?php for ($i = 0; $i < $jml_dewasa; $i++): ?>
              <div class="penumpang-card">
                <div class="penumpang-label">
                  <i class="bi bi-person-circle"></i>
                  Dewasa <?= $jml_dewasa > 1 ? ($i+1) : '' ?>
                </div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="bk-field">
                      <label>Nama Lengkap <span style="color:var(--danger)">*</span></label>
                      <input type="text" name="dewasa_nama[]" class="bk-input"
                             placeholder="Sesuai KTP/Paspor" required
                             value="<?= $i===0 ? clean($_SESSION['nama']??'') : '' ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="bk-field">
                      <label>No. KTP / Paspor</label>
                      <input type="text" name="dewasa_nik[]" class="bk-input"
                             placeholder="Opsional">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="bk-field">
                      <label>Nomor Kursi <span style="color:var(--gray-400);font-weight:400">(opsional)</span></label>
                      <input type="text" name="dewasa_kursi[]" class="bk-input"
                             placeholder="Contoh: 12A"
                             style="text-transform:uppercase;"
                             maxlength="5">
                    </div>
                  </div>
                </div>
              </div>
            <?php endfor; ?>
          </div>
          <?php endif; ?>

          <!-- Data Penumpang Anak -->
          <?php if ($jml_anak > 0): ?>
          <div class="booking-section fade-up">
            <div class="booking-section-title">
              <i class="bi bi-person-hearts"></i>
              Data Penumpang Anak (2–11 tahun)
            </div>
            <?php for ($i = 0; $i < $jml_anak; $i++): ?>
              <div class="penumpang-card">
                <div class="penumpang-label anak">
                  <i class="bi bi-emoji-smile"></i>
                  Anak <?= $jml_anak > 1 ? ($i+1) : '' ?>
                </div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="bk-field">
                      <label>Nama Lengkap <span style="color:var(--danger)">*</span></label>
                      <input type="text" name="anak_nama[]" class="bk-input"
                             placeholder="Nama anak" required>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="bk-field">
                      <label>Nomor Kursi <span style="color:var(--gray-400);font-weight:400">(opsional)</span></label>
                      <input type="text" name="anak_kursi[]" class="bk-input"
                             placeholder="Contoh: 12B" maxlength="5"
                             style="text-transform:uppercase;">
                    </div>
                  </div>
                </div>
              </div>
            <?php endfor; ?>
          </div>
          <?php endif; ?>

          <!-- Kontak Darurat -->
          <div class="booking-section fade-up">
            <div class="booking-section-title">
              <i class="bi bi-telephone-fill"></i>
              Kontak & Pengiriman E-Tiket
            </div>
            <div class="contact-row">
              <div class="bk-field">
                <label>Email <span style="color:var(--danger)">*</span></label>
                <input type="email" name="email_kontak" class="bk-input"
                       placeholder="Email untuk e-tiket"
                       value="<?= clean($_SESSION['email'] ?? '') ?>" required>
              </div>
              <div class="bk-field">
                <label>No. Telepon / WhatsApp</label>
                <input type="tel" name="telepon_kontak" class="bk-input"
                       placeholder="08xxxxxxxxxx">
              </div>
            </div>
            <div class="bk-field">
              <label>Catatan Tambahan <span style="color:var(--gray-400);font-weight:400">(opsional)</span></label>
              <textarea name="catatan" class="bk-input" rows="2"
                        placeholder="Permintaan khusus, kebutuhan disabilitas, dll."
                        style="resize:vertical;"></textarea>
            </div>
          </div>

          <!-- Submit mobile (hanya tampil di mobile) -->
          <div class="d-lg-none fade-up">
            <div style="background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:20px;">
              <div class="os-row total mb-3">
                <span>Total Pembayaran</span>
                <span style="color:var(--blue);font-size:1.1rem;"><?= formatRupiah($total_harga) ?></span>
              </div>
              <button type="submit" class="btn-book" id="btnBookMobile">
                <i class="bi bi-lock-fill"></i> Lanjut ke Pembayaran
              </button>
            </div>
          </div>

        </form>
      </div>

      <!-- ===== SIDEBAR KANAN ===== -->
      <div class="col-lg-4">
        <div class="order-summary fade-up">
          <div class="os-title">Ringkasan Pesanan</div>

          <!-- Rute -->
          <div style="background:var(--gray-50);border-radius:var(--radius-md);padding:12px;margin-bottom:14px;">
            <div style="font-size:.78rem;color:var(--gray-400);font-weight:600;margin-bottom:4px;">
              <?= clean($jadwal['jenis_nama']) ?> · <?= clean($jadwal['kelas']) ?>
            </div>
            <div style="font-size:.92rem;font-weight:700;color:var(--navy);">
              <?= clean($jadwal['kota_asal']) ?>
              <i class="bi bi-arrow-right" style="color:var(--blue);font-size:.8rem;"></i>
              <?= clean($jadwal['kota_tujuan']) ?>
            </div>
            <div style="font-size:.8rem;color:var(--gray-600);margin-top:2px;">
              <?= date('d M Y', strtotime($tgl_berangkat ?: $jadwal['tanggal_berangkat'])) ?> ·
              <?= substr($jadwal['jam_berangkat'],0,5) ?> – <?= substr($jadwal['jam_tiba'],0,5) ?>
            </div>
          </div>

          <!-- Rincian harga -->
          <?php if ($jml_dewasa > 0): ?>
          <div class="os-row">
            <span><?= $jml_dewasa ?>x Dewasa</span>
            <span><?= formatRupiah($jadwal['harga_dewasa'] * $jml_dewasa) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($jml_anak > 0): ?>
          <div class="os-row">
            <span><?= $jml_anak ?>x Anak</span>
            <span><?= formatRupiah($jadwal['harga_anak'] * $jml_anak) ?></span>
          </div>
          <?php endif; ?>
          <div class="os-row">
            <span>Biaya Layanan</span>
            <span style="color:var(--success);">Gratis</span>
          </div>
          <div class="os-row total">
            <span>Total</span>
            <span><?= formatRupiah($total_harga) ?></span>
          </div>

          <!-- Tombol lanjut -->
          <button type="submit" form="bookingForm" class="btn-book" id="btnBook">
            <i class="bi bi-lock-fill"></i> Lanjut ke Pembayaran
          </button>

          <!-- Info keamanan -->
          <div style="margin-top:14px;text-align:center;font-size:.75rem;color:var(--gray-400);
                      display:flex;align-items:center;justify-content:center;gap:6px;">
            <i class="bi bi-shield-check" style="color:var(--success);"></i>
            Transaksi diproteksi enkripsi SSL
          </div>

          <!-- Batas bayar info -->
          <div style="margin-top:12px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.20);
                      border-radius:var(--radius-md);padding:10px 12px;font-size:.78rem;color:#92400E;">
            <i class="bi bi-clock" style="color:var(--warning);"></i>
            Selesaikan pembayaran dalam <strong>60 menit</strong> setelah booking dibuat.
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// Loading state saat submit
document.getElementById('bookingForm').addEventListener('submit', function() {
  ['btnBook','btnBookMobile'].forEach(id => {
    const btn = document.getElementById(id);
    if (btn) {
      btn.innerHTML = '<span class="tg-spinner"></span> Memproses...';
      btn.disabled = true;
    }
  });
});

// Auto uppercase kursi
document.querySelectorAll('input[name="dewasa_kursi[]"], input[name="anak_kursi[]"]').forEach(el => {
  el.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>