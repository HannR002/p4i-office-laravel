# LAPORAN BUKTI TEKNIS SPRINT M0.1
## Koreksi Framework Laravel 13 & Verifikasi Database MariaDB Nyata

- **Pelaksana Implementasi:** Gemini Flash 3.8 Reasoning High (Lead Software Engineer & Technical Test Engineer)
- **Software Architect & Auditor Resmi:** GPT-6
- **Otoritas Administratif:** Ketua / Pengurus Yayasan P4I
- **Workspace Utama:** `D:\p4i-office-laravel` (Aplikasi Laravel 13 Target)
- **Workspace Dokumentasi:** `D:\p4i-office` (Dokumentasi Induk & Repositori Legacy)
- **Branch Target:** `feat/sprint-m0.1-laravel-13-upgrade`
- **Commit HEAD Target:** `64d01d6`
- **Tanggal Selesai:** 31 Oktober 2026
- **Status Akhir Gate:** **PASS (SELURUH GERBANG M0R-01 S/D M0R-08 TERPENUHI)**

---

### 1. Ringkasan Perubahan & Baseline vs Aktual

| Komponen | Status Sprint M0 (Awal) | Status Sprint M0.1 (Koreksi Aktual) | Keterangan Verifikasi |
| :--- | :--- | :--- | :--- |
| **Laravel Framework** | `v10.50.4` (EOL) | **`v13.39.0` (Resmi)** | Terverifikasi via `php artisan --version` |
| **PHP Runtime** | `8.2.12` (XAMPP) | **`8.3.36` (WinGet CLI)** | Terverifikasi via `php -v` (Zend Engine v4.3.36) |
| **Livewire** | `v3.0.0` | **`v4.4.8`** | Terverifikasi via `composer show livewire/livewire` |
| **PHPUnit** | `v10.5.66` | **`v12.5.42`** | Terverifikasi via `phpunit --version` |
| **Database Driver** | MySQL PDO (`mysql`) | **MySQL PDO (`mysql`)** | Terverifikasi via `DB::connection()->getDriverName()` |
| **Database Instance** | SQLite `:memory:` (pada test) | **MariaDB 10.4.32 InnoDB (`p4i_office_test`)** | Terverifikasi query langsung `@@innodb_version` |
| **Database Triggers** | Belum diterapkan | **4 Triggers Engine-Level Aktif** | SQLSTATE 45000 pada raw SQL update/delete |
| **Frontend Assets** | Belum terkompilasi | **Vite v4.5.14 Production Built** | 61 modules, asset di `public/build/` |
| **Code Style** | Belum diformat | **Laravel Pint PASS** | 35 file dirapikan, 0 error |

---

### 2. Evaluasi Acceptance Gates (M0R-01 s/d M0R-08)

| Gate ID | Definisi Kriteria Penerimaan | Status | Bukti Teknis & Validasi Aktual |
| :--- | :--- | :---: | :--- |
| **M0R-01** | **Laravel 13 terverifikasi** | **PASS** | Output `php artisan --version` menghasilkan `Laravel Framework 13.39.0`. Tidak ada dependensi usang. |
| **M0R-02** | **PHP dan dependencies kompatibel** | **PASS** | Output `php -v` menghasilkan `PHP 8.3.36`. Seluruh ekstensi (`pdo_mysql`, `curl`, `mbstring`, `openssl`, `sodium`, `zip`, `intl`, `fileinfo`, `gd`) aktif. `composer check-platform-reqs` menghasilkan *success*. |
| **M0R-03** | **MariaDB/InnoDB nyata teruji** | **PASS** | Koneksi ke MariaDB 10.4.32 aktif di port 3306. Seluruh 15 tabel relasional terverifikasi menggunakan engine InnoDB via `information_schema.TABLES`. Foreign keys (errno 1452) dan unique constraints (errno 1062) teruji melempar exception saat dilanggar. |
| **M0R-04** | **Autentikasi, otorisasi, CSRF & account disabling** | **PASS** | 7 automated tests pada `AuthWorkflowTest` dan 4 tests pada `RoleAccessTest` lulus. Session diregenerasi pada login. Pengguna nonaktif (`DISABLED`) ditolak saat login dan sesinya dicabut secara real-time via middleware `active.user`. |
| **M0R-05** | **Immutability & transaksi konkurensi teruji** | **PASS** | Dual-layer protection: Eloquent observer melempar `ModelImmutableException`. Empat database triggers MariaDB (`trg_letter_revisions_*` & `trg_audit_events_*`) memblokir manipulasi via raw SQL dengan `SQLSTATE 45000`. Pessimistic locking `lockForUpdate()` dan rollback teruji normal. |
| **M0R-06** | **Nomor surat resmi tetap OFF dan NULL** | **PASS** | `config('features.official_numbering_active') === false`. Eksekusi `LetterNumberingService::allocate()` melempar `OfficialNumberingBlockedException`. Setiap surat yang dibuat memiliki `official_number = null`. 4 tests pada `SafetyGuardTest` lulus. |
| **M0R-07** | **Tests, lint/format, build frontend & regresi lulus** | **PASS** | **28 automated tests (95 assertions) PASS** dengan durasi 7.18s dan exit code 0. Laravel Pint format check `result: passed`. Vite build berhasil tanpa error. |
| **M0R-08** | **Checklist kesiapan Hostinger selesai** | **PASS** | Dokumen `docs/hostinger-compatibility-check.md` lengkap merinci isolasi document root, konfigurasi LiteSpeed `.htaccess`, Cron Job 1-menit, PHP 8.3 hPanel, dan SOP backup database. |

---

### 3. Log Hasil Uji Otomatis Lengkap

