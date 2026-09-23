<?php
require __DIR__ . '/includes/config.php';
require_customer();
require_once __DIR__ . '/includes/product_card.php';

$q   = trim($_GET['q'] ?? '');
$kat = trim($_GET['kategori'] ?? '');

$sql = 'SELECT * FROM products WHERE 1=1';
$par = [];
if ($q !== '') {
    $sql .= ' AND (nama LIKE ? OR deskripsi LIKE ?)';
    array_push($par, "%$q%", "%$q%");
}
if ($kat !== '') {
    $sql .= ' AND kategori = ?';
    $par[] = $kat;
}
$sql .= ' ORDER BY (stok = 0), FIELD(kategori, ?, ?, ?), nama';
array_push($par, ...KATEGORI);
$stmt = db()->prepare($sql);
$stmt->execute($par);
$produk = $stmt->fetchAll();

// Tanpa pencarian dan tanpa filter kategori: kelompokkan produk per kategori (seperti etalase toko).
$dikelompokkan = ($q === '' && $kat === '');
$perKategori   = [];
if ($dikelompokkan) {
    foreach (KATEGORI as $k) {
        $perKategori[$k] = array_values(array_filter($produk, fn($p) => $p['kategori'] === $k));
    }
}

function url_filter(string $q, string $kat): string
{
    $qs = http_build_query(array_filter(['q' => $q, 'kategori' => $kat], 'strlen'));
    return 'beranda.php' . ($qs ? '?' . $qs : '');
}

$namaDepan    = explode(' ', trim($_SESSION['nama']))[0];
$halamanAktif = 'beranda';
$judul        = 'Beranda · Sembako Bersaudara';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/customer_topbar.php';

if ($f = flash()) {
    echo '<div class="wrap"><div class="alert alert-' . e($f[0]) . '" role="status">' . e($f[1]) . '</div></div>';
}
?>

<main class="wrap">
  <section class="hero">
    <div>
      <h1>Halo, <?= e($namaDepan) ?>!</h1>
      <p>Sayur segar, bahan kering, dan perlengkapan dapur untuk kebutuhan usaha Anda.</p>
      <form class="search" method="get" action="beranda.php" role="search">
        <?php if ($kat !== ''): ?><input type="hidden" name="kategori" value="<?= e($kat) ?>"><?php endif; ?>
        <label class="sr" for="q">Cari produk</label>
        <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Cari produk, misalnya wortel">
        <button class="btn" type="submit">Cari</button>
      </form>
    </div>
    <img src="assets/img/logo.jpg" alt="" width="160" height="160">
  </section>

  <nav class="chips" aria-label="Kategori produk">
    <a class="chip<?= $kat === '' ? ' on' : '' ?>" href="<?= e(url_filter($q, '')) ?>">Semua produk</a>
    <?php foreach (KATEGORI as $k): ?>
      <a class="chip<?= $kat === $k ? ' on' : '' ?>" href="<?= e(url_filter($q, $k)) ?>"><?= emoji_kategori($k) ?> <?= e($k) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$produk): ?>
    <p class="count">0 produk ditemukan</p>
    <section class="empty">
      <h2>Produk tidak ditemukan</h2>
      <p class="muted">Coba kata kunci lain, atau pilih <a href="beranda.php">Semua produk</a>.</p>
    </section>

  <?php elseif ($dikelompokkan): ?>
    <?php foreach ($perKategori as $k => $daftar): if (!$daftar) continue; ?>
      <section class="kategori-blok" aria-label="<?= e($k) ?>">
        <div class="kategori-head">
          <h2><?= emoji_kategori($k) ?> <?= e($k) ?></h2>
          <a class="lihat-semua" href="<?= e(url_filter('', $k)) ?>">Lihat semua (<?= count($daftar) ?>) &rarr;</a>
        </div>
        <div class="grid">
          <?php foreach (array_slice($daftar, 0, 4) as $p): render_product_card($p); ?><?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

  <?php else: ?>
    <p class="count"><?= count($produk) ?> produk ditemukan</p>
    <section class="grid" aria-label="Daftar produk">
      <?php foreach ($produk as $p): render_product_card($p); ?><?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
