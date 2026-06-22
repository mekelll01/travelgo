<?php
// ============================================================
//  TravelGo — Halaman Pembayaran (pages/payment.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mongodb.php';
requireLogin();

$pageTitle  = 'Pembayaran';
$activeMenu = '';

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$booking_id) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

// ---- Ambil data booking ----
$sql = "
    SELECT b.*,
           j.kode_jadwal, j.jam_berangkat, j.jam_tiba, j.kelas, j.harga_dewasa, j.harga_anak,
           r.durasi_menit,
           o.nama AS operator_nama,
           ka.nama AS kota_asal,   ka.kode AS kota_asal_kode,
           kt.nama AS kota_tujuan, kt.kode AS kota_tujuan_kode,
           jt.nama AS jenis_nama,
           u.nama AS user_nama, u.email AS user_email
    FROM bookings b
    JOIN jadwal j              ON b.jadwal_id = j.id
    JOIN rute r                ON j.rute_id = r.id
    JOIN operator o            ON r.operator_id = o.id
    JOIN kota ka               ON r.kota_asal_id = ka.id
    JOIN kota kt               ON r.kota_tujuan_id = kt.id
    JOIN jenis_transportasi jt ON r.jenis_transportasi_id = jt.id
    JOIN users u               ON b.user_id = u.id
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

// Kalau sudah paid, langsung ke tiket
if ($booking['status'] === 'paid') {
    header('Location: ' . APP_URL . '/pages/ticket.php?booking_id=' . $booking_id);
    exit;
}

// Cek apakah sudah ada pembayaran pending
$pay = $conn->query("SELECT * FROM pembayaran WHERE booking_id = $booking_id LIMIT 1")->fetch_assoc();

$error   = '';
$success = '';

// ---- Variabel promo/diskon ----
$kode_promo_applied = '';
$diskon_rp          = 0;
$total_bayar        = (float)$booking['total_harga'];

// Ambil promo dari session kalau ada
if (isset($_SESSION['promo_'.$booking_id])) {
    $promoSess          = $_SESSION['promo_'.$booking_id];
    $kode_promo_applied = $promoSess['kode'];
    $diskon_rp          = $promoSess['diskon_rp'];
    $total_bayar        = $promoSess['total_bayar'];
}

// ---- Proses submit pilihan metode bayar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$pay) {
    $metode      = clean($_POST['metode']      ?? '');
    $bank_dompet = clean($_POST['bank_dompet'] ?? '');
    $kode_promo  = strtoupper(clean($_POST['kode_promo'] ?? ''));

    // Validasi promo kalau ada
    if (!empty($kode_promo)) {
        try {
            $col = mongoCol('promos');
            $now = new MongoDB\BSON\UTCDateTime();
            $promo = $col ? $col->findOne([
                'kode'           => $kode_promo,
                'is_aktif'       => true,
                'berlaku_hingga' => ['$gte' => $now],
            ]) : null;

            if ($promo) {
                $diskon_pct  = (int)($promo['diskon_pct'] ?? 0);
                $diskon_rp   = round($booking['total_harga'] * $diskon_pct / 100);
                $total_bayar = max(0, $booking['total_harga'] - $diskon_rp);
                $kode_promo_applied = $kode_promo;
                // Simpan di session
                $_SESSION['promo_'.$booking_id] = [
                    'kode'      => $kode_promo,
                    'diskon_rp' => $diskon_rp,
                    'total_bayar'=> $total_bayar,
                ];
            } else {
                $error = 'Kode promo tidak valid atau sudah kedaluwarsa.';
            }
        } catch (Exception $e) {
            // MongoDB error, lanjut tanpa promo
        }
    }

    $allowed = ['transfer_bank','kartu_kredit','dompet_digital','minimarket'];
    if (empty($error) && !in_array($metode, $allowed)) {
        $error = 'Pilih metode pembayaran.';
    }

    if (empty($error)) {
        $kode_bayar  = strtoupper(substr($metode,0,3)) . '-' . rand(100000,999999);
        $batas_bayar = date('Y-m-d H:i:s', strtotime('+' . BATAS_BAYAR_MENIT . ' minutes'));

        $stmtPay = $conn->prepare(
            "INSERT INTO pembayaran (booking_id, metode, bank_atau_dompet, kode_bayar,
             jumlah, status, batas_bayar, created_at)
             VALUES (?, ?, ?, ?, ?, 'menunggu', ?, NOW())"
        );
        $stmtPay->bind_param('isssds', $booking_id, $metode, $bank_dompet,
                              $kode_bayar, $total_bayar, $batas_bayar);

        if ($stmtPay->execute()) {
            // Update total harga di bookings kalau ada diskon
            if ($diskon_rp > 0) {
                $conn->query("UPDATE bookings SET total_harga = $total_bayar WHERE id = $booking_id");
            }
            // Hapus session promo
            unset($_SESSION['promo_'.$booking_id]);
            header('Location: ' . APP_URL . '/pages/payment.php?booking_id=' . $booking_id);
            exit;
        } else {
            $error = 'Terjadi kesalahan. Silakan coba lagi.';
        }
    }
}

