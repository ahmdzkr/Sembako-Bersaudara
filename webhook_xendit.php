<?php
/**
 * Endpoint publik yang dipanggil Xendit setiap ada perubahan status invoice
 * (terutama saat lunas). URL ini yang didaftarkan di Xendit Dashboard >
 * Settings > Webhooks > Invoice Paid Callback, mis.:
 *   https://domain-anda.com/webhook_xendit.php
 *
 * TIDAK memakai require_login() — ini dipanggil server Xendit, bukan browser
 * orang yang login. Keamanannya lewat header X-CALLBACK-TOKEN, bukan sesi.
 */

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/xendit.php';

header('Content-Type: application/json');

$tokenDiterima = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';
if (!hash_equals(XENDIT_CALLBACK_TOKEN, $tokenDiterima)) {
    http_response_code(401);
    echo json_encode(['message' => 'Token verifikasi tidak valid.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['message' => 'Payload tidak valid.']);
    exit;
}

$externalId = (string) ($payload['external_id'] ?? '');
$status     = (string) ($payload['status'] ?? '');
$metode     = $payload['payment_method'] ?? ($payload['payment_channel'] ?? null);

// external_id kita berformat "po-<id_pesanan>-<timestamp>".
if (!preg_match('/^po-(\d+)-/', $externalId, $m)) {
    http_response_code(200); // tetap 200 supaya Xendit tidak mengulang kirim; ini bukan invoice dari sistem kita
    echo json_encode(['message' => 'external_id tidak dikenali, diabaikan.']);
    exit;
}

$orderId = (int) $m[1];
[$ok, $pesan] = xendit_terapkan_status($orderId, $status, $metode);

http_response_code($ok ? 200 : 500);
echo json_encode(['message' => $pesan]);
