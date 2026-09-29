-- ============================================================================
-- MIGRASI database "sembako" untuk pembayaran Midtrans
--
-- Untuk database yang SUDAH BERJALAN (punya data customer, pesanan, produk).
-- Jauh lebih aman daripada meng-import ulang database.sql, karena skrip ini
-- HANYA mengubah tabel `orders` dan tidak menghapus data apa pun.
--
-- Cara pakai: BACKUP dulu (phpMyAdmin > Export), lalu buka database `sembako`
-- > tab SQL > tempel isi file ini > Go. Jalankan SATU KALI saja (menjalankan
-- ulang akan error karena kolomnya sudah ada).
--
-- Yang dilakukan:
--   1. Status pesanan 'diterima' (sistem lama: barang diterima = langsung
--      dianggap lunas/COD) dipecah menjadi 'menunggu_pembayaran' dan 'selesai'.
--      Pesanan lama berstatus 'diterima' otomatis menjadi 'selesai' (dianggap
--      sudah lunas, tanggal bayar = tanggal diterima).
--   2. Menambah kolom pembayaran: dibayar_at, metode_bayar, kolom Midtrans
--      (midtrans_*), dan kolom Xendit lama (xendit_*, tidak dipakai lagi,
--      hanya supaya struktur sama persis dengan database.sql terbaru).
-- ============================================================================

USE sembako;

-- 1) Lebarkan dulu daftar status; 'diterima' dipertahankan sementara agar data lama tidak hilang.
ALTER TABLE orders MODIFY status
  ENUM('menunggu_verifikasi','siap_diproses','siap_kirim','dikirim','diterima','menunggu_pembayaran','selesai','dibatalkan')
  NOT NULL DEFAULT 'menunggu_verifikasi';

-- 2) Kolom baru
ALTER TABLE orders
  ADD COLUMN dibayar_at          TIMESTAMP NULL AFTER diterima_at,
  ADD COLUMN xendit_invoice_id   VARCHAR(100) NULL AFTER dibayar_at,
  ADD COLUMN xendit_external_id  VARCHAR(100) NULL AFTER xendit_invoice_id,
  ADD COLUMN xendit_invoice_url  VARCHAR(255) NULL AFTER xendit_external_id,
  ADD COLUMN xendit_status       VARCHAR(20)  NULL AFTER xendit_invoice_url,
  ADD COLUMN metode_bayar        VARCHAR(50)  NULL AFTER xendit_status,
  ADD COLUMN midtrans_order_id   VARCHAR(64)  NULL AFTER metode_bayar,
  ADD COLUMN midtrans_snap_token VARCHAR(100) NULL AFTER midtrans_order_id,
  ADD COLUMN midtrans_token_at   TIMESTAMP NULL AFTER midtrans_snap_token,
  ADD COLUMN midtrans_status     VARCHAR(30)  NULL AFTER midtrans_token_at;

-- 3) Data lama: 'diterima' pada sistem lama = sudah dianggap lunas.
UPDATE orders
   SET status = 'selesai', dibayar_at = diterima_at, metode_bayar = 'COD (sistem lama)'
 WHERE status = 'diterima';

-- 4) Daftar status final (tanpa 'diterima')
ALTER TABLE orders MODIFY status
  ENUM('menunggu_verifikasi','siap_diproses','siap_kirim','dikirim','menunggu_pembayaran','selesai','dibatalkan')
  NOT NULL DEFAULT 'menunggu_verifikasi';
