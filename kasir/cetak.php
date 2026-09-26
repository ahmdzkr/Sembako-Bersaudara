<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/orders.php';
require_login();
if (!is_admin() && !is_kasir()) { header('Location: ' . home_url()); exit; }

$id    = (int) ($_GET['id'] ?? 0);
$order = order_get($id);
if (!$order) { http_response_code(404); exit('Pesanan tidak ditemukan.'); }
$items = order_items_get($id);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Surat PO <?= e(no_pesanan($id)) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/print.css">
</head>
<body class="doc">
  <div class="doc-toolbar no-print">
    <button onclick="window.print()">Cetak</button>
    <a href="po.php?id=<?= $id ?>">&larr; Kembali</a>
  </div>
  <main class="doc-page">
    <header class="doc-head">
      <div>
        <p class="doc-brand">Sembako <em>Bersaudara</em></p>
        <p class="doc-brand-sub">Grosir sembako &middot; Depok</p>
      </div>
      <div class="doc-title">
        <h1>Surat PO (Purchase Order)</h1>
        <p class="doc-meta">No. <?= e(no_pesanan($id)) ?></p>
        <p class="doc-meta"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></p>
      </div>
    </header>

    <div class="doc-grid">
      <div class="doc-box">
        <h3>Dipesan &amp; dikirim untuk</h3>
        <p><strong><?= e($order['nama_penerima']) ?></strong><br><?= e($order['telepon']) ?><br><?= nl2br(e($order['alamat'])) ?></p>
      </div>
      <div class="doc-box">
        <h3>Status PO</h3>
        <p><span class="doc-status"><?= e(label_status($order['status'])) ?></span></p>
        <?php if ($order['catatan']): ?><p style="margin-top:.5rem"><strong>Catatan:</strong> <?= e($order['catatan']) ?></p><?php endif; ?>
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
    <p class="doc-total">Total: <?= rupiah((int) $order['total']) ?></p>

    <div class="doc-sign">
      <div>Diajukan oleh<div class="line"><?= e($order['nama_penerima']) ?></div></div>
      <div>Diperiksa &amp; disetujui kasir<div class="line">&nbsp;</div></div>
    </div>
  </main>
</body>
</html>
