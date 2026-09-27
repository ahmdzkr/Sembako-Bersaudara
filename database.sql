-- Database Sembako Bersaudara (MySQL / MariaDB) - nama database: sembako
--
-- File ini BUKAN data contoh — isinya adalah data toko Anda yang sebenarnya
-- (produk, foto, akun, pesanan yang sudah ada), dipindahkan ke struktur tabel
-- terbaru yang mendukung satuan_dasar (kg/liter/tanpa basis) untuk Dry Good.
-- Nama file foto (kolom gambar) TIDAK berubah, jadi foto yang sudah Anda
-- unggah ke assets/uploads tetap terpakai seperti biasa.
--
-- Cara jual & stok per kategori:
--   Fresh Good : selalu per kg. harga = Rp/kg, satuan_dasar='kg', stok = kg.
--   Dry Good   : basisnya bisa dipilih per produk (satuan_dasar):
--                - 'kg'    -> dijual kiloan, mis. beras, tepung, gula
--                - 'liter' -> dijual literan, mis. minyak goreng
--                - NULL    -> tidak ada basis kg/liter, hanya per kemasan
--                             tetap, mis. kopi sachet, susu kaleng
--                Kalau satuan_dasar diisi, boleh DITAMBAH opsi beli per
--                kemasan (satuan+harga_satuan+isi_dasar). Kalau satuan_dasar
--                NULL, satuan+harga_satuan WAJIB (satu-satunya cara beli).
--                stok disimpan dalam satuan_dasar (kg/liter) jika ada, atau
--                dalam jumlah kemasan jika satuan_dasar NULL.
--   Lainnya    : sama seperti Dry Good tanpa basis (satuan_dasar selalu NULL),
--                hanya per satuan. stok = jumlah satuan.
--
-- Cara pakai: phpMyAdmin > tab Import > pilih file ini > Go.
-- PERHATIAN: file ini membuat ulang tabel, jadi jalankan ini menggantikan
-- database sembako yang sekarang. Data di bawah ini sudah memuat produk,
-- foto, akun, dan pesanan Anda yang sebelumnya, jadi tidak ada yang hilang.

CREATE DATABASE IF NOT EXISTS sembako
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sembako;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama          VARCHAR(100) NOT NULL,           -- untuk customer: nama pemilik toko
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','admin','kasir','owner') NOT NULL DEFAULT 'customer',
  -- Kolom berikut hanya diisi untuk role customer (akun berkontrak yang dibuatkan owner):
  nama_toko     VARCHAR(150) NULL,
  no_whatsapp   VARCHAR(20)  NULL,
  alamat        TEXT NULL,
  credit_limit  BIGINT UNSIGNED NULL,            -- Rp, plafon kredit sesuai kontrak
  termin_hari   INT UNSIGNED NULL,               -- masa jatuh tempo pembayaran, dalam hari
  aktif         TINYINT(1)   NOT NULL DEFAULT 1, -- nonaktif = akun dibekukan owner, tidak bisa login
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama         VARCHAR(150) NOT NULL,
  kategori     VARCHAR(50)  NOT NULL,            -- 'Fresh Good' | 'Dry Good' | 'Lainnya'
  satuan_dasar VARCHAR(10)  NULL,                -- 'kg' | 'liter' | NULL (tanpa basis berat/volume)
  harga        INT UNSIGNED NULL,                -- Rp per satuan_dasar
  satuan       VARCHAR(20)  NULL,                -- nama kemasan: karung, jerigen, sachet, kaleng, ...
  harga_satuan INT UNSIGNED NULL,                -- Rp per kemasan
  isi_dasar    INT UNSIGNED NULL,                -- jumlah satuan_dasar per 1 kemasan (mis. 25 kg/karung)
  stok         INT UNSIGNED NOT NULL DEFAULT 0,  -- dalam satuan_dasar, atau jumlah kemasan jika satuan_dasar NULL
  deskripsi    TEXT NULL,
  gambar       VARCHAR(255) NULL,                -- nama file di assets/uploads
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (kategori)
) ENGINE=InnoDB;