// ---- Simulasi konfirmasi bayar (dev only) ----
if (isset($_GET['confirm']) && $_GET['confirm'] === '1' && $pay) {
    $conn->query("UPDATE pembayaran SET status='berhasil', waktu_bayar=NOW() WHERE booking_id=$booking_id");
    $conn->query("UPDATE bookings SET status='paid', updated_at=NOW() WHERE id=$booking_id");
    $conn->query("INSERT INTO riwayat_status (booking_id, status_lama, status_baru, keterangan, created_at)
                  VALUES ($booking_id, 'pending', 'paid', 'Pembayaran berhasil', NOW())");
    header('Location: ' . APP_URL . '/pages/ticket.php?booking_id=' . $booking_id);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.pay-page { background: var(--gray-100); min-height: 100vh; padding: 32px 0 64px; }

/* Steps */
.booking-steps { display:flex; align-items:center; gap:0; margin-bottom:28px; }
.bstep { display:flex; align-items:center; gap:8px; font-size:.82rem; font-weight:600; }
.bstep-num {
  width:28px; height:28px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:.78rem; font-weight:800;
  background:var(--gray-200); color:var(--gray-400); flex-shrink:0;
}
.bstep.active .bstep-num { background:var(--blue); color:#fff; }
.bstep.done .bstep-num   { background:var(--success); color:#fff; }
.bstep-label { color:var(--gray-400); }
.bstep.active .bstep-label { color:var(--navy); font-weight:700; }
.bstep-line { flex:1; height:2px; background:var(--gray-200); margin:0 10px; }

/* Section */
.pay-section {
  background:var(--white); border:1px solid var(--gray-200);
  border-radius:var(--radius-lg); padding:24px 28px; margin-bottom:16px;
}
.pay-section-title {
  font-size:1rem; font-weight:800; color:var(--navy);
  margin-bottom:20px; padding-bottom:12px;
  border-bottom:1px solid var(--gray-100);
  display:flex; align-items:center; gap:10px;
}

/* Metode bayar grid */
.metode-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; }
.metode-card {
  border:2px solid var(--gray-200); border-radius:var(--radius-md);
  padding:14px 12px; cursor:pointer; transition:var(--transition);
  display:flex; flex-direction:column; align-items:center; gap:8px;
  text-align:center; position:relative;
}
.metode-card:hover { border-color:var(--blue-light); }
.metode-card input[type=radio] {
  position:absolute; opacity:0; width:0; height:0;
}
.metode-card.selected {
  border-color:var(--blue);
  background:rgba(37,99,235,.04);
  box-shadow:0 0 0 1px var(--blue);
}
.metode-card .mc-icon {
  font-size:1.6rem; line-height:1;
}
.metode-card .mc-label {
  font-size:.78rem; font-weight:700; color:var(--navy); line-height:1.2;
}
.metode-card .mc-sub {
  font-size:.7rem; color:var(--gray-400);
}
.metode-card.selected::after {
  content:'\F26E';  /* Bootstrap icon check-circle-fill */
  font-family:'bootstrap-icons';
  position:absolute; top:6px; right:8px;
  color:var(--blue); font-size:.85rem;
}

/* Bank/dompet options */
.bank-options { display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; }
.bank-chip {
  padding:7px 14px; border:1.5px solid var(--gray-200);
  border-radius:50px; font-size:.82rem; font-weight:600;
  color:var(--gray-600); cursor:pointer; transition:var(--transition);
  background:var(--white);
}
.bank-chip:hover { border-color:var(--blue); color:var(--blue); }
.bank-chip.selected { background:var(--blue); color:#fff; border-color:var(--blue); }

/* Instruksi bayar */
.instruksi-box {
  background:var(--gray-50); border:1px solid var(--gray-200);
  border-radius:var(--radius-md); padding:20px 22px;
}
.kode-bayar-box {
  background:var(--white); border:2px dashed var(--blue);
  border-radius:var(--radius-md); padding:16px 20px;
  text-align:center; margin:14px 0;
}
.kode-bayar-num {
  font-size:1.6rem; font-weight:800; color:var(--blue);
  letter-spacing:2px; font-family:monospace;
}
.kode-copy-btn {
  background:none; border:1.5px solid var(--blue);
  color:var(--blue); border-radius:var(--radius-md);
  padding:5px 14px; font-size:.8rem; font-weight:700;
  cursor:pointer; margin-top:8px; transition:var(--transition);
}
.kode-copy-btn:hover { background:var(--blue); color:#fff; }

/* Countdown timer */
.countdown-box {
  background:rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.25);
  border-radius:var(--radius-md); padding:14px 16px;
  display:flex; align-items:center; gap:12px; margin-bottom:16px;
}
.countdown-num {
  font-size:1.4rem; font-weight:800; color:var(--warning);
  font-family:monospace; letter-spacing:1px; min-width:70px;
}
.countdown-label { font-size:.8rem; color:#92400E; }

/* Instruksi steps */
.instruksi-steps { counter-reset: step; }
.instruksi-step {
  display:flex; gap:12px; margin-bottom:12px;
  font-size:.86rem; color:var(--gray-600); line-height:1.5;
}
.instruksi-step::before {
  counter-increment: step;
  content: counter(step);
  width:22px; height:22px; border-radius:50%;
  background:var(--blue); color:#fff;
  font-size:.72rem; font-weight:800; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  margin-top:1px;
}

/* Order summary */
.order-summary {
  background:var(--white); border:1px solid var(--gray-200);
  border-radius:var(--radius-lg); padding:20px 22px;
  position:sticky; top:80px;
}
.os-title { font-size:.88rem; font-weight:800; color:var(--navy);
  margin-bottom:16px; padding-bottom:10px;
  border-bottom:1px solid var(--gray-100); }
.os-row {
  display:flex; justify-content:space-between;
  font-size:.85rem; margin-bottom:8px; color:var(--gray-600);
}
.os-row.total {
  font-weight:800; color:var(--navy); font-size:1rem;
  padding-top:10px; border-top:1px solid var(--gray-200); margin-top:6px;
}
.os-row.total span:last-child { color:var(--blue); }

.btn-pay {
  width:100%; background:var(--blue); color:#fff;
  border:none; border-radius:var(--radius-md);
  padding:14px; font-size:1rem; font-weight:700;
  font-family:'Plus Jakarta Sans',sans-serif;
  cursor:pointer; transition:var(--transition);
  display:flex; align-items:center; justify-content:center; gap:8px;
  margin-top:16px;
}
.btn-pay:hover { background:var(--blue-light); box-shadow:var(--shadow-blue); transform:translateY(-1px); }

/* Dev confirm btn */
.btn-confirm-dev {
  width:100%; background:var(--success); color:#fff;
  border:none; border-radius:var(--radius-md);
  padding:13px; font-size:.9rem; font-weight:700;
  cursor:pointer; margin-top:10px;
  font-family:'Plus Jakarta Sans',sans-serif;
  transition:var(--transition);
}
.btn-confirm-dev:hover { opacity:.9; }

/* Promo input */
.promo-wrap {
  background: rgba(37,99,235,.04); border: 1.5px solid rgba(37,99,235,.15);
  border-radius: var(--radius-md); padding: 16px 18px; margin-bottom: 16px;
}
.promo-wrap-title {
  font-size: .82rem; font-weight: 800; color: var(--navy);
  margin-bottom: 12px; display: flex; align-items: center; gap: 6px;
}
.promo-input-row { display: flex; gap: 8px; }
.promo-input {
  flex: 1; padding: 10px 14px; border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-md); font-size: .9rem; outline: none;
  font-family: 'Plus Jakarta Sans', sans-serif; text-transform: uppercase;
  transition: var(--transition);
}
.promo-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.08); }
.promo-btn {
  padding: 10px 18px; background: var(--blue); color: #fff;
  border: none; border-radius: var(--radius-md); font-size: .88rem;
  font-weight: 700; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif;
  transition: var(--transition); white-space: nowrap;
}
.promo-btn:hover { background: var(--blue-light); }
.promo-result { margin-top: 10px; font-size: .83rem; display: none; }
.promo-result.success {
  background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.2);
  border-radius: var(--radius-md); padding: 10px 14px; color: #065F46;
  display: flex; align-items: center; gap: 8px;
}
.promo-result.error {
  background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.2);
  border-radius: var(--radius-md); padding: 10px 14px; color: #991B1B;
  display: flex; align-items: center; gap: 8px;
}

