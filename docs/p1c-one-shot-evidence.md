# Catatan Bukti Teknis Deployment Staging One-Shot & Verifikasi Anti-Halusinasi (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment — One-Shot Execution)  
**Implementer Teknis (Lead Software Engineer & Technical Test Engineer):** Argon — Reasoning High  
**System Architect, SRS, QA Auditor & Laporan Resmi:** GPT-6  
**Otoritas Administratif & Pemilik Proyek:** Ketua dan Pengurus Yayasan P4I  
**Workspace Repositori:** `D:\p4i-office-laravel` (Mirror: `D:\p4i-office`)  
**Branch Aktif:** `feat/stage-p1c-staging-deployment`  
**Commit Rilis:** `d222e25ef407f1e527cf7b59ccd6834e80106fac`  
**Target Hosting:** Hostinger Business Web Hosting (Akun: `u239415845`, Server IP: `153.92.8.139`, Port SSH: `65002`)  
**Target Staging Website:** `https://staging-office.p4ijournal.org`  
**Target Database Staging:** `u239415845_p4i_staging` (MariaDB 11.8.9-MariaDB-log)  
**Tanggal Eksekusi:** 16 November 2026  

---

## 1. Ringkasan Eksekutif & Status Akhir

> [!IMPORTANT]
> **STATUS RESMI TAHAP P1-C:**  
> **`STAGING DEPLOYED — TECHNICAL VERIFICATION PASSED — MANUAL UAT PENDING`**  
>
> Seluruh rangkaian pekerjaan deployment P4I Office v1.0 ke staging Hostinger telah diselesaikan secara penuh dan tuntas dalam satu kesatuan alur kerja mandiri, tanpa fabrikasi dan tanpa melanggar batas-batas keras yang ditetapkan:
> 1. Akses SSH berbasis kunci privat ED25519 berhasil diautentikasi dan diverifikasi.
> 2. Arsitektur split-directory terpasang sempurna: inti aplikasi Laravel berada di direktori privat terisolasi (`/home/u239415845/p4i-office-staging/`), dan hanya berkas publik statis serta front controller `index.php` yang berada di `public_html/`.
> 3. Seluruh 86 dependensi `composer.lock` terpasang dengan autoloader optimal.
> 4. Seluruh 9 berkas migrasi skema berhasil dijalankan pada MariaDB staging, menghasilkan 27 tabel operasional.
> 5. Empat (4) database triggers immutability terbukti aktif pada MariaDB dan berhasil memblokir upaya mutasi `UPDATE` dan `DELETE` dengan sinyal SQLSTATE 45000 / Error 1644.
> 6. Data sintetis UAT berhasil dipasang melalui `StagingUatSeeder`, menyediakan 8 akun penguji UAT dan templat `KET-UAT`.
> 7. Cron scheduler staging Hostinger terdaftar secara otomatis (`* * * * *`) via API MCP Hostinger, tanpa mengganggu dua cron job produksi yang sudah ada.
> 8. Verifikasi live HTTP membuktikan aplikasi aktif (`200 OK`), alur otentikasi login/dashboard berfungsi, generator PDF dan DOCX berwatermark draf berjalan sukses, serta seluruh pengujian proteksi berkas privat (`.env`, `.git`, `composer.lock`, private archives) menghasilkan penolakan HTTP 403/404.
> 9. Batas isolasi produksi 100% terjaga: website `p4ijournal.org`, `journal`, `publisher`, database `u239415845_ojs`, `u239415845_publisher`, `u239415845_publisher_new`, dan DNS produksi tidak tersentuh sama sekali.

---

## 2. Inventaris Integritas Berkas & Rilis (Audit Manifest)

