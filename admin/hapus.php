<?php
require __DIR__ . '/../includes/config.php';
require_admin();
require __DIR__ . '/../includes/admin_layout.php';

$id = (int) ($_REQUEST['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) {
    $_SESSION['flash'] = ['error', 'Produk tidak ditemukan.'];
    header('Location: inventory.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_valid()) {
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        hapus_file_foto($p['gambar']);
        $_SESSION['flash'] = ['success', 'Produk “' . $p['nama'] . '” dihapus.'];
    } else {
        $_SESSION['flash'] = ['error', 'Sesi formulir sudah berakhir. Coba hapus lagi.'];
    }
    header('Location: inventory.php');
    exit;
}

admin_start('Hapus produk', 'inventory');
?>
<div class="confirm">
  <h1 class="page-title">Hapus produk ini?</h1>
  <p><strong><?= e($p['nama']) ?></strong> (<?= e($p['kategori']) ?>, stok <?= e(stok_teks($p)) ?>)
     akan dihapus dari toko dan tidak lagi terlihat oleh customer. Tindakan ini tidak bisa dibatalkan.</p>
  <form method="post" class="confirm-actions">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <button class="btn btn-danger" type="submit">Ya, hapus produk</button>
    <a class="btn btn-small" href="inventory.php">Batal</a>
  </form>
</div>
<?php admin_end(); ?>
