<?php
require __DIR__ . '/../includes/config.php';
require_owner();
require __DIR__ . '/../includes/admin_layout.php';

$q = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM users WHERE role = 'customer'";
$par = [];
if ($q !== '') {
    $sql .= ' AND (nama_toko LIKE ? OR nama LIKE ? OR email LIKE ? OR no_whatsapp LIKE ?)';
    array_push($par, "%$q%", "%$q%", "%$q%", "%$q%");
}
$sql .= ' ORDER BY aktif DESC, nama_toko';
$stmt = db()->prepare($sql);
$stmt->execute($par);
$customer = $stmt->fetchAll();

admin_start('Customer', 'customer', 'owner');
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Customer berkontrak</h1>
    <p class="muted">Akun customer hanya bisa dibuat oleh owner, setelah kesepakatan kerja sama disetujui.</p>
  </div>
  <a class="btn btn-icon" href="pelanggan.php"><?= icon('plus', 18) ?> Tambah customer</a>
</div>

<form class="filters" method="get" role="search">
  <label class="sr" for="q">Cari customer</label>
  <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Cari nama toko, pemilik, email, atau WhatsApp">
  <button class="btn" type="submit">Cari</button>
</form>

<p class="count"><?= count($customer) ?> customer</p>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Toko</th><th>Pemilik</th><th>Kontak</th><th class="num">Limit kredit</th><th class="num">Jatuh tempo</th><th>Status</th><th class="num">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($customer as $c): ?>
      <tr>
        <td><strong><?= e($c['nama_toko'] ?: '-') ?></strong></td>
        <td><?= e($c['nama']) ?></td>
        <td><?= e($c['email']) ?><br><span class="muted"><?= e($c['no_whatsapp'] ?: '-') ?></span></td>
        <td class="num"><?= $c['credit_limit'] !== null ? rupiah((int) $c['credit_limit']) : '-' ?></td>
        <td class="num"><?= $c['termin_hari'] !== null ? $c['termin_hari'] . ' hari' : '-' ?></td>
        <td><span class="badge <?= $c['aktif'] ? 'aman' : 'habis' ?>"><?= $c['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td class="num acts">
          <a href="pelanggan.php?id=<?= (int) $c['id'] ?>">Edit</a>
          <a class="<?= $c['aktif'] ? 'danger' : '' ?>" href="nonaktifkan.php?id=<?= (int) $c['id'] ?>"><?= $c['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$customer): ?><tr><td colspan="7" class="muted center">Belum ada customer. Klik "Tambah customer" untuk mulai.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php admin_end(); ?>