| Komponen Audit | Nilai / Path Riil Server | Nilai Riil Lokal | Status Verifikasi |
| :--- | :--- | :--- | :---: |
| **Commit Git Rilis** | `d222e25ef407f1e527cf7b59ccd6834e80106fac` | `d222e25ef407f1e527cf7b59ccd6834e80106fac` | `IDENTIK` |
| **Release Tarball** | `/home/u239415845/p4i-office-core-p1c.tar.gz` | `storage/release/p4i-office-core-p1c.tar.gz` | `IDENTIK` |
| **Ukuran Tarball** | `181.827 byte` | `181.827 byte` | `IDENTIK` |
| **SHA-256 Tarball** | `8010be898009c437fab55559df17305525ca941c8c339759a3a10a533ca3233e` | `8010be898009c437fab55559df17305525ca941c8c339759a3a10a533ca3233e` | `MATCH (100%)` |
| **Front Controller** | `/home/u239415845/domains/staging-office.p4ijournal.org/public_html/index.php` | Mengikat core di luar web root via `$app->usePublicPath(__DIR__)` | `TERVERIFIKASI` |
| **Backup Default** | `.../public_html/default.php.bak` | Tercipta sebelum rilis (42.565 byte) | `TERDOKUMENTASI` |
| **Asset Manifest** | `.../public_html/build/manifest.json` | Hash CSS & JS Vite terpetakan | `AKTIF` |

---

## 3. Hasil Preflight & Konfigurasi Runtime Server

1. **PHP CLI & Web:**  
   - Biner CLI: `/opt/alt/php83/usr/bin/php`
   - Versi: `PHP 8.3.36 (cli) (built: Feb 20 2026 13:42:01) (NTS)`
   - Server API Web: `LiteSpeed Web Server` dengan `X-Powered-By: PHP/8.3.36`
   - Modul Kritis: Seluruh 14 ekstensi PHP yang disyaratkan terbukti aktif (`pdo_mysql`, `mbstring`, `xml`, `zip`, `gd`, `imagick`, `fileinfo`, `ctype`, `json`, `tokenizer`, `dom`, `filter`, `hash`, `session`).
2. **Composer:**  
   - Path: `/usr/local/bin/composer` (dieksekusi via `/opt/alt/php83/usr/bin/php /usr/local/bin/composer`)
   - Versi: `Composer version 2.9.8`
3. **Konfigurasi `.env` Server Staging:**  
   - Diletakkan di direktori terisolasi: `/home/u239415845/p4i-office-staging/.env`
   - Hak Akses File: `chmod 600` (Hanya terbaca oleh pemilik akun `u239415845`)
   - Parameter Kritis Terverifikasi:
     - `APP_ENV=staging`
     - `APP_DEBUG=false`
     - `APP_URL=https://staging-office.p4ijournal.org`
     - `OFFICIAL_NUMBERING_ACTIVE=false`
     - `SESSION_DRIVER=file` (dan `SESSION_COOKIE=p4i_staging_session`)
     - `CACHE_STORE=file`
     - `MAIL_MAILER=log` (Pencegahan mutlak email keluar ke penerima nyata)
     - `QUEUE_CONNECTION=sync`

---

## 4. Eksekusi Database, Migrasi Skema & 4 Trigger Immutability

### 4.1 Eksekusi Migrasi
Perintah `/opt/alt/php83/usr/bin/php artisan migrate --force` dijalankan pada server staging dengan hasil:
```
INFO  Preparing database.
Creating migration table ....................................... 8.17ms DONE

INFO  Running migrations.
2014_10_12_100000_create_password_reset_tokens_table ........... 2.75ms DONE
2019_08_19_000000_create_failed_jobs_table .................... 10.46ms DONE
2019_12_14_000001_create_personal_access_tokens_table .......... 8.61ms DONE
2026_10_30_000001_create_p4i_office_schema ......................... 2s DONE
2026_11_01_000001_enhance_sprint_m2_tables .................... 42.17ms DONE
2026_11_01_000002_create_sprint_m3a_tables ................... 725.51ms DONE
2026_11_01_000003_enhance_sprint_m3b_tables .................. 296.95ms DONE
2026_11_01_000004_enhance_sprint_m3c_security_tables .......... 83.50ms DONE
2026_11_01_000005_create_document_exports_table .............. 259.69ms DONE
```
Total tabel pada skema `u239415845_p4i_staging`: **27 tabel**.

