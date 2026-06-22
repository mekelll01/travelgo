<?php
// ============================================================
//  TravelGo — Halaman Reviews (pages/reviews.php)
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mongodb.php';

$pageTitle  = 'Ulasan Pengguna';
$activeMenu = '';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.reviews-page { background:var(--gray-50); min-height:100vh; padding:40px 0 64px; }

.rv-hero {
  background:linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
  border-radius:var(--radius-lg); padding:32px 36px; margin-bottom:32px;
  position:relative; overflow:hidden;
}
.rv-hero::before {
  content:''; position:absolute; inset:0;
  background-image:radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
  background-size:20px 20px;
}
.rv-hero-content { position:relative; z-index:1; }

/* Stats rating */
.rv-stats { display:flex; gap:24px; flex-wrap:wrap; margin-top:16px; }
.rv-stat { text-align:center; }
.rv-stat-num { font-size:1.8rem; font-weight:800; color:#fff; line-height:1; }
.rv-stat-label { font-size:.72rem; color:rgba(255,255,255,.5); margin-top:2px; }

/* Review cards grid */
.rv-grid {
  display:grid; grid-template-columns:repeat(auto-fill, minmax(320px,1fr));
  gap:16px; margin-bottom:24px;
}
.rv-card {
  background:var(--white); border:1.5px solid var(--gray-200);
  border-radius:var(--radius-lg); padding:20px 22px;
  transition:var(--transition);
  animation:rvFadeIn .4s ease forwards; opacity:0;
}
@keyframes rvFadeIn { to { opacity:1; transform:translateY(0); } }
.rv-card:nth-child(1){animation-delay:.05s}
.rv-card:nth-child(2){animation-delay:.10s}
.rv-card:nth-child(3){animation-delay:.15s}
.rv-card:nth-child(4){animation-delay:.20s}
.rv-card:nth-child(5){animation-delay:.25s}
.rv-card:nth-child(6){animation-delay:.30s}
.rv-card:hover { box-shadow:var(--shadow-md); border-color:var(--blue-light); transform:translateY(-2px); }

.rv-card-header { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
.rv-avatar {
  width:40px; height:40px; border-radius:50%;
  background:var(--blue); display:flex; align-items:center;
  justify-content:center; font-size:.9rem; font-weight:800;
  color:#fff; flex-shrink:0;
}
.rv-name { font-size:.9rem; font-weight:700; color:var(--navy); }
.rv-rute { font-size:.75rem; color:var(--gray-400); margin-top:1px; }
.rv-stars { display:flex; gap:2px; }
.rv-star { color:#FCD34D; font-size:.9rem; }
.rv-star.empty { color:var(--gray-200); }
.rv-komentar {
  font-size:.86rem; color:var(--gray-600); line-height:1.6;
  border-top:1px solid var(--gray-100); padding-top:12px; margin-top:4px;
}
.rv-footer {
  display:flex; align-items:center; justify-content:space-between;
  margin-top:12px; font-size:.72rem; color:var(--gray-400);
}
.rv-operator-badge {
  background:rgba(37,99,235,.08); color:var(--blue);
  padding:2px 8px; border-radius:4px; font-size:.72rem; font-weight:700;
}

/* Form submit review */
.rv-form-card {
  background:var(--white); border:1.5px solid var(--gray-200);
  border-radius:var(--radius-lg); padding:24px 28px; margin-bottom:24px;
}
.rv-form-title {
  font-size:1rem; font-weight:800; color:var(--navy);
  margin-bottom:16px; padding-bottom:12px;
  border-bottom:1px solid var(--gray-100);
}

/* Star picker */
.star-picker { display:flex; gap:6px; margin-bottom:14px; }
.star-pick {
  font-size:1.8rem; cursor:pointer; color:var(--gray-200);
  transition:.15s; line-height:1;
}
.star-pick.active, .star-pick:hover { color:#FCD34D; }

.rv-input {
  width:100%; padding:11px 14px;
  border:1.5px solid var(--gray-200);
  border-radius:var(--radius-md);
  font-size:.9rem; font-family:'Plus Jakarta Sans',sans-serif;
  color:var(--gray-800); background:var(--white);
  transition:var(--transition); outline:none;
}
.rv-input:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(37,99,235,.08); }
.rv-input::placeholder { color:var(--gray-400); }

/* Loading skeleton */
.rv-skeleton {
  background:linear-gradient(90deg, var(--gray-100) 25%, var(--gray-200) 50%, var(--gray-100) 75%);
  background-size:200% 100%;
  animation:shimmer 1.5s infinite; border-radius:8px;
}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
</style>

<div class="reviews-page">
  <div class="container">

    <!-- Hero -->
    <div class="rv-hero">
      <div class="rv-hero-content">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
          <div>
            <h1 style="color:#fff;font-size:1.4rem;font-weight:800;margin:0 0 4px;">
              Ulasan Pengguna TravelGo
            </h1>
            <p style="color:rgba(255,255,255,.6);font-size:.85rem;margin:0;">
              Ribuan pengguna sudah mempercayai TravelGo untuk perjalanan mereka
            </p>
          </div>
          <div class="rv-stats" id="rvStats">
            <div class="rv-stat"><div class="rv-stat-num" id="rvAvg">—</div><div class="rv-stat-label">Rating Rata-rata</div></div>
            <div class="rv-stat"><div class="rv-stat-num" id="rvTotal">—</div><div class="rv-stat-label">Total Ulasan</div></div>
            <div class="rv-stat"><div class="rv-stat-num">98%</div><div class="rv-stat-label">Puas</div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Form submit review (hanya jika login) -->
    <?php if (isLogin()): ?>
    <div class="rv-form-card">
      <div class="rv-form-title"><i class="bi bi-pencil-square" style="color:var(--blue);"></i> Tulis Ulasan</div>
      <div class="row g-3">
        <div class="col-md-4">
          <label style="font-size:.82rem;font-weight:600;color:var(--gray-600);margin-bottom:5px;display:block;">
            Pilih Tiket yang Pernah Dipesan
          </label>
          <select id="rvJadwalId" class="rv-input">
            <option value="">-- Pilih booking --</option>
            <?php
              $mybookings = $conn->query(
                "SELECT b.id as bid, j.id as jid, ka.nama AS asal, kt.nama AS tujuan,
                        j.tanggal_berangkat, o.nama AS op
                 FROM bookings b
                 JOIN jadwal j ON b.jadwal_id=j.id
                 JOIN rute r ON j.rute_id=r.id
                 JOIN kota ka ON r.kota_asal_id=ka.id
                 JOIN kota kt ON r.kota_tujuan_id=kt.id
                 JOIN operator o ON r.operator_id=o.id
                 WHERE b.user_id={$_SESSION['user_id']} AND b.status='paid'
                 ORDER BY b.created_at DESC LIMIT 10"
              )->fetch_all(MYSQLI_ASSOC);
              foreach ($mybookings as $bk):
            ?>
              <option value="<?= $bk['jid'] ?>">
                <?= clean($bk['asal']) ?> → <?= clean($bk['tujuan']) ?> |
                <?= clean($bk['op']) ?> |
                <?= date('d M Y', strtotime($bk['tanggal_berangkat'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8">
          <label style="font-size:.82rem;font-weight:600;color:var(--gray-600);margin-bottom:5px;display:block;">Rating</label>
          <div class="star-picker" id="starPicker">
            <?php for($i=1;$i<=5;$i++): ?>
              <span class="star-pick" data-val="<?=$i?>" onclick="setRating(<?=$i?>)">★</span>
            <?php endfor; ?>
          </div>
          <input type="hidden" id="rvRating" value="0">
        </div>
        <div class="col-12">
          <label style="font-size:.82rem;font-weight:600;color:var(--gray-600);margin-bottom:5px;display:block;">Komentar</label>
          <textarea id="rvKomentar" class="rv-input" rows="3"
                    placeholder="Ceritakan pengalamanmu..."></textarea>
        </div>
        <div class="col-12">
          <button onclick="submitReview()"
                  style="background:var(--blue);color:#fff;border:none;
                         border-radius:var(--radius-md);padding:11px 24px;
                         font-size:.9rem;font-weight:700;cursor:pointer;
                         font-family:'Plus Jakarta Sans',sans-serif;
                         display:inline-flex;align-items:center;gap:7px;">
            <i class="bi bi-send"></i> Kirim Ulasan
          </button>
          <span id="rvSubmitMsg" style="margin-left:12px;font-size:.83rem;"></span>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div style="background:rgba(37,99,235,.06);border:1.5px solid rgba(37,99,235,.15);
                border-radius:var(--radius-lg);padding:16px 20px;margin-bottom:24px;
                font-size:.85rem;color:var(--blue);display:flex;align-items:center;gap:10px;">
      <i class="bi bi-info-circle-fill"></i>
      <span>
        <a href="<?= APP_URL ?>/pages/login.php" style="font-weight:700;">Masuk</a>
        untuk menulis ulasan perjalananmu.
      </span>
    </div>
    <?php endif; ?>

    <!-- Review grid -->
    <div id="rvGrid" class="rv-grid">
      <!-- Loading skeleton -->
      <?php for($i=0;$i<6;$i++): ?>
        <div style="background:var(--white);border:1.5px solid var(--gray-200);border-radius:var(--radius-lg);padding:20px;">
          <div style="display:flex;gap:12px;margin-bottom:14px;">
            <div class="rv-skeleton" style="width:40px;height:40px;border-radius:50%;flex-shrink:0;"></div>
            <div style="flex:1;">
              <div class="rv-skeleton" style="height:14px;width:60%;margin-bottom:6px;"></div>
              <div class="rv-skeleton" style="height:11px;width:40%;"></div>
            </div>
          </div>
          <div class="rv-skeleton" style="height:12px;width:80%;margin-bottom:8px;"></div>
          <div class="rv-skeleton" style="height:12px;width:60%;"></div>
        </div>
      <?php endfor; ?>
    </div>

    <!-- Empty state -->
    <div id="rvEmpty" style="display:none;text-align:center;padding:48px;
         background:var(--white);border-radius:var(--radius-lg);border:1.5px dashed var(--gray-200);">
      <div style="font-size:3rem;margin-bottom:12px;">💬</div>
      <div style="font-weight:800;color:var(--navy);margin-bottom:6px;">Belum Ada Ulasan</div>
      <p style="font-size:.85rem;color:var(--gray-400);">Jadilah yang pertama memberikan ulasan!</p>
    </div>

  </div>
</div>

<script>
// ---- Load reviews ----
function loadReviews() {
  fetch('<?= APP_URL ?>/api/review.php?action=all')
    .then(r => r.json())
    .then(data => {
      const grid  = document.getElementById('rvGrid');
      const empty = document.getElementById('rvEmpty');

      // Update stats
      if (data.data && data.data.length > 0) {
        const avg   = data.data.reduce((s,r) => s+r.rating, 0) / data.data.length;
        document.getElementById('rvAvg').textContent   = avg.toFixed(1) + ' ★';
        document.getElementById('rvTotal').textContent = data.data.length + '+';
      }

      if (!data.data || data.data.length === 0) {
        grid.innerHTML  = '';
        empty.style.display = 'block';
        return;
      }

      grid.innerHTML = data.data.map(r => {
        const stars = Array.from({length:5}, (_,i) =>
          `<span class="rv-star ${i < r.rating ? '' : 'empty'}">★</span>`
        ).join('');
        return `
          <div class="rv-card">
            <div class="rv-card-header">
              <div class="rv-avatar">${r.user_nama.charAt(0).toUpperCase()}</div>
              <div>
                <div class="rv-name">${r.user_nama}</div>
                <div class="rv-rute">${r.rute}</div>
              </div>
              <div class="rv-stars ms-auto">${stars}</div>
            </div>
            <div class="rv-komentar">"${r.komentar}"</div>
            <div class="rv-footer">
              <span class="rv-operator-badge">${r.operator}</span>
              <span>${r.created_at}</span>
            </div>
          </div>
        `;
      }).join('');
    })
    .catch(() => {
      document.getElementById('rvGrid').innerHTML =
        '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--gray-400);">Gagal memuat ulasan.</div>';
    });
}

// ---- Star picker ----
function setRating(val) {
  document.getElementById('rvRating').value = val;
  document.querySelectorAll('.star-pick').forEach((s, i) => {
    s.classList.toggle('active', i < val);
  });
}

// ---- Submit review ----
function submitReview() {
  const jadwal_id = document.getElementById('rvJadwalId')?.value;
  const rating    = document.getElementById('rvRating')?.value;
  const komentar  = document.getElementById('rvKomentar')?.value.trim();
  const msg       = document.getElementById('rvSubmitMsg');

  if (!jadwal_id) { msg.style.color='var(--danger)'; msg.textContent='Pilih tiket dulu.'; return; }
  if (!rating || rating == 0) { msg.style.color='var(--danger)'; msg.textContent='Pilih rating bintang dulu.'; return; }
  if (!komentar) { msg.style.color='var(--danger)'; msg.textContent='Komentar tidak boleh kosong.'; return; }

  msg.style.color = 'var(--gray-400)';
  msg.textContent = 'Mengirim...';

  fetch('<?= APP_URL ?>/api/review.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=submit&jadwal_id=${jadwal_id}&rating=${rating}&komentar=${encodeURIComponent(komentar)}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      msg.style.color = 'var(--success)';
      msg.textContent = '✓ ' + data.message;
      document.getElementById('rvKomentar').value = '';
      document.getElementById('rvRating').value   = 0;
      document.querySelectorAll('.star-pick').forEach(s => s.classList.remove('active'));
    } else {
      msg.style.color = 'var(--danger)';
      msg.textContent = data.message;
    }
  })
  .catch(() => { msg.style.color='var(--danger)'; msg.textContent='Gagal mengirim.'; });
}

// Load saat halaman dibuka
loadReviews();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>