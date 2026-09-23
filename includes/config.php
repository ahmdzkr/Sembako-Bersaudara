<?php
// Pengaturan koneksi database (sesuaikan dengan XAMPP Anda)
const DB_HOST = '127.0.0.1';
const DB_NAME = 'sembako';
const DB_USER = 'root';
const DB_PASS = '';          // XAMPP default: kosong

const LOW_STOCK = 100;       // stok (kg atau satuan) <= angka ini dianggap menipis
const SATUAN    = 'kg';      // semua produk dijual per kilogram

// Kategori produk (tetap 3). Urutan ini dipakai di seluruh halaman.
const KATEGORI  = ['Fresh Good', 'Dry Good', 'Lainnya'];

session_start();

// Awalan URL relatif: halaman di folder admin/ perlu naik satu tingkat.
define('ROOT', basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin' ? '../' : '');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

/** Escape teks sebelum ditampilkan di HTML. */
function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function rupiah(int $angka): string
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function angka(int $n): string
{
    return number_format($n, 0, ',', '.');
}

function emoji_kategori(string $kategori): string
{
    return ['Fresh Good' => '🥬', 'Dry Good' => '🌾', 'Lainnya' => '📦'][$kategori] ?? '🛒';
}

/** 'habis' | 'menipis' | 'aman' */
function status_stok(int $stok): string
{
    if ($stok <= 0) {
        return 'habis';
    }
    return $stok <= LOW_STOCK ? 'menipis' : 'aman';
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

/** Halaman utama sesuai peran. */
function home_url(): string
{
    return ROOT . (is_admin() ? 'admin/index.php' : 'beranda.php');
}

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['flash'] = ['info', 'Silakan masuk terlebih dahulu.'];
        header('Location: ' . ROOT . 'login.php');
        exit;
    }
}

/** Halaman khusus customer; admin diarahkan ke dashboard. */
function require_customer(): void
{
    require_login();
    if (is_admin()) {
        header('Location: ' . home_url());
        exit;
    }
}

/** Halaman khusus admin; customer diarahkan ke beranda. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: ' . home_url());
        exit;
    }
}

function flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_valid(): bool
{
    return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
}

// ---- Foto produk ----
define('UPLOAD_DIR', dirname(__DIR__) . '/assets/uploads/');

/** Hapus file foto produk (hanya nama file hasil unggahan aplikasi ini). */
function hapus_file_foto(?string $nama): void
{
    if ($nama && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $nama) && is_file(UPLOAD_DIR . $nama)) {
        @unlink(UPLOAD_DIR . $nama);
    }
}


// ---- Aturan penjualan per kategori ----
// Fresh Good: hanya kg | Dry Good: kg atau satuan | Lainnya: hanya satuan

function jual_kg(array $p): bool
{
    return $p['kategori'] !== 'Lainnya';
}

function jual_satuan(array $p): bool
{
    return $p['kategori'] !== 'Fresh Good'
        && ($p['satuan'] ?? '') !== '' && $p['harga_satuan'] !== null
        && ($p['kategori'] === 'Lainnya' || (int) $p['isi_kg'] > 0);
}

function mode_valid(array $p, string $mode): bool
{
    return ($mode === 'kg' && jual_kg($p)) || ($mode === 'satuan' && jual_satuan($p));
}

/** Nama satuan stok: 'kg' (Fresh/Dry) atau nama satuan (Lainnya). */
function unit_stok(array $p): string
{
    return $p['kategori'] === 'Lainnya' ? (string) $p['satuan'] : 'kg';
}

function stok_teks(array $p): string
{
    return angka((int) $p['stok']) . ' ' . unit_stok($p);
}

function harga_utama(array $p): string
{
    return jual_kg($p)
        ? rupiah((int) $p['harga']) . ' / kg'
        : rupiah((int) $p['harga_satuan']) . ' / ' . $p['satuan'];
}

/** Harga alternatif per satuan untuk Dry Good, mis. "Rp 365.000 / karung (isi 25 kg)". */
function harga_alt(array $p): ?string
{
    if ($p['kategori'] !== 'Dry Good' || !jual_satuan($p)) {
        return null;
    }
    return rupiah((int) $p['harga_satuan']) . ' / ' . $p['satuan'] . ' (isi ' . angka((int) $p['isi_kg']) . ' kg)';
}

function harga_mode(array $p, string $mode): int
{
    return $mode === 'kg' ? (int) $p['harga'] : (int) $p['harga_satuan'];
}

function label_mode(array $p, string $mode): string
{
    return $mode === 'kg' ? 'kg' : (string) $p['satuan'];
}

/** Stok yang terpakai (dalam satuan stok) untuk membeli $qty dengan $mode. */
function kebutuhan_stok(array $p, string $mode, int $qty): int
{
    if ($p['kategori'] === 'Lainnya') {
        return $qty;
    }
    return $mode === 'satuan' ? $qty * (int) $p['isi_kg'] : $qty;
}

/** Jumlah maksimal yang bisa dibeli dengan $mode dari stok saat ini. */
function maks_beli(array $p, string $mode): int
{
    $stok = (int) $p['stok'];
    if ($p['kategori'] === 'Lainnya') {
        return $mode === 'satuan' ? $stok : 0;
    }
    if ($mode === 'kg') {
        return $stok;
    }
    return (int) $p['isi_kg'] > 0 ? intdiv($stok, (int) $p['isi_kg']) : 0;
}

function no_pesanan(int $id): string
{
    return '#' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}