-- Alur status PO (Purchase Order):
--   menunggu_verifikasi -> customer baru membuat pesanan, menunggu kasir cek dokumen/keuangan.
--   siap_diproses       -> kasir sudah approve PO (stok dikurangi di titik ini), admin mulai menyiapkan.
--   siap_kirim          -> admin sudah menyiapkan barang & mencetak surat jalan/label.
--   dikirim             -> barang dalam perjalanan ke customer.
--   diterima            -> customer konfirmasi barang sudah diterima & sesuai; invoice terbit.
--   dibatalkan          -> PO ditolak kasir (dokumen/keuangan tidak valid), stok TIDAK dikurangi.
CREATE TABLE orders (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  nama_penerima   VARCHAR(100) NOT NULL,
  telepon         VARCHAR(20)  NOT NULL,
  alamat          TEXT NOT NULL,
  catatan         VARCHAR(300) NULL,
  total           BIGINT UNSIGNED NOT NULL,
  status          ENUM('menunggu_verifikasi','siap_diproses','siap_kirim','dikirim','diterima','dibatalkan')
                  NOT NULL DEFAULT 'menunggu_verifikasi',
  diverifikasi_oleh INT UNSIGNED NULL,          -- kasir yang approve/tolak PO ini
  verifikasi_at     TIMESTAMP NULL,
  catatan_kasir     VARCHAR(300) NULL,          -- alasan, terutama saat PO ditolak
  no_resi           VARCHAR(50)  NULL,          -- diisi admin saat menyiapkan pengiriman
  dikirim_at        TIMESTAMP NULL,
  diterima_at       TIMESTAMP NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NULL,
  nama_produk VARCHAR(150) NOT NULL,
  mode        ENUM('dasar','satuan') NOT NULL,   -- 'dasar' = beli per kg/liter, 'satuan' = per kemasan
  qty         INT UNSIGNED NOT NULL,
  satuan      VARCHAR(20)  NOT NULL,             -- label satuan saat dibeli: kg, liter, karung, sachet, ...
  harga       INT UNSIGNED NOT NULL,
  subtotal    BIGINT UNSIGNED NOT NULL,
  FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---- Akun Anda (hash kata sandi asli, tidak diubah) + akun kasir & owner baru ----
-- Kolom kontrak (nama_toko, no_whatsapp, alamat, credit_limit, termin_hari) diisi untuk
-- 2 customer lama sebagai contoh; akun customer BARU dibuat lewat menu Owner > Customer.
INSERT INTO users (id, nama, email, password_hash, role, nama_toko, no_whatsapp, alamat, credit_limit, termin_hari, aktif, created_at) VALUES
(1, 'Admin Toko',   'admin@sembako.com', '$2y$10$Mpo6XQtLoMNKuxnyYJ8wHuC1eUGxXDyTjp2cDEDKg8N.NN9OKT0Me', 'admin',    NULL,                     NULL,            NULL,                   NULL,      NULL, 1, '2026-09-22 02:42:04'),
(2, 'Budi Santoso', 'budi@gmail.com',    '$2y$10$X9XKKhFblx6mroBFDpZdy.jYpz/hIuDWmCttG3KscLFgoZdH6F9pW', 'customer', 'Toko Budi Jaya',         '081234567890',  'Jl. Margonda Raya No. 12, Depok', 50000000, 30,   1, '2026-09-22 02:42:04'),
(3, 'Siti Rahma',   'siti@gmail.com',    '$2y$10$X9XKKhFblx6mroBFDpZdy.jYpz/hIuDWmCttG3KscLFgoZdH6F9pW', 'customer', 'Warung Barokah Siti',    '081298765432',  'Jl. Kartini No. 5, Depok',        50000000, 30,   1, '2026-09-22 02:42:04'),
(4, 'Kasir Toko',   'kasir@sembako.com', '$2y$10$VYKRDsG/T8wS.16S8m2aKuajwEf3ufUGFLvAFrRwz3w3SgIel5JTu', 'kasir',    NULL,                     NULL,            NULL,                   NULL,      NULL, 1, '2026-09-26 00:00:00'),
(5, 'Owner Toko',   'owner@sembako.com', '$2y$10$CP.b.8av.LayITFual9j2.VEWz1iT1oAqemVVa7iyLfR/N95frAiu', 'owner',    NULL,                     NULL,            NULL,                   NULL,      NULL, 1, '2026-09-26 00:00:00');
ALTER TABLE users AUTO_INCREMENT = 6;

-- ---- Produk Anda (foto & harga tetap sama, satuan_dasar diisi otomatis:
--      Fresh Good -> 'kg', Lainnya -> tanpa basis, Dry Good lama -> 'kg'
--      karena sebelumnya semua Dry Good memang selalu berbasis kg) ----
INSERT INTO products (id, nama, kategori, satuan_dasar, harga, satuan, harga_satuan, isi_dasar, stok, deskripsi, gambar, created_at, updated_at) VALUES
(1,  'Sawi Hijau',            'Fresh Good', 'kg', 12000, NULL,     NULL,   NULL, 300, 'Sawi hijau segar, dipanen pagi hari.',              'e815579bccb4e941.jpg', '2026-09-22 02:42:04', '2026-09-23 13:06:58'),
(2,  'Sawi Putih',            'Fresh Good', 'kg', 10000, NULL,     NULL,   NULL, 250, 'Sawi putih renyah untuk sup dan tumisan.',          'cae9f403a2096c91.jpg', '2026-09-22 02:42:04', '2026-09-23 13:06:41'),
(3,  'Timun Lokal',           'Fresh Good', 'kg',  8000, NULL,     NULL,   NULL, 400, 'Timun lokal segar dan renyah.',                     '1e996928f0921dc0.jpg', '2026-09-22 02:42:04', '2026-09-23 13:07:25'),
(4,  'Wortel',                'Fresh Good', 'kg', 14000, NULL,     NULL,   NULL, 500, 'Wortel manis dengan ukuran seragam.',               'baa01f768140d8b6.jpg', '2026-09-22 02:42:04', '2026-09-23 13:07:37'),
(5,  'Brokoli',               'Fresh Good', 'kg', 28000, NULL,     NULL,   NULL, 150, 'Brokoli hijau padat dan segar.',                    '1a64ca047ca1620b.jpg', '2026-09-22 02:42:04', '2026-09-23 13:02:32'),
(6,  'Kembang Kol',           'Fresh Good', 'kg', 22000, NULL,     NULL,   NULL, 120, 'Kembang kol putih bersih.',                         '41283626174cc115.jpg', '2026-09-22 02:42:04', '2026-09-23 13:01:40'),
(7,  'Tomat',                 'Fresh Good', 'kg', 15000, NULL,     NULL,   NULL, 350, 'Tomat merah segar.',                                '2701b3e66ef82231.jpg', '2026-09-22 02:42:04', '2026-09-23 13:07:12'),
(10, 'Bayam',                 'Fresh Good', 'kg',  9000, NULL,     NULL,   NULL,  80, 'Bayam hijau segar.',                                '17d7df1df559588f.jpg', '2026-09-22 02:42:04', '2026-09-22 12:46:58'),
(11, 'Cabai Merah Keriting',  'Fresh Good', 'kg', 48000, NULL,     NULL,   NULL, 200, 'Cabai merah keriting pedas.',                       'c420ffa225553b52.jpg', '2026-09-22 02:42:04', '2026-09-23 13:06:25'),
(41, 'Tepung Terigu',         'Dry Good',   'kg', 13000, 'karung', 245000,   25, 100, 'Tepung terigu Cakra Kembar',                        '854bb16c620d20fc.jpg', '2026-09-23 13:30:35', '2026-09-23 13:30:35'),
(43, 'Gula Putih Rose Brand', 'Dry Good',   'kg', 20250, 'dus',    405000,   20, 300, 'Gula putih Rose Brand\r\n1 dus isi 20',             'b151315229d51e41.jpg', '2026-09-23 15:21:53', '2026-09-23 15:21:53'),
(44, 'Sedotan',               'Lainnya',    NULL,  NULL, 'pak',      5000, NULL, 100, 'Sedotan steril\r\n1 pak 50 pcs',                    '9bc16d308cabc494.jpg', '2026-09-23 16:00:44', '2026-09-23 16:00:44'),
(45, 'Nice Facial Tissue',    'Lainnya',    NULL,  NULL, 'dus',    345000, NULL, 200, 'Tisu kering\r\n1 dus isi 60 pak',                   '2a940a8af3e307f7.jpg', '2026-09-23 16:09:10', '2026-09-23 16:09:10');
ALTER TABLE products AUTO_INCREMENT = 46;

-- ---- Pesanan yang sudah pernah masuk ----
-- Pesanan #1 dulunya berstatus 'baru' (skema lama, sebelum ada alur PO ini) dan
-- tidak punya rincian barang sama sekali (order_items kosong) — kemungkinan data
-- uji coba dari sebelum fitur rincian barang selesai dibuat. Karena tidak ada
-- barang yang bisa diverifikasi, pesanan ini saya tandai 'dibatalkan' dengan
-- catatan penjelas, supaya tidak nyangkut aneh di antrean verifikasi kasir.
-- Pesanan BARU yang dibuat setelah ini akan otomatis mengikuti alur PO lengkap.
INSERT INTO orders (id, user_id, nama_penerima, telepon, alamat, catatan, total, status, catatan_kasir, created_at) VALUES
(1, 2, 'Budi Santoso', '08343453455', 'depok', 'mantap', 90000, 'dibatalkan', 'Data lama sebelum fitur PO dibuat; tidak ada rincian barang untuk diverifikasi.', '2026-09-22 02:44:15');
ALTER TABLE orders AUTO_INCREMENT = 2;

-- (order_items untuk pesanan #1 memang kosong di data asal Anda; rincian
-- barangnya tidak tersimpan saat pesanan itu dibuat, jadi tidak ada yang
-- bisa dipindahkan ke sini.)
ALTER TABLE order_items AUTO_INCREMENT = 2;
