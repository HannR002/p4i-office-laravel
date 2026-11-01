# Bukti Teknis Provisioning Infrastruktur Hostinger Staging (Tahap P1-A)

**Proyek:** P4I Office v1.0  
**Tahap:** Otorisasi Terbatas Tahap P1-A (Hostinger Staging Provisioning)  
**Tanggal Persetujuan Pemilik Proyek:** 1 November 2026  
**Model Implementer (Lead Software Engineer & Technical Test Engineer):** Argon — Reasoning Medium  
**Tim Perancangan, Software Architect, Auditor & Penyusun Laporan Resmi:** GPT-6  
**Otoritas Administratif & Pemilik Proyek:** Ketua dan Pengurus Yayasan P4I  
**Target Infrastruktur:** Hostinger Business Web Hosting  
**Status Laporan:**  
**`STAGING INFRASTRUCTURE PROVISIONED — APPLICATION DEPLOYMENT NOT AUTHORIZED`**

---

## 1. Konfirmasi & Batasan Otorisasi Terbatas Pemilik Proyek

Sesuai instruksi otorisasi terbatas Tahap P1-A dari Ketua/Pengurus Yayasan P4I tertanggal 1 November 2026, implementer diberikan izin **hanya untuk 2 tindakan infrastruktur**:
1. Membuat 1 (satu) website/subdomain baru dan terpisah sebagai lingkungan staging P4I Office.
2. Membuat 1 (satu) database MariaDB dan 1 (satu) pengguna database khusus staging.

> [!IMPORTANT]
> **BATASAN KETAT OPERASIONAL (STRICT SAFETY BOUNDARIES):**
> - **DILARANG** mengubah website P4I yang sudah aktif, database lama/produksi, maupun konfigurasi aplikasi yang sedang berjalan.
> - **DILARANG** mengunggah berkas aplikasi, mengisi `.env` pada server, menjalankan migrasi database, membuat trigger pada server, memasang cron job scheduler, atau melakukan deployment kode pada tahap ini.
> - **DILARANG** menyimpan kata sandi, token, atau informasi sensitif dalam Git, chat, screenshot, maupun laporan (*Zero Credential Leakage*).
> - Seluruh aktivitas berhenti segera setelah dua sumber daya staging selesai dibuat dan diverifikasi.

---

## 2. Hasil Audit Hostinger (Tahap 1)

### 2.1 Status Konektor / MCP Hostinger
Pemeriksaan konektor MCP Hostinger menunjukkan status **TERSEDIA dan TERAUTENTIKASI**:
- `hostinger-hosting`: Aktif (operasi `hosting_websites_list`, `hosting_websites_start-setup`, `hosting_databases_create`, `hosting_databases_list` berfungsi normal).
- `hostinger-billing`: Aktif (operasi `billing_subscriptions_list` berfungsi normal).
- `hostinger-domains`: Aktif (operasi `domains_portfolio_list` berfungsi normal).
- `hostinger-dns`: Aktif (operasi `dns_records_list` berfungsi normal).

### 2.2 Verifikasi Paket Hosting dan Akun
Berdasarkan query aktual ke endpoint billing (`billing_subscriptions_list`) dan hosting (`hosting_websites_list`):
- **Paket Hosting:** `Business Web Hosting`
- **Subscription ID:** `16A2QoVNdOgJtYOE` (Status: `non_renewing`, Aktif s.d. 2027-06-26)
- **Order ID:** `1009584994`
- **Client ID:** `1021958446`
- **Username Akun Hosting:** `u239415845`
- **Server Internal Database Host:** `srv1980.hstgr.io` / `127.0.0.1:3306`

### 2.3 Verifikasi Domain Milik Proyek (Anti-Asumsi `p4i.or.id`)
Pemeriksaan portofolio domain (`domains_portfolio_list`) membuktikan bahwa domain yang benar-benar dimiliki dan aktif pada akun Hostinger pemilik proyek adalah:
- **Domain Resmi Terdaftar:** **`p4ijournal.org`** (Domain ID: `32328896`, TLD: `.org`, Aktif s.d. 2027-06-26).
- *Catatan Audit:* Domain `p4i.or.id` **TIDAK TERDAFTAR** pada akun Hostinger ini. Sesuai instruksi audit butir 5, implementer tidak mengasumsikan `p4i.or.id` dan secara sah menggunakan basis domain `p4ijournal.org`.

### 2.4 Audit Subdomain dan Website Eksisting (Pencegahan Duplikasi)
Daftar website yang telah aktif sebelum provisioning:
1. `p4ijournal.org` (vhost `main`, root: `/home/u239415845/domains/p4ijournal.org/public_html`)
2. `journal.p4ijournal.org` (vhost `subdomain`, root: `/home/u239415845/domains/p4ijournal.org/public_html`)
3. `publisher.p4ijournal.org` (vhost `subdomain`, root: `/home/u239415845/domains/p4ijournal.org/public_html/publisher_link`)
4. `slategray-cod-828424.hostingersite.com` (vhost `addon`, root: `/home/u239415845/domains/slategray-cod-828424.hostingersite.com/public_html`)

