<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/orders.php';
require_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $_SESSION['flash'] = ['error', 'Sesi formulir sudah berakhir. Coba lagi.'];
    } elseif (($_POST['aksi'] ?? '') === 'terima') {
        [$ok, $pesan] = order_konfirmasi_diterima((int) ($_POST['id'] ?? 0), (int) $_SESSION['user_id']);
        $_SESSION['flash'] = [$ok ? 'success' : 'error', $pesan];
    }
    header('Location: pesanan.php');
    exit;
}

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
        <details class="order-card" <?= in_array($o['status'], ['menunggu_verifikasi', 'siap_diproses', 'siap_kirim', 'dikirim'], true) ? 'open' : '' ?>>
          <summary>
            <span class="order-no"><?= e(no_pesanan((int) $o['id'])) ?></span>
            <span class="order-date muted"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></span>
            <span class="badge status-<?= e($o['status']) ?>"><?= e(label_status($o['status'])) ?></span>
            <span class="order-total"><?= rupiah((int) $o['total']) ?></span>
          </summary>

          <ol class="tracker">
            <?php
            $urutan = ['menunggu_verifikasi', 'siap_diproses', 'siap_kirim', 'dikirim', 'diterima'];
            $posisi = array_search($o['status'], $urutan, true);
            foreach ($urutan as $i => $u):
                $done = $o['status'] === 'dibatalkan' ? false : $i <= $posisi;
            ?>
              <li class="<?= $done ? 'done' : '' ?>"><?= e(label_status($u)) ?></li>
            <?php endforeach; ?>
          </ol>
          <?php if ($o['status'] === 'dibatalkan'): ?>
            <p class="order-alamat"><strong>PO dibatalkan.</strong><?php if ($o['catatan_kasir']): ?> Alasan: <?= e($o['catatan_kasir']) ?><?php endif; ?></p>
          <?php endif; ?>

          <ul class="rows">
            <?php foreach ($itemsByOrder[$o['id']] ?? [] as $it): ?>
              <li><span><?= e($it['nama_produk']) ?> &times; <?= angka((int) $it['qty']) ?> <?= e($it['satuan']) ?></span><span><?= rupiah((int) $it['subtotal']) ?></span></li>
            <?php endforeach; ?>
            <?php if (empty($itemsByOrder[$o['id']])): ?><li><span class="muted">Tidak ada rincian barang.</span></li><?php endif; ?>
          </ul>
          <p class="order-alamat"><strong>Dikirim ke:</strong> <?= e($o['nama_penerima']) ?>, <?= e($o['telepon']) ?><br><?= nl2br(e($o['alamat'])) ?></p>
          <?php if ($o['catatan']): ?><p class="order-alamat"><strong>Catatan:</strong> <?= e($o['catatan']) ?></p><?php endif; ?>
          <?php if ($o['no_resi']): ?><p class="order-alamat"><strong>No. resi:</strong> <?= e($o['no_resi']) ?></p><?php endif; ?>

          <div class="order-actions">
            <?php if ($o['status'] === 'dikirim'): ?>
              <form method="post" onsubmit="return confirm('Konfirmasi barang sudah diterima dan sesuai?');">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="terima">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <button class="btn" type="submit">Konfirmasi barang diterima</button>
              </form>
            <?php elseif ($o['status'] === 'diterima'): ?>
              <a class="btn btn-small" href="invoice.php?id=<?= (int) $o['id'] ?>" target="_blank" rel="noopener">Lihat invoice</a>
            <?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
