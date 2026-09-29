<?php
/**
 * Webhook "Payment Notification" dari Midtrans. Daftarkan URL ini di
 * Midtrans Dashboard > Settings > Configuration > Payment Notification URL:
 *
 *     https://domain-anda.com/payment/notification.php
 *
 * TIDAK memakai login/CSRF karena dipanggil server Midtrans. Isi notifikasi
 * tidak dipercaya begitu saja: kita hanya mengambil order_id-nya, lalu
 * menanyakan status sebenarnya ke Midtrans (Get Status API, memakai Server
 * Key kita). Dengan begitu notifikasi palsu tidak bisa melunasi invoice.
 *
 * Alamat ini harus bisa dijangkau dari internet. Di localhost/XAMPP notifikasi
 * tidak akan sampai, gunakan tombol "Cek status" di halaman invoice sebagai gantinya.
 */

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/midtrans.php';

$data = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($data) || empty($data['order_id']) || !is_string($data['order_id'])) {
    json_balas(400, ['message' => 'Payload tidak valid.']);
}

// Hanya order_id buatan sistem ini (PO-<id>-<timestamp>). Notifikasi uji dari dashboard
// Midtrans (order_id contoh) cukup dijawab 200 supaya tidak dikirim ulang terus-menerus.
if (!preg_match('/^PO-\d+-\d+$/', $data['order_id'])) {
    json_balas(200, ['message' => 'order_id bukan dari sistem ini, diabaikan.']);
}

try {
    midtrans_init();
    $status = \Midtrans\Transaction::status($data['order_id']);
    [$ok, $pesan] = midtrans_terapkan_status($status);
} catch (\Throwable $e) {
    if ((int) $e->getCode() === 404) {
        json_balas(200, ['message' => 'Transaksi tidak ditemukan di Midtrans, diabaikan.']);
    }
    error_log('[midtrans] notifikasi gagal diproses: ' . $e->getMessage());
    json_balas(500, ['message' => 'Gagal memproses notifikasi.']); // 5xx: Midtrans akan mencoba mengirim ulang
}

if (!$ok) {
    error_log('[midtrans] notifikasi ditolak: ' . $pesan);
}
json_balas(200, ['message' => $pesan]);
