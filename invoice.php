<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/orders.php';
require __DIR__ . '/includes/midtrans.php';
require_login();

$id    = (int) ($_GET['id'] ?? 0);
$order = order_get($id);
if (!$order) { http_response_code(404); exit('Pesanan tidak ditemukan.'); }
if (!order_boleh_dilihat($order)) { http_response_code(403); exit('Anda tidak berhak melihat invoice ini.'); }
if (!in_array($order['status'], ['menunggu_pembayaran', 'selesai'], true)) {
    $_SESSION['flash'] = ['info', 'Invoice baru terbit setelah barang dikonfirmasi diterima.'];
    header('Location: ' . (is_admin() || is_kasir() || is_owner() ? ROOT . 'admin/pesanan.php' : 'pesanan.php'));
    exit;
}

$pemilik = (int) $order['user_id'] === (int) ($_SESSION['user_id'] ?? 0);
$staf    = is_admin() || is_kasir() || is_owner();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $_SESSION['flash'] = ['error', 'Sesi formulir sudah berakhir. Coba lagi.'];
    } else {
        $aksi = $_POST['aksi'] ?? '';
        if ($aksi === 'sinkron' && $order['status'] === 'menunggu_pembayaran') {
            [$ok, $pesan] = midtrans_sinkron_status($id);
            // Kalau masih belum lunas, kotak petunjuk permanen di bawah invoice sudah
            // menjelaskan status terkininya — flash di sini cukup untuk galat saja,
            // supaya tidak menampilkan dua kalimat senada sekaligus.
            $statusBaru = order_get($id)['status'] ?? $order['status'];
            if (!$ok || $statusBaru === 'selesai') {
                $_SESSION['flash'] = [$ok ? 'success' : 'error', $pesan];
            }
        } elseif ($aksi === 'tandai_manual' && $staf && $order['status'] === 'menunggu_pembayaran') {
            [$ok, $pesan] = order_tandai_lunas_staf($id);
            $_SESSION['flash'] = [$ok ? 'success' : 'error', $pesan];
        }
    }
    header('Location: invoice.php?id=' . $id);
    exit;
}

