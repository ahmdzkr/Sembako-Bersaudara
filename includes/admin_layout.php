<?php
/** Ikon garis sederhana (inline SVG). */
function icon(string $nama, int $ukuran = 20): string
{
    static $p = [
        'home'   => '<path d="M3 11 12 3l9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'box'    => '<path d="m21 8-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'bell'   => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'panel'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/>',
        'layers' => '<path d="m12 2 10 5-10 5L2 7l10-5z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
        'alert'  => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'xcircle'=> '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>',
        'wallet' => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20M16 15h2"/>',
        'users'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'check'  => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    ];
    return '<svg class="ic" width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($p[$nama] ?? '') . '</svg>';
}

/** Membuka halaman admin: header HTML, sidebar, dan topbar. */
function admin_start(string $judulHalaman, string $aktif): void
{
    $perluIsi = (int) db()->query('SELECT COUNT(*) FROM products WHERE stok <= ' . LOW_STOCK)->fetchColumn();
    preg_match('/^./u', $_SESSION['nama'], $huruf);
    $inisial  = strtoupper($huruf[0] ?? 'A');

    $judul    = $judulHalaman . ' · Sembako Bersaudara';
    $extraCss = ['admin.css'];
    require __DIR__ . '/header.php';
    ?>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <a class="side-brand" href="index.php">
      <img src="../assets/img/logo-mark.png" alt="" width="44" height="44">
      <span>Sembako <em>Bersaudara</em></span>
    </a>
    <nav class="nav" aria-label="Menu admin">
      <p class="side-group">Menu utama</p>
      <a href="index.php"<?= $aktif === 'dashboard' ? ' class="active" aria-current="page"' : '' ?>><?= icon('home') ?> Dashboard</a>
      <a href="inventory.php"<?= $aktif === 'inventory' ? ' class="active" aria-current="page"' : '' ?>><?= icon('box') ?> Stok produk</a>
      <a href="produk.php"<?= $aktif === 'tambah' ? ' class="active" aria-current="page"' : '' ?>><?= icon('plus') ?> Tambah produk</a>

      <p class="side-group">Akun</p>
      <form method="post" action="../logout.php">
        <?= csrf_field() ?>
        <button type="submit" class="nav-btn"><?= icon('logout') ?> Keluar</button>
      </form>
    </nav>
  </aside>
  <div class="scrim" id="navScrim"></div>

  <div class="main-col">
    <header class="admin-top">
      <button class="icon-btn square" id="navToggle" type="button" aria-label="Buka atau tutup menu" aria-controls="sidebar"><?= icon('panel') ?></button>
      <div class="top-right">
        <a class="icon-btn" href="inventory.php?status=perlu" aria-label="<?= $perluIsi ?> produk perlu diisi ulang">
          <?= icon('bell', 22) ?>
          <?php if ($perluIsi > 0): ?><span class="dot"><?= $perluIsi ?></span><?php endif; ?>
        </a>
        <span class="who"><?= e($_SESSION['nama']) ?></span>
        <span class="avatar" aria-hidden="true"><?= e($inisial) ?></span>
      </div>
    </header>
    <main class="content">
<?php if ($f = flash()): ?>
      <div class="alert alert-<?= e($f[0]) ?>" role="status"><?= e($f[1]) ?></div>
<?php endif; ?>
<?php
}

function admin_end(): void
{
    ?>
    </main>
  </div>
</div>
<script>
(function () {
  var b = document.body, m = window.matchMedia('(max-width: 900px)');
  document.getElementById('navToggle').addEventListener('click', function () {
    b.classList.toggle(m.matches ? 'nav-open' : 'nav-collapsed');
  });
  document.getElementById('navScrim').addEventListener('click', function () { b.classList.remove('nav-open'); });
})();
</script>
<?php
    require __DIR__ . '/footer.php';
}
