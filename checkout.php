<?php
require __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/cart.php';
require_customer();

$items = cart_items();
if (!$items) {
    $_SESSION['flash'] = ['info', 'Keranjang masih kosong.'];
    header('Location: beranda.php');
    exit;
}
$total = cart_total($items);
$err   = [];
$v     = ['nama_penerima' => $_SESSION['nama'], 'telepon' => '', 'alamat' => '', 'catatan' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $err['umum'] = 'Sesi formulir sudah berakhir. Muat ulang halaman lalu coba lagi.';
    } else {
        $v['nama_penerima'] = trim($_POST['nama_penerima'] ?? '');
        $v['telepon']       = trim($_POST['telepon'] ?? '');
        $v['alamat']        = trim($_POST['alamat'] ?? '');
        $v['catatan']       = trim($_POST['catatan'] ?? '');

        if ($v['nama_penerima'] === '') { $err['nama_penerima'] = 'Isi nama penerima.'; }
        if (!preg_match('/^[0-9+\-\s]{8,20}$/', $v['telepon'])) { $err['telepon'] = 'Isi nomor telepon yang benar (8-20 digit).'; }
        if ($v['alamat'] === '') { $err['alamat'] = 'Isi alamat pengiriman.'; }

        if (!$err) {
            // PO dibuat dengan status 'menunggu_verifikasi'. Stok BELUM dikurangi di sini —
            // stok baru dikurangi saat kasir menyetujui PO (lihat includes/orders.php::po_approve).
            // Jumlah beli tetap sudah dibatasi sesuai stok saat ini lewat cart_items() di atas,
            // jadi ini hanya jaga-jaga sederhana, bukan penguncian stok.
            $pdo = db();
            try {
                $pdo->beginTransaction();

                $q = $pdo->prepare('INSERT INTO orders (user_id, nama_penerima, telepon, alamat, catatan, total) VALUES (?,?,?,?,?,?)');
                $q->execute([$_SESSION['user_id'], $v['nama_penerima'], $v['telepon'], $v['alamat'], $v['catatan'] ?: null, $total]);
                $orderId = (int) $pdo->lastInsertId();

                $qi = $pdo->prepare('INSERT INTO order_items (order_id, product_id, nama_produk, mode, qty, satuan, harga, subtotal) VALUES (?,?,?,?,?,?,?,?)');
                foreach ($items as $it) {
                    $qi->execute([$orderId, $it['product']['id'], $it['product']['nama'], $it['mode'], $it['qty'], $it['label'], $it['harga'], $it['subtotal']]);
                }

                $pdo->commit();
                cart_clear();
                $_SESSION['flash'] = ['success', 'PO ' . no_pesanan($orderId) . ' berhasil dibuat dan menunggu verifikasi kasir. Terima kasih!'];
                header('Location: pesanan.php');
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $err['umum'] = 'Pesanan gagal disimpan. Coba lagi.';
            }
        }
    }
}

function ferr(array $err, string $k): string { return isset($err[$k]) ? '<p class="field-err">' . e($err[$k]) . '</p>' : ''; }
function faerr(array $err, string $k): string { return isset($err[$k]) ? ' aria-invalid="true"' : ''; }

$halamanAktif = 'keranjang';
$judul        = 'Checkout · Sembako Bersaudara';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/customer_topbar.php';
?>
<main class="wrap">
  <h1 class="page-title">Checkout</h1>
  <?php if (isset($err['umum'])): ?><div class="alert alert-error" role="alert"><?= e($err['umum']) ?></div><?php endif; ?>

  <div class="checkout-grid">
    <form method="post" class="pform-main co-form" novalidate>
      <?= csrf_field() ?>
      <label for="nama_penerima">Nama penerima</label>
      <input id="nama_penerima" name="nama_penerima" type="text" maxlength="100" required value="<?= e($v['nama_penerima']) ?>"<?= faerr($err, 'nama_penerima') ?>>
      <?= ferr($err, 'nama_penerima') ?>

      <label for="telepon">Nomor telepon</label>
      <input id="telepon" name="telepon" type="tel" maxlength="20" required value="<?= e($v['telepon']) ?>" placeholder="08xxxxxxxxxx"<?= faerr($err, 'telepon') ?>>
      <?= ferr($err, 'telepon') ?>

      <label for="alamat">Alamat pengiriman</label>
      <textarea id="alamat" name="alamat" rows="3" required<?= faerr($err, 'alamat') ?>><?= e($v['alamat']) ?></textarea>
      <?= ferr($err, 'alamat') ?>

      <label for="catatan">Catatan <span class="muted">(boleh kosong)</span></label>
      <textarea id="catatan" name="catatan" rows="2" maxlength="300"><?= e($v['catatan']) ?></textarea>

      <button class="btn" type="submit">Buat pesanan</button>
      <p class="hint">Setelah PO dibuat, kasir akan memeriksa dokumen dan keuangan terlebih dahulu sebelum pesanan diproses. Anda bisa memantau statusnya di halaman Pesanan saya.</p>
    </form>

    <aside class="pform-side co-summary">
      <span class="lbl">Ringkasan pesanan</span>
      <ul class="rows">
        <?php foreach ($items as $it): ?>
          <li><span><?= e($it['product']['nama']) ?> &times; <?= angka($it['qty']) ?> <?= e($it['label']) ?></span><span><?= rupiah($it['subtotal']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <div class="co-total">Total <strong><?= rupiah($total) ?></strong></div>
    </aside>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