/* Alert */
.pay-alert {
  background:rgba(239,68,68,.08); color:#991B1B;
  border:1px solid rgba(239,68,68,.20);
  border-radius:var(--radius-md); padding:12px 16px;
  font-size:.86rem; font-weight:500;
  display:flex; align-items:center; gap:10px; margin-bottom:16px;
}

.fade-up { opacity:0; transform:translateY(12px); animation:fuAnim .4s ease forwards; }
@keyframes fuAnim { to { opacity:1; transform:translateY(0); } }
</style>

<div class="pay-page">
  <div class="container">

    <!-- Steps -->
    <div class="booking-steps mb-4 fade-up">
      <div class="bstep done">
        <div class="bstep-num"><i class="bi bi-check"></i></div>
        <div class="bstep-label">Pilih Jadwal</div>
      </div>
      <div class="bstep-line"></div>
      <div class="bstep done">
        <div class="bstep-num"><i class="bi bi-check"></i></div>
        <div class="bstep-label">Isi Data</div>
      </div>
      <div class="bstep-line"></div>
      <div class="bstep active">
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

      <!-- ===== KIRI ===== -->
      <div class="col-lg-8">

        <?php if ($error): ?>
          <div class="pay-alert"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
        <?php endif; ?>

        <?php if (!$pay): ?>
        <!-- ====== PILIH METODE BAYAR ====== -->
        <form method="POST" action="" id="payForm">
          <div class="pay-section fade-up">
            <div class="pay-section-title">
              <i class="bi bi-credit-card-2-front" style="color:var(--blue)"></i>
              Pilih Metode Pembayaran
            </div>

            <div class="metode-grid" id="metodeGrid">

              <!-- Transfer Bank -->
              <label class="metode-card" id="card_transfer_bank">
                <input type="radio" name="metode" value="transfer_bank">
                <div class="mc-icon">🏦</div>
                <div class="mc-label">Transfer Bank</div>
                <div class="mc-sub">Virtual Account</div>
              </label>

              <!-- Kartu Kredit -->
              <label class="metode-card" id="card_kartu_kredit">
                <input type="radio" name="metode" value="kartu_kredit">
                <div class="mc-icon">💳</div>
                <div class="mc-label">Kartu Kredit</div>
                <div class="mc-sub">Visa / Mastercard</div>
              </label>

              <!-- Dompet Digital -->
              <label class="metode-card" id="card_dompet_digital">
                <input type="radio" name="metode" value="dompet_digital">
                <div class="mc-icon">📱</div>
                <div class="mc-label">Dompet Digital</div>
                <div class="mc-sub">GoPay, OVO, Dana</div>
              </label>

              <!-- Minimarket -->
              <label class="metode-card" id="card_minimarket">
                <input type="radio" name="metode" value="minimarket">
                <div class="mc-icon">🏪</div>
                <div class="mc-label">Minimarket</div>
                <div class="mc-sub">Alfamart, Indomaret</div>
              </label>

            </div>

            <!-- Sub-pilihan bank/dompet -->
            <div id="subOptions" style="display:none;margin-top:16px;">
              <div style="font-size:.82rem;font-weight:700;color:var(--gray-600);margin-bottom:8px;" id="subLabel">
                Pilih Bank:
              </div>
              <div class="bank-options" id="bankChips"></div>
              <input type="hidden" name="bank_dompet" id="bankDompetVal">
            </div>

          </div>

          <!-- Kode Promo -->
          <div class="promo-wrap fade-up">
            <div class="promo-wrap-title">
              <i class="bi bi-tag-fill" style="color:#FBBF24;"></i>
              Kode Promo
              <?php if ($kode_promo_applied): ?>
                <span style="background:rgba(16,185,129,.1);color:var(--success);
                             padding:2px 10px;border-radius:50px;font-size:.72rem;">
                  ✓ <?= clean($kode_promo_applied) ?> diterapkan
                </span>
              <?php endif; ?>
            </div>

            <?php if (!$kode_promo_applied): ?>
            <div class="promo-input-row">
              <input type="text" id="promoInput" class="promo-input"
                     placeholder="Masukkan kode promo (contoh: TGFLASH50)"
                     oninput="this.value=this.value.toUpperCase()">
              <button type="button" class="promo-btn" onclick="validasiPromo()">
                <i class="bi bi-check2"></i> Pakai
              </button>
            </div>
            <div id="promoResult" class="promo-result"></div>
            <input type="hidden" name="kode_promo" id="kodePromoHidden" value="">
            <?php else: ?>
            <!-- Promo sudah diterapkan -->
            <div class="promo-result success" style="display:flex;">
              <i class="bi bi-check-circle-fill"></i>
              <div>
                Promo <strong><?= clean($kode_promo_applied) ?></strong> berhasil diterapkan!
                Hemat <strong><?= formatRupiah($diskon_rp) ?></strong>
              </div>
            </div>
            <input type="hidden" name="kode_promo" value="<?= clean($kode_promo_applied) ?>">
            <?php endif; ?>

            <!-- Rincian harga setelah diskon -->
            <?php if ($diskon_rp > 0): ?>
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--gray-200);">
              <div style="display:flex;justify-content:space-between;font-size:.85rem;color:var(--gray-600);margin-bottom:4px;">
                <span>Harga asli</span>
                <span style="text-decoration:line-through;"><?= formatRupiah($booking['total_harga']) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:.85rem;color:var(--success);margin-bottom:4px;">
                <span>Diskon promo</span>
                <span>- <?= formatRupiah($diskon_rp) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:1rem;font-weight:800;color:var(--navy);margin-top:6px;">
                <span>Total Bayar</span>
                <span style="color:var(--blue);"><?= formatRupiah($total_bayar) ?></span>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <div class="fade-up" style="text-align:right;">
            <button type="submit" class="btn-pay" id="btnPay" style="max-width:300px;margin-left:auto;" disabled>
              <i class="bi bi-lock-fill"></i> Konfirmasi Pembayaran
            </button>
          </div>
        </form>

        <?php else: ?>
        <!-- ====== INSTRUKSI PEMBAYARAN ====== -->
        <div class="pay-section fade-up">
          <div class="pay-section-title">
            <i class="bi bi-hourglass-split" style="color:var(--warning)"></i>
            Instruksi Pembayaran
          </div>

          <!-- Countdown -->
          <?php
            $batas = strtotime($pay['batas_bayar']);
            $now   = time();
            $sisa  = max(0, $batas - $now);
          ?>
          <div class="countdown-box">
            <div>
              <div class="countdown-num" id="countdown">--:--</div>
              <div class="countdown-label">Sisa waktu pembayaran</div>
            </div>
            <div style="font-size:.82rem;color:#92400E;">
              Selesaikan sebelum<br>
              <strong><?= date('d M Y, H:i', $batas) ?> WIB</strong>
            </div>
          </div>

          <!-- Kode bayar -->
          <?php
            $metodeLbl = [
              'transfer_bank'  => 'Nomor Virtual Account',
              'kartu_kredit'   => 'Kode Transaksi',
              'dompet_digital' => 'Kode Pembayaran',
              'minimarket'     => 'Kode Pembayaran',
            ];
          ?>
          <div class="kode-bayar-box">
            <div style="font-size:.78rem;color:var(--gray-400);font-weight:600;margin-bottom:6px;">
              <?= $metodeLbl[$pay['metode']] ?? 'Kode Bayar' ?>
              <?= $pay['bank_atau_dompet'] ? '(' . clean($pay['bank_atau_dompet']) . ')' : '' ?>
            </div>
            <div class="kode-bayar-num" id="kodeBayar"><?= clean($pay['kode_bayar']) ?></div>
            <button class="kode-copy-btn" onclick="copyKode()">
              <i class="bi bi-clipboard"></i> Salin Kode
            </button>
          </div>

          <!-- Instruksi steps -->
          <div class="instruksi-box">
            <?php
              $instruksi = [
                'transfer_bank' => [
                  'Buka aplikasi mobile banking atau ATM bank kamu.',
                  'Pilih menu <strong>Transfer</strong> → <strong>Virtual Account</strong>.',
                  'Masukkan nomor virtual account di atas.',
                  'Pastikan nominal sesuai: <strong>' . formatRupiah($pay['jumlah']) . '</strong>.',
                  'Konfirmasi dan simpan bukti transfer.',
                ],
                'kartu_kredit' => [
                  'Masukkan nomor kartu kredit kamu.',
                  'Isi tanggal kedaluwarsa dan CVV.',
                  'Masukkan OTP yang dikirim ke nomor HP terdaftar.',
                  'Pembayaran sebesar <strong>' . formatRupiah($pay['jumlah']) . '</strong> akan diproses.',
                ],
                'dompet_digital' => [
                  'Buka aplikasi ' . clean($pay['bank_atau_dompet'] ?? 'dompet digital') . ' kamu.',
                  'Pilih menu <strong>Bayar</strong> atau <strong>Scan QR</strong>.',
                  'Masukkan kode pembayaran di atas.',
                  'Konfirmasi nominal: <strong>' . formatRupiah($pay['jumlah']) . '</strong>.',
                ],
                'minimarket' => [
                  'Kunjungi kasir Alfamart atau Indomaret terdekat.',
                  'Tunjukkan kode pembayaran di atas ke kasir.',
                  'Bayar tunai sebesar <strong>' . formatRupiah($pay['jumlah']) . '</strong>.',
                  'Simpan struk sebagai bukti pembayaran.',
                ],
              ];
              $steps = $instruksi[$pay['metode']] ?? $instruksi['transfer_bank'];
            ?>
            <div style="font-size:.82rem;font-weight:700;color:var(--gray-600);margin-bottom:12px;">
              Cara Pembayaran:
            </div>
            <div class="instruksi-steps">
              <?php foreach ($steps as $step): ?>
                <div class="instruksi-step"><?= $step ?></div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Tombol simulasi (hanya untuk development) -->
        <div class="pay-section fade-up" style="background:rgba(16,185,129,.04);border-color:rgba(16,185,129,.2);">
          <div style="font-size:.8rem;font-weight:700;color:var(--success);margin-bottom:8px;">
            <i class="bi bi-code-slash"></i> Mode Development
          </div>
          <p style="font-size:.82rem;color:var(--gray-600);margin-bottom:12px;">
            Untuk testing, klik tombol di bawah untuk simulasi pembayaran berhasil.
          </p>
          <a href="?booking_id=<?= $booking_id ?>&confirm=1" class="btn-confirm-dev">
            <i class="bi bi-check-circle"></i> Simulasi Pembayaran Berhasil
          </a>
        </div>

        <?php endif; ?>
      </div>

      <!-- ===== SIDEBAR KANAN ===== -->
      <div class="col-lg-4">
        <div class="order-summary fade-up">
          <div class="os-title">Ringkasan Pemesanan</div>

          <!-- Booking info -->
          <div style="background:var(--gray-50);border-radius:var(--radius-md);padding:12px;margin-bottom:14px;">
            <div style="font-size:.72rem;color:var(--gray-400);font-weight:600;margin-bottom:4px;">
              Kode Booking
            </div>
            <div style="font-size:1rem;font-weight:800;color:var(--navy);font-family:monospace;letter-spacing:1px;">
              <?= clean($booking['kode_booking']) ?>
            </div>
          </div>

          <!-- Rute -->
          <div style="background:var(--gray-50);border-radius:var(--radius-md);padding:12px;margin-bottom:14px;">
            <div style="font-size:.78rem;color:var(--gray-400);font-weight:600;margin-bottom:4px;">
              <?= clean($booking['jenis_nama']) ?> · <?= clean($booking['kelas']) ?>
            </div>
            <div style="font-size:.92rem;font-weight:700;color:var(--navy);">
              <?= clean($booking['kota_asal']) ?>
              <i class="bi bi-arrow-right" style="color:var(--blue);font-size:.8rem;"></i>
              <?= clean($booking['kota_tujuan']) ?>
            </div>
            <div style="font-size:.8rem;color:var(--gray-600);margin-top:2px;">
              <?= date('d M Y', strtotime($booking['tanggal_berangkat'] ?? 'now')) ?> ·
              <?= substr($booking['jam_berangkat'],0,5) ?> – <?= substr($booking['jam_tiba'],0,5) ?>
            </div>
          </div>

          <!-- Harga -->
          <?php if ($booking['jml_dewasa'] > 0): ?>
          <div class="os-row">
            <span><?= $booking['jml_dewasa'] ?>x Dewasa</span>
            <span><?= formatRupiah($booking['harga_dewasa'] * $booking['jml_dewasa']) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($booking['jml_anak'] > 0): ?>
          <div class="os-row">
            <span><?= $booking['jml_anak'] ?>x Anak</span>
            <span><?= formatRupiah($booking['harga_anak'] * $booking['jml_anak']) ?></span>
          </div>
          <?php endif; ?>
          <div class="os-row">
            <span>Biaya Layanan</span>
            <span style="color:var(--success)">Gratis</span>
          </div>
          <div class="os-row total">
            <span>Total</span>
            <span id="sidebarTotal"><?= formatRupiah($total_bayar) ?></span>
          </div>
          <?php if ($diskon_rp > 0): ?>
          <div style="font-size:.75rem;color:var(--success);margin-top:4px;text-align:right;">
            Hemat <?= formatRupiah($diskon_rp) ?> dengan promo!
          </div>
          <?php endif; ?>

          <!-- Status -->
          <div style="margin-top:14px;padding:10px 12px;border-radius:var(--radius-md);
                      background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);
                      font-size:.8rem;color:#92400E;display:flex;align-items:center;gap:8px;">
            <i class="bi bi-clock" style="color:var(--warning);font-size:1rem;"></i>
            <div>
              Status: <strong>Menunggu Pembayaran</strong><br>
              <span style="font-size:.72rem;">Booking: <?= clean($booking['kode_booking']) ?></span>
            </div>
          </div>

          <!-- Link ke riwayat -->
          <a href="<?= APP_URL ?>/pages/history.php"
             style="display:block;text-align:center;font-size:.8rem;color:var(--blue);
                    margin-top:12px;text-decoration:none;font-weight:600;">
            <i class="bi bi-clock-history"></i> Lihat Riwayat Booking
          </a>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// ---- Pilih metode bayar ----
