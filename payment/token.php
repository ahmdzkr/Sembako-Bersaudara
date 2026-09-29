<?php
/**
 * Endpoint JSON: membuat Snap transaction token untuk satu invoice.
 * Dipanggil lewat fetch() dari tombol "Bayar sekarang" di invoice.php.
 *
 * Berbeda dari contoh awal (yang menerima jumlah & data customer langsung dari
 * browser), di sini jumlah tagihan dan data customer SELALU diambil dari
 * database berdasarkan order_id, dan hanya pemilik invoice yang boleh
 * membuat sesi pembayarannya. Kalau tidak begitu, siapa pun bisa mengubah
 * jumlah bayar lewat DevTools.
 *
 * Request  : POST  csrf, order_id, [baru=1 untuk memaksa sesi baru]
 * Response : {"ok":true,"token":"...","order_id":"PO-00005-..."}  atau  {"ok":false,"pesan":"..."}
 */

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/midtrans.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_balas(405, ['ok' => false, 'pesan' => 'Metode tidak diizinkan.']);
}
if (!is_logged_in()) {
    json_balas(401, ['ok' => false, 'pesan' => 'Sesi login Anda sudah berakhir. Silakan masuk kembali.']);
}
if (!csrf_valid()) {
    json_balas(400, ['ok' => false, 'pesan' => 'Sesi formulir sudah berakhir. Muat ulang halaman lalu coba lagi.']);
}

$orderId = (int) ($_POST['order_id'] ?? 0);
$order   = order_get($orderId);
if (!$order || (int) $order['user_id'] !== (int) $_SESSION['user_id']) {
    json_balas(404, ['ok' => false, 'pesan' => 'Invoice tidak ditemukan.']);
}

[$ok, $hasil] = midtrans_buat_token($orderId, !empty($_POST['baru']));
if (!$ok) {
    json_balas(422, ['ok' => false, 'pesan' => $hasil]);
}
json_balas(200, ['ok' => true, 'token' => $hasil['token'], 'order_id' => $hasil['order_id']]);
