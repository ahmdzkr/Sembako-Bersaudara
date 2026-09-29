<?php
/**
 * Integrasi Midtrans Snap (pembayaran invoice lewat pop-up bawaan Midtrans).
 *
 * Alur singkat:
 *   1. invoice.php  -> tombol "Bayar sekarang" memanggil payment/token.php lewat JavaScript.
 *   2. token.php    -> midtrans_buat_token(): Snap token dibuat dari data pesanan di DATABASE
 *                      (jumlah tagihan tidak pernah diambil dari browser).
 *   3. snap.js      -> window.snap.pay(token) membuka pop-up pembayaran Midtrans.
 *   4. Status pembayaran DIPASTIKAN dari sisi server, bukan dari callback JavaScript:
 *        - payment/notification.php : webhook otomatis dari Midtrans (butuh URL publik)
 *        - tombol "Cek status"      : midtrans_sinkron_status() bertanya ke Midtrans (Get Status API)
 *      keduanya berujung di midtrans_terapkan_status().
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/../payment/midtrans-php-master/Midtrans.php';

/** Sesi Snap yang lebih tua dari ini tidak dipakai ulang (Snap token punya masa berlaku). */
const MIDTRANS_TOKEN_UMUR_MAKS_DETIK = 23 * 3600;

function midtrans_terkonfigurasi(): bool
{
    return MIDTRANS_SERVER_KEY !== '' && MIDTRANS_CLIENT_KEY !== '';
}

function midtrans_init(): void
{
    \Midtrans\Config::$serverKey    = MIDTRANS_SERVER_KEY;
    \Midtrans\Config::$isProduction = MIDTRANS_IS_PRODUCTION;
    \Midtrans\Config::$isSanitized  = true;
    \Midtrans\Config::$is3ds        = true;
}

function midtrans_snap_js_url(): string
{
    return MIDTRANS_IS_PRODUCTION
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js';
}

