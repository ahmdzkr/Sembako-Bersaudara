<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/orders.php';
require_kasir();

$id    = (int) ($_GET['id'] ?? 0);
$order = order_get($id);
if (!$order) {
    $_SESSION['flash'] = ['error', 'Pesanan tidak ditemukan.'];
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $_SESSION['flash'] = ['error', 'Sesi formulir sudah berakhir. Coba lagi.'];
    } else {
        $aksi = $_POST['aksi'] ?? '';
        if ($aksi === 'approve') {
            [$ok, $pesan] = po_approve($id, (int) $_SESSION['user_id']);
        } elseif ($aksi === 'reject') {
            [$ok, $pesan] = po_reject($id, (int) $_SESSION['user_id'], trim($_POST['alasan'] ?? ''));
        } else {
            $ok = false; $pesan = 'Aksi tidak dikenal.';
        }
        $_SESSION['flash'] = [$ok ? 'success' : 'error', $pesan];
    }
    header('Location: ' . ($order['status'] === 'menunggu_verifikasi' ? 'po.php?id=' . $id : 'index.php'));
    exit;
}

$items = order_items_get($id);

require __DIR__ . '/../includes/admin_layout.php';
admin_start('PO ' . no_pesanan($id), 'verifikasi', 'kasir');
?>
<p class="breadcrumb"><a href="index.php">Verifikasi PO</a> &rsaquo; <?= e(no_pesanan($id)) ?></p>
<div class="page-head">
  <div>
    <h1 class="page-title">Surat PO <?= e(no_pesanan($id)) ?></h1>
    <p class="muted">Diajukan <?= date('d M Y, H:i', strtotime($order['created_at'])) ?> oleh <?= e($order['nama_penerima']) ?></p>
  </div>
  <a class="btn btn-icon" href="cetak.php?id=<?= $id ?>" target="_blank" rel="noopener"><?= icon('printer', 18) ?> Cetak surat PO</a>
</div>

<span class="badge status-<?= e($order['status']) ?>"><?= e(label_status($order['status'])) ?></span>

<div class="table-wrap" style="margin-top:1.2rem">
  <table class="table">
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
    <?php if (!$items): ?><tr><td colspan="4" class="muted center">Tidak ada rincian barang.</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong><?= rupiah((int) $order['total']) ?></strong></td></tr></tfoot>
  </table>
</div>

<div class="panel" style="margin-top:1.2rem">
  <h2>Data pengiriman</h2>
  <p><strong>Penerima:</strong> <?= e($order['nama_penerima']) ?><br>
    <strong>Telepon:</strong> <?= e($order['telepon']) ?><br>
    <strong>Alamat:</strong> <?= nl2br(e($order['alamat'])) ?></p>
  <?php if ($order['catatan']): ?><p><strong>Catatan customer:</strong> <?= e($order['catatan']) ?></p><?php endif; ?>
</div>

<?php if ($order['status'] === 'menunggu_verifikasi'): ?>
  <div class="po-actions">
    <form method="post" onsubmit="return confirm('Setujui PO ini? Stok akan langsung dikurangi.');">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="approve">
      <button class="btn" type="submit">Setujui PO (kurangi stok)</button>
    </form>
    <form method="post" class="po-reject" onsubmit="return confirm('Tolak PO ini?');">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="reject">
      <input type="text" name="alasan" placeholder="Alasan penolakan (opsional)" class="mini-input">
      <button class="btn btn-danger" type="submit">Tolak PO</button>
    </form>
  </div>
<?php elseif ($order['catatan_kasir']): ?>
  <p class="muted" style="margin-top:1rem"><strong>Catatan verifikasi:</strong> <?= e($order['catatan_kasir']) ?></p>
<?php endif; ?>
<?php admin_end(); ?>
