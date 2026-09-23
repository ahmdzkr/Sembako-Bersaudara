<?php
/** Keranjang belanja: disimpan di session, per customer yang sedang login. */

function cart_key(int $productId, string $mode): string
{
    return $productId . ':' . $mode;
}

function cart_count(): int
{
    $n = 0;
    foreach (($_SESSION['cart'] ?? []) as $it) {
        $n += (int) $it['qty'];
    }
    return $n;
}

/** Tambah produk ke keranjang. Mengembalikan [berhasil, pesan]. */
function cart_add(int $productId, string $mode, int $qty): array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $p = $stmt->fetch();

    if (!$p) {
        return [false, 'Produk tidak ditemukan.'];
    }
    if (!mode_valid($p, $mode)) {
        return [false, 'Cara beli itu tidak tersedia untuk produk ini.'];
    }
    if ($qty < 1) {
        return [false, 'Jumlah pembelian minimal 1.'];
    }

    $maks = maks_beli($p, $mode);
    if ($maks < 1) {
        return [false, $p['nama'] . ' sedang habis.'];
    }

    $key   = cart_key($productId, $mode);
    $sudah = (int) ($_SESSION['cart'][$key]['qty'] ?? 0);
    $total = min($sudah + $qty, $maks);

    $_SESSION['cart'][$key] = ['product_id' => $productId, 'mode' => $mode, 'qty' => $total];

    $pesan = $p['nama'] . ' (' . angka($total) . ' ' . label_mode($p, $mode) . ') ada di keranjang.';
    if ($total < $sudah + $qty) {
        $pesan .= ' Jumlah disesuaikan dengan stok yang tersedia.';
    }
    return [true, $pesan];
}

function cart_update(string $key, int $qty): void
{
    if (empty($_SESSION['cart'][$key])) {
        return;
    }
    if ($qty < 1) {
        unset($_SESSION['cart'][$key]);
        return;
    }
    $item = $_SESSION['cart'][$key];
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$item['product_id']]);
    $p = $stmt->fetch();
    if (!$p || !mode_valid($p, $item['mode'])) {
        unset($_SESSION['cart'][$key]);
        return;
    }
    $_SESSION['cart'][$key]['qty'] = min($qty, max(0, maks_beli($p, $item['mode'])));
    if ($_SESSION['cart'][$key]['qty'] < 1) {
        unset($_SESSION['cart'][$key]);
    }
}

function cart_remove(string $key): void
{
    unset($_SESSION['cart'][$key]);
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

/**
 * Ambil isi keranjang lengkap dengan data produk terkini.
 * Baris yang produknya sudah tidak ada atau cara belinya tidak lagi valid otomatis dibuang.
 */
function cart_items(): array
{
    $items = [];
    foreach (($_SESSION['cart'] ?? []) as $key => $it) {
        $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$it['product_id']]);
        $p = $stmt->fetch();

        if (!$p || !mode_valid($p, $it['mode'])) {
            unset($_SESSION['cart'][$key]);
            continue;
        }

        $maks = maks_beli($p, $it['mode']);
        $qty  = (int) $it['qty'];
        $over = $qty > $maks;
        if ($over) {
            $qty = max(0, $maks);
            $_SESSION['cart'][$key]['qty'] = $qty;
            if ($qty < 1) {
                unset($_SESSION['cart'][$key]);
                continue;
            }
        }

        $harga = harga_mode($p, $it['mode']);
        $items[] = [
            'key'      => $key,
            'product'  => $p,
            'mode'     => $it['mode'],
            'qty'      => $qty,
            'label'    => label_mode($p, $it['mode']),
            'harga'    => $harga,
            'subtotal' => $harga * $qty,
            'maks'     => $maks,
            'disesuaikan' => $over,
        ];
    }
    return $items;
}

function cart_total(array $items): int
{
    $total = 0;
    foreach ($items as $it) {
        $total += $it['subtotal'];
    }
    return $total;
}