/** Balasan JSON untuk endpoint di folder payment/ (langsung menghentikan skrip). */
function json_balas(int $kode, array $data): void
{
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Buat Snap transaction token untuk sebuah invoice (atau pakai ulang sesi yang masih berlaku).
 * Mengembalikan [true, ['token' => ..., 'order_id' => ...]] atau [false, pesan_untuk_customer].
 */
function midtrans_buat_token(int $orderId, bool $paksaBaru = false): array
{
    if (!midtrans_terkonfigurasi()) {
        return [false, 'Pembayaran online belum dikonfigurasi. Hubungi admin toko.'];
    }
    $order = order_get($orderId);
    if (!$order) {
        return [false, 'Invoice tidak ditemukan.'];
    }
    if ($order['status'] !== 'menunggu_pembayaran') {
        return [false, 'Invoice ini tidak sedang menunggu pembayaran.'];
    }
    if ($order['midtrans_status'] === 'challenge') {
        return [false, 'Pembayaran Anda sedang ditinjau oleh Midtrans. Silakan cek status beberapa saat lagi.'];
    }

    // Pakai ulang sesi yang masih berlaku (mis. customer sudah memilih transfer VA lalu menutup
    // pop-up), supaya satu invoice tidak menumpuk banyak transaksi pending.
    if (!$paksaBaru && $order['midtrans_snap_token'] && in_array($order['midtrans_status'], [null, 'pending'], true)) {
        $q = db()->prepare('SELECT TIMESTAMPDIFF(SECOND, midtrans_token_at, NOW()) FROM orders WHERE id = ?');
        $q->execute([$orderId]);
        $umur = $q->fetchColumn();
        if ($umur !== false && $umur !== null && (int) $umur >= 0 && (int) $umur < MIDTRANS_TOKEN_UMUR_MAKS_DETIK) {
            return [true, ['token' => $order['midtrans_snap_token'], 'order_id' => $order['midtrans_order_id']]];
        }
    }

    $q = db()->prepare('SELECT nama, email, nama_toko, no_whatsapp FROM users WHERE id = ?');
    $q->execute([$order['user_id']]);
    $u = $q->fetch() ?: ['nama' => $order['nama_penerima'], 'email' => '', 'nama_toko' => '', 'no_whatsapp' => ''];

    // order_id ke Midtrans harus unik tiap percobaan bayar: timestamp + 2 digit acak
    // (agar dua permintaan dalam detik yang sama tidak bertabrakan). Polanya PO-<id>-<angka>.
    $midOrderId = sprintf('PO-%05d-%s%02d', $orderId, time(), random_int(0, 99));

    $params = [
        'transaction_details' => [
            'order_id'     => $midOrderId,
            'gross_amount' => (int) $order['total'],   // dari database, bukan dari browser
        ],
        'customer_details' => [
            'first_name' => $u['nama_toko'] ?: $u['nama'],
            'last_name'  => $u['nama_toko'] ? $u['nama'] : '',
            'email'      => $u['email'],
            'phone'      => $u['no_whatsapp'] ?: $order['telepon'],
        ],
    ];

    // Rincian barang ikut dikirim agar tampil di halaman pembayaran, tetapi hanya bila
    // jumlahnya persis sama dengan total (Midtrans menolak jika tidak sama).
    $rincian = [];
    $jumlah  = 0;
    foreach (order_items_get($orderId) as $it) {
        $rincian[] = [
            'id'       => (string) ($it['product_id'] ?? 'item-' . $it['id']),
            'price'    => (int) $it['harga'],
            'quantity' => (int) $it['qty'],
            'name'     => mb_strcut($it['nama_produk'] . ' (' . $it['satuan'] . ')', 0, 50, 'UTF-8'),
        ];
        $jumlah += (int) $it['harga'] * (int) $it['qty'];
    }
    if ($rincian && $jumlah === (int) $order['total']) {
        $params['item_details'] = $rincian;
    }

    try {
        midtrans_init();
        $token = \Midtrans\Snap::getSnapToken($params);
    } catch (\Throwable $e) {
        error_log('[midtrans] gagal membuat Snap token untuk PO ' . $orderId . ': ' . $e->getMessage());
        return [false, 'Sesi pembayaran belum bisa dibuat. Silakan coba lagi beberapa saat lagi.'];
    }

    $q = db()->prepare('UPDATE orders SET midtrans_order_id = ?, midtrans_snap_token = ?, midtrans_token_at = NOW(), midtrans_status = NULL WHERE id = ?');
    $q->execute([$midOrderId, $token, $orderId]);

    return [true, ['token' => $token, 'order_id' => $midOrderId]];
}

/** Tanya status terbaru ke Midtrans (Get Status API) lalu terapkan ke pesanan. Dipakai tombol "Cek status". */
function midtrans_sinkron_status(int $orderId): array
{
    if (!midtrans_terkonfigurasi()) {
        return [false, 'Pembayaran online belum dikonfigurasi.'];
    }
    $order = order_get($orderId);
    if (!$order) {
        return [false, 'Pesanan tidak ditemukan.'];
    }
    if ($order['status'] === 'selesai') {
        return [true, 'Invoice ini sudah tercatat lunas.'];
    }
    if (!$order['midtrans_order_id']) {
        return [true, 'Belum ada pembayaran yang dimulai untuk invoice ini. Klik "Bayar sekarang" untuk memulai.'];
    }

    try {
        midtrans_init();
        $status = \Midtrans\Transaction::status($order['midtrans_order_id']);
    } catch (\Throwable $e) {
        if ((int) $e->getCode() === 404) {
            // Transaksi belum ada di Midtrans = customer belum memilih metode pembayaran di pop-up.
            return [true, 'Belum ada pembayaran yang tercatat di Midtrans. Selesaikan dulu lewat tombol pembayaran.'];
        }
        error_log('[midtrans] gagal cek status PO ' . $orderId . ': ' . $e->getMessage());
        return [false, 'Belum bisa menghubungi Midtrans. Silakan coba lagi beberapa saat lagi.'];
    }
    return midtrans_terapkan_status($status);
}

/** Nama metode pembayaran yang ramah dibaca, dari respons status Midtrans. */
function midtrans_label_metode(object $s): string
{
    $tipe = (string) ($s->payment_type ?? '');
    switch ($tipe) {
        case 'credit_card':
            return 'Kartu kredit/debit';
        case 'bank_transfer':
            $bank = '';
            if (!empty($s->va_numbers) && is_array($s->va_numbers) && !empty($s->va_numbers[0]->bank)) {
                $bank = strtoupper((string) $s->va_numbers[0]->bank);
            } elseif (!empty($s->permata_va_number)) {
                $bank = 'PERMATA';
            }
            return 'Transfer bank (VA)' . ($bank !== '' ? ' ' . $bank : '');
        case 'echannel':
            return 'Mandiri Bill';
        case 'gopay':
            return 'GoPay';
        case 'qris':
            return 'QRIS';
        case 'shopeepay':
            return 'ShopeePay';
        case 'cstore':
            return 'Minimarket' . (!empty($s->store) ? ' ' . ucfirst((string) $s->store) : '');
        case 'akulaku':
            return 'Akulaku';
        default:
            return $tipe !== '' ? mb_strcut($tipe, 0, 50, 'UTF-8') : 'Midtrans';
    }
}

/**
 * Terapkan hasil status transaksi Midtrans ke pesanan. Dipakai oleh webhook maupun tombol "Cek status".
 * Aman dipanggil berulang untuk data yang sama (idempoten). $s = objek respons Get Status API.
 */
function midtrans_terapkan_status(object $s): array
{
    $midOrderId = (string) ($s->order_id ?? '');
    if (!preg_match('/^PO-(\d+)-\d+$/', $midOrderId, $m)) {
        return [false, 'order_id Midtrans tidak dikenali.'];
    }
    $orderId = (int) $m[1];
    $order   = order_get($orderId);
    if (!$order) {
        return [false, 'Pesanan tidak ditemukan.'];
    }

    // Jumlah yang tercatat di Midtrans harus persis sama dengan tagihan kita.
    $jumlahMidtrans = (int) round((float) ($s->gross_amount ?? 0));
    if ($jumlahMidtrans !== (int) $order['total']) {
        error_log('[midtrans] jumlah tidak cocok untuk PO ' . $orderId . ': midtrans=' . $jumlahMidtrans . ' tagihan=' . $order['total']);
        return [false, 'Jumlah pembayaran tidak sesuai dengan tagihan. Hubungi admin toko.'];
    }

    $trx   = (string) ($s->transaction_status ?? '');
    $fraud = (string) ($s->fraud_status ?? '');
    $lunas = $trx === 'settlement' || ($trx === 'capture' && $fraud === 'accept');
    $statusSimpan = ($trx === 'capture' && $fraud === 'challenge') ? 'challenge' : $trx;

    if ($lunas) {
        // Berlaku untuk percobaan bayar mana pun (bukan hanya yang terbaru), karena customer bisa
        // saja membayar VA dari sesi lama. "AND status" menjaga agar tidak diproses dua kali.
        $q = db()->prepare(
            "UPDATE orders SET status = 'selesai', dibayar_at = NOW(), metode_bayar = ?,
                    midtrans_order_id = ?, midtrans_status = ?
             WHERE id = ? AND status = 'menunggu_pembayaran'"
        );
        $q->execute([midtrans_label_metode($s), $midOrderId, $statusSimpan, $orderId]);
        if ($q->rowCount() === 1) {
            return [true, 'Pembayaran ' . no_pesanan($orderId) . ' terkonfirmasi lunas. Terima kasih!'];
        }
        return [true, 'Invoice ini sudah tercatat lunas.'];
    }

    // Status belum lunas hanya dicatat untuk percobaan bayar yang sedang aktif.
    if ($order['status'] === 'menunggu_pembayaran' && $order['midtrans_order_id'] === $midOrderId) {
        db()->prepare('UPDATE orders SET midtrans_status = ? WHERE id = ?')->execute([$statusSimpan, $orderId]);
    }

    switch ($statusSimpan) {
        case 'pending':
            return [true, 'Pembayaran belum diterima. Selesaikan pembayaran sesuai instruksi (mis. transfer ke nomor VA), lalu cek status lagi.'];
        case 'expire':
            return [true, 'Sesi pembayaran sudah kedaluwarsa. Klik "Bayar sekarang" untuk membuat yang baru.'];
        case 'cancel':
        case 'deny':
        case 'failure':
            return [true, 'Pembayaran tidak berhasil. Anda bisa mencoba lagi lewat tombol "Bayar sekarang".'];
        case 'challenge':
            return [true, 'Pembayaran sedang ditinjau oleh Midtrans. Silakan cek status beberapa saat lagi.'];
        default:
            return [true, 'Status pembayaran di Midtrans: ' . $statusSimpan . '.'];
    }
}
