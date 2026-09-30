-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 30 Sep 2026 pada 13.42
-- Versi server: 10.4.22-MariaDB
-- Versi PHP: 8.1.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sembako`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `nama_penerima` varchar(100) NOT NULL,
  `telepon` varchar(20) NOT NULL,
  `alamat` text NOT NULL,
  `catatan` varchar(300) DEFAULT NULL,
  `total` bigint(20) UNSIGNED NOT NULL,
  `status` enum('menunggu_verifikasi','siap_diproses','siap_kirim','dikirim','menunggu_pembayaran','selesai','dibatalkan') NOT NULL DEFAULT 'menunggu_verifikasi',
  `diverifikasi_oleh` int(10) UNSIGNED DEFAULT NULL,
  `verifikasi_at` timestamp NULL DEFAULT NULL,
  `catatan_kasir` varchar(300) DEFAULT NULL,
  `no_resi` varchar(50) DEFAULT NULL,
  `dikirim_at` timestamp NULL DEFAULT NULL,
  `diterima_at` timestamp NULL DEFAULT NULL,
  `dibayar_at` timestamp NULL DEFAULT NULL,
  `xendit_invoice_id` varchar(100) DEFAULT NULL,
  `xendit_external_id` varchar(100) DEFAULT NULL,
  `xendit_invoice_url` varchar(255) DEFAULT NULL,
  `xendit_status` varchar(20) DEFAULT NULL,
  `metode_bayar` varchar(50) DEFAULT NULL,
  `midtrans_order_id` varchar(64) DEFAULT NULL,
  `midtrans_snap_token` varchar(100) DEFAULT NULL,
  `midtrans_token_at` timestamp NULL DEFAULT NULL,
  `midtrans_status` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `nama_penerima`, `telepon`, `alamat`, `catatan`, `total`, `status`, `diverifikasi_oleh`, `verifikasi_at`, `catatan_kasir`, `no_resi`, `dikirim_at`, `diterima_at`, `dibayar_at`, `xendit_invoice_id`, `xendit_external_id`, `xendit_invoice_url`, `xendit_status`, `metode_bayar`, `midtrans_order_id`, `midtrans_snap_token`, `midtrans_token_at`, `midtrans_status`, `created_at`) VALUES
(1, 2, 'Budi Santoso', '08343453455', 'depok', 'mantap', 90000, 'dibatalkan', NULL, NULL, 'Data lama sebelum fitur PO dibuat; tidak ada rincian barang untuk diverifikasi.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-21 19:44:15'),
(2, 2, 'Budi Santoso', '08343453455', 'bogor', 'pe', 506250, 'selesai', 4, '2026-09-27 03:51:23', NULL, '028', '2026-09-27 04:02:10', '2026-09-27 04:12:11', '2026-09-27 04:12:11', NULL, NULL, NULL, NULL, 'COD (sistem lama)', NULL, NULL, NULL, NULL, '2026-09-27 03:49:50'),
(3, 2, 'Budi Santoso', '08343453455', 'solo', 'pe', 48000, 'selesai', 4, '2026-09-29 16:48:43', NULL, '123', '2026-09-29 16:49:36', '2026-09-29 16:50:03', '2026-09-29 16:54:02', NULL, NULL, NULL, NULL, 'QRIS', 'PO-00003-179070061464', 'ad211580-0d5f-4956-891c-eb513b5f8f9e', '2026-09-29 16:50:15', 'settlement', '2026-09-29 16:48:20'),
(4, 2, 'Budi Santoso', '08343453455', 'bogor', 'mantap men', 22000, 'dibatalkan', 4, '2026-09-29 16:59:05', 'beli mulu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-29 16:58:17'),
(5, 2, 'Budi Santoso', '08343453455', 'padang', 'boleh juga', 1215000, 'selesai', 4, '2026-09-29 17:05:26', NULL, '1234', '2026-09-29 17:06:34', '2026-09-29 17:07:24', '2026-09-29 17:08:28', NULL, NULL, NULL, NULL, 'Transfer bank (VA) BCA', 'PO-00005-179070167055', '95453be2-5505-4117-a74c-ae34bc7a4dff', '2026-09-29 17:07:50', 'settlement', '2026-09-29 17:04:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `mode` enum('dasar','satuan') NOT NULL,
  `qty` int(10) UNSIGNED NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `harga` int(10) UNSIGNED NOT NULL,
  `subtotal` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `nama_produk`, `mode`, `qty`, `satuan`, `harga`, `subtotal`) VALUES
(2, 2, 43, 'Gula Putih Rose Brand', 'dasar', 5, 'kg', 20250, 101250),
(3, 2, 43, 'Gula Putih Rose Brand', 'satuan', 1, 'dus', 405000, 405000),
(4, 3, 11, 'Cabai Merah Keriting', 'dasar', 1, 'kg', 48000, 48000),
(5, 4, 6, 'Kembang Kol', 'dasar', 1, 'kg', 22000, 22000),
(6, 5, 43, 'Gula Putih Rose Brand', 'satuan', 3, 'dus', 405000, 1215000);

-- --------------------------------------------------------

--
-- Struktur dari tabel `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(150) NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `satuan_dasar` varchar(10) DEFAULT NULL,
  `harga` int(10) UNSIGNED DEFAULT NULL,
  `satuan` varchar(20) DEFAULT NULL,
  `harga_satuan` int(10) UNSIGNED DEFAULT NULL,
  `isi_dasar` int(10) UNSIGNED DEFAULT NULL,
  `stok` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `deskripsi` text DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `products`
