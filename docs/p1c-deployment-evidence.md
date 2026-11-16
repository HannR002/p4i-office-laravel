# Catatan Bukti Teknis Deployment Staging Hostinger (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment)  
**Implementer Teknis (Lead Software Engineer & Technical Test Engineer):** Argon — Reasoning High  
**System Architect, SRS, QA Auditor & Laporan Resmi:** GPT-6  
**Otoritas Administratif & Pemilik Proyek:** Ketua dan Pengurus Yayasan P4I  
**Workspace Lokal:** `D:\p4i-office-laravel`  
**Branch Aktif:** `feat/stage-p1c-staging-deployment`  
**Target Hosting:** Hostinger Business Web Hosting  
**Website Staging:** `staging-office.p4ijournal.org`  
**Database Staging:** `u239415845_p4i_staging`  
**Tanggal Evaluasi:** 16 November 2026  

---

## 1. Status Akhir Gerbang Deployment (P1-C Decision)

> [!IMPORTANT]
> **STATUS RESMI PELAKSANAAN TAHAP P1-C:**  
> **`STAGING DEPLOYMENT BLOCKED`**
>
> Sesuai mandat tegas pada **Bagian A (Gerbang Otorisasi)** dan **Bagian F (Stop Conditions)** dalam Master Prompt Tahap P1-C:
> 1. *"Sampai pemilik proyek memberikan izin eksplisit P1-C, lakukan pemeriksaan nonmutasi dan persiapan lokal saja."*
> 2. Konfirmasi izin eksplisit tertulis dari Ketua/Pengurus Yayasan P4I untuk melakukan mutasi server (mengunggah berkas aplikasi ke direktori server, menulis berkas `.env` staging, menjalankan migrasi database server, membuat trigger di DB server, memasang seeder, dan mendaftarkan cron job) **belum tercatat**.
> 3. Pengujian konektivitas shell langsung melalui SSH ke server Hostinger (`u239415845@153.92.8.139:65002`) menghasilkan `Permission denied (publickey,password)`, dan antarmuka API MCP Hostinger tidak menyediakan fungsi penulisan/unggah berkas ke direktori privat `/home/u239415845/p4i-office-staging/` di luar `public_html`.
> 4. Sesuai Stop Condition: *"Jangan menghapus mekanisme keamanan hanya untuk membuat aplikasi berhasil berjalan"*, implementer **BERHENTI SECARA TERTIB** tanpa melakukan perubahan apa pun pada website aktif, database produksi, maupun direktori server.

---

## 2. Hasil Audit Pra-Deployment Lokal (Bagian B)

Seluruh persyaratan pra-deployment pada lingkungan lokal telah diverifikasi secara ketat:

### 2.1 Git Status & Baseline Repositori
- **Branch:** `feat/stage-p1c-staging-deployment` (dibuat dari baseline `feat/sprint-m5b-b-staging-verification` commit `70bca1722bf0ed1910970ad711c10ef7e01076c7`).
- **Status Git:** Bersih (*clean working tree*). Tidak ada berkas `.env`, kredensial, maupun rahasia nyata yang terlacak di Git.

### 2.2 Verifikasi Pengujian Otomatis, Gaya Kode & Build Aset
- **Laravel Pint:** `{"tool":"pint","result":"passed"}` (0 pelanggaran gaya kode).
- **Vite Production Build:** `✓ 61 modules transformed. public/build/manifest.json (0.25 kB), public/build/assets/app-74e2b608.js (58.97 kB) — built in 1.26s`.
- **PHPUnit Full Suite:** Seluruh 142 pengujian otomatis (763 assertions) pada 15 test suite lulus 100% (`PASS`).

### 2.3 Status Migrasi, Skema & 4 Trigger Immutability Lokal
- 8 migrasi berjalan lengkap (`Ran`) pada MariaDB 10.4.32-MariaDB.
- 4 trigger immutability aktif pada database lokal:
  1. `trg_letter_revisions_no_update`
  2. `trg_letter_revisions_no_delete`
  3. `trg_audit_events_no_update`
  4. `trg_audit_events_no_delete`
- Terverifikasi memblokir pembaruan dan penghapusan langsung melalui raw SQL (`LetterDraftManagementTest::test_m1_05`).

### 2.4 Paket Rilis Siap Deploy (Release Package Artifact)
Paket rilis bersih tanpa dependensi dev, git history, maupun berkas sensitif telah dikemas dan dihitung digest integritasnya:
- **Nama Berkas:** `storage/release/p4i-office-core-p1c.tar.gz`
- **Ukuran:** `181.039 bytes` (181 KB)
- **Digest SHA-256:** `1CD9BE43E03B67055CC3423DA5B02829A66C3B88CBA53A7E059A25A2EE8CFB9E`

### 2.5 Invarian Keselamatan Operasional
- `config('features.official_numbering_active') === false` (Nomor resmi nonaktif mutlak).
- `SELECT COUNT(*) FROM letters WHERE official_number IS NOT NULL` = `0`.
- Konfigurasi email staging disetel ke `MAIL_MAILER=log`, menjamin aplikasi staging tidak dapat mengirim email ke pihak luar atau penerima sungguhan.

