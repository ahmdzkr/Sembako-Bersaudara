<?php
// Pengaturan koneksi database (sesuaikan dengan XAMPP Anda)
const DB_HOST = '127.0.0.1';
const DB_NAME = 'sembako';
const DB_USER = 'root';
const DB_PASS = '';          // XAMPP default: kosong

const LOW_STOCK = 100;       // stok (dalam satuan_dasar, atau satuan) <= angka ini dianggap menipis

// Kategori produk (tetap 3). Urutan ini dipakai di seluruh halaman.
const KATEGORI  = ['Fresh Good', 'Dry Good', 'Lainnya'];

// Pilihan basis berat/volume untuk Dry Good (Fresh Good selalu 'kg', Lainnya selalu tanpa basis).
const SATUAN_DASAR_PILIHAN = ['kg' => 'Kilogram (kg)', 'liter' => 'Liter'];

// Alur status PO (Purchase Order), berurutan sesuai tahapan bisnis.
// label = teks yang tampil ke pengguna, kelas = akhiran class CSS badge (.badge.status-<kelas>).
const STATUS_PESANAN = [
    'menunggu_verifikasi' => 'Menunggu verifikasi',
    'siap_diproses'       => 'Siap diproses',
    'siap_kirim'          => 'Siap kirim',
    'dikirim'             => 'Dikirim',
    'diterima'            => 'Diterima',
    'dibatalkan'          => 'Dibatalkan',
];

function label_status(string $status): string
{
    return STATUS_PESANAN[$status] ?? $status;
}

session_start();

// Awalan URL relatif: halaman di folder admin/ atau kasir/ perlu naik satu tingkat.
define('ROOT', in_array(basename(dirname($_SERVER['SCRIPT_FILENAME'])), ['admin', 'kasir'], true) ? '../' : '');

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

function is_kasir(): bool
{
    return ($_SESSION['role'] ?? '') === 'kasir';
}

/** Halaman utama sesuai peran. */
function home_url(): string
{
    if (is_admin()) {
        return ROOT . 'admin/index.php';
    }
    if (is_kasir()) {
        return ROOT . 'kasir/index.php';
    }
    return ROOT . 'beranda.php';
}

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['flash'] = ['info', 'Silakan masuk terlebih dahulu.'];
        header('Location: ' . ROOT . 'login.php');
        exit;
    }
}

/** Halaman khusus customer; admin/kasir diarahkan ke halaman kerja masing-masing. */
function require_customer(): void
{
    require_login();
    if (is_admin() || is_kasir()) {
        header('Location: ' . home_url());
        exit;
    }
}

/** Halaman khusus admin; peran lain diarahkan ke halaman masing-masing. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: ' . home_url());
        exit;
    }
}

/** Halaman khusus kasir; peran lain diarahkan ke halaman masing-masing. */
function require_kasir(): void
{
    require_login();
    if (!is_kasir()) {
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


// ---- Aturan penjualan produk ----
// Ditentukan dari data produk, bukan cuma nama kategori, supaya Dry Good bisa
// beragam: basis kg (beras), basis liter (minyak), atau tanpa basis sama
// sekali dan hanya dijual per kemasan (kopi sachet, susu kaleng).

/** Produk ini bisa dibeli berdasarkan satuan dasarnya (kg atau liter)? */
function jual_dasar(array $p): bool
{
    return ($p['satuan_dasar'] ?? '') !== '' && $p['satuan_dasar'] !== null;
}

/** Produk ini bisa dibeli per kemasan (karung, jerigen, sachet, kaleng, ...)? */
function jual_satuan(array $p): bool
{
    return ($p['satuan'] ?? '') !== '' && $p['satuan'] !== null && $p['harga_satuan'] !== null;
}

function mode_valid(array $p, string $mode): bool
{
    return ($mode === 'dasar' && jual_dasar($p)) || ($mode === 'satuan' && jual_satuan($p));
}

/** Nama satuan stok: satuan_dasar (kg/liter) jika ada, atau nama kemasan (mis. Lainnya, atau Dry Good tanpa basis). */
function unit_stok(array $p): string
{
    return jual_dasar($p) ? (string) $p['satuan_dasar'] : (string) $p['satuan'];
}

function stok_teks(array $p): string
{
    return angka((int) $p['stok']) . ' ' . unit_stok($p);
}

/** Harga utama yang tampil di kartu/detail: per satuan dasar jika ada, kalau tidak per kemasan. */
function harga_utama(array $p): string
{
    return jual_dasar($p)
        ? rupiah((int) $p['harga']) . ' / ' . $p['satuan_dasar']
        : rupiah((int) $p['harga_satuan']) . ' / ' . $p['satuan'];
}

/** Harga alternatif per kemasan, mis. "Rp 365.000 / karung (isi 25 kg)". Hanya ada jika produk punya KEDUANYA. */
function harga_alt(array $p): ?string
{
    if (!jual_dasar($p) || !jual_satuan($p)) {
        return null;
    }
    return rupiah((int) $p['harga_satuan']) . ' / ' . $p['satuan'] . ' (isi ' . angka((int) $p['isi_dasar']) . ' ' . $p['satuan_dasar'] . ')';
}

function harga_mode(array $p, string $mode): int
{
    return $mode === 'dasar' ? (int) $p['harga'] : (int) $p['harga_satuan'];
}

function label_mode(array $p, string $mode): string
{
    return $mode === 'dasar' ? (string) $p['satuan_dasar'] : (string) $p['satuan'];
}

/** Stok (dalam satuan_dasar, atau jumlah kemasan bila tanpa basis) yang terpakai untuk membeli $qty dengan $mode. */
function kebutuhan_stok(array $p, string $mode, int $qty): int
{
    if (!jual_dasar($p)) {
        return $qty; // stok memang disimpan dalam jumlah kemasan
    }
    return $mode === 'satuan' ? $qty * (int) $p['isi_dasar'] : $qty;
}

/** Jumlah maksimal yang bisa dibeli dengan $mode dari stok saat ini. */
function maks_beli(array $p, string $mode): int
{
    $stok = (int) $p['stok'];
    if (!jual_dasar($p)) {
        return $mode === 'satuan' ? $stok : 0;
    }
    if ($mode === 'dasar') {
        return $stok;
    }
    return (int) $p['isi_dasar'] > 0 ? intdiv($stok, (int) $p['isi_dasar']) : 0;
}

function no_pesanan(int $id): string
{
    return '#' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}