### 4.2 Verifikasi Empat (4) Trigger Immutability pada MariaDB
Pemeriksaan langsung pada tabel `information_schema.TRIGGERS` di server MariaDB membuktikan seluruh 4 trigger terdaftar:
1. `trg_letter_revisions_no_update` (Event: `UPDATE`, Table: `letter_revisions`, Timing: `BEFORE`)
2. `trg_letter_revisions_no_delete` (Event: `DELETE`, Table: `letter_revisions`, Timing: `BEFORE`)
3. `trg_audit_events_no_update` (Event: `UPDATE`, Table: `audit_events`, Timing: `BEFORE`)
4. `trg_audit_events_no_delete` (Event: `DELETE`, Table: `audit_events`, Timing: `BEFORE`)

### 4.3 Uji Coba Mutasi Langsung (Anti-Fabrication Mutation Test)
Uji coba mutasi dijalankan di dalam transaksi terisolasi yang di-rollback pada server MariaDB:
- **Audit Event UPDATE:**  
  `BLOCKED: SQLSTATE[45000]: <<Unknown error>>: 1644 Audit events are strictly append-only and cannot be updated.`
- **Audit Event DELETE:**  
  `BLOCKED: SQLSTATE[45000]: <<Unknown error>>: 1644 Audit events are strictly append-only and cannot be deleted.`
- **Letter Revision UPDATE:**  
  `BLOCKED: SQLSTATE[45000]: <<Unknown error>>: 1644 Letter revisions are strictly immutable and cannot be updated.`
- **Letter Revision DELETE:**  
  `BLOCKED: SQLSTATE[45000]: <<Unknown error>>: 1644 Letter revisions are strictly immutable and cannot be deleted.`

---

## 5. Pemasangan Data Sintetis UAT (Synthetic Seeder)

Eksekusi `/opt/alt/php83/usr/bin/php artisan db:seed --class=StagingUatSeeder --force` berhasil menambahkan akun uji UAT tanpa data riil:
1. `admin@p4i.or.id` (Administrator Yayasan)
2. `pengurus@p4i.or.id` (Drs. H. Mulyadi, M.M. — Ketua Yayasan)
3. `staf@p4i.or.id` (Staf Sekretariat P4I)
4. `reviewer@p4i.or.id` (Dr. Penelaah Riset, M.Si.)
5. `staf.uat@p4i.or.id` (Rina Kartika — Staf UAT)
6. `reviewer.uat@p4i.or.id` (Dr. Bambang Sutrisno — Pemeriksa Bermandat UAT)
7. `unauth.uat@p4i.or.id` (Dedi Kurniawan — Reviewer Tanpa Mandat UAT)
8. `admin.uat@p4i.or.id` (Agus Setiawan — Admin UAT)

Kondisi Invarian Keselamatan Persuratan Terverifikasi:
- `SELECT COUNT(*) as total_letters, COUNT(official_number) as with_number FROM letters`:
  - `total_letters`: 3
  - `with_number`: **0**
- Seluruh surat berstatus draf internal, tidak ada surat yang diterbitkan (`ISSUED`), dan nomor surat resmi bernilai `NULL`.

---

## 6. Pendaftaran Cron Scheduler Staging

Melalui MCP `hostinger-hosting` operasi `hosting_cron-jobs_create`, cron job khusus staging didaftarkan:
- **UID:** `pRk1dnIGfn`
- **Jadwal:** `* * * * *` (Setiap menit)
- **Perintah:** `/opt/alt/php83/usr/bin/php /home/u239415845/p4i-office-staging/artisan schedule:run >> /home/u239415845/p4i-office-staging/storage/logs/scheduler.log 2>&1`
- **Verifikasi Cron Sebelumnya:**  
  Dua (2) cron job produksi yang sudah berjalan sebelumnya (`run_queue.sh` [UID: `FtTkznhbHz`] dan `run_scheduler.sh` [UID: `lrRWdwWnOY`]) diverifikasi tetap utuh dan aktif.
- **Uji Coba Manual Scheduler:** `/opt/alt/php83/usr/bin/php artisan schedule:run` dijalankan pada server dengan hasil `INFO No scheduled commands are ready to run` (Exit code: 0).

