# Changelog NOWRD CMS

Semua catatan di bawah merangkum evolusi fitur berdasarkan nama arsip, dokumentasi rilis yang tersedia, dan catatan pengujian proyek. Untuk v1–v19, beberapa rincian historis diringkas dari arsip/README pengembangan dan bukan rekonstruksi commit demi commit. Versi historis tidak berarti semua paket harus dipasang berurutan.

## [v30] — Integrations & API
- Menambahkan API JSON read-only berversi (`/api/v1/`) untuk daftar artikel terbit, detail artikel, dan kategori.
- Menambahkan paginasi, batas ukuran halaman, filter bahasa, format error JSON, dan endpoint indeks API.
- Endpoint publik hanya membaca konten terbit; tidak ada endpoint tulis anonim atau akses admin melalui API.
- Menambahkan dokumentasi `API-V1.md` dan checklist deployment akhir.

## [v29] — Multilingual Publishing
- Menambahkan dukungan terjemahan per artikel dengan status draft/terbit dan slug terjemahan.
- Menyediakan alur admin untuk mengelola terjemahan serta tautan bahasa pada artikel publik.
- Menambahkan penyimpanan terjemahan secara aditif melalui tabel `post_translations`.
- Batasan: bukan penerjemahan otomatis dan bukan lokalisasi penuh seluruh antarmuka.

## [v28] — Visual Page Builder
- Menambahkan fondasi/antarmuka page builder visual untuk membantu penyusunan halaman.
- Rincian perilaku dan cakupan fitur historis v28 perlu diverifikasi terhadap paket sumber v28 tersendiri; dokumentasi khusus v28 tidak tersedia di paket v30 yang digunakan untuk repo ini.

## [v27] — Analytics & Reader Experience
- Menambahkan statistik tampilan halaman agregat harian, tren 7/30/90 hari, dan artikel populer 30 hari.
- Menambahkan estimasi waktu baca, indikator progres membaca, dan artikel terkait.
- Menghindari penyimpanan IP mentah, user-agent, cookie analitik, atau identitas pengunjung.

## [v26] — Security & Recovery
- Menambahkan halaman pemeriksaan keamanan untuk admin, ringkasan audit autentikasi, dan hitungan percobaan login.
- Memperkuat header keamanan dan aturan perlindungan file konfigurasi/backup serta direktori upload.
- Menambahkan peningkatan hash password setelah login bila algoritma PHP merekomendasikannya.
- Pemeriksaan ini adalah baseline defensif, bukan audit keamanan formal.

## [v25] — Media Library Pro
- Menambahkan pencarian media, filter orientasi dan alt text, pengurutan, serta pagination sisi server.
- Mempertahankan validasi gambar, edit alt text, pemilih media, dan perlindungan penghapusan.

## [v24] — SEO Audit & Discoverability
- Menambahkan audit SEO berbasis heuristik untuk judul, deskripsi, panjang konten, heading, gambar sampul, dan canonical URL.
- Menampilkan akses ke sitemap dan RSS dari panel audit.

## [v23] — Complete Theme Engine
- Menyatukan tema Classic dan Glassmorphism di beranda, artikel, kartu, kontrol pencarian, taksonomi, dan footer.
- Menambahkan dukungan reduced motion dan fallback bila `backdrop-filter` tidak tersedia.

## [v22] — Visitor Theme Switch
- Menambahkan pemilih tema Classic/Glass untuk pengunjung publik.
- Pilihan pengunjung disimpan lokal di browser dan tidak mengubah pengaturan global admin.

## [v21] — Global Visual Settings
- Menambahkan preferensi visual global yang disimpan pada tabel `settings`.
- Menyediakan pengaturan tema, mode terang/gelap/sistem, palet, gerakan, blur, dan dock untuk admin berizin.

## [v20] — Visual Refinement
- Memoles tampilan Classic dan Glassmorphism, palet warna, transisi, dock mengambang, dan responsivitas.
- Menambahkan opsi gerakan dan dukungan reduced motion.

## [v19] — Performance & Deployment Pro
- Iterasi untuk optimasi performa dan kesiapan deployment shared hosting.
- Rincian perubahan per-berkas tidak tersedia lengkap pada dokumentasi yang disertakan; validasi produksi tetap diperlukan.

## [v18] — Editorial Workflow, Shared Hosting & Cache
- Iterasi alur editorial dan optimasi cache untuk lingkungan shared hosting.
- Periksa catatan paket rilis terkait sebelum upgrade situs aktif.

## [v17] — Role-Based Access Control
- Menambahkan kontrol akses berbasis peran/izin untuk membatasi kemampuan administrator/editor.
- Dokumentasi izin tersedia di `ROLE-BASED-ACCESS.md`.

## [v16] — Admin Control Center
- Iterasi dashboard dan pusat kendali administrasi untuk mengelola area CMS.

## [v15] — Security, Backup & Recovery
- Iterasi keamanan, backup, dan pemulihan untuk membantu operasional serta perawatan CMS.

## [v14] — Maintenance & Editorial Workflow
- Menambahkan/menyempurnakan utilitas pemeliharaan dan alur kerja editorial.

## [v13] — Editorial Identity & Sustainable Admin
- Iterasi identitas editorial dan pengalaman admin yang lebih konsisten untuk penggunaan jangka panjang.

## [v12] — Advanced Display & Branding
- Memperluas opsi tampilan dan branding agar identitas situs dapat disesuaikan.

## [v11] — Writing Studio Plus
- Iterasi editor penulisan dengan peningkatan pengalaman penyusunan dan penyuntingan artikel.

## [v10] — Classic & Modern Glass
- Memperkenalkan/menyempurnakan pilihan tampilan Classic dan Glassmorphism.

## [v9] — Editorial Workflow & Revision History
- Menambahkan riwayat revisi artikel dan alur pemulihan versi sebelumnya.
- Menyertakan pemeriksaan CSRF/akses untuk aksi pemulihan sesuai catatan pengujian.

## [v8] — SEO & Scheduling
- Menambahkan metadata SEO artikel, canonical URL, sitemap, dan RSS.
- Menambahkan publikasi terjadwal dan migrasi kolom SEO secara aditif untuk basis v7.

## [v7] — Content Organization
- Menambahkan kategori, tag, pencarian/filter, dan pagination untuk pengelolaan konten.
- Menambahkan relasi artikel dengan kategori/tag.

## [v6] — Media Library
- Menambahkan pustaka media untuk mengelola aset gambar.

## [v5] — Writing Studio
- Menambahkan pengalaman editor penulisan yang lebih kaya.

## [v4] — Optimized Foundation
- Mengoptimalkan fondasi CMS setelah iterasi starter awal.

## [v3] — Starter Iteration
- Iterasi struktur starter dan pengalaman dasar CMS.

## [v2] — Starter Iteration
- Perbaikan lanjutan dari paket starter awal.

## [v1] — Starter
- Fondasi awal NOWRD CMS.

---

### Catatan akurasi
- Repo ini memuat source v30 yang tersedia, bukan semua paket source v1–v29.
- Changelog v1–v30 adalah ringkasan evolusi dari artefak dan dokumentasi yang tersedia; beberapa versi awal/tengah diringkas karena catatan rilis lengkapnya tidak tersedia dalam ZIP v30.
- Jangan menafsirkan nomor versi sebagai bukti bahwa setiap rilis telah melewati pengujian produksi. Lihat `TESTING.md`.
