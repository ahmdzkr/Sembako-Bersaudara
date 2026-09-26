<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/orders.php';
require_admin();
require __DIR__ . '/../includes/admin_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $_SESSION['flash'] = ['error', 'Sesi formulir sudah berakhir. Coba lagi.'];
    } else {
        $id   = (int) ($_POST['id'] ?? 0);
        $aksi = $_POST['aksi'] ?? '';
        if ($aksi === 'siap_kirim') {
            [$ok, $pesan] = order_tandai_siap_kirim($id, trim($_POST['no_resi'] ?? ''));
            $_SESSION['flash'] = [$ok ? 'success' : 'error', $pesan];
        } elseif ($aksi === 'dikirim') {
            [$ok, $pesan] = order_tandai_dikirim($id);
            $_SESSION['flash'] = [$ok ? 'success' : 'error', $pesan];
        }
    }
    header('Location: pesanan.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

$status = $_GET['status'] ?? '';
$sql = 'SELECT o.*, u.nama AS nama_customer FROM orders o JOIN users u ON u.id = o.user_id WHERE 1=1';
$par = [];
if (isset(STATUS_PESANAN[$status])) {
    $sql .= ' AND o.status = ?';
    $par[] = $status;
} else {
    $status = '';
}
$sql .= ' ORDER BY o.id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($par);
$pesanan = $stmt->fetchAll();

$tab = ['' => 'Semua'] + STATUS_PESANAN;

admin_start('Pesanan', 'pesanan');
?>
<h1 class="page-title">Pesanan</h1>
<p class="muted">Siapkan barang, cetak dokumen, dan perbarui status pengiriman.</p>

<nav class="chips" aria-label="Filter status pesanan">
  <?php foreach ($tab as $kode => $label): ?>
    <a class="chip<?= $status === $kode ? ' on' : '' ?>" href="pesanan.php<?= $kode !== '' ? '?status=' . urlencode($kode) : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<p class="count"><?= count($pesanan) ?> pesanan</p>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>No. PO</th><th>Customer</th><th>Tanggal</th><th class="num">Total</th><th>Status</th><th>Dokumen</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($pesanan as $o): ?>
      <tr>
        <td><strong><?= e(no_pesanan((int) $o['id'])) ?></strong></td>
        <td><?= e($o['nama_customer']) ?></td>
        <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
        <td class="num"><?= rupiah((int) $o['total']) ?></td>
        <td><span class="badge status-<?= e($o['status']) ?>"><?= e(label_status($o['status'])) ?></span></td>
        <td>
          <?php if (in_array($o['status'], ['siap_diproses', 'siap_kirim', 'dikirim', 'diterima'], true)): ?>
            <a href="surat_jalan.php?id=<?= (int) $o['id'] ?>" target="_blank" rel="noopener">Surat jalan</a> ·
            <a href="label.php?id=<?= (int) $o['id'] ?>" target="_blank" rel="noopener">Label</a>
          <?php else: ?>
            <span class="muted">&mdash;</span>
          <?php endif; ?>
          <?php if ($o['status'] === 'diterima'): ?>
            · <a href="../invoice.php?id=<?= (int) $o['id'] ?>" target="_blank" rel="noopener">Invoice</a>
          <?php endif; ?>
        </td>
        <td class="acts">
          <?php if ($o['status'] === 'siap_diproses'): ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="siap_kirim">
              <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
              <input type="text" name="no_resi" placeholder="No. resi (opsional)" class="mini-input">
              <button class="btn btn-small" type="submit">Tandai siap kirim</button>
            </form>
          <?php elseif ($o['status'] === 'siap_kirim'): ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="dikirim">
              <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
              <button class="btn btn-small" type="submit">Tandai dikirim</button>
            </form>
          <?php elseif ($o['status'] === 'dikirim'): ?>
            <span class="muted">Menunggu konfirmasi customer</span>
          <?php else: ?>
            <span class="muted">&mdash;</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pesanan): ?>
      <tr><td colspan="7" class="muted center">Tidak ada pesanan pada status ini.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php admin_end(); ?>
