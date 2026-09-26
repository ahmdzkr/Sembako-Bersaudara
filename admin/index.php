<?php
require __DIR__ . '/../includes/config.php';
require_admin();
require __DIR__ . '/../includes/admin_layout.php';

$s = db()->query(
    'SELECT COUNT(*) total, COALESCE(SUM(stok),0) stok,
            COALESCE(SUM(stok <= ' . LOW_STOCK . '),0) perlu, COALESCE(SUM(stok = 0),0) habis,
            COALESCE(SUM(stok > ' . LOW_STOCK . '),0) aman
     FROM products'
)->fetch();
// Nilai persediaan dihitung per baris karena rumus harga berbeda per kategori
// (Fresh/Dry Good pakai harga per kg, Lainnya pakai harga per satuan).
$nilai = 0;
foreach (db()->query('SELECT satuan_dasar, harga, harga_satuan, stok FROM products') as $row) {
    // Nilai = harga per satuan yang stoknya memang disimpan dengan satuan itu:
    // kalau ada basis (kg/liter) stok disimpan dalam basis itu, kalau tidak stok disimpan per kemasan.
    $adaDasar = ($row['satuan_dasar'] ?? '') !== '';
    $nilai += ($adaDasar ? (int) $row['harga'] : (int) $row['harga_satuan']) * (int) $row['stok'];
}
$s['nilai'] = $nilai;
$customer = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();

$rows = db()->query('SELECT kategori, COUNT(*) produk, SUM(stok) stok FROM products GROUP BY kategori')->fetchAll(PDO::FETCH_UNIQUE);
$perKategori = [];
foreach (KATEGORI as $k) {
    $perKategori[] = ['kategori' => $k, 'produk' => (int) ($rows[$k]['produk'] ?? 0), 'stok' => (int) ($rows[$k]['stok'] ?? 0)];
}
$maks = max(1, ...array_column($perKategori, 'stok'));

$perluIsi = db()->query('SELECT * FROM products WHERE stok <= ' . LOW_STOCK . ' ORDER BY stok, nama LIMIT 6')->fetchAll();

admin_start('Dashboard', 'dashboard');
?>
<h1 class="page-title">Dashboard</h1>
<p class="muted">Ringkasan stok toko hari ini.</p>

<section class="stats" aria-label="Ringkasan">
  <div class="stat tone-orange">
    <span class="stat-icon"><?= icon('box', 26) ?></span>
    <div><div class="stat-label">Total produk</div><div class="stat-num"><?= angka((int) $s['total']) ?></div>
      <div class="stat-sub">di <?= count(KATEGORI) ?> kategori</div></div>
  </div>
  <div class="stat tone-green">
    <span class="stat-icon"><?= icon('layers', 26) ?></span>
    <div><div class="stat-label">Total stok</div><div class="stat-num"><?= angka((int) $s['stok']) ?></div>
      <div class="stat-sub">kg dari produk Fresh &amp; Dry Good</div></div>
  </div>
  <a class="stat tone-amber" href="inventory.php?status=perlu">
    <span class="stat-icon"><?= icon('alert', 26) ?></span>
    <div><div class="stat-label">Perlu diisi ulang</div><div class="stat-num"><?= angka((int) $s['perlu']) ?></div>
      <div class="stat-sub">stok <?= LOW_STOCK ?> kg atau kurang</div></div>
  </a>
  <a class="stat tone-brown" href="inventory.php?status=habis">
    <span class="stat-icon"><?= icon('xcircle', 26) ?></span>
    <div><div class="stat-label">Stok habis</div><div class="stat-num"><?= angka((int) $s['habis']) ?></div>
      <div class="stat-sub">belum bisa dibeli</div></div>
  </a>
</section>

<section class="tiles" aria-label="Info toko">
  <div class="tile">
    <div class="tile-top"><div class="tile-num"><?= rupiah((int) $s['nilai']) ?></div><span class="tile-ic c-orange"><?= icon('wallet', 32) ?></span></div>
    <div class="tile-label">Nilai persediaan</div>
    <div class="tile-foot"><span class="muted">Harga jual × stok</span><a href="inventory.php">Lihat</a></div>
  </div>
  <div class="tile">
    <div class="tile-top"><div class="tile-num"><?= angka($customer) ?></div><span class="tile-ic c-green"><?= icon('users', 32) ?></span></div>
    <div class="tile-label">Customer terdaftar</div>
    <div class="tile-foot"><span class="muted">Akun yang bisa melihat produk</span></div>
  </div>
  <div class="tile">
    <div class="tile-top"><div class="tile-num"><?= angka((int) $s['aman']) ?></div><span class="tile-ic c-amber"><?= icon('check', 32) ?></span></div>
    <div class="tile-label">Produk dengan stok aman</div>
    <div class="tile-foot"><span class="muted">Stok di atas <?= LOW_STOCK ?> kg</span><a href="inventory.php?status=aman">Lihat</a></div>
  </div>
</section>

<section class="panels">
  <div class="panel">
    <h2>Stok per kategori</h2>
    <ul class="bars">
      <?php foreach ($perKategori as $k): ?>
        <li>
          <span class="bar-name"><?= e($k['kategori']) ?> <small class="muted">(<?= (int) $k['produk'] ?> produk)</small></span>
          <span class="bar-track"><span class="bar-fill" style="width:<?= max(4, round($k['stok'] / $maks * 100)) ?>%"></span></span>
          <span class="bar-val"><?= angka((int) $k['stok']) ?> kg</span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="panel">
    <h2>Perlu diisi ulang</h2>
    <?php if (!$perluIsi): ?>
      <p class="muted">Semua produk stoknya masih aman.</p>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($perluIsi as $p): $st = status_stok((int) $p['stok']); ?>
          <li>
            <span><?= e($p['nama']) ?></span>
            <span class="badge <?= $st ?>"><?= $st === 'habis' ? 'Habis' : 'Sisa ' . stok_teks($p) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="panel-foot"><a href="inventory.php?status=perlu">Lihat semua</a></p>
    <?php endif; ?>
  </div>
</section>
<?php admin_end(); ?>
