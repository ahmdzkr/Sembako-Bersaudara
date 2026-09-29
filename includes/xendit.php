<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/orders.php';

/** Panggilan generik ke Xendit API. Mengembalikan [berhasil, data_json_sebagai_array]. */
function xendit_request(string $method, string $path, array $body = []): array
{
    $ch = curl_init('https://api.xendit.co' . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERPWD        => XENDIT_SECRET_KEY . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $raw      = curl_exec($ch);
    $errno    = curl_errno($ch);
    $errmsg   = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return [false, ['message' => 'Tidak bisa menghubungi Xendit: ' . $errmsg]];
    }
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        $data = [];
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        return [true, $data];
    }
    return [false, $data ?: ['message' => 'Xendit membalas dengan kode ' . $httpCode]];
}

/**
 * Buat invoice Xendit baru untuk sebuah pesanan dan simpan hasilnya ke tabel orders.
 * Mengembalikan [berhasil, invoice_url_atau_pesan_error].
 */
function xendit_buat_invoice(int $orderId): array
{
    if (!xendit_terkonfigurasi()) {
        return [false, 'Pembayaran online belum dikonfigurasi. Hubungi admin toko.'];
    }

    $order = order_get($orderId);
    if (!$order) {
        return [false, 'Pesanan tidak ditemukan.'];
    }
    $items = order_items_get($orderId);

    $stmt = db()->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$order['user_id']]);
    $email = (string) $stmt->fetchColumn() ?: 'customer@sembako-bersaudara.test';

    $lineItems = [];
    foreach ($items as $it) {
        $lineItems[] = [
            'name'     => mb_substr($it['nama_produk'], 0, 256),
            'quantity' => (int) $it['qty'],
            'price'    => (int) $it['harga'],
            'category' => 'Sembako',
        ];
    }

    // external_id harus unik tiap kali dibuat (dipakai lagi kalau invoice lama kedaluwarsa).
    $externalId = 'po-' . $orderId . '-' . time();

    [$ok, $data] = xendit_request('POST', '/v2/invoices', [
        'external_id'          => $externalId,
        'amount'               => (int) $order['total'],
        'description'          => 'Pembayaran ' . no_pesanan($orderId) . ' - Sembako Bersaudara',
        'invoice_duration'     => 3 * 24 * 3600, // berlaku 3 hari sebelum kedaluwarsa
        'payer_email'          => $email,
        'success_redirect_url' => rtrim(APP_BASE_URL, '/') . '/invoice.php?id=' . $orderId,
        'failure_redirect_url' => rtrim(APP_BASE_URL, '/') . '/invoice.php?id=' . $orderId,
        'currency'             => 'IDR',
        'items'                => $lineItems,
    ]);

    if (!$ok) {
        return [false, $data['message'] ?? 'Gagal membuat tagihan Xendit.'];
    }
    if (empty($data['invoice_url']) || empty($data['id'])) {
        return [false, 'Xendit tidak mengembalikan data invoice yang lengkap.'];
    }

    $q = db()->prepare('UPDATE orders SET xendit_invoice_id=?, xendit_external_id=?, xendit_invoice_url=?, xendit_status=? WHERE id=?');
    $q->execute([$data['id'], $externalId, $data['invoice_url'], $data['status'] ?? 'PENDING', $orderId]);

    return [true, $data['invoice_url']];
}

/** Tarik status terbaru langsung dari Xendit (dipakai tombol "Cek status pembayaran"). */
function xendit_sinkron_status(int $orderId): array
{
    $order = order_get($orderId);
    if (!$order || !$order['xendit_invoice_id']) {
        return [false, 'Belum ada tagihan Xendit untuk pesanan ini.'];
    }
    [$ok, $data] = xendit_request('GET', '/v2/invoices/' . $order['xendit_invoice_id']);
    if (!$ok) {
        return [false, $data['message'] ?? 'Gagal menghubungi Xendit.'];
    }
    $metode = $data['payment_method'] ?? ($data['payment_channel'] ?? null);
    return xendit_terapkan_status($orderId, (string) ($data['status'] ?? ''), $metode);
}

/**
 * Terapkan status dari Xendit ke pesanan kita. Dipakai baik oleh webhook
 * (otomatis, real-time) maupun tombol sinkronisasi manual (fallback).
 * Aman dipanggil berkali-kali dengan status yang sama (idempoten).
 */
function xendit_terapkan_status(int $orderId, string $statusXendit, ?string $metodeBayar): array
{
    $order = order_get($orderId);
    if (!$order) {
        return [false, 'Pesanan tidak ditemukan.'];
    }

    db()->prepare('UPDATE orders SET xendit_status = ? WHERE id = ?')->execute([$statusXendit, $orderId]);

    if (in_array($statusXendit, ['PAID', 'SETTLED'], true)) {
        if ($order['status'] === 'menunggu_pembayaran') {
            $q = db()->prepare("UPDATE orders SET status = 'selesai', dibayar_at = NOW(), metode_bayar = ? WHERE id = ?");
            $q->execute([$metodeBayar, $orderId]);
        }
        return [true, 'Pembayaran ' . no_pesanan($orderId) . ' terkonfirmasi lunas.'];
    }
    if ($statusXendit === 'EXPIRED') {
        return [true, 'Tagihan Xendit untuk ' . no_pesanan($orderId) . ' sudah kedaluwarsa.'];
    }
    return [true, 'Status Xendit saat ini: ' . $statusXendit . '.'];
}

/** Staf menandai lunas secara manual (mis. customer transfer langsung di luar Xendit). */
function order_tandai_lunas_manual(int $orderId): array
{
    $order = order_get($orderId);
    if (!$order || $order['status'] !== 'menunggu_pembayaran') {
        return [false, 'Pesanan ini bukan berstatus menunggu pembayaran.'];
    }
    $q = db()->prepare("UPDATE orders SET status = 'selesai', dibayar_at = NOW(), metode_bayar = 'Manual oleh staf' WHERE id = ?");
    $q->execute([$orderId]);
    return [true, 'Pesanan ' . no_pesanan($orderId) . ' ditandai lunas secara manual.'];
}