const metodeRadios = document.querySelectorAll('input[name="metode"]');
const subOptions   = document.getElementById('subOptions');
const subLabel     = document.getElementById('subLabel');
const bankChips    = document.getElementById('bankChips');
const bankVal      = document.getElementById('bankDompetVal');
const btnPay       = document.getElementById('btnPay');

// ---- Validasi kode promo via AJAX ----
function validasiPromo() {
  const kode   = document.getElementById('promoInput')?.value.trim();
  const result = document.getElementById('promoResult');
  const hidden = document.getElementById('kodePromoHidden');
  if (!kode || !result) return;

  result.className = 'promo-result';
  result.style.display = 'flex';
  result.innerHTML = '<i class="bi bi-hourglass-split"></i> Memvalidasi...';

  const total = <?= $booking['total_harga'] ?>;
  fetch(`<?= APP_URL ?>/api/promo.php?action=validasi&kode=${kode}&total=${total}`)
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        result.className = 'promo-result success';
        result.innerHTML = `
          <i class="bi bi-check-circle-fill"></i>
          <div>
            <strong>${data.judul}</strong> — Hemat <strong>${data.diskon_fmt}</strong><br>
            <span style="font-size:.75rem;">Total jadi <strong>${data.total_fmt}</strong></span>
          </div>`;
        if (hidden) hidden.value = kode;
        // Update sidebar total
        const sidebarTotal = document.getElementById('sidebarTotal');
        if (sidebarTotal) sidebarTotal.textContent = data.total_fmt;
      } else {
        result.className = 'promo-result error';
        result.innerHTML = `<i class="bi bi-x-circle-fill"></i> ${data.message}`;
        if (hidden) hidden.value = '';
      }
    })
    .catch(() => {
      result.className = 'promo-result error';
      result.innerHTML = '<i class="bi bi-x-circle-fill"></i> Gagal menghubungi server.';
    });
}

