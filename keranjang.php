<?php
require __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/cart.php';
require_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $_SESSION['flash'] = ['error', 'Sesi formulir sudah berakhir. Coba lagi.'];
    } else {
        $aksi = $_POST['aksi'] ?? '';
        if ($aksi === 'tambah') {
            [$ok, $pesan] = cart_add((int) ($_POST['product_id'] ?? 0), (string) ($_POST['mode'] ?? ''), (int) ($_POST['qty'] ?? 0));
            $_SESSION['flash'] = [$ok ? 'success' : 'error', $pesan];
            if ($ok && !empty($_POST['kembali'])) {
                header('Location: beranda.php');
                exit;
            }
        } elseif ($aksi === 'ubah') {
            foreach ((array) ($_POST['qty'] ?? []) as $key => $qty) {
                cart_update((string) $key, (int) $qty);
            }
            $_SESSION['flash'] = ['info', 'Keranjang diperbarui.'];
        } elseif ($aksi === 'hapus') {
            cart_remove((string) ($_POST['key'] ?? ''));
            $_SESSION['flash'] = ['info', 'Produk dihapus dari keranjang.'];
        }
    }
    header('Location: keranjang.php');
    exit;
}

$items = cart_items();
$total = cart_total($items);
$adaPenyesuaian = false;
foreach ($items as $it) { if ($it['disesuaikan']) { $adaPenyesuaian = true; } }

$halamanAktif = 'keranjang';
$judul        = 'Keranjang · Sembako Bersaudara';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/customer_topbar.php';
?>
<main class="wrap">
  <h1 class="page-title">Keranjang belanja</h1>

  <?php if ($f = flash()): ?>
    <div class="alert alert-<?= e($f[0]) ?>" role="status"><?= e($f[1]) ?></div>
  <?php endif; ?>
  <?php if ($adaPenyesuaian): ?>
    <div class="alert alert-info" role="status">Ada jumlah yang disesuaikan karena stok berkurang. Periksa kembali sebelum checkout.</div>
  <?php endif; ?>

  <?php if (!$items): ?>
    <section class="empty">
      <h2>Keranjang masih kosong</h2>
      <p class="muted">Yuk pilih produk dulu di <a href="beranda.php">halaman produk</a>.</p>
    </section>
  <?php else: ?>
    <form method="post" id="form-keranjang">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="ubah">
      <div class="cart-wrap">
        <table class="cart-table">
          <thead>
            <tr>
              <th scope="col">Produk</th>
              <th scope="col">Cara beli</th>
              <th scope="col" class="num">Harga</th>
              <th scope="col" class="center">Jumlah</th>
              <th scope="col" class="num">Subtotal</th>
              <th scope="col"><span class="sr">Aksi</span></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $i => $it): $p = $it['product']; ?>
            <tr>
              <td data-label="Produk">
                <span class="pcell">
                  <span class="pthumb"><?php if ($p['gambar']): ?><img src="assets/uploads/<?= e($p['gambar']) ?>" alt=""><?php else: ?><?= emoji_kategori($p['kategori']) ?><?php endif; ?></span>
                  <strong><?= e($p['nama']) ?></strong>
                </span>
              </td>
              <td data-label="Cara beli">per <?= e($it['label']) ?></td>
              <td data-label="Harga" class="num"><?= rupiah($it['harga']) ?></td>
              <td data-label="Jumlah" class="center">
                <input type="number" name="qty[<?= e($it['key']) ?>]" value="<?= $it['qty'] ?>" min="1" max="<?= $it['maks'] ?>" class="qty-input" aria-label="Jumlah <?= e($p['nama']) ?>">
              </td>
              <td data-label="Subtotal" class="num"><strong><?= rupiah($it['subtotal']) ?></strong></td>
              <td class="aksi"><button class="link-btn" type="submit" form="hapus-<?= $i ?>">Hapus</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="cart-actions">
        <button class="btn btn-small" type="submit">Perbarui jumlah</button>
        <div class="cart-total">Total: <strong><?= rupiah($total) ?></strong></div>
      </div>
    </form>

    <?php /* Form hapus dipisah dari form utama supaya menekan Enter di kolom jumlah tidak ikut menghapus produk pertama. */ ?>
    <?php foreach ($items as $i => $it): ?>
      <form method="post" id="hapus-<?= $i ?>" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="hapus">
        <input type="hidden" name="key" value="<?= e($it['key']) ?>">
      </form>
    <?php endforeach; ?>

    <p class="checkout-cta"><a class="btn" href="">Lanjut ke checkout</a></p>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