$order   = order_get($id); // ambil ulang, siapa tahu baru saja berubah lewat aksi POST redirect sebelumnya
$items   = order_items_get($id);
$stmtU   = db()->prepare('SELECT termin_hari FROM users WHERE id = ?');
$stmtU->execute([$order['user_id']]);
$terminHari = (int) ($stmtU->fetchColumn() ?: DEFAULT_TERMIN_HARI);
$jatuhTempo = jatuh_tempo_invoice($order['diterima_at'], $terminHari);
$lewatTempo = $order['status'] === 'menunggu_pembayaran' && invoice_lewat_tempo($order['diterima_at'], $terminHari);
$statusMidtrans = $order['midtrans_status'];
$bolehBayar = $pemilik && $order['status'] === 'menunggu_pembayaran' && $statusMidtrans !== 'challenge';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= e(no_pesanan($id)) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/print.css">
</head>
<body class="doc">
  <div class="doc-toolbar no-print">
    <button onclick="window.print()">Cetak</button>
    <a href="<?= $staf ? 'admin/pesanan.php' : 'pesanan.php' ?>">&larr; Kembali</a>
  </div>
  <main class="doc-page">
    <?php if ($f = flash()): ?><div class="alert alert-<?= e($f[0]) ?> no-print" role="status"><?= e($f[1]) ?></div><?php endif; ?>

    <header class="doc-head">
      <div>
        <p class="doc-brand">Sembako <em>Bersaudara</em></p>
        <p class="doc-brand-sub">Grosir sembako &middot; Depok</p>
      </div>
      <div class="doc-title">
        <h1>Invoice</h1>
        <p class="doc-meta">No. <?= e(no_pesanan($id)) ?></p>
        <p class="doc-meta">Diterbitkan <?= date('d M Y', strtotime($order['diterima_at'])) ?></p>
      </div>
    </header>

    <div class="doc-grid">
      <div class="doc-box">
        <h3>Ditagihkan kepada</h3>
        <p><strong><?= e($order['nama_penerima']) ?></strong><br><?= e($order['telepon']) ?><br><?= nl2br(e($order['alamat'])) ?></p>
      </div>
      <div class="doc-box">
        <h3>Status pembayaran</h3>
        <p>
          <?php if ($order['status'] === 'selesai'): ?>
            <span class="doc-status doc-status-lunas">Lunas</span>
            <br><span class="doc-meta">Dibayar <?= date('d M Y', strtotime($order['dibayar_at'])) ?><?php if ($order['metode_bayar']): ?> &middot; <?= e($order['metode_bayar']) ?><?php endif; ?></span>
          <?php else: ?>
            <span class="doc-status doc-status-belum">Menunggu pembayaran</span>
            <br><span class="doc-meta">Jatuh tempo <?= $jatuhTempo->format('d M Y') ?><?php if ($lewatTempo): ?> &mdash; <strong class="doc-lewat">sudah lewat jatuh tempo</strong><?php endif; ?></span>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <table class="doc-table">
      <thead><tr><th>Produk</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><?= e($it['nama_produk']) ?></td>
          <td class="num"><?= angka((int) $it['qty']) ?> <?= e($it['satuan']) ?></td>
          <td class="num"><?= rupiah((int) $it['harga']) ?></td>
          <td class="num"><?= rupiah((int) $it['subtotal']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="doc-total">Total tagihan: <?= rupiah((int) $order['total']) ?></p>

    <?php if ($order['status'] === 'menunggu_pembayaran'): ?>
      <div class="no-print pay-box">
        <?php if (!midtrans_terkonfigurasi()): ?>
          <p class="hint">Pembayaran online belum dikonfigurasi oleh toko ini. Silakan hubungi admin/kasir untuk instruksi pembayaran.</p>
        <?php else: ?>
          <?php
          $petunjuk = [
              'pending'   => 'Pembayaran Anda belum selesai (mis. transfer VA belum dibayar). Klik "Lanjutkan pembayaran" untuk melihat instruksinya lagi.',
              'expire'    => 'Sesi pembayaran sebelumnya sudah kedaluwarsa. Klik "Bayar sekarang" untuk memulai yang baru.',
              'cancel'    => 'Pembayaran sebelumnya dibatalkan. Anda bisa mencoba lagi.',
              'deny'      => 'Pembayaran sebelumnya ditolak. Anda bisa mencoba metode lain.',
              'failure'   => 'Pembayaran sebelumnya gagal. Anda bisa mencoba lagi.',
              'challenge' => 'Pembayaran Anda sedang ditinjau oleh Midtrans. Silakan cek status beberapa saat lagi.',
          ];
          ?>
          <?php if ($statusMidtrans && isset($petunjuk[$statusMidtrans])): ?>
            <p class="hint"><?= e($petunjuk[$statusMidtrans]) ?></p>
          <?php endif; ?>

          <form method="post" id="form-sinkron" class="pay-actions">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="sinkron">
            <?php if ($bolehBayar): ?>
              <button class="btn" type="button" id="pay-button"><?= $statusMidtrans === 'pending' ? 'Lanjutkan pembayaran' : 'Bayar sekarang' ?></button>
            <?php endif; ?>
            <button class="btn btn-outline" type="submit"><?= $bolehBayar ? 'Sudah bayar? Cek status' : 'Cek status pembayaran' ?></button>
          </form>
          <p class="pay-msg" id="pay-msg" role="status" aria-live="polite"></p>
          <?php if ($bolehBayar): ?>
            <p class="hint">Pilih transfer bank (VA), e-wallet, QRIS, kartu, atau metode lain di jendela pembayaran Midtrans. Status akan berubah otomatis begitu pembayaran diterima; jika belum berubah, klik "Cek status".</p>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($staf): ?>
          <form method="post" style="margin-top:.8rem" onsubmit="return confirm('Tandai invoice ini lunas secara manual? Gunakan hanya jika customer membayar di luar Midtrans.');">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="tandai_manual">
            <button class="btn-link" type="submit">Tandai lunas manual (di luar Midtrans)</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($bolehBayar && midtrans_terkonfigurasi()): ?>
        <script src="<?= e(midtrans_snap_js_url()) ?>" data-client-key="<?= e(MIDTRANS_CLIENT_KEY) ?>"></script>
        <script>
        (function () {
          var csrf = <?= json_encode(csrf_token()) ?>;
          var orderId = <?= (int) $id ?>;
          var tombol = document.getElementById('pay-button');
          var pesan = document.getElementById('pay-msg');
          var formSinkron = document.getElementById('form-sinkron');
          var sibuk = false;        // mencegah klik ganda saat token diminta
          var paksaBaru = false;    // minta sesi baru setelah pembayaran error
          var sudahSelesai = false; // pembayaran sukses: form sinkron sedang dikirim

          function tampil(teks, jenis) {
            pesan.textContent = teks || '';
            pesan.className = 'pay-msg' + (jenis ? ' ' + jenis : '');
          }
          function bebaskan(teks, jenis) {
            sibuk = false;
            tombol.disabled = false;
            tampil(teks, jenis);
          }
          function gagalServer(teks) {
            var err = new Error(teks);
            err.dariServer = true;
            return err;
          }

          tombol.addEventListener('click', function () {
            if (sibuk) { return; }
            if (!window.snap) {
              tampil('Modul pembayaran Midtrans belum termuat. Periksa koneksi internet Anda lalu muat ulang halaman.', 'err');
              return;
            }
            sibuk = true;
            tombol.disabled = true;
            tampil('Menyiapkan pembayaran…');

            var data = new URLSearchParams();
            data.set('csrf', csrf);
            data.set('order_id', orderId);
            if (paksaBaru) { data.set('baru', '1'); }

            fetch('payment/token.php', {
              method: 'POST',
              body: data,
              credentials: 'same-origin',
              headers: { 'Accept': 'application/json' }
            })
              .then(function (r) {
                return r.json().catch(function () { throw gagalServer('Respons server tidak dapat dibaca. Coba lagi.'); });
              })
              .then(function (res) {
                if (!res.ok || !res.token) { throw gagalServer(res.pesan || 'Sesi pembayaran gagal dibuat.'); }
                paksaBaru = false;
                tampil('');
                window.snap.pay(res.token, {
                  onSuccess: function () {
                    // Kepastian lunas selalu dari server (bertanya ke Midtrans), bukan dari callback ini.
                    sudahSelesai = true;
                    tampil('Pembayaran berhasil. Memverifikasi ke Midtrans…');
                    formSinkron.submit();
                  },
                  onPending: function () {
                    bebaskan('Instruksi pembayaran sudah dibuat. Selesaikan pembayaran, lalu klik "Cek status". Anda bisa membuka instruksinya lagi lewat tombol ini.');
                    tombol.textContent = 'Lanjutkan pembayaran';
                  },
                  onError: function () {
                    paksaBaru = true;
                    bebaskan('Pembayaran gagal diproses. Klik tombol ini lagi untuk mencoba dengan sesi baru.', 'err');
                  },
                  onClose: function () {
                    if (!sudahSelesai) { bebaskan('Jendela pembayaran ditutup sebelum selesai. Anda bisa melanjutkannya kapan saja.'); }
                  }
                });
              })
              .catch(function (e) {
                bebaskan(e && e.dariServer ? e.message : 'Tidak dapat menghubungi server. Periksa koneksi Anda lalu coba lagi.', 'err');
              });
          });
        })();
        </script>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</body>
</html>