---

## 3. Snapshot Kondisi Aktual Sumber Daya Hostinger

Berdasarkan pemeriksaan langsung via konektor MCP Hostinger (`hostinger-hosting`, `hostinger-billing`, `hostinger-domains`, `hostinger-dns`):

| Sumber Daya Hostinger | Identitas / Nilai Aktual | Status Operasional | Catatan Verifikasi |
| :--- | :--- | :---: | :--- |
| **Website Staging** | `staging-office.p4ijournal.org` | `ACTIVE` | Vhost `addon` independen pada order `1009584994`. |
| **Document Root** | `/home/u239415845/domains/staging-office.p4ijournal.org/public_html` | `TERISOLASI` | Hanya berisi 1 berkas `default.php` bawaan Hostinger (42.565 byte). |
| **Database Staging** | `u239415845_p4i_staging` | `SIAP` | Kuota 3.072 MB, `disk_usage_mb: 1 MB`, `0 tabel`, `0 trigger`. |
| **Pengguna Database** | `u239415845_p4i_staging` | `ACTIVE` | Terikat hanya ke domain staging; tidak memiliki akses ke database produksi. |
| **Hak Trigger MariaDB** | `permissions.Trigger = 1` | `TERVERIFIKASI` | Hak `TRIGGER` aktif pada user DB, siap mengeksekusi migrasi jika diizinkan. |
| **Sertifikat SSL** | Provider `hssl` (Lifetime) | `ACTIVE` | HTTPS redirect aktif, kadaluarsa 2027-01-30. |
| **DNS Zone** | Host `staging-office` A (`153.92.8.139`) & AAAA | `PROPAGATED` | 13 record produksi lama (`@`, `www`, `journal`, `publisher`, MX, DKIM) 100% utuh. |
| **Website Produksi** | `p4ijournal.org`, `journal`, `publisher` | `TIDAK TERSENTUH` | Vhost dan berkas produksi 100% utuh. |
| **Database Produksi** | `u239415845_ojs`, `u239415845_publisher`, `publisher_new` | `TIDAK TERSENTUH` | Tabel dan data produksi 100% utuh. |

---

## 4. Evaluasi Gerbang Otorisasi & Hambatan Teknis

### 4.1 Hambatan Otorisasi Administratif
Sesuai Bagian A Master Prompt P1-C, otorisasi eksplisit dari Ketua/Pengurus Yayasan P4I untuk melakukan deployment kode ke server Hostinger belum diberikan. Implementer diinstruksikan melakukan pemeriksaan nonmutasi dan persiapan lokal saja sampai izin eksplisit tercatat.

### 4.2 Hambatan Akses Teknis Deployment
1. **Ketiadaan Kredensial SSH / Public Key:** Akses shell SSH ke `u239415845@153.92.8.139:65002` memerlukan penambahan SSH public key atau password akun hosting pada hPanel oleh pemilik akun.
2. **Keterbatasan Antarmuka MCP Hostinger:** Operasi `hosting_deploy` yang disediakan platform hanya menempatkan berkas ke dalam web root publik (`public_html/`), sedangkan spesifikasi arsitektur keamanan Bagian C mewajibkan inti Laravel ditaruh di luar `public_html` (`/home/u239415845/p4i-office-staging/`).
3. **Eksekusi Migrasi & CLI:** Menjalankan `php artisan migrate --force` dan seeder pada server Hostinger memerlukan akses terminal SSH atau eksekutor skrip CLI terotorisasi.

---

## 5. Prosedur Pembukaan Gerbang Menuju Eksekusi Deployment

Untuk membuka gerbang deployment dan mengeksekusi Tahap P1-C secara penuh, pemilik proyek (Ketua/Pengurus Yayasan P4I) perlu:
1. Memberikan pernyataan konfirmasi tertulis izin deployment P1-C.
2. Memasang SSH Public Key pada hPanel Hostinger (*Advanced -> SSH Access*), atau menyediakan akses deployment terkontrol.
3. Menyetujui eksekusi migrasi database, pembuatan trigger, dan pemasangan data seeder sintetis pada `u239415845_p4i_staging`.

---

## 6. Prosedur Rollback Cepat

Apabila proses deployment di masa mendatang mengalami kegagalan teknis:
1. **Rollback Web Root:** Kosongkan `/home/u239415845/domains/staging-office.p4ijournal.org/public_html` dan kembalikan berkas `default.php`.
2. **Rollback Inti Aplikasi:** Hapus direktori `/home/u239415845/p4i-office-staging/`.
3. **Rollback Database:** Reset skema database `u239415845_p4i_staging` menggunakan `DROP TABLE` pada seluruh tabel staging, atau buat ulang database kosong via API Hostinger.
4. **Jaminan Isolasi:** Tidak ada risiko terhadap website atau database produksi karena seluruh jalur direktori dan nama database terpisah total.
