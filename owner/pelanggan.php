<?php
require __DIR__ . '/../includes/config.php';
require_owner();
require __DIR__ . '/../includes/admin_layout.php';

$id  = (int) ($_GET['id'] ?? 0);
$cus = null;
if ($id > 0) {
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$id]);
    $cus = $stmt->fetch();
    if (!$cus) {
        $_SESSION['flash'] = ['error', 'Customer tidak ditemukan.'];
        header('Location: index.php');
        exit;
    }
}
$edit = $cus !== null;
$v    = $cus ?? [
    'nama_toko' => '', 'nama' => '', 'no_whatsapp' => '', 'email' => '', 'alamat' => '',
    'credit_limit' => DEFAULT_CREDIT_LIMIT, 'termin_hari' => DEFAULT_TERMIN_HARI,
];
$err = [];

function mb_strlen_aman(string $s): int
{
    return preg_match_all('/./us', $s);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $err['umum'] = 'Sesi formulir sudah berakhir. Muat ulang halaman lalu coba lagi.';
    } else {
        $v['nama_toko']    = trim($_POST['nama_toko'] ?? '');
        $v['nama']         = trim($_POST['nama'] ?? '');
        $v['no_whatsapp']  = trim($_POST['no_whatsapp'] ?? '');
        $v['email']        = strtolower(trim($_POST['email'] ?? ''));
        $v['alamat']       = trim($_POST['alamat'] ?? '');
        $v['credit_limit'] = trim($_POST['credit_limit'] ?? '');
        $v['termin_hari']  = trim($_POST['termin_hari'] ?? '');
        $password          = (string) ($_POST['password'] ?? '');

        if ($v['nama_toko'] === '' || mb_strlen_aman($v['nama_toko']) > 150) { $err['nama_toko'] = 'Isi nama toko (maksimal 150 huruf).'; }
        if ($v['nama'] === '' || mb_strlen_aman($v['nama']) > 100) { $err['nama'] = 'Isi nama pemilik (maksimal 100 huruf).'; }
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) { $err['email'] = 'Isi email yang benar.'; }
        if (!preg_match('/^[0-9+\-\s]{8,20}$/', $v['no_whatsapp'])) { $err['no_whatsapp'] = 'Isi nomor WhatsApp yang benar (8-20 digit).'; }
        if ($v['alamat'] === '') { $err['alamat'] = 'Isi alamat lengkap toko.'; }

        $creditLimit = filter_var($v['credit_limit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000000000]]);
        if ($creditLimit === false) { $err['credit_limit'] = 'Limit kredit harus angka bulat, 0 atau lebih.'; }

        $terminHari = filter_var($v['termin_hari'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
        if ($terminHari === false) { $err['termin_hari'] = 'Masa jatuh tempo harus angka bulat, minimal 1 hari.'; }

        if (!isset($err['email'])) {
            $q = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $q->execute([$v['email'], $id]);
            if ($q->fetch()) { $err['email'] = 'Email ini sudah dipakai akun lain.'; }
        }

        if (!$edit && $password === '') {
            $err['password'] = 'Isi kata sandi awal untuk akun ini (minimal 8 karakter).';
        } elseif ($password !== '' && mb_strlen_aman($password) < 8) {
            $err['password'] = 'Kata sandi minimal 8 karakter.';
        }

        if (!$err) {
            if ($edit) {
                if ($password !== '') {
                    $q = db()->prepare('UPDATE users SET nama_toko=?, nama=?, no_whatsapp=?, email=?, alamat=?, credit_limit=?, termin_hari=?, password_hash=? WHERE id=?');
                    $q->execute([$v['nama_toko'], $v['nama'], $v['no_whatsapp'], $v['email'], $v['alamat'], $creditLimit, $terminHari, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $q = db()->prepare('UPDATE users SET nama_toko=?, nama=?, no_whatsapp=?, email=?, alamat=?, credit_limit=?, termin_hari=? WHERE id=?');
                    $q->execute([$v['nama_toko'], $v['nama'], $v['no_whatsapp'], $v['email'], $v['alamat'], $creditLimit, $terminHari, $id]);
                }
                $_SESSION['flash'] = ['success', 'Data customer “' . $v['nama_toko'] . '” disimpan.'];
            } else {
                $q = db()->prepare("INSERT INTO users (nama_toko, nama, no_whatsapp, email, alamat, credit_limit, termin_hari, password_hash, role) VALUES (?,?,?,?,?,?,?,?,'customer')");
                $q->execute([$v['nama_toko'], $v['nama'], $v['no_whatsapp'], $v['email'], $v['alamat'], $creditLimit, $terminHari, password_hash($password, PASSWORD_DEFAULT)]);
                $_SESSION['flash'] = ['success', 'Akun customer “' . $v['nama_toko'] . '” dibuat. Bagikan email dan kata sandi ini langsung ke customer.'];
            }
            header('Location: index.php');
            exit;
        }
    }
}

function field_err(array $err, string $k): string
{
    return isset($err[$k]) ? '<p class="field-err" id="e-' . $k . '">' . e($err[$k]) . '</p>' : '';
}
function attr_err(array $err, string $k): string
{
    return isset($err[$k]) ? ' aria-invalid="true" aria-describedby="e-' . $k . '"' : '';
}

admin_start($edit ? 'Edit customer' : 'Tambah customer', $edit ? 'customer' : 'tambah', 'owner');
?>
<h1 class="page-title"><?= $edit ? 'Edit customer' : 'Tambah customer' ?></h1>
<p class="muted">Buat akun ini setelah kesepakatan kerja sama disetujui langsung dengan pemilik toko.</p>

<?php if (isset($err['umum'])): ?><div class="alert alert-error" role="alert"><?= e($err['umum']) ?></div><?php endif; ?>

<form class="pform" method="post" novalidate>
  <?= csrf_field() ?>
  <div class="pform-main">
    <label for="nama_toko">Nama toko</label>
    <input id="nama_toko" name="nama_toko" type="text" maxlength="150" required value="<?= e($v['nama_toko']) ?>"<?= attr_err($err, 'nama_toko') ?>>
    <?= field_err($err, 'nama_toko') ?>

    <label for="nama">Nama pemilik</label>
    <input id="nama" name="nama" type="text" maxlength="100" required value="<?= e($v['nama']) ?>"<?= attr_err($err, 'nama') ?>>
    <?= field_err($err, 'nama') ?>

    <div class="two">
      <div>
        <label for="no_whatsapp">No. WhatsApp</label>
        <input id="no_whatsapp" name="no_whatsapp" type="tel" maxlength="20" required value="<?= e($v['no_whatsapp']) ?>" placeholder="08xxxxxxxxxx"<?= attr_err($err, 'no_whatsapp') ?>>
        <?= field_err($err, 'no_whatsapp') ?>
      </div>
      <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="150" required value="<?= e($v['email']) ?>"<?= attr_err($err, 'email') ?>>
        <?= field_err($err, 'email') ?>
      </div>
    </div>

    <label for="alamat">Alamat lengkap</label>
    <textarea id="alamat" name="alamat" rows="3" required<?= attr_err($err, 'alamat') ?>><?= e($v['alamat']) ?></textarea>
    <?= field_err($err, 'alamat') ?>

    <div class="two">
      <div>
        <label for="credit_limit">Limit kredit (Rp)</label>
        <input id="credit_limit" name="credit_limit" type="number" min="0" step="1" inputmode="numeric" required value="<?= e((string) $v['credit_limit']) ?>"<?= attr_err($err, 'credit_limit') ?>>
        <p class="hint">Bawaan Rp 50.000.000 sesuai kontrak standar, bisa disesuaikan per customer.</p>
        <?= field_err($err, 'credit_limit') ?>
      </div>
      <div>
        <label for="termin_hari">Masa jatuh tempo (hari)</label>
        <input id="termin_hari" name="termin_hari" type="number" min="1" step="1" inputmode="numeric" required value="<?= e((string) $v['termin_hari']) ?>"<?= attr_err($err, 'termin_hari') ?>>
        <p class="hint">Bawaan 30 hari sejak invoice terbit.</p>
        <?= field_err($err, 'termin_hari') ?>
      </div>
    </div>

    <label for="password"><?= $edit ? 'Ganti kata sandi' : 'Kata sandi awal' ?> <?php if ($edit): ?><span class="muted">(kosongkan jika tidak diubah)</span><?php endif; ?></label>
    <input id="password" name="password" type="text" minlength="8" autocomplete="off" <?= $edit ? '' : 'required' ?><?= attr_err($err, 'password') ?>>
    <p class="hint">Minimal 8 karakter. Bagikan kata sandi ini langsung ke customer setelah akun dibuat/diubah.</p>
    <?= field_err($err, 'password') ?>
  </div>

  <div class="pform-side">
    <span class="lbl">Ringkasan</span>
    <p class="hint">Akun ini otomatis berperan sebagai <strong>customer</strong> dan hanya bisa memesan produk lewat aplikasi setelah dibuatkan di sini.</p>
    <?php if ($edit): ?>
      <p class="hint">Status saat ini: <strong><?= $cus['aktif'] ? 'Aktif' : 'Nonaktif' ?></strong>. Aktif/nonaktifkan lewat daftar customer.</p>
    <?php endif; ?>
  </div>

  <div class="pform-actions">
    <button class="btn" type="submit"><?= $edit ? 'Simpan perubahan' : 'Buat akun customer' ?></button>
    <a class="btn btn-small" href="index.php">Batal</a>
  </div>
</form>
<?php admin_end(); ?>
