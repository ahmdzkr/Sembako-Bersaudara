<?php
/** Fungsi bantu untuk alur PO: ambil data pesanan, dan transisi antar status. */

function order_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $o = $stmt->fetch();
    return $o ?: null;
}

function order_items_get(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

/** Pesanan boleh dilihat oleh pemiliknya sendiri, atau oleh admin/kasir. */
function order_boleh_dilihat(array $order): bool
{
    return is_admin() || is_kasir() || ((int) $order['user_id'] === (int) ($_SESSION['user_id'] ?? 0));
}

/**
 * Kasir menyetujui PO: stok dikurangi di titik ini (bukan saat checkout), lalu
 * status berubah jadi 'siap_diproses'. Mengembalikan [berhasil, pesan].
 */
function po_approve(int $orderId, int $kasirId): array
{
    $order = order_get($orderId);
    if (!$order) {
        return [false, 'Pesanan tidak ditemukan.'];
    }
    if ($order['status'] !== 'menunggu_verifikasi') {
        return [false, 'Pesanan ini sudah diproses sebelumnya.'];
    }

    $items = order_items_get($orderId);
    if (!$items) {
        return [false, 'Pesanan ini tidak punya rincian barang, tidak bisa diverifikasi.'];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        foreach ($items as $it) {
            if ($it['product_id'] === null) {
                throw new RuntimeException('Produk "' . $it['nama_produk'] . '" sudah dihapus dari katalog, PO tidak bisa diproses.');
            }
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$it['product_id']]);
            $p = $stmt->fetch();
            if (!$p) {
                throw new RuntimeException('Produk "' . $it['nama_produk'] . '" sudah dihapus dari katalog, PO tidak bisa diproses.');
            }

            $butuh = kebutuhan_stok($p, $it['mode'], (int) $it['qty']);
            $q = $pdo->prepare('UPDATE products SET stok = stok - ? WHERE id = ? AND stok >= ?');
            $q->execute([$butuh, $p['id'], $butuh]);
            if ($q->rowCount() !== 1) {
                throw new RuntimeException('Stok "' . $it['nama_produk'] . '" tidak lagi mencukupi (sisa ' . stok_teks($p) . '). PO tidak bisa disetujui.');
            }
        }

        $q = $pdo->prepare("UPDATE orders SET status = 'siap_diproses', diverifikasi_oleh = ?, verifikasi_at = NOW() WHERE id = ?");
        $q->execute([$kasirId, $orderId]);

        $pdo->commit();
        return [true, 'PO ' . no_pesanan($orderId) . ' disetujui. Stok sudah dikurangi dan pesanan siap diproses admin.'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return [false, $e instanceof RuntimeException ? $e->getMessage() : 'PO gagal disetujui. Coba lagi.'];
    }
}

/** Kasir menolak PO (mis. dokumen/keuangan tidak valid). Stok tidak tersentuh. */
function po_reject(int $orderId, int $kasirId, string $alasan): array
{
    $order = order_get($orderId);
    if (!$order) {
        return [false, 'Pesanan tidak ditemukan.'];
    }
    if ($order['status'] !== 'menunggu_verifikasi') {
        return [false, 'Pesanan ini sudah diproses sebelumnya.'];
    }

    $q = db()->prepare("UPDATE orders SET status = 'dibatalkan', diverifikasi_oleh = ?, verifikasi_at = NOW(), catatan_kasir = ? WHERE id = ?");
    $q->execute([$kasirId, $alasan !== '' ? $alasan : null, $orderId]);
    return [true, 'PO ' . no_pesanan($orderId) . ' ditolak.'];
}

/** Admin menandai barang sudah disiapkan & siap dikirim (boleh sertakan no. resi). */
function order_tandai_siap_kirim(int $orderId, ?string $noResi): array
{
    $order = order_get($orderId);
    if (!$order || $order['status'] !== 'siap_diproses') {
        return [false, 'Pesanan tidak dalam status yang bisa ditandai siap kirim.'];
    }
    $q = db()->prepare('UPDATE orders SET status = ?, no_resi = ? WHERE id = ?');
    $q->execute(['siap_kirim', $noResi !== '' ? $noResi : null, $orderId]);
    return [true, 'Pesanan ' . no_pesanan($orderId) . ' ditandai siap kirim.'];
}

/** Admin menandai barang sudah berangkat/dalam perjalanan. */
function order_tandai_dikirim(int $orderId): array
{
    $order = order_get($orderId);
    if (!$order || $order['status'] !== 'siap_kirim') {
        return [false, 'Pesanan tidak dalam status yang bisa ditandai dikirim.'];
    }
    $q = db()->prepare("UPDATE orders SET status = 'dikirim', dikirim_at = NOW() WHERE id = ?");
    $q->execute([$orderId]);
    return [true, 'Pesanan ' . no_pesanan($orderId) . ' ditandai dikirim.'];
}

/** Customer mengonfirmasi barang sudah diterima & sesuai. Invoice otomatis bisa diterbitkan setelah ini. */
function order_konfirmasi_diterima(int $orderId, int $userId): array
{
    $order = order_get($orderId);
    if (!$order || (int) $order['user_id'] !== $userId) {
        return [false, 'Pesanan tidak ditemukan.'];
    }
    if ($order['status'] !== 'dikirim') {
        return [false, 'Pesanan ini belum berstatus dikirim.'];
    }
    $q = db()->prepare("UPDATE orders SET status = 'diterima', diterima_at = NOW() WHERE id = ?");
    $q->execute([$orderId]);
    return [true, 'Terima kasih, barang pesanan ' . no_pesanan($orderId) . ' dikonfirmasi diterima. Invoice sudah bisa dilihat.'];
}
