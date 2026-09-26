<?php
require __DIR__ . '/../includes/config.php';
require_admin();
require __DIR__ . '/../includes/admin_layout.php';

const MAKS_FOTO = 2 * 1024 * 1024; // 2 MB

$id     = (int) ($_GET['id'] ?? 0);
$produk = null;
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $produk = $stmt->fetch();
    if (!$produk) {
        $_SESSION['flash'] = ['error', 'Produk tidak ditemukan.'];
        header('Location: inventory.php');
        exit;
    }
}
$edit = $produk !== null;
$v    = $produk ?? [
    'nama' => '', 'kategori' => '', 'satuan_dasar' => '', 'harga' => '', 'satuan' => '',
    'harga_satuan' => '', 'isi_dasar' => '', 'stok' => '', 'deskripsi' => '', 'gambar' => null,
];
$err  = [];

function mb_strlen_aman(string $s): int
{
    return preg_match_all('/./us', $s);
}
function angka_atau_null($v): ?int
{
    $v = trim((string) $v);
    return $v === '' ? null : filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000000]]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $err['umum'] = 'Ukuran data terlalu besar. Foto maksimal 2 MB.';
    } elseif (!csrf_valid()) {
        $err['umum'] = 'Sesi formulir sudah berakhir. Muat ulang halaman lalu coba lagi.';
    } else {
        $v['nama']         = trim($_POST['nama'] ?? '');
        $v['kategori']     = trim($_POST['kategori'] ?? '');
        $v['satuan_dasar'] = trim($_POST['satuan_dasar'] ?? '');
        $v['satuan']       = trim($_POST['satuan'] ?? '');
        $v['deskripsi']    = trim($_POST['deskripsi'] ?? '');
        $v['harga']        = trim($_POST['harga'] ?? '');
        $v['harga_satuan'] = trim($_POST['harga_satuan'] ?? '');
        $v['isi_dasar']    = trim($_POST['isi_dasar'] ?? '');
        $v['stok']         = trim($_POST['stok'] ?? '');

        $kat = $v['kategori'];
        if (!in_array($kat, KATEGORI, true)) {
            $err['kategori'] = 'Pilih salah satu kategori.';
        }

        // Basis (satuan_dasar) ditentukan per kategori:
        //  - Fresh Good : selalu 'kg', tidak bisa dipilih admin.
        //  - Lainnya    : selalu tanpa basis (NULL).
        //  - Dry Good   : admin memilih kg / liter / tanpa basis lewat dropdown.
        if ($kat === 'Fresh Good') {
            $dasar = 'kg';
        } elseif ($kat === 'Lainnya') {
            $dasar = '';
        } else {
            $dasar = $v['satuan_dasar'];
            if ($dasar !== '' && !isset(SATUAN_DASAR_PILIHAN[$dasar])) {
                $err['satuan_dasar'] = 'Pilihan basis tidak valid.';
            }
        }
        $pakaiDasar = $dasar !== '';
        // Kemasan (satuan+harga_satuan) wajib jika tidak ada basis sama sekali (mis. Lainnya, atau
        // Dry Good tanpa kg/liter); jika ada basis, kemasan sifatnya opsional (boleh dikosongkan).
        $kemasanWajib   = !$pakaiDasar;
        $kemasanDiisi   = $v['satuan'] !== '' || $v['harga_satuan'] !== '';

        if ($v['nama'] === '' || mb_strlen_aman($v['nama']) > 150) { $err['nama'] = 'Isi nama produk (maksimal 150 huruf).'; }

        $harga = $hargaSatuan = $isiDasar = null;

        if ($pakaiDasar) {
            $harga = angka_atau_null($v['harga']);
            if ($harga === null) { $err['harga'] = 'Harga per ' . $dasar . ' harus angka bulat, 0 atau lebih.'; }
        }

        if ($kemasanWajib || $kemasanDiisi) {
            if ($v['satuan'] === '' || mb_strlen_aman($v['satuan']) > 20) { $err['satuan'] = 'Isi nama satuan/kemasan, misalnya karung, jerigen, sachet, atau kaleng (maksimal 20 huruf).'; }
            $hargaSatuan = angka_atau_null($v['harga_satuan']);
            if ($hargaSatuan === null) { $err['harga_satuan'] = 'Harga per satuan harus angka bulat, 0 atau lebih.'; }
            if ($pakaiDasar) {
                $isiDasar = angka_atau_null($v['isi_dasar']);
                if ($isiDasar === null || $isiDasar < 1) { $err['isi_dasar'] = 'Isi jumlah ' . $dasar . ' per satuan kemasan, minimal 1.'; }
            }
        }

        $stok = filter_var($v['stok'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
        if ($stok === false) {
            $err['stok'] = $pakaiDasar
                ? 'Stok harus angka bulat (dalam ' . $dasar . '), 0 atau lebih.'
                : 'Stok harus angka bulat (jumlah satuan/kemasan), 0 atau lebih.';
        }
        if (mb_strlen_aman($v['deskripsi']) > 500) { $err['deskripsi'] = 'Deskripsi maksimal 500 huruf.'; }

        // Validasi foto (opsional)
        $ext  = null;
        $foto = $_FILES['foto'] ?? null;
        if ($foto && $foto['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($foto['error'] === UPLOAD_ERR_INI_SIZE || $foto['error'] === UPLOAD_ERR_FORM_SIZE || $foto['size'] > MAKS_FOTO) {
                $err['foto'] = 'Ukuran foto maksimal 2 MB.';
            } elseif ($foto['error'] !== UPLOAD_ERR_OK) {
                $err['foto'] = 'Foto gagal diunggah. Coba lagi.';
            } else {
                $info = @getimagesize($foto['tmp_name']);
                $ext  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
                if ($ext === null) { $err['foto'] = 'Format foto harus JPG, PNG, atau WebP.'; }
            }
        }

        if (!$err) {
            $gambarLama = $edit ? $produk['gambar'] : null;
            $gambar     = $gambarLama;

            if ($ext !== null) {
                if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0755, true); }
                $namaFile = bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($foto['tmp_name'], UPLOAD_DIR . $namaFile)) {
                    $gambar = $namaFile;
                } else {
                    $err['foto'] = 'Foto tidak bisa disimpan. Pastikan folder assets/uploads bisa ditulis.';
                }
            } elseif ($edit && !empty($_POST['hapus_foto'])) {
                $gambar = null;
            }
        }

        if (!$err) {
            $dasarSimpan  = $pakaiDasar ? $dasar : null;
            $satuanSimpan = ($kemasanWajib || $kemasanDiisi) ? $v['satuan'] : null;
            if ($edit) {
                $q = db()->prepare('UPDATE products SET nama=?, kategori=?, satuan_dasar=?, harga=?, satuan=?, harga_satuan=?, isi_dasar=?, stok=?, deskripsi=?, gambar=? WHERE id=?');
                $q->execute([$v['nama'], $kat, $dasarSimpan, $harga, $satuanSimpan, $hargaSatuan, $isiDasar, $stok, $v['deskripsi'] ?: null, $gambar, $id]);
                if ($gambar !== $gambarLama) { hapus_file_foto($gambarLama); }
                $_SESSION['flash'] = ['success', 'Perubahan pada “' . $v['nama'] . '” disimpan.'];
            } else {
                $q = db()->prepare('INSERT INTO products (nama, kategori, satuan_dasar, harga, satuan, harga_satuan, isi_dasar, stok, deskripsi, gambar) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $q->execute([$v['nama'], $kat, $dasarSimpan, $harga, $satuanSimpan, $hargaSatuan, $isiDasar, $stok, $v['deskripsi'] ?: null, $gambar]);
                $_SESSION['flash'] = ['success', 'Produk “' . $v['nama'] . '” ditambahkan.'];
            }
            header('Location: inventory.php');
            exit;
        }
        $v['gambar'] = $edit ? $produk['gambar'] : null;
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

admin_start($edit ? 'Edit produk' : 'Tambah produk', $edit ? 'inventory' : 'tambah');
?>
<h1 class="page-title"><?= $edit ? 'Edit produk' : 'Tambah produk' ?></h1>
<p class="muted">Fresh Good selalu per kg. Lainnya selalu per satuan/kemasan. Dry Good bebas dipilih: per kg, per liter, atau hanya per kemasan (sachet, kaleng, dsb.), dengan opsi tambahan kemasan seperti karung atau jerigen.</p>

<?php if (isset($err['umum'])): ?><div class="alert alert-error" role="alert"><?= e($err['umum']) ?></div><?php endif; ?>

<form class="pform" method="post" enctype="multipart/form-data" novalidate id="produkForm">
  <?= csrf_field() ?>
  <div class="pform-main">
    <label for="nama">Nama produk</label>
    <input id="nama" name="nama" type="text" maxlength="150" required value="<?= e($v['nama']) ?>"<?= attr_err($err, 'nama') ?>>
    <?= field_err($err, 'nama') ?>

    <label for="kategori">Kategori</label>
    <select id="kategori" name="kategori" required<?= attr_err($err, 'kategori') ?>>
      <option value="">Pilih kategori</option>
      <?php foreach (KATEGORI as $k): ?>
        <option value="<?= e($k) ?>"<?= $v['kategori'] === $k ? ' selected' : '' ?>><?= e($k) ?></option>
      <?php endforeach; ?>
    </select>
    <?= field_err($err, 'kategori') ?>

    <div class="grp-pilihdasar">
      <label for="satuan_dasar">Cara jual utama (basis)</label>
      <select id="satuan_dasar" name="satuan_dasar"<?= attr_err($err, 'satuan_dasar') ?>>
        <option value="">Tanpa basis, hanya per kemasan (sachet, kaleng, dsb.)</option>
        <?php foreach (SATUAN_DASAR_PILIHAN as $kode => $label): ?>
          <option value="<?= e($kode) ?>"<?= (string) $v['satuan_dasar'] === $kode ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Pilih "kg" untuk produk seperti beras/tepung, "liter" untuk minyak goreng, atau "Tanpa basis" untuk produk yang selalu dijual dalam kemasan tetap seperti kopi sachet atau susu kaleng.</p>
      <?= field_err($err, 'satuan_dasar') ?>
    </div>

    <div class="two grp-kg">
      <div>
        <label for="harga" id="lblHarga">Harga per satuan dasar (Rp)</label>
        <input id="harga" name="harga" type="number" min="0" step="1" inputmode="numeric" value="<?= e((string) $v['harga']) ?>"<?= attr_err($err, 'harga') ?>>
        <?= field_err($err, 'harga') ?>
      </div>
    </div>

    <div class="grp-satuan-head">
      <span class="lbl">Kemasan <span class="muted" id="ketKemasan">(opsional, tambahan cara beli per kemasan)</span></span>
    </div>
    <div class="two grp-satuan">
      <div>
        <label for="satuan">Nama satuan/kemasan</label>
        <input id="satuan" name="satuan" type="text" maxlength="20" list="satuan-list" placeholder="karung, jerigen, sachet, kaleng…" value="<?= e((string) $v['satuan']) ?>"<?= attr_err($err, 'satuan') ?>>
        <datalist id="satuan-list"><option value="karung"><option value="pak"><option value="dus"><option value="jerigen"><option value="bal"><option value="sachet"><option value="renceng"><option value="kaleng"><option value="botol"><option value="kotak"></datalist>
        <?= field_err($err, 'satuan') ?>
      </div>
      <div>
        <label for="harga_satuan">Harga per kemasan (Rp)</label>
        <input id="harga_satuan" name="harga_satuan" type="number" min="0" step="1" inputmode="numeric" value="<?= e((string) $v['harga_satuan']) ?>"<?= attr_err($err, 'harga_satuan') ?>>
        <?= field_err($err, 'harga_satuan') ?>
      </div>
    </div>

    <div class="two grp-isidasar">
      <div>
        <label for="isi_dasar" id="lblIsiDasar">Isi 1 kemasan (dalam satuan dasar)</label>
        <input id="isi_dasar" name="isi_dasar" type="number" min="1" step="1" inputmode="numeric" value="<?= e((string) $v['isi_dasar']) ?>"<?= attr_err($err, 'isi_dasar') ?>>
        <p class="hint" id="hintIsiDasar">Misalnya 1 karung beras = 25 kg, atau 1 jerigen minyak = 15 liter.</p>
        <?= field_err($err, 'isi_dasar') ?>
      </div>
    </div>

    <label for="stok" id="lblStok">Stok</label>
    <input id="stok" name="stok" type="number" min="0" step="1" inputmode="numeric" required value="<?= e((string) $v['stok']) ?>"<?= attr_err($err, 'stok') ?>>
    <p class="hint" id="hintStok"></p>
    <?= field_err($err, 'stok') ?>

    <label for="deskripsi">Deskripsi <span class="muted">(boleh kosong)</span></label>
    <textarea id="deskripsi" name="deskripsi" rows="4" maxlength="500"<?= attr_err($err, 'deskripsi') ?>><?= e($v['deskripsi']) ?></textarea>
    <?= field_err($err, 'deskripsi') ?>
  </div>

  <div class="pform-side">
    <span class="lbl">Foto produk</span>
    <div class="preview" id="preview">
      <?php if ($v['gambar']): ?>
        <img src="../assets/uploads/<?= e($v['gambar']) ?>" alt="Foto <?= e($v['nama']) ?>">
      <?php else: ?>
        <span class="muted" id="preview-kosong">Belum ada foto</span>
      <?php endif; ?>
    </div>
    <label for="foto" class="btn btn-small file-btn">Pilih foto</label>
    <input class="sr" id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp"<?= attr_err($err, 'foto') ?>>
    <p class="hint">JPG, PNG, atau WebP, maksimal 2 MB.</p>
    <?= field_err($err, 'foto') ?>
    <?php if ($edit && $v['gambar']): ?>
      <label class="check"><input type="checkbox" name="hapus_foto" value="1"> Hapus foto saat ini</label>
    <?php endif; ?>
  </div>

  <div class="pform-actions">
    <button class="btn" type="submit"><?= $edit ? 'Simpan perubahan' : 'Simpan produk' ?></button>
    <a class="btn btn-small" href="inventory.php">Batal</a>
  </div>
</form>
<script>
(function () {
  var katSel = document.getElementById('kategori');
  var dasarSel = document.getElementById('satuan_dasar');
  var dasarWrap = document.querySelector('.grp-pilihdasar');
  var hargaInput = document.getElementById('harga');
  var satuanInput = document.getElementById('satuan');
  var hsInput = document.getElementById('harga_satuan');
  var isiInput = document.getElementById('isi_dasar');

  function labelDasar() {
    // Fresh Good selalu kg; kategori lain ikut pilihan dropdown (kosong = tanpa basis)
    return katSel.value === 'Fresh Good' ? 'kg' : dasarSel.value;
  }

  function terapkan() {
    var kat = katSel.value;
    var isFresh = kat === 'Fresh Good';
    var isLainnya = kat === 'Lainnya';
    var isDry = kat === 'Dry Good';

    // Pemilihan basis hanya relevan & terlihat untuk Dry Good.
    dasarWrap.style.display = isDry ? '' : 'none';
    if (isFresh) { dasarSel.value = 'kg'; }
    if (isLainnya) { dasarSel.value = ''; }

    var dasar = labelDasar();
    var pakaiDasar = dasar !== '' && !isLainnya;

    document.querySelectorAll('.grp-kg').forEach(function (el) { el.style.display = pakaiDasar ? '' : 'none'; });
    hargaInput.required = pakaiDasar;
    document.getElementById('lblHarga').textContent = 'Harga per ' + (dasar || 'satuan dasar') + ' (Rp)';

    // Kemasan wajib kalau tidak ada basis sama sekali; opsional kalau ada basis.
    var kemasanWajib = !pakaiDasar;
    satuanInput.required = kemasanWajib;
    hsInput.required = kemasanWajib;
    document.getElementById('ketKemasan').textContent = kemasanWajib
      ? '(wajib, ini satu-satunya cara beli produk ini)'
      : '(opsional, tambahan cara beli per kemasan)';

    document.querySelectorAll('.grp-isidasar').forEach(function (el) { el.style.display = pakaiDasar ? '' : 'none'; });
    isiInput.required = pakaiDasar; // hanya relevan dipakai kalau kemasan juga diisi, tapi aman selalu ada nilainya
    document.getElementById('lblIsiDasar').textContent = 'Isi 1 kemasan (' + (dasar || 'satuan dasar') + ')';
    document.getElementById('hintIsiDasar').textContent = dasar === 'liter'
      ? 'Misalnya 1 jerigen minyak = 15 liter.'
      : 'Misalnya 1 karung beras = 25 kg.';

    var lblStok = pakaiDasar ? ('Stok (' + dasar + ')') : 'Stok (jumlah satuan/kemasan)';
    document.getElementById('lblStok').textContent = lblStok;
    document.getElementById('hintStok').textContent = pakaiDasar
      ? 'Diisi dalam ' + dasar + ', bukan jumlah kemasan.'
      : 'Diisi dalam jumlah satuan/kemasan, karena produk ini tidak punya basis kg/liter.';
  }

  katSel.addEventListener('change', terapkan);
  dasarSel.addEventListener('change', terapkan);
  terapkan();
})();
document.getElementById('foto').addEventListener('change', function () {
  var f = this.files[0], box = document.getElementById('preview');
  if (!f) { return; }
  var img = document.createElement('img');
  img.alt = 'Pratinjau foto'; img.src = URL.createObjectURL(f);
  box.innerHTML = ''; box.appendChild(img);
});
</script>
<?php admin_end(); ?>
