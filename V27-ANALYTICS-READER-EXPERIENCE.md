# NOWRD CMS v27 — Analytics & Reader Experience

## Fitur
- Statistik agregat harian untuk beranda dan tampilan artikel.
- Dashboard admin di `admin/analytics.php`, khusus akun dengan izin `settings.manage`.
- Artikel populer 30 hari dan tren harian 7/30/90 hari.
- Estimasi waktu baca pada halaman artikel dan indikator progres membaca.
- Artikel terkait berdasarkan kategori bila tersedia.
- Privasi: tidak menyimpan IP mentah, user-agent, cookie analitik, atau identitas pengunjung. Permintaan admin yang sedang login tidak dihitung.

## Instalasi
1. Backup file, database, `config.php`, dan `uploads/`.
2. Unggah file v27 dengan mempertahankan struktur `NOWRD/`.
3. Tabel `analytics_daily` dibuat otomatis saat halaman publik atau dashboard analytics pertama kali dibuka. Akun database perlu izin `CREATE TABLE` untuk migrasi awal; jika tidak ada, administrator harus membuat tabel sesuai `database.sql`.
4. Buka `admin/analytics.php` dengan akun administrator.

## Batasan interpretasi
- Ini menghitung page views, bukan pengunjung unik atau sesi.
- Refresh halaman bisa menambah hit; bot juga bisa tercatat.
- Data mulai terkumpul setelah v27 aktif, tidak ada backfill historis.
- Analytics bersifat first-party dan agregat, tetapi tetap cantumkan penggunaan statistik dalam kebijakan privasi situs bila diwajibkan.