### 2.5 Audit Database Eksisting
Daftar database yang telah aktif sebelum provisioning:
1. `u239415845_ojs` (User: `u239415845_user`, Domain: `p4ijournal.org`, Disk: 100 MB)
2. `u239415845_publisher` (User: `u239415845_publisher`, Domain: `p4ijournal.org`, Disk: 4 MB)
3. `u239415845_publisher_new` (User: `u239415845_publisher1`, Domain: `p4ijournal.org`, Disk: 4 MB)

---

## 3. Hasil Pembuatan Website Staging (Tahap 2)

Sesuai pola yang direkomendasikan pemilik proyek (`staging-office.<domain-terverifikasi>`), website staging dibuat sebagai **independent website (vhost addon)** pada hosting order `1009584994`:

| Parameter | Nilai Konfigurasi Aktual | Catatan Verifikasi |
| :--- | :--- | :--- |
| **Nama Domain Staging** | **`staging-office.p4ijournal.org`** | Bebas konflik / belum pernah digunakan sebelumnya |
| **Tipe Vhost** | `addon` (*Independent Website*) | Dibuat via `hosting_websites_start-setup` |
| **Root Directory** | **`/home/u239415845/domains/staging-office.p4ijournal.org/public_html`** | **Terpisah 100% dari direktori website produksi** (`/domains/p4ijournal.org/public_html`) |
| **Parent Domain** | `null` | Berdiri sendiri, bukan subdirektori di bawah website aktif |
| **Status Vhost** | `is_enabled: true` | Setup selesai dalam status `completed` |
| **Waktu Pembuatan** | 2026-11-01 16:48:05 UTC | Verifikasi setup selesai 2026-11-01 16:48:08 UTC |

### Verifikasi Dampak DNS Zone
Pemeriksaan zona DNS `p4ijournal.org` pasca-pembuatan website:
- **Record Baru yang Ditambahkan Otomatis:**
  - `staging-office` (A) -> `153.92.8.139` (TTL 1800)
  - `staging-office` (AAAA) -> `2a02:4780:6:2080:0:e45:3225:4` (TTL 1800)
  - `www.staging-office` (CNAME) -> `staging-office.p4ijournal.org.` (TTL 300)
  - `ftp.staging-office` (A) -> `153.92.8.139` (TTL 1800)
- **Status 13 Record DNS Produksi Lama:**
  - Record `@` (A: `216.198.79.1`), `www` (CNAME: `cname.vercel-dns.com.`), `journal` (ALIAS), `publisher` (ALIAS), `ftp` (A), MX records (`mx1.hostinger.com`, `mx2.hostinger.com`), SPF, DKIM (a, b, c), DMARC, dan autodiscover **100% UTUH dan TIDAK MENGALAMI PERUBAHAN APAPUN**.

---

## 4. Hasil Pembuatan Database Staging (Tahap 3)

Database MariaDB khusus staging dibuat melalui operasi `hosting_databases_create` dengan isolasi penuh:

| Parameter | Nilai Konfigurasi Aktual | Catatan Keamanan |
| :--- | :--- | :--- |
| **Nama Database** | **`u239415845_p4i_staging`** | Memakai prefiks resmi akun `u239415845_` |
| **Nama Pengguna (User)** | **`u239415845_p4i_staging`** | Pengguna baru terisolasi, bukan user produksi |
| **Website yang Ditugaskan** | `staging-office.p4ijournal.org` | Dibatasi hanya untuk domain staging |
| **Kata Sandi Pengguna** | *[TERENKRIPSI / DIRAHASIAKAN]* | Kata sandi acak 24 karakter entropi tinggi, tidak dicatat di Git/laporan |
| **Host Database Internal** | `127.0.0.1:3306` (atau `localhost`) | Sesuai panduan arsitektur Hostinger |
| **Host Database Eksternal** | `srv1980.hstgr.io:3306` | Khusus koneksi jarak jauh terotorisasi |
| **Alokasi Kuota Disk** | `3072 MB` (3 GB) | `disk_usage_mb: 1 MB` (bersih, belum ada tabel) |
| **Waktu Pembuatan** | 2026-11-01 16:48:46 UTC | Status aktif terverifikasi pada query database list |

### Verifikasi Hak Istimewa Pengguna Database (Privileges)
Pengguna `u239415845_p4i_staging` memiliki hak lengkap yang dibatasi secara ketat hanya pada database `u239415845_p4i_staging`:
- `Trigger: 1` — **Wajib dan aktif**, memastikan 4 trigger immutability (`trg_letter_revisions_no_update`, `trg_letter_revisions_no_delete`, `trg_audit_events_no_update`, `trg_audit_events_no_delete`) dapat dipasang saat migrasi diizinkan nanti.
- `Select: 1`, `Insert: 1`, `Update: 1`, `Delete: 1`, `Create: 1`, `Drop: 1`, `Index: 1`, `Alter: 1`, `References: 1`, `Lock tables: 1`, `Create temporary tables: 1`, `Create routine: 1`, `Alter routine: 1`, `Execute: 1`, `Create view: 1`, `Show view: 1`, `Event: 1`.
- Pengguna ini **TIDAK MEMILIKI HAK AKSES** ke database `u239415845_ojs`, `u239415845_publisher`, maupun `u239415845_publisher_new`.