// Enter key di input promo
document.getElementById('promoInput')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); validasiPromo(); }
});

const bankMap = {
  transfer_bank:  { label:'Pilih Bank:', options:['BCA','Mandiri','BNI','BRI','BSI'] },
  kartu_kredit:   { label:'Jenis Kartu:', options:['Visa','Mastercard','JCB'] },
  dompet_digital: { label:'Pilih Dompet:', options:['GoPay','OVO','Dana','ShopeePay','LinkAja'] },
  minimarket:     { label:'Pilih Minimarket:', options:['Alfamart','Indomaret'] },
};

metodeRadios.forEach(r => {
  r.addEventListener('change', () => {
    // Highlight card
    document.querySelectorAll('.metode-card').forEach(c => c.classList.remove('selected'));
    r.closest('.metode-card').classList.add('selected');

    const info = bankMap[r.value];
    subLabel.textContent = info.label;
    bankChips.innerHTML  = '';
    bankVal.value        = '';
    btnPay.disabled      = true;

    info.options.forEach(opt => {
      const chip = document.createElement('button');
      chip.type      = 'button';
      chip.className = 'bank-chip';
      chip.textContent = opt;
      chip.addEventListener('click', () => {
        bankChips.querySelectorAll('.bank-chip').forEach(c => c.classList.remove('selected'));
        chip.classList.add('selected');
        bankVal.value = opt;
        btnPay.disabled = false;
      });
      bankChips.appendChild(chip);
    });

    subOptions.style.display = 'block';
  });
});

// ---- Countdown timer ----
<?php if ($pay && $sisa > 0): ?>
let sisa = <?= $sisa ?>;
const cd = document.getElementById('countdown');
function updateCountdown() {
  if (sisa <= 0) { cd.textContent = '00:00'; cd.style.color='var(--danger)'; return; }
  const m = String(Math.floor(sisa/60)).padStart(2,'0');
  const s = String(sisa % 60).padStart(2,'0');
  cd.textContent = m + ':' + s;
  if (sisa <= 300) cd.style.color = 'var(--danger)';
  sisa--;
  setTimeout(updateCountdown, 1000);
}
updateCountdown();
<?php endif; ?>

// ---- Copy kode ----
function copyKode() {
  const kode = document.getElementById('kodeBayar');
  if (kode) {
    navigator.clipboard.writeText(kode.textContent.trim())
      .then(() => alert('Kode berhasil disalin!'));
  }
}

// ---- Loading state ----
const payForm = document.getElementById('payForm');
if (payForm) {
  payForm.addEventListener('submit', () => {
    const btn = document.getElementById('btnPay');
    btn.innerHTML = '<span class="tg-spinner"></span> Memproses...';
    btn.disabled = true;
  });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>