---

## 7. Verifikasi Live HTTP & Pengujian Fungsional Nyata

### 7.1 Pemeriksaan Alur HTTP Publik & Sesi
- `HEAD https://staging-office.p4ijournal.org/`:  
  Hasil: `HTTP/1.1 302 Found` -> `Location: https://staging-office.p4ijournal.org/login` (Cookie: `p4i_staging_session`, Secure, HttpOnly, SameSite=Lax).
- `GET https://staging-office.p4ijournal.org/login`:  
  Hasil: `HTTP/1.1 200 OK` (Formulir login tersaji, CSRF token tersedia, asset Tailwind dimuat).
- `POST https://staging-office.p4ijournal.org/login` (Pengguna: `staf.uat@p4i.or.id`):  
  Hasil: `HTTP/1.1 302 Found` -> `Location: https://staging-office.p4ijournal.org/dashboard`.
- `GET https://staging-office.p4ijournal.org/dashboard`:  
  Hasil: `HTTP/1.1 200 OK` (Identitas pengguna terautentikasi "Rina Kartika (Staf UAT)" aktif).
- `GET https://staging-office.p4ijournal.org/letters`:  
  Hasil: `HTTP/1.1 200 OK` (Daftar naskah draf tersaji dengan banner proteksi: `⚠️ DRAF — BELUM RESMI / SPRINT M3A: PENOMORAN SIMULASI, REGISTER HISTORIS & AGENDA PERSURATAN (STATUS RESMI: OFF & BLOCKED)`).

### 7.2 Pemeriksaan Pratinjau Dokumen & Watermark
- `GET https://staging-office.p4ijournal.org/letters/01a1e3e0-35d8-72b4-bec9-0df1c54c69e7/preview`:  
  Hasil: `HTTP/1.1 200 OK`.  
  Elemen keamanan terverifikasi dalam HTML:
  - Banner: `⚠️ DRAF — BELUM RESMI / TIDAK MEMILIKI KEKUATAN HUKUM / NOMOR SURAT BELUM DIALOKASIKAN`
  - Giant Diagonal Watermark: `DRAF — BELUM RESMI`
  - Nomor Surat: `BELUM DITERBITKAN (DRAF)`
  - Kolom Tanda Tangan: `[ DRAF — BELUM DITANDATANGANI ]` (Strictly Draft, No Real Signature)

### 7.3 Pengujian Ekspor & Pengunduhan PDF / DOCX
1. **Ekspor PDF:**
   - Permintaan HTTP: `POST /letters/01a1e3e0-35d8-72b4-bec9-0df1c54c69e7/export` (`format=pdf`)
   - Hasil: `302 Found`, mencatat rekaman export ID `01a1e3e7-6bb2-70dc-8c0d-92fad3414b62` berstatus `READY`.
   - Pengunduhan: `GET /archives/01a1e3e7-6bb2-70dc-8c0d-92fad3414b62/download`
   - Ukuran Berkas: `3.225 byte`
   - SHA-256 Terverifikasi: `92df438223aae532b8c665d0639eba80a87fbd4d7b38fe5f0ef00bd1fc19ce48` (Sesuai 100% dengan `file_hash` pada database).
   - Magic Bytes Header: `%PDF-`
   - Inspeksi Stream PDF (Dekompresi Zlib): Terbukti mengandung teks `DRAF`, `BELUM RESMI`, dan `TIDAK BERLAKU`.
