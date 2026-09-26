<?php
require __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/cart.php';
require_customer();

$id   = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) {
    $_SESSION['flash'] = ['error', 'Produk tidak ditemukan.'];
    header('Location: beranda.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $_SESSION['flash'] = ['error', 'Sesi formulir sudah berakhir. Muat ulang halaman lalu coba lagi.'];
    } else {
        [$ok, $pesan] = cart_add($id, (string) ($_POST['mode'] ?? ''), (int) ($_POST['qty'] ?? 0));
        $_SESSION['flash'] = [$ok ? 'success' : 'error', $pesan];
    }
    header('Location: produk_detail.php?id=' . $id);
    exit;
}

$st = status_stok((int) $p['stok']);

$halamanAktif = 'beranda';
$judul        = $p['nama'] . ' · Sembako Bersaudara';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/customer_topbar.php';
?>
<main class="wrap">
  <p class="breadcrumb">
    <a href="beranda.php">Produk</a> &rsaquo;
    <a href="beranda.php?kategori=<?= urlencode($p['kategori']) ?>"><?= e($p['kategori']) ?></a> &rsaquo;
    <span><?= e($p['nama']) ?></span>
  </p>

  <?php if ($f = flash()): ?><div class="alert alert-<?= e($f[0]) ?>" role="status"><?= e($f[1]) ?></div><?php endif; ?>

  <section class="detail">
    <div class="detail-media">
      <span class="pill"><?= emoji_kategori($p['kategori']) ?> <?= e($p['kategori']) ?></span>
      <?php if ($p['gambar']): ?>
        <img src="assets/uploads/<?= e($p['gambar']) ?>" alt="<?= e($p['nama']) ?>">
      <?php else: ?>
        <span class="detail-emoji" aria-hidden="true"><?= emoji_kategori($p['kategori']) ?></span>
      <?php endif; ?>
    </div>

    <div class="detail-info">
      <h1><?= e($p['nama']) ?></h1>

      <div class="price-row">
        <div class="price price-lg"><?= harga_utama($p) ?></div>
        <?php if ($st === 'habis'): ?>
          <span class="badge habis">Stok habis</span>
        <?php elseif ($st === 'menipis'): ?>
          <span class="badge menipis">Sisa <?= e(stok_teks($p)) ?></span>
        <?php else: ?>
          <span class="badge aman">Stok <?= e(stok_teks($p)) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($alt = harga_alt($p)): ?><p class="price-alt muted"><?= e($alt) ?></p><?php endif; ?>

      <p class="detail-desc"><?= nl2br(e($p['deskripsi'] !== null && $p['deskripsi'] !== '' ? $p['deskripsi'] : 'Tidak ada deskripsi untuk produk ini.')) ?></p>

      <?php if ($st !== 'habis'): ?>
        <div class="detail-beli">
          <?php if (jual_dasar($p)): $m = maks_beli($p, 'dasar'); ?>
            <form method="post" class="beli-baris">
              <?= csrf_field() ?>
              <input type="hidden" name="mode" value="dasar">
              <label for="qty-dasar">Beli per <?= e($p['satuan_dasar']) ?></label>
              <div class="beli-input">
                <input id="qty-dasar" name="qty" type="number" min="1" max="<?= $m ?>" value="1" class="qty-input">
                <span class="beli-label"><?= e($p['satuan_dasar']) ?></span>
                <button class="btn" type="submit">+ Keranjang</button>
              </div>
              <p class="hint">Tersedia <?= angka($m) ?> <?= e($p['satuan_dasar']) ?>.</p>
            </form>
          <?php endif; ?>
          <?php if (jual_satuan($p)): $m = maks_beli($p, 'satuan'); ?>
            <form method="post" class="beli-baris">
              <?= csrf_field() ?>
              <input type="hidden" name="mode" value="satuan">
              <label for="qty-st">Beli per <?= e($p['satuan']) ?></label>
              <div class="beli-input">
                <input id="qty-st" name="qty" type="number" min="1" max="<?= $m ?>" value="1" class="qty-input">
                <span class="beli-label"><?= e($p['satuan']) ?></span>
                <button class="btn" type="submit">+ Keranjang</button>
              </div>
              <p class="hint">Tersedia <?= angka($m) ?> <?= e($p['satuan']) ?>.</p>
            </form>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p class="muted">Produk ini sedang habis. Silakan cek kembali lain waktu.</p>
      <?php endif; ?>

      <p class="detail-back"><a href="beranda.php">&larr; Kembali ke daftar produk</a></p>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
