# RENCANA DEPLOYMENT & ARSITEKTUR LINGKUNGAN STAGING HOSTINGER
## SPRINT M5A — PERSIAPAN STAGING LARAVEL TERISOLASI (TANPA EKSEKUSI SERVER)

**Sistem:** P4I Office v1.0  
**Target Hosting:** Hostinger Business Web Hosting  
**Penyusun Teknis:** Gemini (Lead Software Engineer)  
**Arsitek & Auditor Independen:** GPT-6  
**Otoritas Pemberi Izin:** Ketua dan Dewan Pengurus Yayasan P4I (Pemilik Proyek)  
**Status Pelaksanaan:** **PERENCANAAN LOKAL SELESAI — EKSEKUSI KE HOSTINGER BLOCKED (MENUNGGU IZIN EKSPLISIT)**  

---

## 1. PRINSIP KESELAMATAN & BATAS KEWENANGAN

> **PERINGATAN KERAS TATA KELOLA:**  
> Dokumen ini adalah rancangan teknis persiapan (*staging readiness blueprint*). **Tidak ada akses, perubahan konfigurasi, pembuatan database, atau pengunggahan berkas apa pun yang dilakukan ke akun Hostinger pemilik proyek.** Seluruh langkah pada dokumen ini hanya boleh dieksekusi setelah pemilik proyek (Ketua/Pengurus Yayasan P4I) memberikan izin tertulis eksplisit untuk lingkungan staging.

Mengapa fasilitas *Staging Otomatis* bawaan hPanel Hostinger **tidak digunakan**?
- Fitur *One-Click Staging* pada Hostinger dirancang khusus untuk instalasi CMS **WordPress**.
- P4I Office adalah aplikasi **Laravel 13 Custom** dengan struktur direktori di luar `public_html`, penyimpanan arsip privat (`storage/app/private_archives`), serta trigger imutabilitas MariaDB. Oleh karena itu, staging wajib dibangun menggunakan **Subdomain Khusus Terisolasi** dan **Database MariaDB Staging Terpisah**.

---

## 2. ARSITEKTUR DIREKTORI & ISOLASI DOCUMENT ROOT (GATE STG-06, STG-10)

Pada Hostinger Business Web Hosting, hanya direktori `public/` milik Laravel yang boleh diakses melalui protokol HTTP/HTTPS. Seluruh kode inti aplikasi (`app/`, `config/`, `database/`, `vendor/`, `.env`) dan arsip dokumen privat (`storage/app/private_archives/`) **wajib berada di luar document root publik**.

### Tata Letak Direktori pada Server Hostinger:
```
/home/<USER_HOSTINGER_PLACEHOLDER>/
├── p4i_staging_core/                       <-- DI LUAR WEB ROOT (Tidak dapat diakses via HTTP)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   │   ├── app/
│   │   │   └── private_archives/           <-- Penyimpanan PDF/DOCX Privat (Mode 0750)
│   │   ├── framework/
│   │   └── logs/
│   │       └── laravel.log                 <-- Log Harian Terisolasi
│   ├── vendor/
│   ├── .env                                <-- Mode 0600 (Hanya dibaca oleh proses PHP user)
│   └── artisan
│
└── domains/
    └── staging-office.<DOMAIN_P4I_PLACEHOLDER>/
        └── public_html/                    <-- DOCUMENT ROOT SUBDOMAIN STAGING
            ├── .htaccess                   <-- Pengamanan rute, HTTPS redirect & Basic Auth
            ├── .htpasswd                   <-- Proteksi akses lapisan pra-login staging
            ├── robots.txt                  <-- User-agent: * / Disallow: /
            ├── index.php                   <-- Menunjuk ke /home/<USER>/p4i_staging_core/
            └── build/                      <-- Aset statis terkompilasi Vite (CSS/JS)
```

### Pengikatan `index.php` di `public_html` ke `p4i_staging_core`:
Pada berkas `public_html/index.php` di subdomain staging:
```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../../../p4i_staging_core/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../../../p4i_staging_core/vendor/autoload.php';

$app = require_once __DIR__.'/../../../p4i_staging_core/bootstrap/app.php';

// Pastikan public_path() tetap menunjuk ke document root subdomain
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
```
Dengan pola ini:
1. Berkas `.env`, `storage/app/private_archives`, `database/`, dan `vendor/` berada 3 level di luar `public_html` dan **mustahil diakses langsung melalui URL browser**.
2. Aset statis Vite (`public_html/build/`) tetap disajikan secara cepat oleh web server LiteSpeed Hostinger.

---

## 3. KONFIGURASI ENVIRONMENT STAGING (`.env.staging.example`)

Konfigurasi staging wajib menggunakan `APP_KEY` baru yang berbeda dari pengembangan lokal maupun produksi, serta menonaktifkan tampilan jejak error (`APP_DEBUG=false`) agar tidak pernah membocorkan variabel konfigurasi atau struktur SQL ke layar pengguna:

```ini
APP_NAME="P4I Office (STAGING SIMULASI)"
APP_ENV=staging
APP_KEY=<GENERATE_VIA_PHP_ARTISAN_KEY_GENERATE_ON_STAGING>
APP_DEBUG=false
APP_URL=https://staging-office.<DOMAIN_P4I_PLACEHOLDER>

LOG_CHANNEL=daily
LOG_DAYS=14
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<DB_STAGING_NAME_PLACEHOLDER>
DB_USERNAME=<DB_STAGING_USER_PLACEHOLDER>
DB_PASSWORD=<DB_STAGING_PASSWORD_PLACEHOLDER>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

FILESYSTEM_DISK=local

# INVARIAN KESELAMATAN KERAS (WAJIB FALSE DI STAGING)
FEATURE_OFFICIAL_NUMBERING_ACTIVE=false
P4I_OFFICIAL_NUMBERING_ACTIVE=false
```