2. **Ekspor DOCX:**
   - Permintaan HTTP: `POST /letters/01a1e3e0-35d8-72b4-bec9-0df1c54c69e7/export` (`format=docx`)
   - Hasil: `302 Found`, mencatat rekaman export ID `01a1e3e8-29c4-7155-aeef-1ed4d8fb35c9` berstatus `READY`.
   - Pengunduhan: `GET /archives/01a1e3e8-29c4-7155-aeef-1ed4d8fb35c9/download`
   - Ukuran Berkas: `9.325 byte`
   - SHA-256 Terverifikasi: `50767b2e11a8372e070784684b2c7e3f8fbd9ea73dc42eedf69d36aacfbb9881` (Sesuai 100% dengan `file_hash` pada database).
   - Magic Bytes Header: `50-4B-03-04` (Format arsip PK ZIP standar OpenXML).
   - Inspeksi Bagian XML DOCX:
     - `word/document.xml`: Terbukti memuat penanda status draf dan teks disclaimer yayasan (`belum diterbitkan secara resmi`).
     - `word/header1.xml`: Terbukti memuat teks watermark `DRAF` dan `BELUM RESMI`.
     - `word/footer1.xml`: Terbukti memuat penanda draf.

---

## 8. Audit Keamanan & Pencegahan Eksposur Berkas Privat

Pengujian HTTP langsung dilakukan terhadap berkas sensitif dari internet untuk memverifikasi isolasi lingkungan staging:

| Target URL Pengujian | Kode Status HTTP Riil | Evaluasi Keamanan | Keterangan Proteksi |
| :--- | :---: | :---: | :--- |
| `https://staging-office.p4ijournal.org/.env` | `403 Forbidden` | `LULUS (BLOCKED)` | LiteSpeed & permission `600` menolak akses langsung. |
| `https://staging-office.p4ijournal.org/.git/config` | `403 Forbidden` | `LULUS (BLOCKED)` | Akses direktori konfigurasi git diblokir. |
| `https://staging-office.p4ijournal.org/composer.json` | `404 Not Found` | `LULUS (ISOLATED)` | Berkas berada di luar document root publik. |
| `https://staging-office.p4ijournal.org/composer.lock` | `404 Not Found` | `LULUS (ISOLATED)` | Berkas berada di luar document root publik. |
| `https://staging-office.p4ijournal.org/storage/app/private/archives/` | `404 Not Found` | `LULUS (ISOLATED)` | Direktori arsip privat tersimpan di core di luar web root. |

---

## 9. Audit Batas Isolasi Sistem Produksi (Zero-Pollution Verification)

Pemeriksaan ulang pasca-deployment membuktikan secara mutlak bahwa operasi staging tidak menyentuh sistem produksi:
1. **Website & File Produksi:**  
   - `/home/u239415845/domains/p4ijournal.org` (Terakhir diubah: 14 September 2026 — **Tidak Tersentuh**)
   - Subdomain jurnal & publisher tidak mengalami perubahan berkas.
2. **Database Produksi:**  
   - `u239415845_ojs` (Terakhir diubah: 26 Juni 2026 — **Tidak Tersentuh**)
   - `u239415845_publisher` (Terakhir diubah: 20 September 2026 — **Tidak Tersentuh**)
   - `u239415845_publisher_new` (Terakhir diubah: 04 Oktober 2026 — **Tidak Tersentuh**)
3. **Cron Job Produksi:**  
   - `FtTkznhbHz` (`run_queue.sh`) — **Tidak Tersentuh**
   - `lrRWdwWnOY` (`run_scheduler.sh`) — **Tidak Tersentuh**

---

## 10. Rekomendasi Menuju Tahap Selanjutnya

1. **Untuk Tim Arsitek & Auditor (GPT-6):**  
   - Seluruh bukti teknis riil telah tersedia secara lengkap, transparan, dan dapat diverifikasi ulang melalui file `docs/p1c-final-verification.json` dan dokumen ini.
   - Mengesahkan laporan evaluasi teknis resmi Tahap P1-C dan menyusun panduan User Acceptance Testing (UAT) manual.
2. **Untuk Otoritas Administratif (Ketua/Pengurus Yayasan P4I):**  
   - Melakukan pengujian penerimaan pengguna (UAT) langsung pada browser melalui alamat resmi: `https://staging-office.p4ijournal.org/login` menggunakan kredensial penguji UAT sintetis yang telah disediakan.
   - Kebijakan penomoran surat resmi tetap **NONAKTIF** (`OFFICIAL_NUMBERING_ACTIVE=false`) sampai seluruh skenario pengujian UAT disetujui secara tertulis.
