<?php
require __DIR__ . '/includes/config.php';
require_customer();

$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$akun = $stmt->fetch();
if (!$akun) {
    // Akun dihapus/dinonaktifkan di tengah sesi yang sedang berjalan.
    header('Location: logout.php');
    exit;
}

$v = [
    'nama_toko'   => $akun['nama_toko'],
    'nama'        => $akun['nama'],
    'no_whatsapp' => $akun['no_whatsapp'],
    'email'       => $akun['email'],
    'alamat'      => $akun['alamat'],
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
        $aksi = $_POST['aksi'] ?? 'profil';

        if ($aksi === 'profil') {
            $v['nama_toko']   = trim($_POST['nama_toko'] ?? '');
            $v['nama']        = trim($_POST['nama'] ?? '');
            $v['no_whatsapp'] = trim($_POST['no_whatsapp'] ?? '');
            $v['email']       = strtolower(trim($_POST['email'] ?? ''));
            $v['alamat']      = trim($_POST['alamat'] ?? '');

            if ($v['nama_toko'] === '' || mb_strlen_aman($v['nama_toko']) > 150) { $err['nama_toko'] = 'Isi nama toko (maksimal 150 huruf).'; }
            if ($v['nama'] === '' || mb_strlen_aman($v['nama']) > 100) { $err['nama'] = 'Isi nama pemilik (maksimal 100 huruf).'; }
            if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) { $err['email'] = 'Isi email yang benar.'; }
            if (!preg_match('/^[0-9+\-\s]{8,20}$/', $v['no_whatsapp'])) { $err['no_whatsapp'] = 'Isi nomor WhatsApp yang benar (8-20 digit).'; }
            if ($v['alamat'] === '') { $err['alamat'] = 'Isi alamat lengkap.'; }

            if (!isset($err['email'])) {
                $q = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
                $q->execute([$v['email'], $akun['id']]);
                if ($q->fetch()) { $err['email'] = 'Email ini sudah dipakai akun lain.'; }
            }

            if (!$err) {
                $q = db()->prepare('UPDATE users SET nama_toko=?, nama=?, no_whatsapp=?, email=?, alamat=? WHERE id=?');
                $q->execute([$v['nama_toko'], $v['nama'], $v['no_whatsapp'], $v['email'], $v['alamat'], $akun['id']]);
                $_SESSION['nama'] = $v['nama']; // supaya nama di header langsung ikut berubah
                $_SESSION['flash'] = ['success', 'Profil berhasil diperbarui.'];
                header('Location: profil.php');
                exit;
            }

        } elseif ($aksi === 'sandi') {
            $lama   = (string) ($_POST['sandi_lama'] ?? '');
            $baru   = (string) ($_POST['sandi_baru'] ?? '');
            $ulang  = (string) ($_POST['sandi_ulang'] ?? '');

            if (!password_verify($lama, $akun['password_hash'])) {
                $err['sandi_lama'] = 'Kata sandi saat ini salah.';
            }
            if (mb_strlen_aman($baru) < 8) {
                $err['sandi_baru'] = 'Kata sandi baru minimal 8 karakter.';
            }
            if ($baru !== $ulang) {
                $err['sandi_ulang'] = 'Konfirmasi kata sandi tidak sama.';
            }

            if (!$err) {
                $q = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $q->execute([password_hash($baru, PASSWORD_DEFAULT), $akun['id']]);
                $_SESSION['flash'] = ['success', 'Kata sandi berhasil diganti.'];
                header('Location: profil.php');
                exit;
            }
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

$halamanAktif = 'profil';
$judul        = 'Profil saya · Sembako Bersaudara';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/customer_topbar.php';
?>
<main class="wrap">
  <h1 class="page-title">Profil saya</h1>
  <?php if ($f = flash()): ?><div class="alert alert-<?= e($f[0]) ?>" role="status"><?= e($f[1]) ?></div><?php endif; ?>

  <div class="profile-form">
    <section class="profile-section">
      <h2>Data akun</h2>
      <p class="muted">Informasi ini dipakai saat pesanan diproses dan untuk dihubungi toko.</p>
      <?php if (isset($err['umum'])): ?><div class="alert alert-error" role="alert"><?= e($err['umum']) ?></div><?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="profil">

        <label for="nama_toko">Nama toko</label>
        <input id="nama_toko" name="nama_toko" type="text" maxlength="150" required value="<?= e($v['nama_toko']) ?>"<?= attr_err($err, 'nama_toko') ?>>
        <?= field_err($err, 'nama_toko') ?>

        <label for="nama">Nama pemilik</label>
        <input id="nama" name="nama" type="text" maxlength="100" required value="<?= e($v['nama']) ?>"<?= attr_err($err, 'nama') ?>>
        <?= field_err($err, 'nama') ?>

        <div class="profile-two">
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

        <button class="btn" type="submit">Simpan perubahan</button>
      </form>
    </section>

    <section class="profile-section">
      <h2>Ganti kata sandi</h2>
      <p class="muted">Kosongkan bagian ini kalau tidak ingin mengganti kata sandi.</p>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="sandi">

        <label for="sandi_lama">Kata sandi saat ini</label>
        <input id="sandi_lama" name="sandi_lama" type="password" autocomplete="current-password"<?= attr_err($err, 'sandi_lama') ?>>
        <?= field_err($err, 'sandi_lama') ?>

        <div class="profile-two">
          <div>
            <label for="sandi_baru">Kata sandi baru</label>
            <input id="sandi_baru" name="sandi_baru" type="password" minlength="8" autocomplete="new-password"<?= attr_err($err, 'sandi_baru') ?>>
            <?= field_err($err, 'sandi_baru') ?>
          </div>
          <div>
            <label for="sandi_ulang">Ulangi kata sandi baru</label>
            <input id="sandi_ulang" name="sandi_ulang" type="password" minlength="8" autocomplete="new-password"<?= attr_err($err, 'sandi_ulang') ?>>
            <?= field_err($err, 'sandi_ulang') ?>
          </div>
        </div>

        <button class="btn" type="submit">Ganti kata sandi</button>
      </form>
    </section>

    <section class="profile-section">
      <h2>Kontrak</h2>
      <p class="muted">Diatur oleh pemilik toko, tidak bisa diubah sendiri. Hubungi toko kalau ada yang perlu disesuaikan.</p>
      <div class="profile-readonly">
        <div><strong><?= $akun['credit_limit'] !== null ? rupiah((int) $akun['credit_limit']) : '-' ?></strong>Limit kredit</div>
        <div><strong><?= $akun['termin_hari'] !== null ? $akun['termin_hari'] . ' hari' : '-' ?></strong>Masa jatuh tempo</div>
      </div>
    </section>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
