<?php
require __DIR__ . '/includes/config.php';
require_customer();

$stmt = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
$stmt->execute([$_SESSION['user_id']]);
$pesanan = $stmt->fetchAll();

$itemsByOrder = [];
if ($pesanan) {
    $ids = array_column($pesanan, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $qi  = db()->prepare("SELECT * FROM order_items WHERE order_id IN ($in) ORDER BY id");
    $qi->execute($ids);
    foreach ($qi->fetchAll() as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
    }
}

$labelStatus = ['baru' => 'Baru', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

$halamanAktif = 'pesanan';
$judul        = 'Pesanan saya · Sembako Bersaudara';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/customer_topbar.php';
?>
<main class="wrap">
  <h1 class="page-title">Pesanan saya</h1>
  <?php if ($f = flash()): ?><div class="alert alert-<?= e($f[0]) ?>" role="status"><?= e($f[1]) ?></div><?php endif; ?>

  <?php if (!$pesanan): ?>
    <section class="empty">
      <h2>Belum ada pesanan</h2>
      <p class="muted">Pesanan yang Anda buat akan muncul di sini. <a href="beranda.php">Mulai belanja</a>.</p>
    </section>
  <?php else: ?>
    <div class="orders">
      <?php foreach ($pesanan as $o): ?>
        <details class="order-card">
          <summary>
            <span class="order-no"><?= e(no_pesanan((int) $o['id'])) ?></span>
            <span class="order-date muted"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></span>
            <span class="badge status-<?= e($o['status']) ?>"><?= e($labelStatus[$o['status']]) ?></span>
            <span class="order-total"><?= rupiah((int) $o['total']) ?></span>
          </summary>
          <ul class="rows">
            <?php foreach ($itemsByOrder[$o['id']] ?? [] as $it): ?>
              <li><span><?= e($it['nama_produk']) ?> &times; <?= angka((int) $it['qty']) ?> <?= e($it['satuan']) ?></span><span><?= rupiah((int) $it['subtotal']) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <p class="order-alamat"><strong>Dikirim ke:</strong> <?= e($o['nama_penerima']) ?>, <?= e($o['telepon']) ?><br><?= nl2br(e($o['alamat'])) ?></p>
          <?php if ($o['catatan']): ?><p class="order-alamat"><strong>Catatan:</strong> <?= e($o['catatan']) ?></p><?php endif; ?>
        </details>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
