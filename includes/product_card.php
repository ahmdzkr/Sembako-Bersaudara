<?php
/**
 * Cetak satu kartu produk ringkas (gambar, nama, harga, stok, deskripsi).
 * Seluruh kartu adalah tautan ke halaman detail; pemilihan jumlah dan
 * tombol keranjang hanya ada di halaman detail produk.
 * Panggil: render_product_card($p);
 */
function render_product_card(array $p): void
{
    $st   = status_stok((int) $p['stok']);
    $link = 'produk_detail.php?id=' . (int) $p['id'];
    ?>
    <a class="card <?= $st === 'habis' ? 'habis' : '' ?>" href="<?= e($link) ?>">
      <div class="thumb">
        <?php if ($p['gambar']): ?>
          <img src="assets/uploads/<?= e($p['gambar']) ?>" alt="<?= e($p['nama']) ?>" loading="lazy">
        <?php else: ?>
          <span class="thumb-emoji" aria-hidden="true"><?= emoji_kategori($p['kategori']) ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <h3><?= e($p['nama']) ?></h3>
        <p class="desc"><?= e($p['deskripsi']) ?></p>
        <div class="price-row">
          <div class="price"><?= harga_utama($p) ?></div>
          <?php if ($st === 'habis'): ?>
            <span class="badge habis">Habis</span>
          <?php elseif ($st === 'menipis'): ?>
            <span class="badge menipis">Sisa <?= e(stok_teks($p)) ?></span>
          <?php else: ?>
            <span class="badge aman">Stok <?= e(stok_teks($p)) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </a>
    <?php
}