---

## 5. Matriks Verifikasi Isolasi & Integritas Lingkungan (Tahap 4)

| Item Pemeriksaan | Target Isolasi | Hasil Aktual | Status |
| :--- | :--- | :--- | :---: |
| **Pendaftaran Website Staging** | Terdaftar pada akun hosting `u239415845` | Terdaftar sebagai vhost addon independen dengan domain `staging-office.p4ijournal.org` | `PASS` |
| **Pemisahan Direktori Staging** | Berada di luar direktori website produksi | `/home/u239415845/domains/staging-office.p4ijournal.org/public_html` terpisah total dari `/domains/p4ijournal.org/public_html` | `PASS` |
| **Ketersediaan Database Staging** | Database MariaDB baru siap pakai | `u239415845_p4i_staging` aktif dengan hak `Trigger` dan kuota 3 GB | `PASS` |
| **Pengguna Database Khusus** | Akun pengguna baru dengan hak terbatas | `u239415845_p4i_staging` dibuat tanpa akses ke database lain | `PASS` |
| **Integritas Website Lama** | Tidak ada modifikasi vhost/file produksi | `p4ijournal.org`, `journal`, dan `publisher` tidak disentuh | `PASS` |
| **Integritas Database Lama** | Tidak ada modifikasi tabel/data produksi | `u239415845_ojs`, `u239415845_publisher`, `u239415845_publisher_new` utuh | `PASS` |
| **Integritas DNS Record Lama** | 13 DNS record produksi tetap utuh | Seluruh A, CNAME, MX, SPF, DKIM, DMARC produksi tidak berubah | `PASS` |
| **Beban Biaya / Tagihan** | Tanpa biaya tambahan atau upgrade paket | Dibuat dalam kuota paket Business Web Hosting aktif (`Rp 0`) | `PASS` |
| **Kepatuhan Larangan Deployment** | Tidak mengunggah kode / migrasi / cron | Belum ada berkas aplikasi, `.env`, tabel, trigger, atau cron job di server | `PASS` |

---

## 6. Status Langkah Lanjutan yang Masih Membutuhkan Otorisasi

Seluruh infrastruktur dasar staging telah siap. Namun, sesuai pembagian wewenang tata kelola proyek, implementer **BERHENTI PADA TITIK INI** dan menunggu izin resmi berikutnya dari Ketua/Pengurus Yayasan P4I untuk langkah-langkah di bawah ini:

| Langkah Lanjutan | Status Otorisasi | Persyaratan Sebelum Eksekusi |
| :--- | :---: | :--- |
| **1. Pengunggahan Berkas Rilis Aplikasi ke Server** | `BELUM DIIZINKAN` (`BLOCKED`) | Memerlukan izin tertulis pengunggahan paket aplikasi ke `/home/u239415845/p4i-office/`. |
| **2. Konfigurasi Berkas `.env` Staging pada Server** | `BELUM DIIZINKAN` (`BLOCKED`) | Memerlukan izin penempatan kredensial DB staging dan pembangkitan `APP_KEY` unik. |
| **3. Eksekusi Migrasi & Trigger Immutability pada DB Staging** | `BELUM DIIZINKAN` (`BLOCKED`) | Memerlukan izin eksekusi `php artisan migrate --force` pada `u239415845_p4i_staging`. |
| **4. Pemasangan Data Sintetis Seeder UAT** | `BELUM DIIZINKAN` (`BLOCKED`) | Memerlukan izin eksekusi `StagingUatSeeder` berisi akun uji dan templat simulasi. |
| **5. Pendaftaran Cron Job Scheduler di hPanel** | `BELUM DIIZINKAN` (`BLOCKED`) | Memerlukan izin pendaftaran cron `* * * * * php artisan schedule:run`. |
| **6. Pelaksanaan UAT Manual oleh Staf/Pengurus P4I** | `BELUM DIIZINKAN` (`BLOCKED`) | Menunggu aplikasi ter-deploy dan peserta UAT manusia ditugaskan oleh pengurus. |

---

## 7. Pernyataan Penutup Implementer

Provisioning dua sumber daya infrastruktur staging (website independen `staging-office.p4ijournal.org` dan database MariaDB `u239415845_p4i_staging`) telah diselesaikan secara presisi, terisolasi, dan aman tanpa menyentuh aset produksi. Dokumen bukti teknis ini diserahkan kepada **GPT-6** untuk evaluasi arsitektur dan audit independen, serta kepada **Ketua dan Pengurus Yayasan P4I** untuk keputusan otorisasi tahap deployment berikutnya.
