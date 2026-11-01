# Paket Persiapan Lokal & Preflight Hostinger Staging (Tahap P0 — Sprint M5B-B)

**Proyek:** P4I Office v1.0  
**Sprint:** M5B-B (Controlled Hostinger Staging Verification & User Acceptance Testing)  
**Acuan Spesifikasi:** `P4I_Office_Addendum_SRS_Sprint_M5B_B_2026-11-01.docx` — Bagian D (`STB-01`, `STB-05`, `STB-14`)  
**Implementer Teknis:** Argon — Reasoning Medium  
**Tim Perancangan & Auditor:** GPT-6  
**Status Paket:** `LOCAL PREPARATION COMPLETE — STAGING AUTHORIZATION PENDING`  
**Tanggal Verifikasi:** 2026-11-01  

---

## 1. Ringkasan Paket Staging Reproducible (12 Komponen Wajib Tahap P0)

Paket persiapan staging lokal telah disusun agar dapat direproduksi secara deterministik pada **Hostinger Business Web Hosting** begitu gerbang izin pemilik proyek (`STB-02`) dibuka:

### 1.1 Daftar Ekstensi PHP 8.3 Wajib (14 Ekstensi)
Seluruh 14 ekstensi PHP yang dibutuhkan oleh Laravel 13, DomPDF, PhpWord, dan penyimpanan terenkripsi telah diverifikasi melalui [`StagingPreflightCommand`](file:///d:/p4i-office-laravel/app/Console/Commands/StagingPreflightCommand.php):

| No | Ekstensi PHP | Kegunaan pada P4I Office v1.0 | Status Lokal (PHP 8.3.28) |
| :-: | :--- | :--- | :---: |
| 1 | `pdo_mysql` | Koneksi database MariaDB/InnoDB dan eksekusi transaksi `FOR UPDATE` | `PASS` (`true`) |
| 2 | `mbstring` | Pemrosesan string UTF-8 Bahasa Indonesia pada draf surat & PDF/DOCX | `PASS` (`true`) |
| 3 | `xml` | Parser XML dasar untuk pembuatan dokumen OOXML (`.docx`) dan DomPDF | `PASS` (`true`) |
| 4 | `zip` | Pembuatan dan ekstraksi struktur kontainer `.docx` (`ZipArchive`) | `PASS` (`true`) |
| 5 | `gd` | Pemrosesan citra/elemen grafis dan watermark pada DomPDF | `PASS` (`true`) |
| 6 | `openssl` | Enkripsi cookie sesi, token CSRF, dan pembuat `APP_KEY` AES-256-CBC | `PASS` (`true`) |
| 7 | `fileinfo` | Deteksi tipe MIME (`application/pdf`, `application/vnd.openxmlformats...`) | `PASS` (`true`) |
| 8 | `ctype` | Validasi karakter input dan sanitasi templat | `PASS` (`true`) |
| 9 | `json` | Serialisasi payload revisi, `form_schema`, dan `audit_events` | `PASS` (`true`) |
| 10 | `tokenizer` | Kompilasi templat Blade dan analisis sintaks PHP | `PASS` (`true`) |
| 11 | `dom` | Manipulasi pohon DOM HTML/XML pada DomPDF dan PhpWord | `PASS` (`true`) |
| 12 | `filter` | Validasi dan sanitasi input HTTP request | `PASS` (`true`) |
| 13 | `hash` | Perhitungan digest integritas `SHA-256` (`hash('sha256', ...)`) | `PASS` (`true`) |
| 14 | `session` | Manajemen sesi pengguna terautentikasi dan regenerasi sesi | `PASS` (`true`) |

### 1.2 Verifikasi Dependensi Composer (`composer.lock`)
- Berkas [`composer.lock`](file:///d:/p4i-office-laravel/composer.lock) terkunci dan dilacak di Git untuk menjamin instalasi deterministik menggunakan perintah:
  ```bash
  composer install --no-dev --optimize-autoloader --no-interaction
  ```
- Paket utama terverifikasi: `laravel/framework: ^13.0`, `livewire/livewire: ^4.0`, `barryvdh/laravel-dompdf: ^3.1`, `phpoffice/phpword: ^1.3`.

### 1.3 Kebutuhan Konfigurasi MariaDB / InnoDB
- **Storage Engine:** Wajib `InnoDB` (`default_storage_engine = InnoDB`) untuk mendukung transaksi ACID, row-level locking (`SELECT ... FOR UPDATE`), dan foreign key constraints.
- **Charset & Collation:** `utf8mb4` / `utf8mb4_unicode_ci`.
- **Hak Istimewa (Privileges) Pengguna DB Staging:** `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `INDEX`, `REFERENCES`, `TRIGGER` (hak `TRIGGER` wajib agar ke-4 trigger immutability `trg_letter_revisions_no_update`, `trg_letter_revisions_no_delete`, `trg_audit_events_no_update`, `trg_audit_events_no_delete` dapat dipasang).

### 1.4 Struktur Direktori Aplikasi Laravel pada Hostinger
Kode inti aplikasi ditempatkan **di luar direktori publik** untuk mencegah eksposur `.env`, kode sumber, dan arsip privat:

```text
/home/<akun_hosting>/
├── p4i-office/                          # Root aplikasi Laravel (DI LUAR web root publik)
│   ├── app/
│   ├── bootstrap/cache/                 # chmod 775
│   ├── config/
│   ├── database/
│   ├── public/                          # Target symlink atau Document Root subdomain staging
│   ├── resources/
│   ├── routes/
│   ├── storage/                         # chmod 775 (rekursif)
│   │   ├── app/
│   │   │   └── private/
│   │   │       └── archives/            # Disk `private_archives` (<org_id>/<year>/PDF|DOCX/<uuid>.ext)
│   │   ├── framework/
│   │   └── logs/
│   ├── vendor/
│   ├── .env                             # chmod 600 (Hanya di server, TIDAK di Git)
│   └── artisan
└── domains/
    └── staging-office.p4i.or.id/
        └── public_html -> /home/<akun_hosting>/p4i-office/public
```

### 1.5 Konfigurasi Document Root ke Direktori `public`
- Document root subdomain staging wajib diarahkan langsung ke `/home/<akun_hosting>/p4i-office/public` (atau menggunakan symlink `public_html -> /home/<akun_hosting>/p4i-office/public`).
- Berkas [`public/.htaccess`](file:///d:/p4i-office-laravel/public/.htaccess) mengarahkan seluruh request ke front controller `public/index.php` dan memblokir directory listing (`Options -MultiViews -Indexes`).

### 1.6 Konfigurasi Penyimpanan Privat di Luar Direktori Publik (`STB-05`)
- Disk `private_archives` dikonfigurasi di [`config/filesystems.php`](file:///d:/p4i-office-laravel/config/filesystems.php) pada jalur `storage_path('app/private/archives')` dengan `'visibility' => 'private'` dan `'serve' => false`.
- Tidak ada perintah `php artisan storage:link` yang mengekspos `storage/app/private/archives` ke dalam `public/storage`.
- Seluruh unduhan wajib melewati controller terautentikasi [`DocumentArchiveController::download`](file:///d:/p4i-office-laravel/app/Http/Controllers/DocumentArchiveController.php) yang memverifikasi:
  1. Autentikasi sesi aktif (`auth`, `active.user`),
  2. Isolasi tenant organisasi (`organization_id`),
  3. Kebijakan akses surat (`Gate::authorize('view', $letter)` termasuk pengecekan mandat untuk surat `RAHASIA`),
  4. Kecocokan hash integritas `SHA-256` sebelum streaming berkas.

### 1.7 Konfigurasi HTTPS, Cookie Sesi, dan Keamanan Header (`STB-04`)
Templat konfigurasi [`.env.staging.example`](file:///d:/p4i-office-laravel/.env.staging.example) menetapkan:
- `APP_ENV=staging`
- `APP_DEBUG=false`
- `APP_URL=https://staging-office.p4i.or.id`
- `SESSION_DRIVER=database`
- `SESSION_HTTP_ONLY=true`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_SAME_SITE=lax`
- Proteksi CSRF aktif pada grup middleware `web`, dan pembatasan laju (*rate limiting*) login 5 percobaan per menit per kombinasi email + IP pada [`AuthController`](file:///d:/p4i-office-laravel/app/Http/Controllers/AuthController.php).

### 1.8 Prosedur Pembuatan `APP_KEY` dan Pengelolaan Rahasia (`STB-01`)
1. Salin `.env.staging.example` menjadi `.env` langsung di server staging (`cp .env.staging.example .env && chmod 600 .env`).
2. Bangkitkan kunci enkripsi unik khusus server staging:
   ```bash
   php artisan key:generate --force
   ```
3. Isi `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` melalui terminal SSH Hostinger tanpa pernah menyimpannya di Git, pesan commit, ataupun dokumen laporan.

### 1.9 Konfigurasi Logging Aplikasi
- `LOG_CHANNEL=stack` / `LOG_STACK=daily` dengan `LOG_LEVEL=info` pada `storage/logs/laravel-YYYY-MM-DD.log`.
- Seluruh kegagalan ekspor atau pemulihan crash dicatat ke tabel immutable `audit_events` (`DOCUMENT_EXPORT_FAILED`, `DOCUMENT_EXPORT_RECONCILED_READY`, `DOCUMENT_EXPORT_RECONCILED_ERROR`) tanpa membocorkan stack trace ke pengguna akhir.

### 1.10 Prosedur Backup dan Rollback Aplikasi + Database (`STB-12`)
- **Sebelum Deploy/Update Staging:**
  1. Ekspor dump konsisten database MariaDB:
     ```bash
     mysqldump --single-transaction --routines --triggers -u <user> -p <db_staging> | gzip > backup_db_$(date +%Y%m%d_%H%M%S).sql.gz
     ```
  2. Arsipkan direktori penyimpanan privat beserta manifest SHA-256:
     ```bash
     find storage/app/private/archives -type f -exec sha256sum {} + > manifest_sha256_$(date +%Y%m%d_%H%M%S).txt
     tar -czf backup_archives_$(date +%Y%m%d_%H%M%S).tar.gz storage/app/private/archives manifest_sha256_*.txt
     ```
- **Prosedur Rollback Cepat:** Kembalikan commit Git sebelumnya (`git checkout <commit_baseline>`), pulihkan dump SQL, ekstrak ulang `backup_archives_*.tar.gz`, lalu jalankan `php artisan optimize:clear` dan `php artisan p4i:staging-preflight`.

### 1.11 Daftar Tugas Terjadwal (Cron Jobs) yang Dibutuhkan (`STB-06`)
Pada hPanel Hostinger -> *Advanced* -> *Cron Jobs*, cukup didaftarkan **1 entri scheduler standar Laravel**:
```cron
* * * * * /usr/bin/php /home/<akun_hosting>/p4i-office/artisan schedule:run >> /dev/null 2>&1
```
Scheduler Laravel di [`routes/console.php`](file:///d:/p4i-office-laravel/routes/console.php) secara otomatis mengeksekusi:
- `p4i:reconcile-exports --stale-minutes=15` setiap 15 menit (`*/15 * * * *`) dengan pengunci `withoutOverlapping(10)` dan `runInBackground()`.

### 1.12 Perlindungan Akses Publik terhadap Lingkungan Staging
- Selama masa pengujian staging dan UAT, lingkungan staging dilindungi dengan:
  1. Header `X-Robots-Tag: noindex, nofollow` agar tidak diindeks mesin pencari,
  2. Opsional HTTP Basic Auth tingkat web server (`.htpasswd` pada subdomain staging) sehingga hanya tim penguji UAT Yayasan P4I yang dapat mengakses halaman login staging,
  3. Penonaktifan rute helper `/dev-login/{email}` secara paksa karena rute tersebut hanya terdaftar jika `app()->environment('local')` bernilai `true`.

---

## 2. Bukti Eksekusi Aktual `php artisan p4i:staging-preflight --json`

Berikut adalah keluaran JSON aktual dari perintah [`php artisan p4i:staging-preflight --json`](file:///d:/p4i-office-laravel/app/Console/Commands/StagingPreflightCommand.php) pada lingkungan verifikasi lokal:

```json
{
    "timestamp": "2026-11-01T15:02:11+00:00",
    "environment": "local",
    "php_version": "8.3.28",
    "php_compatible": true,
    "extensions": {
        "pdo_mysql": true,
        "mbstring": true,
        "xml": true,
        "zip": true,
        "gd": true,
        "openssl": true,
        "fileinfo": true,
        "ctype": true,
        "json": true,
        "tokenizer": true,
        "dom": true,
        "filter": true,
        "hash": true,
        "session": true
    },
    "all_extensions_loaded": true,
    "composer_lock_present": true,
    "scheduler_registered": true,
    "db_version": "10.4.32-MariaDB",
    "innodb_supported": true,
    "immutability_triggers": {
        "trg_letter_revisions_no_update": true,
        "trg_letter_revisions_no_delete": true,
        "trg_audit_events_no_update": true,
        "trg_audit_events_no_delete": true
    },
    "all_triggers_present": true,
    "private_storage_outside_public": true,
    "official_numbering_active": false,
    "non_null_official_numbers": 0,
    "safety_invariants_intact": true,
    "memory_limit": "128M",
    "max_execution_time": "0",
    "hostinger_authorization_gate": "PENDING",
    "staging_authorization_status": "LOCAL_PREPARATION_COMPLETE_STAGING_AUTHORIZATION_PENDING",
    "staging_deployment_status": "LOCAL_PREPARATION_COMPLETE_HOSTINGER_STAGING_BLOCKED"
}
```

