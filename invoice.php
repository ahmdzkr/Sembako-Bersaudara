<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/orders.php';
require_login();

$id    = (int) ($_GET['id'] ?? 0);
$order = order_get($id);
if (!$order) { http_response_code(404); exit('Pesanan tidak ditemukan.'); }
if (!order_boleh_dilihat($order)) { http_response_code(403); exit('Anda tidak berhak melihat invoice ini.'); }
if ($order['status'] !== 'diterima') {
    $_SESSION['flash'] = ['info', 'Invoice baru terbit setelah barang dikonfirmasi diterima.'];
    header('Location: ' . (is_admin() || is_kasir() ? ROOT . 'admin/pesanan.php' : 'pesanan.php'));
    exit;
}
$items = order_items_get($id);
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
    <a href="<?= is_admin() || is_kasir() ? 'admin/pesanan.php' : 'pesanan.php' ?>">&larr; Kembali</a>
  </div>
  <main class="doc-page">
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
        <h3>Riwayat pesanan</h3>
        <p>
          Dipesan: <?= date('d M Y', strtotime($order['created_at'])) ?><br>
          <?php if ($order['verifikasi_at']): ?>Disetujui: <?= date('d M Y', strtotime($order['verifikasi_at'])) ?><br><?php endif; ?>
          <?php if ($order['dikirim_at']): ?>Dikirim: <?= date('d M Y', strtotime($order['dikirim_at'])) ?><br><?php endif; ?>
          Diterima: <?= date('d M Y', strtotime($order['diterima_at'])) ?>
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
    <p class="doc-meta">Status pembayaran: Lunas (dibayar saat barang diterima / COD)</p>
  </main>
</body>
</html>
