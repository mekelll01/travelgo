/* ============================================================
   TravelGo — Global JavaScript
   ============================================================ */

"use strict";

/* ------------------------------------------------------------
   1. Search Panel — Tab & Form Logic
------------------------------------------------------------ */

/** Aktifkan tab jenis transportasi */
function initSearchTabs() {
  const tabs = document.querySelectorAll('.tg-tab');
  if (!tabs.length) return;

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      // Update hidden input jenis transportasi
      const jenisInput = document.getElementById('jenis_transportasi_id');
      if (jenisInput) jenisInput.value = tab.dataset.jenis;
    });
  });
}

/** Toggle sekali jalan / pulang pergi */
function initTripToggle() {
  const btns = document.querySelectorAll('.tg-trip-btn');
  const returnField = document.getElementById('return-field');
  if (!btns.length) return;

  btns.forEach(btn => {
    btn.addEventListener('click', () => {
      btns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      if (returnField) {
        const isPP = btn.dataset.trip === 'pulang_pergi';
        returnField.classList.toggle('show', isPP);

        // Wajibkan field tanggal kembali
        const inputKembali = document.getElementById('tgl_kembali');
        if (inputKembali) inputKembali.required = isPP;
      }
    });
  });
}

/** Swap tombol asal — tujuan */
function initSwapButton() {
  const btn = document.getElementById('swap-btn');
  if (!btn) return;

  btn.addEventListener('click', () => {
    const asal   = document.getElementById('kota_asal');
    const tujuan = document.getElementById('kota_tujuan');
    if (!asal || !tujuan) return;

    const tmpVal  = asal.value;
    const tmpText = asal.options[asal.selectedIndex]?.text;

    // Tukar value
    asal.value   = tujuan.value;
    tujuan.value = tmpVal;
  });
}

/** Set tanggal minimum = hari ini */
function initDateMin() {
  const today = new Date().toISOString().split('T')[0];
  ['tgl_berangkat', 'tgl_kembali'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.min = today;
  });

  // Pastikan tgl kembali >= tgl berangkat
  const tglBerangkat = document.getElementById('tgl_berangkat');
  const tglKembali   = document.getElementById('tgl_kembali');
  if (tglBerangkat && tglKembali) {
    tglBerangkat.addEventListener('change', () => {
      tglKembali.min = tglBerangkat.value;
      if (tglKembali.value && tglKembali.value < tglBerangkat.value) {
        tglKembali.value = tglBerangkat.value;
      }
    });
  }
}

/** Klik rute populer → isi form */
function initPopularRoutes() {
  document.querySelectorAll('.tg-chip[data-asal]').forEach(chip => {
    chip.addEventListener('click', () => {
      const asal   = document.getElementById('kota_asal');
      const tujuan = document.getElementById('kota_tujuan');
      if (asal)   asal.value   = chip.dataset.asal;
      if (tujuan) tujuan.value = chip.dataset.tujuan;

      // Scroll ke search panel
      document.getElementById('search-panel')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });
}

/* ------------------------------------------------------------
   2. Search Results — Filter & Sort
------------------------------------------------------------ */

function initResultFilters() {
  const sortSelect = document.getElementById('sort-hasil');
  if (!sortSelect) return;

  sortSelect.addEventListener('change', () => {
    const cards = [...document.querySelectorAll('.tg-result-card')];
    const container = document.getElementById('hasil-container');
    if (!container) return;

    cards.sort((a, b) => {
      const getPrice = el => parseInt(el.dataset.harga || 0);
      const getTime  = el => el.dataset.jam || '';

      if (sortSelect.value === 'harga-asc')  return getPrice(a) - getPrice(b);
      if (sortSelect.value === 'harga-desc') return getPrice(b) - getPrice(a);
      if (sortSelect.value === 'jam-asc')    return getTime(a).localeCompare(getTime(b));
      return 0;
    });

    cards.forEach(c => container.appendChild(c));
  });
}

/* ------------------------------------------------------------
   3. Seat Map (pages/booking.php)
------------------------------------------------------------ */

function initSeatMap() {
  const seats = document.querySelectorAll('.tg-seat:not(.taken):not(.aisle)');
  const inputKursi  = document.getElementById('no_kursi');
  const maxSeat = parseInt(document.getElementById('seat-map')?.dataset.max || 1);

  if (!seats.length) return;

  seats.forEach(seat => {
    seat.addEventListener('click', () => {
      const selected = [...document.querySelectorAll('.tg-seat.selected')];

      if (seat.classList.contains('selected')) {
        seat.classList.remove('selected');
      } else {
        if (selected.length >= maxSeat) {
          showToast('Jumlah kursi sudah sesuai penumpang.', 'warning');
          return;
        }
        seat.classList.add('selected');
      }

      // Update hidden input
      const nums = [...document.querySelectorAll('.tg-seat.selected')].map(s => s.dataset.kursi);
      if (inputKursi) inputKursi.value = nums.join(',');
    });
  });
}

/* ------------------------------------------------------------
   4. Payment Countdown Timer
------------------------------------------------------------ */

function initCountdown() {
  const el = document.getElementById('countdown');
  if (!el) return;

  const batasBayar = new Date(el.dataset.batas).getTime();

  const tick = () => {
    const now  = Date.now();
    const diff = batasBayar - now;

    if (diff <= 0) {
      el.textContent = '00:00';
      showToast('Waktu pembayaran habis. Booking dibatalkan.', 'danger');
      setTimeout(() => { window.location.href = el.dataset.redirect || '/'; }, 2500);
      return;
    }

    const m = String(Math.floor(diff / 60000)).padStart(2, '0');
    const s = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
    el.textContent = `${m}:${s}`;

    // Warna merah kalau < 5 menit
    el.closest('.tg-countdown')?.classList.toggle('urgent', diff < 300000);
  };

  tick();
  setInterval(tick, 1000);
}

/** Pilih metode pembayaran */
function initPaymentMethod() {
  document.querySelectorAll('.tg-payment-method').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.tg-payment-method').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');

      const radio = card.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;

      // Tampilkan instruksi khusus metode
      const instruksi = document.querySelectorAll('.tg-instruksi');
      instruksi.forEach(i => i.classList.add('d-none'));
      const target = document.getElementById('instruksi-' + card.dataset.metode);
      if (target) target.classList.remove('d-none');
    });
  });
}

