<?php
require __DIR__ . '/includes/config.php';

if (is_logged_in()) {
    header('Location: ' . home_url());
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!csrf_valid()) {
        $error = 'Sesi formulir sudah berakhir. Muat ulang halaman lalu coba lagi.';
    } else {
        $stmt = db()->prepare('SELECT id, nama, password_hash, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['role']    = $user['role'];
            header('Location: ' . home_url());
            exit;
        }
        $error = 'Email atau kata sandi salah. Periksa lagi lalu coba masuk.';
    }
}

$info  = flash();
$judul = 'Masuk · Sembako Bersaudara';
require __DIR__ . '/includes/header.php';
?>
<main class="login">
  <section class="login-brand">
    <img src="assets/img/logo.jpg" alt="Logo Sembako Bersaudara: keranjang beras, minyak, gula, dan tepung">
  </section>

  <section class="login-panel">
    <h1>Selamat datang</h1>
    <p class="muted">Supplier Sembako & Bahan Makanan</p>

    <?php if ($error): ?>
      <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php elseif ($info): ?>
      <div class="alert alert-<?= e($info[0]) ?>" role="alert"><?= e($info[1]) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>
      <label for="email">Email</label>
      <input id="email" name="email" type="email" autocomplete="email" required autofocus
            placeholder="nama@email.com" value="<?= e($_POST['email'] ?? '') ?>">

      <label for="password">Kata sandi</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>

      <button type="submit" class="btn">Masuk</button>
    </form>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
