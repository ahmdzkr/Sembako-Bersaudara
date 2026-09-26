<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/orders.php';
require_kasir();
require __DIR__ . '/../includes/admin_layout.php';

$stmt = db()->query(
    "SELECT o.*, u.nama AS nama_customer FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.status = 'menunggu_verifikasi' ORDER BY o.id ASC"
);
$antrean = $stmt->fetchAll();

admin_start('Verifikasi PO', 'verifikasi', 'kasir');
?>
<h1 class="page-title">Verifikasi PO</h1>
<p class="muted">Periksa dokumen dan keuangan setiap PO sebelum disetujui. Stok baru dikurangi setelah PO disetujui.</p>

<p class="count"><?= count($antrean) ?> PO menunggu verifikasi</p>

<?php if (!$antrean): ?>
  <section class="empty">
    <h2>Tidak ada PO yang menunggu</h2>
    <p class="muted">Semua PO sudah diperiksa. PO baru dari customer akan muncul di sini.</p>
  </section>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>No. PO</th><th>Customer</th><th>Tanggal</th><th class="num">Total</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($antrean as $o): ?>
        <tr>
          <td><strong><?= e(no_pesanan((int) $o['id'])) ?></strong></td>
          <td><?= e($o['nama_customer']) ?></td>
          <td><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></td>
          <td class="num"><?= rupiah((int) $o['total']) ?></td>
          <td class="acts"><a href="po.php?id=<?= (int) $o['id'] ?>">Periksa &amp; verifikasi &rarr;</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php admin_end(); ?>