/* ------------------------------------------------------------
   5. Form Validation
------------------------------------------------------------ */

function initFormValidation() {
  document.querySelectorAll('form[data-validate]').forEach(form => {
    form.addEventListener('submit', e => {
      let valid = true;

      form.querySelectorAll('[required]').forEach(field => {
        field.classList.remove('tg-field-error');

        if (!field.value.trim()) {
          field.classList.add('tg-field-error');
          valid = false;
        }
      });

      // Validasi email
      const email = form.querySelector('input[type="email"]');
      if (email && email.value && !isValidEmail(email.value)) {
        email.classList.add('tg-field-error');
        valid = false;
      }

      // Validasi password minimal 8 karakter
      const pw = form.querySelector('input[name="password"]');
      if (pw && pw.value && pw.value.length < 8) {
        pw.classList.add('tg-field-error');
        showToast('Password minimal 8 karakter.', 'danger');
        valid = false;
      }

      // Konfirmasi password
      const pw2 = form.querySelector('input[name="password_confirm"]');
      if (pw && pw2 && pw.value !== pw2.value) {
        pw2.classList.add('tg-field-error');
        showToast('Password tidak cocok.', 'danger');
        valid = false;
      }

      if (!valid) {
        e.preventDefault();
        // Scroll ke field error pertama
        form.querySelector('.tg-field-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  });
}

/* ------------------------------------------------------------
   6. AJAX Helpers
------------------------------------------------------------ */

/**
 * Kirim AJAX request (fetch wrapper)
 * @param {string} url  - endpoint api/
 * @param {object} data - data yang dikirim
 * @param {function} cb - callback(response)
 */
async function ajaxPost(url, data, cb) {
  const btn = document.querySelector('[data-loading]');
  if (btn) {
    btn.dataset.origText = btn.innerHTML;
    btn.innerHTML = '<span class="tg-spinner"></span> Memproses...';
    btn.disabled  = true;
  }

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(data),
    });
    const json = await res.json();
    cb(json);
  } catch (err) {
    showToast('Koneksi gagal. Periksa jaringan kamu.', 'danger');
  } finally {
    if (btn) {
      btn.innerHTML = btn.dataset.origText;
      btn.disabled  = false;
    }
  }
}

/* ------------------------------------------------------------
   7. Toast Notification
------------------------------------------------------------ */

/**
 * Tampilkan toast di pojok kanan bawah
 * @param {string} msg   - pesan
 * @param {string} type  - 'success' | 'danger' | 'warning' | 'info'
 */
function showToast(msg, type = 'info') {
  let container = document.getElementById('tg-toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'tg-toast-container';
    Object.assign(container.style, {
      position: 'fixed', bottom: '24px', right: '24px',
      zIndex: '9999', display: 'flex', flexDirection: 'column', gap: '8px',
    });
    document.body.appendChild(container);
  }

  const icons = { success: 'check-circle', danger: 'x-circle', warning: 'exclamation-triangle', info: 'info-circle' };

  const toast = document.createElement('div');
  toast.className = `tg-alert tg-alert-${type} tg-fade-in`;
  toast.style.cssText = 'min-width:260px;max-width:340px;box-shadow:0 4px 20px rgba(0,0,0,.15);';
  toast.innerHTML = `<i class="bi bi-${icons[type] || 'info-circle'}"></i> ${msg}`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity .3s';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

/* ------------------------------------------------------------
   8. Utilities
------------------------------------------------------------ */

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/** Format angka jadi rupiah di DOM (elemen dengan class .tg-format-rp) */
function formatRupiahDOM() {
  document.querySelectorAll('.tg-format-rp').forEach(el => {
    const num = parseInt(el.textContent.replace(/\D/g, ''));
    if (!isNaN(num)) el.textContent = 'Rp ' + num.toLocaleString('id-ID');
  });
}

/** Konfirmasi sebelum aksi kritis (delete, cancel, dll) */
function initConfirmActions() {
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm || 'Yakin?')) e.preventDefault();
    });
  });
}

/** Aktifkan tooltip Bootstrap */
function initTooltips() {
  if (typeof bootstrap !== 'undefined') {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
      new bootstrap.Tooltip(el);
    });
  }
}

/* ------------------------------------------------------------
   9. Init semua saat DOM siap
------------------------------------------------------------ */

document.addEventListener('DOMContentLoaded', () => {
  initSearchTabs();
  initTripToggle();
  initSwapButton();
  initDateMin();
  initPopularRoutes();
  initResultFilters();
  initSeatMap();
  initCountdown();
  initPaymentMethod();
  initFormValidation();
  formatRupiahDOM();
  initConfirmActions();
  initTooltips();
});