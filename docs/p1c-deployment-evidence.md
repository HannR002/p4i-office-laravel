# Catatan Bukti Teknis Deployment Staging Hostinger (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment)  
**Implementer Teknis (Lead Software Engineer & Technical Test Engineer):** Argon — Reasoning High  
**System Architect, SRS, QA Auditor & Laporan Resmi:** GPT-6  
**Otoritas Administratif & Pemilik Proyek:** Ketua dan Pengurus Yayasan P4I  
**Workspace Repositori:** `D:\p4i-office-laravel` (Mirror: `D:\p4i-office`)  
**Branch Aktif:** `feat/stage-p1c-staging-deployment`  
**Commit Rilis:** `d222e25ef407f1e527cf7b59ccd6834e80106fac`  
**Target Hosting:** Hostinger Business Web Hosting  
**Website Staging:** `https://staging-office.p4ijournal.org`  
**Database Staging:** `u239415845_p4i_staging`  
**Tanggal Evaluasi:** 16 November 2026  

---

## 1. Status Akhir Gerbang Deployment (P1-C Decision)

> [!IMPORTANT]
> **STATUS RESMI PELAKSANAAN TAHAP P1-C:**  
> **`STAGING DEPLOYMENT COMPLETED & VERIFIED`**
>
> 1. Pemilik proyek telah memberikan instruksi eksplisit dan memasang SSH Public Key (`id_ed25519_p4i_staging.pub`) pada hPanel Hostinger.
> 2. Seluruh rangkaian eksekusi deployment split-directory berhasil dituntaskan tanpa kesalahan.
> 3. Inti Laravel beroperasi di luar web root pada direktori privat `/home/u239415845/p4i-office-staging/`.
> 4. Front controller dan aset publik terpasang di `/home/u239415845/domains/staging-office.p4ijournal.org/public_html/`.
> 5. Seluruh 86 paket dependensi `composer.lock` terpasang dengan autoloader teroptimasi.
> 6. Database `u239415845_p4i_staging` termigrasi penuh (9 migrasi, 27 tabel, 4 trigger immutability aktif).
> 7. Seeder sintetis UAT terpasang (8 pengguna, templat `KET-UAT`).
> 8. Staging cron job (`pRk1dnIGfn`) terdaftar dan teruji.
> 9. Verifikasi live HTTP, login staf UAT, pembuatan dan pengunduhan PDF/DOCX terbukti berhasil 100%.

---

## 2. Hasil Audit Pra-Deployment Lokal

### 2.1 Git Status & Baseline Repositori
- **Branch:** `feat/stage-p1c-staging-deployment`.
- **Status Git:** Bersih (*clean working tree*). Tidak ada berkas `.env`, kredensial, maupun rahasia nyata yang terlacak di Git.
- **Commit Baseline:** `d222e25ef407f1e527cf7b59ccd6834e80106fac`.

### 2.2 Verifikasi Pengujian Otomatis, Gaya Kode & Build Aset
- **Laravel Pint:** `{"tool":"pint","result":"passed"}` (0 pelanggaran gaya kode).
- **Vite Production Build:** `public/build/manifest.json` (0.25 kB), `public/build/assets/app-74e2b608.js` (58.97 kB).
- **PHPUnit Full Suite:** Seluruh 142 pengujian otomatis (763 assertions) pada 15 test suite lulus 100% (`PASS`).

### 2.3 Paket Rilis Siap Deploy (Release Package Artifact)
- **Nama Berkas:** `storage/release/p4i-office-core-p1c.tar.gz`
- **Ukuran:** `181.827 bytes` (181 KB)
- **Digest SHA-256:** `8010be898009c437fab55559df17305525ca941c8c339759a3a10a533ca3233e`
- **SHA-256 di Server Hostinger:** `8010be898009c437fab55559df17305525ca941c8c339759a3a10a533ca3233e` (**IDENTIK**)

---

## 3. Snapshot Kondisi Aktual Sumber Daya Hostinger Pasca-Deployment

| Sumber Daya Hostinger | Identitas / Nilai Aktual | Status Operasional | Catatan Verifikasi |
| :--- | :--- | :---: | :--- |
| **Website Staging** | `staging-office.p4ijournal.org` | `ACTIVE (200 OK)` | Vhost `addon` independen pada order `1009584994`. |
| **Document Root** | `/home/u239415845/domains/staging-office.p4ijournal.org/public_html` | `TERPASANG` | Berisi `index.php`, `.htaccess`, `favicon.ico`, `robots.txt`, dan direktori `build/`. |
| **Core Laravel** | `/home/u239415845/p4i-office-staging/` | `TERISOLASI` | Berada di luar web root publik, permission `.env` = 600. |
| **Database Staging** | `u239415845_p4i_staging` | `OPERASIONAL` | 27 tabel, 4 triggers, 8 synthetic users, 3 draft letters. |
| **Hak Trigger MariaDB** | `permissions.Trigger = 1` | `TERVERIFIKASI` | 4 trigger immutability aktif dan memblokir mutasi raw SQL. |
| **Sertifikat SSL** | Provider `hssl` (Lifetime) | `ACTIVE` | HTTPS redirect aktif, kadaluarsa 2027-01-30. |
| **Cron Scheduler** | UID: `pRk1dnIGfn` | `ACTIVE` | `* * * * *` menjalankan `schedule:run`. |
| **Website Produksi** | `p4ijournal.org`, `journal`, `publisher` | `TIDAK TERSENTUH` | File dan konfigurasi produksi 100% utuh. |
| **Database Produksi** | `u239415845_ojs`, `u239415845_publisher`, `publisher_new` | `TIDAK TERSENTUH` | Tabel dan data produksi 100% utuh. |

---

## 4. Invarian Keselamatan Operasional yang Ditegakkan

1. **Penomoran Resmi Nonaktif:**  
   `config('features.official_numbering_active') === false` (Terverifikasi dari cache konfigurasi runtime server).
2. **Nomor Surat Resmi Bernilai NULL:**  
   `SELECT COUNT(*) FROM letters WHERE official_number IS NOT NULL` = `0`.
3. **Pencegahan Outbound Email Nyata:**  
   `MAIL_MAILER=log`, seluruh transmisi surat elektronik dicegat ke berkas log server.
4. **Isolasi Domain Sesi:**  
   `SESSION_DOMAIN=null`, `SESSION_COOKIE=p4i_staging_session`, mencegah pembagian cookie sesi dengan website OJS atau Publisher.
5. **Watermark Dokumen Permanen:**  
   Setiap berkas PDF dan DOCX yang diekspor memuat penanda status: `DRAF — BELUM RESMI — TIDAK BERLAKU SEBAGAI SURAT KELUAR`.

---

## 5. Prosedur Rollback Aman (Jika Diperlukan)

Apabila tim arsitek memutuskan perlunya reset lingkungan staging:
1. **Rollback Web Root:** Kosongkan `/home/u239415845/domains/staging-office.p4ijournal.org/public_html` dan pulihkan `default.php.bak`.
2. **Rollback Inti Aplikasi:** Hapus direktori `/home/u239415845/p4i-office-staging/`.
3. **Rollback Database:** Jalankan `/opt/alt/php83/usr/bin/php artisan migrate:reset --force` atau bersihkan tabel pada database `u239415845_p4i_staging`.
4. **Hapus Cron Staging:** Panggil operasi `hosting_cron-jobs_delete` untuk UID `pRk1dnIGfn`.
5. **Jaminan Nol Dampak:** Seluruh prosedur rollback tidak memiliki keterkaitan fisik dengan direktori maupun database produksi.