--

INSERT INTO `products` (`id`, `nama`, `kategori`, `satuan_dasar`, `harga`, `satuan`, `harga_satuan`, `isi_dasar`, `stok`, `deskripsi`, `gambar`, `created_at`, `updated_at`) VALUES
(1, 'Sawi Hijau', 'Fresh Good', 'kg', 12000, NULL, NULL, NULL, 300, 'Sawi hijau segar, dipanen pagi hari.', 'e815579bccb4e941.jpg', '2026-09-21 19:42:04', '2026-09-23 06:06:58'),
(2, 'Sawi Putih', 'Fresh Good', 'kg', 10000, NULL, NULL, NULL, 250, 'Sawi putih renyah untuk sup dan tumisan.', 'cae9f403a2096c91.jpg', '2026-09-21 19:42:04', '2026-09-23 06:06:41'),
(3, 'Timun Lokal', 'Fresh Good', 'kg', 8000, NULL, NULL, NULL, 400, 'Timun lokal segar dan renyah.', '1e996928f0921dc0.jpg', '2026-09-21 19:42:04', '2026-09-23 06:07:25'),
(4, 'Wortel', 'Fresh Good', 'kg', 14000, NULL, NULL, NULL, 500, 'Wortel manis dengan ukuran seragam.', 'baa01f768140d8b6.jpg', '2026-09-21 19:42:04', '2026-09-23 06:07:37'),
(5, 'Brokoli', 'Fresh Good', 'kg', 28000, NULL, NULL, NULL, 150, 'Brokoli hijau padat dan segar.', '1a64ca047ca1620b.jpg', '2026-09-21 19:42:04', '2026-09-23 06:02:32'),
(6, 'Kembang Kol', 'Fresh Good', 'kg', 22000, NULL, NULL, NULL, 120, 'Kembang kol putih bersih.', '41283626174cc115.jpg', '2026-09-21 19:42:04', '2026-09-23 06:01:40'),
(7, 'Tomat', 'Fresh Good', 'kg', 15000, NULL, NULL, NULL, 350, 'Tomat merah segar.', '2701b3e66ef82231.jpg', '2026-09-21 19:42:04', '2026-09-23 06:07:12'),
(10, 'Bayam', 'Fresh Good', 'kg', 9000, NULL, NULL, NULL, 80, 'Bayam hijau segar.', '17d7df1df559588f.jpg', '2026-09-21 19:42:04', '2026-09-22 05:46:58'),
(11, 'Cabai Merah Keriting', 'Fresh Good', 'kg', 48000, NULL, NULL, NULL, 199, 'Cabai merah keriting pedas.', 'c420ffa225553b52.jpg', '2026-09-21 19:42:04', '2026-09-29 16:48:43'),
(41, 'Tepung Terigu', 'Dry Good', 'kg', 13000, 'karung', 245000, 25, 100, 'Tepung terigu Cakra Kembar', '854bb16c620d20fc.jpg', '2026-09-23 06:30:35', '2026-09-23 06:30:35'),
(43, 'Gula Putih Rose Brand', 'Dry Good', 'kg', 20250, 'dus', 405000, 20, 215, 'Gula putih Rose Brand\r\n1 dus isi 20', 'b151315229d51e41.jpg', '2026-09-23 08:21:53', '2026-09-29 17:05:26'),
(44, 'Sedotan', 'Lainnya', NULL, NULL, 'pak', 5000, NULL, 101, 'Sedotan steril\r\n1 pak 50 pcs', '9bc16d308cabc494.jpg', '2026-09-23 09:00:44', '2026-09-30 11:38:04'),
(45, 'Nice Facial Tissue', 'Lainnya', NULL, NULL, 'dus', 345000, NULL, 200, 'Tisu kering\r\n1 dus isi 60 pak', '2a940a8af3e307f7.jpg', '2026-09-23 09:09:10', '2026-09-23 09:09:10'),
(46, 'Spaghetti La Fonte 225 gram', 'Dry Good', NULL, NULL, 'dus', 350000, NULL, 500, 'Spaghetti La Fonte 225 gram \r\nisi 40 pak/dus', '42de6211c20a4d88.jpg', '2026-09-30 11:37:30', '2026-09-30 11:37:53');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','admin','kasir','owner') NOT NULL DEFAULT 'customer',
  `nama_toko` varchar(150) DEFAULT NULL,
  `no_whatsapp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `credit_limit` bigint(20) UNSIGNED DEFAULT NULL,
  `termin_hari` int(10) UNSIGNED DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password_hash`, `role`, `nama_toko`, `no_whatsapp`, `alamat`, `credit_limit`, `termin_hari`, `aktif`, `created_at`) VALUES
