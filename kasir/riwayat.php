<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/orders.php';
require_kasir();
require __DIR__ . '/../includes/admin_layout.php';

$stmt = db()->query(
    "SELECT o.*, u.nama AS nama_customer, k.nama AS nama_kasir FROM orders o
     JOIN users u ON u.id = o.user_id
     LEFT JOIN users k ON k.id = o.diverifikasi_oleh
     WHERE o.status <> 'menunggu_verifikasi' ORDER BY o.verifikasi_at DESC, o.id DESC LIMIT 100"
);
$riwayat = $stmt->fetchAll();

admin_start('Riwayat PO', 'riwayat', 'kasir');
?>
<h1 class="page-title">Riwayat PO</h1>
<p class="muted">PO yang sudah pernah diverifikasi (disetujui atau ditolak).</p>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>No. PO</th><th>Customer</th><th class="num">Total</th><th>Status</th><th>Diverifikasi oleh</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($riwayat as $o): ?>
      <tr>
        <td><strong><?= e(no_pesanan((int) $o['id'])) ?></strong></td>
        <td><?= e($o['nama_customer']) ?></td>
        <td class="num"><?= rupiah((int) $o['total']) ?></td>
        <td><span class="badge status-<?= e($o['status']) ?>"><?= e(label_status($o['status'])) ?></span></td>
        <td><?= $o['nama_kasir'] ? e($o['nama_kasir']) : '<span class="muted">&mdash;</span>' ?></td>
        <td><a href="po.php?id=<?= (int) $o['id'] ?>">Lihat</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$riwayat): ?><tr><td colspan="6" class="muted center">Belum ada riwayat.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php admin_end(); ?>