```text
Command: php artisan test
Environment: Testing (DB: MariaDB 10.4.32 / p4i_office_test via MySQL PDO)

   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\AuthWorkflowTest
  ✓ login page renders successfully                                                                              2.90s  
  ✓ user can login with correct credentials                                                                      0.09s  
  ✓ user cannot login with invalid password                                                                      0.25s  
  ✓ disabled user is prevented from login                                                                        0.06s  
  ✓ user can logout                                                                                              0.04s  
  ✓ dashboard requires authentication                                                                            0.05s  
  ✓ middleware revokes session if user becomes disabled                                                          0.07s  

   PASS  Tests\Feature\DatabaseSchemaTest
  ✓ all 15 tables can be created and related transactionally                                                     0.08s  
  ✓ letter revision is strictly immutable                                                                        0.05s  
  ✓ audit event is strictly immutable                                                                            0.04s  

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                                                                0.03s  

   PASS  Tests\Feature\MariaDbVerificationTest
  ✓ active connection is real mariadb innodb                                                                     0.04s  
  ✓ foreign key constraints enforced by mariadb                                                                  0.03s  
  ✓ unique constraints enforced by mariadb                                                                       0.05s  
  ✓ transaction rollback works on mariadb innodb                                                                 0.05s  
  ✓ concurrent operations with lock for update                                                                   0.04s  
  ✓ raw sql update bypass blocked by mariadb trigger                                                             0.05s  
  ✓ raw sql delete bypass blocked by mariadb trigger                                                             0.04s  
  ✓ data persistence across connection reconnect                                                                 0.06s  

   PASS  Tests\Feature\RoleAccessTest
  ✓ staff cannot access reviewer approval queue                                                                  2.24s  
  ✓ reviewer can access reviewer approval queue                                                                  0.05s  
  ✓ admin can access reviewer approval queue                                                                     0.06s  
  ✓ dashboard displays user roles                                                                                0.05s  

   PASS  Tests\Feature\SafetyGuardTest
  ✓ safety guard feature numbering is strictly false                                                             0.04s  
  ✓ allocate official number throws blocked exception                                                            0.03s  
  ✓ new letter always has null official number                                                                   0.05s  
  ✓ numbering policies defaults to inactive                                                                      0.05s  

  Tests:    28 passed (95 assertions)
  Duration: 7.18s
  Exit Code: 0
```

---

### 4. Bukti Verifikasi Triggers MariaDB Engine

Eksekusi query terhadap instance MariaDB `p4i_office_dev`:

```sql
SHOW TRIGGERS\G
```

Hasil:
1. `trg_audit_events_no_update` (Event: `UPDATE`, Timing: `BEFORE`, Action: `SIGNAL SQLSTATE '45000'`)
2. `trg_audit_events_no_delete` (Event: `DELETE`, Timing: `BEFORE`, Action: `SIGNAL SQLSTATE '45000'`)
3. `trg_letter_revisions_no_update` (Event: `UPDATE`, Timing: `BEFORE`, Action: `SIGNAL SQLSTATE '45000'`)
4. `trg_letter_revisions_no_delete` (Event: `DELETE`, Timing: `BEFORE`, Action: `SIGNAL SQLSTATE '45000'`)

---

### 5. Frontend Asset Compilation Log (Vite)

```text
vite v4.5.14 building for production...
transforming...
✓ 61 modules transformed.
rendering chunks...
computing gzip size...
public/build/manifest.json            0.25 kB │ gzip:  0.14 kB
public/build/assets/app-4ed993c7.js   0.00 kB │ gzip:  0.02 kB
public/build/assets/app-74e2b608.js  58.97 kB │ gzip: 22.21 kB
✓ built in 546ms
```

---

### 6. Daftar Dokumen Penyerahan Sprint M0.1

Seluruh artefak dokumentasi teknis telah diserahkan di direktori `docs/`:

1. [**`docs/framework-upgrade-decision.md`**](file:///d:/p4i-office/docs/framework-upgrade-decision.md): Evaluasi perbandingan Strategi A vs Strategi B, penyiapan PHP 8.3 CLI, dan arsitektur `bootstrap/app.php` modern.
2. [**`docs/mariadb-integration-verification.md`**](file:///d:/p4i-office/docs/mariadb-integration-verification.md): Bukti pengujian 15 tabel InnoDB, foreign keys, transaksi rollback, pessimistic row locking, dan dual-layer triggers.
3. [**`docs/hostinger-compatibility-check.md`**](file:///d:/p4i-office/docs/hostinger-compatibility-check.md): Spesifikasi lingkungan Hostinger Business Web Hosting, isolasi document root, LiteSpeed `.htaccess`, Cron Job 1-menit, dan SOP backup database.
4. [**`docs/implementation-evidence-sprint-m0r.md`**](file:///d:/p4i-office/docs/implementation-evidence-sprint-m0r.md): Laporan bukti teknis komprehensif, daftar gate, log pengujian, dan status penutupan sprint.

---

### 7. Status Tata Kelola & Stop Condition

> [!IMPORTANT]
> **Sprint M0.1 dinyatakan SELESAI PENUH (PASS).**
> - Seluruh temuan kritis dari Sprint M0 (kebutuhan Laravel 13, PHP 8.3, dan pengujian MariaDB nyata) telah diselesaikan secara tuntas dan terbukti dengan 28 automated tests (95 assertions).
> - Sesuai aturan tata kelola proyek:
>   - **Gemini IDE BERHENTI DI SINI.**
>   - Tidak memulai konversi fitur Sprint M1 (Livewire draft editor, reviewer workflow modal, mandate management) sebelum evaluasi dan persetujuan resmi diberikan oleh **GPT-6** dan **Ketua/Pengurus Yayasan P4I**.
>   - Fitur penomoran surat resmi (`official_number`) tetap terkunci pada status **OFF / NULL**.
