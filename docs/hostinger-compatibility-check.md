# CHECKLIST & SPESIFIKASI KOMPATIBILITAS HOSTINGER BUSINESS WEB HOSTING
## P4I Office v1.0 — Sprint M0.1: Target Infrastructure Verification

- **Penyusun:** Gemini Flash 3.8 — Lead Software Engineer
- **Auditor & Reviewer:** GPT-6
- **Otoritas Administratif:** Ketua / Pengurus Yayasan P4I
- **Target Deployment:** Hostinger Business Web Hosting
- **Tanggal Audit:** 31 Oktober 2026

---

### 1. Profil Lingkungan Hosting Target

| Parameter | Spesifikasi Hostinger Business Web Hosting | Kebutuhan Laravel 13 & MariaDB | Status Evaluasi |
| :--- | :--- | :--- | :---: |
| **Web Server** | LiteSpeed Enterprise Web Server | HTTP/2, HTTPS, Rewrite Rules | **KOMPATIBEL** |
| **Sistem Operasi** | CloudLinux OS | Isolasi resource LVE, CageFS | **KOMPATIBEL** |
| **Versi PHP** | Tersedia PHP 8.2, **PHP 8.3**, PHP 8.4 via hPanel PHP Selector | **PHP ^8.3** (Wajib Laravel 13) | **KOMPATIBEL** |
| **Mesin Database** | MariaDB 10.6+ / 10.11+ LTS InnoDB | MariaDB 10.4+ InnoDB dengan Triggers | **KOMPATIBEL** |
| **Manajemen DB** | phpMyAdmin via hPanel | GUI administrasi schema & data | **KOMPATIBEL** |
| **Akses Console** | SSH Access + Git + Composer 2.x | Deployment CLI, migrations, caching | **KOMPATIBEL** |
| **Tugas Terjadwal** | Cron Job (Frekuensi minimal 1 menit) | `schedule:run` untuk housekeeping | **KOMPATIBEL** |
| **SSL / HTTPS** | Free Let's Encrypt Wildcard SSL | Enkripsi end-to-end HTTPS | **KOMPATIBEL** |

---

### 2. Matriks Ekstensi PHP 8.3 Hostinger

Seluruh ekstensi yang dibutuhkan Laravel 13 dan modul persuratan P4I Office tersedia dan didukung pada Hostinger Business Web Hosting:

| Ekstensi PHP | Fungsi pada P4I Office | Ketersediaan di Hostinger |
| :--- | :--- | :---: |
| `pdo_mysql` | Koneksi database MariaDB / MySQL InnoDB | Aktif (Default) |
| `mbstring` | Pemrosesan multibyte string, surat, dan UTF-8 | Aktif (Default) |
| `openssl` | Enkripsi AES-256 session, cookies, dan token | Aktif (Default) |
| `curl` | HTTP Client untuk webhook yayasan | Aktif (Default) |
| `fileinfo` | Validasi MIME type lampiran draf surat | Aktif (Default) |
| `gd` | Pemrosesan gambar stempel draf dan kop | Aktif (Default) |
| `intl` | Format tanggal persuratan Indonesia (Carbon locale `id`) | Aktif (Default) |
| `sodium` | Kriptografi modern password hashing | Aktif (Default) |
| `zip` | Ekspor arsip surat dan bundling lampiran | Aktif (Default) |
| `bcmath` | Kalkulasi presisi dan hashing UUID | Aktif (Default) |

---

### 3. Isolasi Struktur Direktori & Keamanan Document Root

Pada paket shared web hosting seperti Hostinger Business, Document Root publik default adalah `public_html/`. Menempatkan seluruh proyek Laravel di dalam `public_html/` adalah **pelanggaran keamanan kritis** karena berisiko membocorkan file `.env` dan kode sumber aplikasi.

#### Rancangan Struktur Isolasi Direktori:
```text
/home/u123456789/
├── domains/
│   └── p4i.or.id/
│       ├── p4i-office-core/             <── CORE LARAVEL 13 (Di Luar Web Root)
│       │   ├── app/
│       │   ├── bootstrap/
│       │   ├── config/
│       │   ├── database/
│       │   ├── routes/
│       │   ├── storage/                 <── Private storage (Draf Surat)
│       │   ├── vendor/
│       │   ├── .env                     <── AMAN: Tidak dapat diakses browser
│       │   ├── artisan
│       │   └── composer.json
│       │
│       └── public_html/                 <── HANYA DIRECTORY PUBLIC/
│           ├── index.php                <── Menunjuk ke ../p4i-office-core/
│           ├── build/                   <── Asset terkompilasi Vite (CSS/JS)
│           ├── favicon.ico
│           ├── robots.txt
│           └── .htaccess                <── Rewrite rules LiteSpeed & Security Headers
```

#### Modifikasi File `public_html/index.php`:
```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Register maintenance mode
if (file_exists($maintenance = __DIR__.'/../p4i-office-core/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register Autoloader
require __DIR__.'/../p4i-office-core/vendor/autoload.php';

// Bootstrap Laravel
/** @var Application $app */
$app = require_once __DIR__.'/../p4i-office-core/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

---

### 4. LiteSpeed `.htaccess` & Security Headers

Untuk menjamin kinerja dan proteksi terhadap serangan web umum pada Hostinger:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Paksa HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

---

### 5. Penjadwalan Tugas (Cron Job) & Background Processing

Hostinger Business Web Hosting tidak menyediakan *long-running daemon supervisor* untuk queue worker berkelanjutan. Oleh karena itu:
1. **Konfigurasi Cron Job di hPanel:**
   ```bash
   * * * * * cd /home/u123456789/domains/p4i.or.id/p4i-office-core && /usr/bin/php8.3 artisan schedule:run >> /dev/null 2>&1
   ```
2. **Eksekusi Antrean (Queue):**
   - Menggunakan driver `database` pada tabel MariaDB.
   - Diproses setiap menit melalui scheduler:
     ```php
     // routes/console.php
     Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute();
     ```
   - Pola `--stop-when-empty` mencegah proses menjadi zombie dan mematuhi batas LVE CloudLinux.

---

### 6. SOP Backup & Disaster Recovery

1. **Database:**
   - Pencadangan harian otomatis Hostinger hPanel.
   - Script terjadwal via cron untuk ekspor aman:
     ```bash
     mysqldump -u [db_user] -p'[password]' [db_name] | gzip > /home/u123456789/backups/p4i_office_$(date +\%F).sql.gz
     ```
2. **Private Storage:**
   - Direktori `storage/app/letters/` diikutsertakan dalam backup mingguan terenkripsi.

---

### 7. Catatan Keamanan & Larangan Operasional

> [!CAUTION]
> Sesuai batasan tata kelola Sprint M0.1:
> - **DILARANG** melakukan deployment ke akun live Hostinger pada sprint ini.
> - **DILARANG** mengubah konfigurasi PHP atau database di server produksi.
> - Seluruh verifikasi di atas diselesaikan secara lokal dan siap diterapkan seketika otorisasi deployment resmi diberikan oleh Ketua/Pengurus Yayasan P4I dan disetujui auditor GPT-6.
