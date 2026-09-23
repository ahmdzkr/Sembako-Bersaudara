<?php
require __DIR__ . '/../includes/config.php';
require_admin();
require __DIR__ . '/../includes/admin_layout.php';

$q      = trim($_GET['q'] ?? '');
$kat    = trim($_GET['kategori'] ?? '');
$status = $_GET['status'] ?? '';
$kondisi = [
    'perlu' => 'stok <= ' . LOW_STOCK,
    'habis' => 'stok = 0',
    'aman'  => 'stok > ' . LOW_STOCK,
];

$sql = 'SELECT * FROM products WHERE 1=1';
$par = [];
if ($q !== '')   { $sql .= ' AND nama LIKE ?';   $par[] = "%$q%"; }
if ($kat !== '') { $sql .= ' AND kategori = ?';  $par[] = $kat; }
if (isset($kondisi[$status])) { $sql .= ' AND ' . $kondisi[$status]; } else { $status = ''; }
$sql .= ' ORDER BY stok, nama';
$stmt = db()->prepare($sql);
$stmt->execute($par);
$produk = $stmt->fetchAll();

$daftarKategori = KATEGORI;
$tab = ['' => 'Semua', 'perlu' => 'Perlu diisi ulang', 'habis' => 'Habis', 'aman' => 'Aman'];

admin_start('Stok produk', 'inventory');
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Stok produk</h1>
    <p class="muted">Pantau, ubah, dan kelola produk sembako.</p>
  </div>
  <a class="btn btn-icon" href="produk.php"><?= icon('plus', 18) ?> Tambah produk</a>
</div>

<form class="filters" method="get" role="search">
  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <label class="sr" for="q">Cari produk</label>
  <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Cari nama produk">
  <label class="sr" for="kategori">Kategori</label>
  <select id="kategori" name="kategori">
    <option value="">Semua kategori</option>
    <?php foreach ($daftarKategori as $k): ?>
      <option value="<?= e($k) ?>"<?= $k === $kat ? ' selected' : '' ?>><?= e($k) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Terapkan</button>
</form>

<nav class="chips" aria-label="Filter status stok">
  <?php foreach ($tab as $kode => $label):
      $qs = http_build_query(array_filter(['q' => $q, 'kategori' => $kat, 'status' => $kode], 'strlen')); ?>
    <a class="chip<?= $status === $kode ? ' on' : '' ?>" href="inventory.php<?= $qs ? '?' . e($qs) : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<p class="count"><?= count($produk) ?> produk</p>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Produk</th><th>Kategori</th><th class="num">Harga</th><th class="num">Stok</th><th>Status</th><th class="num">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($produk as $p): $st = status_stok((int) $p['stok']); ?>
      <tr>
        <td><span class="pcell"><span class="pthumb"><?php if ($p['gambar']): ?><img src="../assets/uploads/<?= e($p['gambar']) ?>" alt=""><?php else: ?><?= emoji_kategori($p['kategori']) ?><?php endif; ?></span><strong><?= e($p['nama']) ?></strong></span></td>
        <td><?= e($p['kategori']) ?></td>
        <td class="num"><?= harga_utama($p) ?></td>
        <td class="num"><?= e(stok_teks($p)) ?></td>
        <td><span class="badge <?= $st ?>"><?= ['aman' => 'Aman', 'menipis' => 'Menipis', 'habis' => 'Habis'][$st] ?></span></td>
        <td class="num acts">
          <a href="produk.php?id=<?= (int) $p['id'] ?>" aria-label="Edit <?= e($p['nama']) ?>">Edit</a>
          <a class="danger" href="hapus.php?id=<?= (int) $p['id'] ?>" aria-label="Hapus <?= e($p['nama']) ?>">Hapus</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$produk): ?>
      <tr><td colspan="6" class="muted center">Tidak ada produk yang cocok dengan filter ini.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php admin_end(); ?>
