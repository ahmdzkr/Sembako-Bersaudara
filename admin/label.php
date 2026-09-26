<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/orders.php';
require_admin();

$id    = (int) ($_GET['id'] ?? 0);
$order = order_get($id);
if (!$order) { http_response_code(404); exit('Pesanan tidak ditemukan.'); }
if (!in_array($order['status'], ['siap_diproses', 'siap_kirim', 'dikirim', 'diterima'], true)) {
    $_SESSION['flash'] = ['error', 'Label baru bisa dicetak setelah PO disetujui kasir.'];
    header('Location: pesanan.php');
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Label <?= e(no_pesanan($id)) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/print.css">
</head>
<body class="doc">
  <div class="doc-toolbar no-print">
    <button onclick="window.print()">Cetak</button>
    <a href="pesanan.php">&larr; Kembali</a>
  </div>
  <main class="doc-page label-page">
    <p class="doc-brand" style="margin:0 0 .5rem">Sembako <em>Bersaudara</em></p>
    <div class="label-box">
      <div class="label-from">
        <strong>Pengirim:</strong> Sembako Bersaudara, Depok
      </div>
      <div class="label-to">
        <h3 style="margin:0;color:var(--muted);font-size:.8rem;text-transform:uppercase">Kepada</h3>
        <h2><?= e($order['nama_penerima']) ?></h2>
        <p><?= e($order['telepon']) ?></p>
        <p><?= nl2br(e($order['alamat'])) ?></p>
      </div>
      <div class="label-no">
        <span>PO: <?= e(no_pesanan($id)) ?></span>
        <span>Resi: <?= e($order['no_resi'] ?: '-') ?></span>
      </div>
    </div>
  </main>
</body>
</html>
