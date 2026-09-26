<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/orders.php';
require_admin();

$id    = (int) ($_GET['id'] ?? 0);
$order = order_get($id);
if (!$order) { http_response_code(404); exit('Pesanan tidak ditemukan.'); }
if (!in_array($order['status'], ['siap_diproses', 'siap_kirim', 'dikirim', 'diterima'], true)) {
    $_SESSION['flash'] = ['error', 'Surat jalan baru bisa dicetak setelah PO disetujui kasir.'];
    header('Location: pesanan.php');
    exit;
}
$items = order_items_get($id);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Surat Jalan <?= e(no_pesanan($id)) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/print.css">
</head>
<body class="doc">
  <div class="doc-toolbar no-print">
    <button onclick="window.print()">Cetak</button>
    <a href="pesanan.php">&larr; Kembali</a>
  </div>
  <main class="doc-page">
    <header class="doc-head">
      <div>
        <p class="doc-brand">Sembako <em>Bersaudara</em></p>
        <p class="doc-brand-sub">Grosir sembako &middot; Depok</p>
      </div>
      <div class="doc-title">
        <h1>Surat Jalan</h1>
        <p class="doc-meta">No. PO <?= e(no_pesanan($id)) ?></p>
        <p class="doc-meta"><?= date('d M Y', strtotime($order['created_at'])) ?></p>
      </div>
    </header>

    <div class="doc-box">
      <h3>Kirim ke</h3>
      <p><strong><?= e($order['nama_penerima']) ?></strong><br><?= e($order['telepon']) ?><br><?= nl2br(e($order['alamat'])) ?></p>
    </div>

    <table class="doc-table">
      <thead><tr><th style="width:2.5rem">No</th><th>Produk</th><th class="num">Jumlah</th><th style="width:6rem">Cek</th></tr></thead>
      <tbody>
      <?php foreach ($items as $i => $it): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($it['nama_produk']) ?></td>
          <td class="num"><?= angka((int) $it['qty']) ?> <?= e($it['satuan']) ?></td>
          <td>&#9633;</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($order['catatan']): ?><p><strong>Catatan:</strong> <?= e($order['catatan']) ?></p><?php endif; ?>

    <div class="doc-sign">
      <div>Disiapkan oleh (gudang)<div class="line">&nbsp;</div></div>
      <div>Diterima oleh (penerima)<div class="line">&nbsp;</div></div>
    </div>
  </main>
</body>
</html>
