# KEPUTUSAN STRATEGIS UPGRADE FRAMEWORK & EVALUASI ARSITEKTUR
## P4I Office v1.0 — Sprint M0.1: Laravel 13 & PHP 8.3 Upgrade

- **Penyusun Laporan:** Gemini Flash 3.8 — Lead Software Engineer
- **Auditor & Perancang Sistem:** GPT-6
- **Otoritas Administratif:** Ketua / Pengurus Yayasan P4I
- **Workspace:** `D:\p4i-office-laravel` & `D:\p4i-office`
- **Tanggal Keputusan:** 31 Oktober 2026

---

### 1. Latar Belakang & Evaluasi Temuan Sprint M0

Pada penutupan Sprint M0, auditor sistem (GPT-6) menerima fondasi aplikasi secara bersyarat dengan satu temuan kritis:
- Framework yang terpasang pada Sprint M0 adalah **Laravel 10.50.4 (PHP 8.2)**.
- Laravel 10 telah mencapai akhir masa dukungan keamanan resmi (*End of Life*).
- Keputusan Arsitektur Resmi Proyek P4I Office mewajibkan **Laravel 13** yang berjalan di atas **PHP 8.3/8.4** dengan database **MariaDB InnoDB** untuk target hosting **Hostinger Business Web Hosting**.

Sesuai instruksi Sprint M0.1, pelaksana teknis diwajibkan menyelesaikan upgrade ke Laravel 13 sebelum fitur bisnis persuratan (Sprint M1) diizinkan untuk dikembangkan.

---

### 2. Audit Lingkungan Lokal (Baseline Platform Discovery)

Pemeriksaan aktual terhadap lingkungan lokal mengungkap status awal sebagai berikut:
1. **PHP Awal:** PHP 8.2.12 CLI (ZTS Visual C++ 2019 x64) yang berasal dari `C:\xampp\php\php.exe`.
2. **Kendala Versi Framework:** `laravel/framework:^13.0` dan `laravel/laravel:^13.0` mensyaratkan `php: ^8.3`. Upaya instalasi Laravel 13 di atas PHP 8.2 akan ditolak secara mutlak oleh Composer solver.
3. **Penyediaan PHP 8.3 Mandiri:**
   - Dilakukan instalasi resmi package Microsoft WinGet: `PHP.PHP.8.3` (versi **8.3.36**).
   - Lokasi binary: `C:\Users\Asus Gaming\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe`.
   - Konfigurasi `php.ini` dibuat dari templat `php.ini-development` dengan mengaktifkan ekstensi wajib:
     `pdo_mysql`, `pdo_sqlite`, `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `mysqli`, `openssl`, `sodium`, `zip`, `exif`.
   - PATH prioritas lokal diarahkan ke binary PHP 8.3.36.
4. **Verifikasi Tooling:**
   - `php -v` $\rightarrow$ **PHP 8.3.36** (cli, Zend Engine v4.3.36).
   - `composer --version` $\rightarrow$ **Composer version 2.8.6**.
   - `php artisan --version` $\rightarrow$ **Laravel Framework 13.39.0**.

---

### 3. Evaluasi Perbandingan Strategi Upgrade

Instruksi Sprint M0.1 mewajibkan analisis dua opsi strategi:

| Parameter Evaluasi | Strategi A: Porting Skeleton Bersih Laravel 13 | Strategi B: Upgrade In-Place Bertahap (10 $\rightarrow$ 11 $\rightarrow$ 12 $\rightarrow$ 13) |
| :--- | :--- | :--- |
| **Metode Pelaksanaan** | Membuat skeleton Laravel 13 murni, lalu mentransplantasikan domain code Sprint M0. | Menjalankan composer update beruntun melewati versi 11, 12, dan 13 secara sekuensial. |
| **Risiko Dependency Conflict** | **Sangat Rendah (0 conflict):** Dependency tree Laravel 13 langsung terselesaikan secara harmonis oleh Packagist. | **Sangat Tinggi:** Konflik paket transisi (`spatie/laravel-ignition`, `doctrine/dbal` v3 vs v4, `nunomaduro/collision`, schema `phpunit.xml` 10 $\rightarrow$ 11 $\rightarrow$ 12). |
| **Integritas Kode Domain** | **Terjamin Utuh:** Seluruh domain P4I Office (1 migration 15 tabel, 14 models, 5 controllers, 1 middleware, views, tests) dipindahkan tanpa distorsi. | **Rentan Kerusakan:** Konfigurasi kernel lama (`app/Http/Kernel.php`) dan config lama rawan bertabrakan dengan loader baru. |
| **Waktu & Stabilitas** | Cepat, bersih, tervalidasi seketika dengan test suite otomatis. | Membutuhkan debugging dependensi manual di setiap iterasi versi. |
| **Keputusan Pelaksana** | **DIPILIH (RECOMMENDED)** | **DITOLAK** |

**Alasan Pemilihan Strategi A:**
Fondasi Sprint M0 belum memiliki kode legacy yang terikat pada dependensi pihak ketiga usang. Transplantasi domain layer ke skeleton Laravel 13.39.0 menjamin arsitektur yang 100% *native*, minim beban teknis (*zero technical debt*), dan langsung memenuhi standar resmi Laravel modern.

---

### 4. Perubahan Arsitektur & Modernisasi Internal Laravel 13

1. **Bootstrap Terpadu (`bootstrap/app.php`):**
   - Laravel 13 meniadakan `app/Http/Kernel.php` dan `app/Console/Kernel.php` konvensional.
   - Pendaftaran middleware dilakukan secara deklaratif melalui `Application::configure()`:
     ```php
     return Application::configure(basePath: dirname(__DIR__))
         ->withRouting(
             web: __DIR__.'/../routes/web.php',
             commands: __DIR__.'/../routes/console.php',
             health: '/up',
         )
         ->withMiddleware(function (Middleware $middleware): void {
             $middleware->alias([
                 'active.user' => \App\Http\Middleware\EnsureUserIsActive::class,
             ]);
         })
         ->withExceptions(function (Exceptions $exceptions): void {
             //
         })->create();
     ```
2. **Pembersihan Dependensi Token API (`Sanctum`):**
   - Paket `laravel/sanctum` yang merupakan bawaan skeleton lama Laravel 10 dihapus karena P4I Office menggunakan autentikasi sesi berbasis cookie web standar (`Auth::attempt()`, `Session::regenerate()`).
   - File konfigurasi yatim `config/sanctum.php` dihapus, dan trait `HasApiTokens` dihilangkan dari `app/Models/User.php`.
3. **Kompatibilitas Livewire:**
   - Dipasang `livewire/livewire` versi **v4.4.8** yang memiliki dukungan penuh untuk Laravel 13 dan PHP 8.3+.
4. **Pembaruan Test Framework (PHPUnit 12):**
   - File `phpunit.xml` dimodernisasi sesuai skema PHPUnit 12.5.
   - Environment database pada `phpunit.xml` langsung diarahkan ke MariaDB aktual (`p4i_office_test`), bukan in-memory SQLite.

---

### 5. Pengelolaan Repositori & Mitigasi Risiko

- Branch `master` pada `D:\p4i-office-laravel` dipertahankan utuh pada commit Sprint M0 (`4017431`) sebagai titik pemulihan (*rollback point*).
- Seluruh pekerjaan upgrade dilakukan pada working branch terisolasi: `feat/sprint-m0.1-laravel-13-upgrade`.
- Commit perubahan: `64d01d6` (*feat(m0.1): upgrade to Laravel 13, verify MariaDB InnoDB, add engine triggers and regression tests*).