(1, 'Admin Toko', 'admin@sembako.com', '$2y$10$Mpo6XQtLoMNKuxnyYJ8wHuC1eUGxXDyTjp2cDEDKg8N.NN9OKT0Me', 'admin', NULL, NULL, NULL, NULL, NULL, 1, '2026-09-21 19:42:04'),
(2, 'Budi Santoso', 'budi@gmail.com', '$2y$10$X9XKKhFblx6mroBFDpZdy.jYpz/hIuDWmCttG3KscLFgoZdH6F9pW', 'customer', 'Toko Budi Jaya', '081234567890', 'Jl. Margonda Raya No. 12, Depok', 50000000, 30, 1, '2026-09-21 19:42:04'),
(3, 'Siti Rahma', 'siti@gmail.com', '$2y$10$X9XKKhFblx6mroBFDpZdy.jYpz/hIuDWmCttG3KscLFgoZdH6F9pW', 'customer', 'Warung Barokah Siti', '081298765432', 'Jl. Kartini No. 5, Depok', 50000000, 30, 1, '2026-09-21 19:42:04'),
(4, 'Kasir Toko', 'kasir@sembako.com', '$2y$10$VYKRDsG/T8wS.16S8m2aKuajwEf3ufUGFLvAFrRwz3w3SgIel5JTu', 'kasir', NULL, NULL, NULL, NULL, NULL, 1, '2026-09-25 17:00:00'),
(5, 'Owner Toko', 'owner@sembako.com', '$2y$10$CP.b.8av.LayITFual9j2.VEWz1iT1oAqemVVa7iyLfR/N95frAiu', 'owner', NULL, NULL, NULL, NULL, NULL, 1, '2026-09-25 17:00:00');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `diverifikasi_oleh` (`diverifikasi_oleh`);

--
-- Indeks untuk tabel `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indeks untuk tabel `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategori` (`kategori`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