> **Catatan Keamanan:** Rute `/dev-login/{email}` pada `routes/web.php` dibungkus dalam `if (app()->environment('local'))`, sehingga ketika `APP_ENV=staging`, rute pintas login lokal tersebut **otomatis nonaktif**.

---

## 4. PENGENDALIAN AKSES STAGING & HTTPS/SSL (GATE STG-10)

Agar lingkungan staging yang aktif 24 jam tidak dapat diakses oleh publik atau diindeks mesin pencari:

1. **Sertifikat SSL/HTTPS Wajib:**
   - Aktifkan *Force HTTPS* pada hPanel Hostinger untuk subdomain staging.
   - Cookie sesi dikunci dengan `SESSION_SECURE_COOKIE=true` dan `SESSION_HTTP_ONLY=true`.
2. **Perlindungan Ganda (HTTP Basic Auth pada `.htaccess`):**
   Selain halaman login aplikasi P4I Office, direktori `public_html` staging dilindungi dengan HTTP Basic Authentication pada level web server LiteSpeed serta header anti-indeks:
   ```apache
   # Proteksi Mesin Pencari & Clickjacking
   <IfModule mod_headers.c>
       Header set X-Robots-Tag "noindex, nofollow, noarchive"
       Header always set X-Frame-Options "SAMEORIGIN"
       Header always set X-Content-Type-Options "nosniff"
       Header always set Referrer-Policy "strict-origin-when-cross-origin"
   </IfModule>

   # Blokir akses ke dot-files (.env, .git, .htpasswd)
   <FilesMatch "^\.">
       Require all denied
   </FilesMatch>
   ```
3. **Isolasi Data Sintetis:**
   Database staging (`<DB_STAGING_NAME_PLACEHOLDER>`) hanya boleh diisi dengan akun uji sintetis (`@staging.p4i.test`) dan naskah draf simulasi. Dilarang mengunggah data pribadi riil atau surat rahasia asli yayasan sebelum tahap produksi disahkan.

---

## 5. PROSEDUR DEPLOYMENT STAGING BERTAHAP (SAAT IZIN DIBERIKAN)

Setelah Ketua/Pengurus Yayasan P4I memberikan izin tertulis eksplisit, urutan eksekusi deployment ke Hostinger adalah sebagai berikut:

### Langkah 1: Persiapan Subdomain & Database di hPanel
1. Buat subdomain terpisah `staging-office.<DOMAIN_P4I_PLACEHOLDER>` dengan versi PHP **8.3** (atau 8.4).
2. Buat database MariaDB baru khusus staging beserta akun pengguna database yang hanya memiliki hak akses pada database staging tersebut (termasuk hak `TRIGGER`).

### Langkah 2: Pengunggahan Kode & Instalasi Dependensi via SSH
```bash
# 1. Masuk ke direktori home akun Hostinger via SSH
cd ~
mkdir -p p4i_staging_core

# 2. Tarik kode dari branch yang telah diaudit GPT-6
cd ~/p4i_staging_core
git clone --branch feat/sprint-m5a-staging-readiness <REPO_URL_PLACEHOLDER> .

# 3. Instal dependensi PHP produksi menggunakan Composer 2
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Siapkan konfigurasi .env staging dan kunci enkripsi
cp .env.example .env
# (Sesuaikan variabel DB staging dan APP_ENV=staging)
php artisan key:generate --force

# 5. Atur hak akses direktori penyimpanan privat
mkdir -p storage/app/private_archives
chmod -R 750 storage bootstrap/cache
chmod 600 .env
```

### Langkah 3: Eksekusi Migrasi, Preflight Check & Seeding Sintetis
```bash
# 1. Jalankan migrasi tabel dan trigger imutabilitas
php artisan migrate --force

# 2. Jalankan pemeriksaan preflight otomatis
php artisan p4i:staging-preflight

# 3. Isi data sintetis khusus pengujian UAT staging
php artisan db:seed --class=DatabaseSeeder --force

# 4. Cache konfigurasi, rute, dan tampilan Blade
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Langkah 4: Konfigurasi Cron Job Hostinger (GATE STG-12)
Tambahkan 1 entri Cron Job pada hPanel Hostinger (interval setiap 15 menit atau setiap menit untuk Laravel Scheduler) guna menjalankan rekonsiliasi ekspor yang terputus mendadak:
```cron
*/15 * * * * /usr/bin/php /home/<USER_HOSTINGER_PLACEHOLDER>/p4i_staging_core/artisan p4i:reconcile-exports --stale-minutes=15 >> /dev/null 2>&1
```

---

## 6. PEMANTAUAN ERROR TANPA KEBOCORAN SECRETS

1. `APP_DEBUG=false` wajib aktif setiap saat di lingkungan staging dan produksi. Ketika terjadi eksepsi, pengguna hanya melihat halaman HTTP 403/404/422/500 standar tanpa stack trace.
2. Log kesalahan dicatat ke `/home/<USER_HOSTINGER_PLACEHOLDER>/p4i_staging_core/storage/logs/laravel-YYYY-MM-DD.log` dengan rotasi 14 hari (`LOG_CHANNEL=daily`).
3. Tidak ada variabel kredensial yang dicetak ke dalam `audit_events` maupun log aplikasi.

