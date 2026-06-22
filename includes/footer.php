<!-- =========================================================
     FOOTER
========================================================= -->
<footer class="tg-footer mt-auto">
  <div class="container">
    <div class="row gy-4">

      <!-- Kolom 1: Brand -->
      <div class="col-lg-4">
        <a class="tg-logo text-decoration-none" href="<?= APP_URL ?>/index.php">
          <i class="bi bi-airplane-fill"></i> Travel<span>Go</span>
        </a>
        <p class="mt-3 tg-footer-desc">
          Platform pemesanan tiket transportasi online terpercaya.
          Pesawat, kereta, bus, kapal, dan travel — semua ada di sini.
        </p>
        <div class="d-flex gap-3 mt-3">
          <a href="#" class="tg-social"><i class="bi bi-instagram"></i></a>
          <a href="#" class="tg-social"><i class="bi bi-twitter-x"></i></a>
          <a href="#" class="tg-social"><i class="bi bi-facebook"></i></a>
          <a href="#" class="tg-social"><i class="bi bi-youtube"></i></a>
        </div>
      </div>

      <!-- Kolom 2: Layanan -->
      <div class="col-6 col-lg-2">
        <h6 class="tg-footer-title">Layanan</h6>
        <ul class="list-unstyled tg-footer-links">
          <li><a href="<?= APP_URL ?>/index.php?jenis=1"><i class="bi bi-airplane"></i> Pesawat</a></li>
          <li><a href="<?= APP_URL ?>/index.php?jenis=2"><i class="bi bi-train-front"></i> Kereta</a></li>
          <li><a href="<?= APP_URL ?>/index.php?jenis=3"><i class="bi bi-bus-front"></i> Bus</a></li>
          <li><a href="<?= APP_URL ?>/index.php?jenis=4"><i class="bi bi-water"></i> Kapal</a></li>
          <li><a href="<?= APP_URL ?>/index.php?jenis=5"><i class="bi bi-car-front"></i> Travel</a></li>
        </ul>
      </div>

      <!-- Kolom 3: Informasi -->
      <div class="col-6 col-lg-2">
        <h6 class="tg-footer-title">Informasi</h6>
        <ul class="list-unstyled tg-footer-links">
          <li><a href="#">Tentang Kami</a></li>
          <li><a href="#">Cara Pesan</a></li>
          <li><a href="<?= APP_URL ?>/pages/reviews.php"><i class="bi bi-star"></i> Ulasan Pengguna</a></li>
          <li><a href="#">Syarat & Ketentuan</a></li>
          <li><a href="#">Kebijakan Privasi</a></li>
          <li><a href="#">FAQ</a></li>
        </ul>
      </div>

      <!-- Kolom 4: Kontak -->
      <div class="col-lg-4">
        <h6 class="tg-footer-title">Hubungi Kami</h6>
        <ul class="list-unstyled tg-footer-contact">
          <li><i class="bi bi-envelope"></i> support@travelgo.com</li>
          <li><i class="bi bi-telephone"></i> 0800-1234-5678 (Bebas Pulsa)</li>
          <li><i class="bi bi-clock"></i> Senin – Minggu, 08.00 – 22.00 WIB</li>
        </ul>

      </div>

    </div>

    <!-- Bottom bar -->
    <div class="tg-footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Semua hak dilindungi.</span>
      <div class="d-flex gap-3 flex-wrap">
        <img src="<?= APP_URL ?>/assets/img/payment/bca.png"     alt="BCA"    class="tg-payment-icon" onerror="this.style.display='none'">
        <img src="<?= APP_URL ?>/assets/img/payment/mandiri.png" alt="Mandiri" class="tg-payment-icon" onerror="this.style.display='none'">
        <img src="<?= APP_URL ?>/assets/img/payment/bni.png"     alt="BNI"    class="tg-payment-icon" onerror="this.style.display='none'">
        <img src="<?= APP_URL ?>/assets/img/payment/ovo.png"     alt="OVO"    class="tg-payment-icon" onerror="this.style.display='none'">
        <img src="<?= APP_URL ?>/assets/img/payment/gopay.png"   alt="GoPay"  class="tg-payment-icon" onerror="this.style.display='none'">
      </div>
    </div>

  </div>
</footer>
<!-- /FOOTER -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script src="<?= APP_URL ?>/assets/js/script.js"></script>

<?= isset($extraScript) ? $extraScript : '' ?>

</body>
</html>