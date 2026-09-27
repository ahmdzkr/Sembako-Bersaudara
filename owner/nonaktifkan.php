<?php
require __DIR__ . '/../includes/config.php';
require_owner();
require __DIR__ . '/../includes/admin_layout.php';

$id  = (int) ($_REQUEST['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
$stmt->execute([$id]);
$cus = $stmt->fetch();

if (!$cus) {
    $_SESSION['flash'] = ['error', 'Customer tidak ditemukan.'];
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_valid()) {
        $baru = $cus['aktif'] ? 0 : 1;
        db()->prepare('UPDATE users SET aktif = ? WHERE id = ?')->execute([$baru, $id]);
        $_SESSION['flash'] = ['success', 'Akun “' . $cus['nama_toko'] . '” ' . ($baru ? 'diaktifkan kembali.' : 'dinonaktifkan. Customer ini tidak bisa login sampai diaktifkan lagi.')];
    } else {
        $_SESSION['flash'] = ['error', 'Sesi formulir sudah berakhir. Coba lagi.'];
    }
    header('Location: index.php');
    exit;
}

admin_start($cus['aktif'] ? 'Nonaktifkan customer' : 'Aktifkan customer', 'customer', 'owner');
?>
<div class="confirm">
  <h1 class="page-title"><?= $cus['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?> akun ini?</h1>
  <p>
    <strong><?= e($cus['nama_toko']) ?></strong> (<?= e($cus['nama']) ?>, <?= e($cus['email']) ?>)
    <?php if ($cus['aktif']): ?>
      tidak akan bisa login atau membuat pesanan baru sampai diaktifkan kembali. Data dan riwayat pesanan lama tetap tersimpan.
    <?php else: ?>
      akan bisa login dan memesan produk lagi seperti biasa.
    <?php endif; ?>
  </p>
  <form method="post" class="confirm-actions">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $cus['id'] ?>">
    <button class="btn <?= $cus['aktif'] ? 'btn-danger' : '' ?>" type="submit"><?= $cus['aktif'] ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?></button>
    <a class="btn btn-small" href="index.php">Batal</a>
  </form>
</div>
<?php admin_end(); ?>
