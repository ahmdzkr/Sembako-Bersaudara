<?php
require_once __DIR__ . '/cart.php';
/** Topbar untuk halaman customer. Panggil dengan $halamanAktif = 'beranda'|'keranjang'|'pesanan'. */
$halamanAktif = $halamanAktif ?? '';
$jumlahKeranjang = cart_count();
?>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="beranda.php">Sembako <em>Bersaudara</em></a>
    <nav class="ctabs" aria-label="Menu">
      <a href="beranda.php"<?= $halamanAktif === 'beranda' ? ' class="on" aria-current="page"' : '' ?>>Produk</a>
      <a href="pesanan.php"<?= $halamanAktif === 'pesanan' ? ' class="on" aria-current="page"' : '' ?>>Pesanan saya</a>
    </nav>
    <div class="user">
      <a class="cart-link" href="keranjang.php" aria-label="Keranjang belanja, <?= $jumlahKeranjang ?> item">
        🛒<?php if ($jumlahKeranjang > 0): ?><span class="cart-dot"><?= $jumlahKeranjang ?></span><?php endif; ?>
      </a>
      <span class="who-name"><?= e($_SESSION['nama']) ?></span>
      <form method="post" action="logout.php">
        <?= csrf_field() ?>
        <button class="btn btn-small" type="submit">Keluar</button>
      </form>
    </div>
  </div>
</header>
