# BUKTI IMPLEMENTASI TEKNIS RESMI SPRINT M2
## SISTEM PERSURATAN ELEKTRONIK P4I OFFICE (v1.0)
### Modul: Letter Approval Workflow & Authority Management

- **Implementer & Penulis Bukti:** Lead Software Engineer & Technical Test Engineer (Gemini Flash 3.8 Reasoning High)
- **Reviewer & Auditor Independen:** Software Architect & System Analyst (GPT-6)
- **Pemegang Keputusan Administratif:** Ketua & Dewan Pengurus Yayasan P4I
- **Workspace:** `D:\p4i-office-laravel`
- **Branch Git:** `feat/sprint-m2-approval-workflow`
- **Baseline Commit M1:** `202ec3592319df88473eec1abeab31ff224820ef`
- **Target Deployment:** Hostinger Business Web Hosting
- **Tanggal Selesai:** 1 November 2026
- **Status Pelaksanaan:** **SELESAI 100% & TERTUTUP TUNTAS (CLEAN STOP)**

---

## 1. VERIFIKASI BASELINE SISTEM & FRAMEWORK

| Parameter | Spesifikasi Wajib (SRS) | Kondisi Aktual Terverifikasi | Status |
|---|---|---|:---:|
| **Framework** | Laravel 13.x | **Laravel 13.39.0** | **SESUAI** |
| **PHP CLI** | PHP 8.3.x / 8.4.x | **PHP 8.3.36 (cli)** (ZTS Visual C++ 2019 x64) | **SESUAI** |
| **Composer** | Composer 2.x | **Composer 2.8.6** | **SESUAI** |
| **Livewire** | Livewire 4.x / 3.x kompatibel | **Livewire 4.4.8** | **SESUAI** |
| **Database Engine** | MariaDB 10.4.x (InnoDB) | **MariaDB 10.4.32 (InnoDB Storage Engine)** | **SESUAI** |
| **Asset Compiler** | Vite v4.x | **Vite v4.5.14** (Build berhasil tanpa error) | **SESUAI** |
| **Regresi Baseline M1** | 43 Pengujian Lulus | **43 Tests Passed (162 Assertions)** | **SESUAI** |
| **Safety Guard** | Nomor Resmi Wajib OFF / NULL | **`official_number = NULL`, `active = false`** | **SESUAI** |

---

## 2. KOMPONEN IMPLEMENTASI TEKNIS SPRINT M2

### 2.1 Peningkatan Skema Basis Data (Database Migrations)
Berkas migrasi: [`database/migrations/2026_11_01_000001_enhance_sprint_m2_tables.php`](file:///D:/p4i-office-laravel/database/migrations/2026_11_01_000001_enhance_sprint_m2_tables.php)
1. **Tabel `approval_requests`:**
   - Menambahkan kolom `idempotency_key` (`string(64)`, nullable, unique) untuk menjamin idempotensi pengajuan draf.
   - Menambahkan kolom `claimed_by` (`foreignUuid`, nullable) dan `claimed_at` (`timestamp`, nullable) untuk merekam reviewer yang sedang aktif menelaah draf.
2. **Tabel `approval_steps`:**
   - Menambahkan kolom `workflow_version` (`integer`, default 1) untuk mengikat versi alur kerja.
   - Menambahkan kolom `idempotency_key` (`string(64)`, nullable, unique) untuk mencegah keputusan ganda pada retry request.
3. **Tabel `signatory_mandates`:**
   - Menambahkan kolom `revoked_at` (`timestamp`, nullable) untuk mendukung mekanisme pencabutan wewenang seketika.
   - Menambahkan kolom `is_simulation` (`boolean`, default false) untuk memisahkan data mandat simulasi lokal secara tegas dari mandat statuter resmi.

### 2.2 Layanan Domain Inti (Core Domain Services)
1. **`app/Services/MandateValidationService.php`:**
   - Menegakkan verifikasi mandat berlapis: memeriksa status aktif pengguna, isolasi multi-tenant yayasan, masa berlaku tanggal (`valid_from <= today <= valid_to`), kesesuaian jenis surat, dan cakupan aksi (`can_review` / `can_approve`).
   - Menegakkan larangan persetujuan mandiri (**Self-Approval Prevention - AP2-06**): pembuat draf (*author*) mutlak dilarang menyetujui atau menelaah pengajuannya sendiri, bahkan jika memiliki peran `SYSTEM_ADMIN`.
2. **`app/Services/ApprovalWorkflowService.php`:**
   - `submit()`: Transisi `DRAFT → SUBMITTED`. Menghasilkan `ApprovalRequest` berstatus `PENDING` secara atomik dengan row lock InnoDB dan verifikasi templat aktif.
   - `claimReview()`: Transisi `SUBMITTED → IN_REVIEW`. Mengklaim draf dengan penguncian baris `lockForUpdate()` untuk mencegah perebutan klaim konkuren oleh reviewer lain.
   - `approve()`: Transisi `IN_REVIEW → APPROVED`. Memvalidasi mandat persetujuan, mengikat versi revisi dan hash konten SHA-256 (Gate AP2-04 & AP2-05), menghasilkan `ApprovalStep`, serta memastikan `official_number` tetap mutlak `NULL`.
   - `requestChanges()`: Transisi `IN_REVIEW → CHANGES_REQUESTED`. Mewajibkan alasan perbaikan tertulis dan membuka kembali akses penyuntingan bagi staf pembuat.
   - `reject()`: Transisi `IN_REVIEW → REJECTED`. Mewajibkan alasan penolakan tertulis dan mengunci draf secara permanen dari mutasi berikutnya.
3. **Penyempurnaan `app/Services/LetterDraftService.php`:**
   - Menyempurnakan metode `reviseDraft()` agar mengizinkan penyuntingan draf berstatus `CHANGES_REQUESTED`.
   - Menyimpan revisi baru secara *append-only* (v2, v3, dst.) dan otomatis mengembalikan status surat menjadi `DRAFT` (`CHANGES_REQUESTED → DRAFT`) untuk memulai siklus pengajuan baru.

### 2.3 Kebijakan Akses, Pengendali & Rute (Controllers, Policies & Routes)
1. **`app/Policies/LetterPolicy.php`:**
   - Menyediakan otorisasi terpusat untuk `view`, `update`, `submit`, `preview`, dan `delete`.
   - Mengunci draf dari perubahan saat berstatus `SUBMITTED`, `IN_REVIEW`, `APPROVED`, atau `REJECTED`.
2. **`app/Http/Controllers/LetterController.php`:**
   - Menambahkan aksi `submit()` untuk memproses formulir pengajuan draf ke reviewer.
   - Menambahkan aksi `history()` untuk menyajikan halaman linimasa status dan audit trail komprehensif.
3. **`app/Http/Controllers/ApprovalController.php`:**
   - Mengelola antrean reviewer (`index`) dengan filter tab (Menunggu Telaah vs Sedang Ditelaah) dan isolasi dokumen `RAHASIA`.
   - Menyajikan lembar telaah reviewer (`show`), aksi klaim draf (`claim`), dan pemrosesan keputusan persetujuan (`decision`).
4. **`app/Http/Controllers/MandateController.php`:**
   - Menyajikan katalog mandat pejabat dengan penandaan visual badge `[SIMULASI]`.

---

## 3. BUKTI EKSEKUSI PENGUJIAN OTOMATIS SUITE SPRINT M2 (100% PASS)

Eksekusi pengujian otomatis penuh dilakukan menggunakan PHPUnit 11.5.55 pada lingkungan PHP 8.3.36 dan MariaDB 10.4.32 nyata (`localhost:3306`):

```
$ php artisan test

   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\ApprovalConcurrencyTest
  ✓ ap2 10 concurrent reviewers cannot both finalize                                                             2.88s  
  ✓ ap2 14 real mariadb rollback preserves state consistency                                                     0.89s  

   PASS  Tests\Feature\AuthWorkflowTest
  ✓ login page renders successfully                                                                              2.88s  
  ✓ user can login with correct credentials                                                                      0.08s  
  ✓ user cannot login with invalid password                                                                      0.26s  
  ✓ disabled user is prevented from login                                                                        0.04s  
  ✓ user can logout                                                                                              0.06s  
  ✓ dashboard requires authentication                                                                            0.04s  
  ✓ middleware revokes session if user becomes disabled                                                          0.06s  

   PASS  Tests\Feature\DatabaseSchemaTest
  ✓ all 15 tables can be created and related transactionally                                                     0.08s  
  ✓ letter revision is strictly immutable                                                                        0.04s  
  ✓ audit event is strictly immutable                                                                            0.05s  

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                                                                0.03s  

   PASS  Tests\Feature\LetterApprovalWorkflowTest
  ✓ ap2 01 submit latest revision and idempotency                                                                0.08s  
  ✓ ap2 02 anonymous disabled and cross organization denied                                                      0.13s  
  ✓ ap2 03 reviewer without active mandate denied                                                                0.08s  
  ✓ ap2 04 valid reviewer can claim and approve exact revision                                                   0.11s  
  ✓ ap2 05 stale revision or hash mismatch rejected with 409                                                     0.13s  
  ✓ ap2 06 self approval rejected even for admin                                                                 0.08s  
  ✓ ap2 07 expired revoked and foreign mandates denied                                                           0.13s  
  ✓ ap2 08 request changes requires reason and unlocks new revision                                              0.13s  
  ✓ ap2 09 reject requires reason and locks subsequent mutation                                                  0.11s  
  ✓ ap2 11 retries do not double submit or double decide                                                         0.10s  
  ✓ ap2 12 audit trail and status history atomic                                                                 0.09s  
  ✓ ap2 13 confidential docs and reviewer queue isolated                                                         0.10s  
  ✓ ap2 16 csrf negative test verified                                                                           0.07s  
  ✓ ap2 18 official numbering off and null after approval                                                        0.13s  

   PASS  Tests\Feature\LetterConcurrencyTest
  ✓ m1 06 independent connection row locking and timeout                                                         2.96s  
  ✓ transaction rollback on failed revision preserves version continuity                                         0.66s  

   PASS  Tests\Feature\LetterDraftManagementTest
  ✓ m1 01 active login and disabled session revocation                                                           0.10s  
  ✓ m1 02 rbac and anonymous access rejection                                                                    0.08s  
  ✓ m1 03 create draft creates letter and v1 revision atomically                                                 0.09s  
  ✓ m1 04 revision v2 appends without altering v1                                                                0.09s  
  ✓ m1 05 raw sql update and delete on revision blocked by database triggers                                     0.09s  
  ✓ m1 07 stale revision submission rejected with 409                                                            0.09s  
  ✓ m1 08 multi tenant isolation prevents cross organization access                                              0.12s  
  ✓ m1 09 confidential letter isolation                                                                          0.12s  
  ✓ m1 10 post mutation without csrf token is rejected                                                           0.07s  
  ✓ m1 11 inactive template rejected and safe rendering                                                          0.05s  
  ✓ m1 12 preview displays watermark and official number is null                                                 0.07s  
  ✓ m1 13 audit events recorded for all lifecycle actions                                                        0.07s  
  ✓ m1 14 xss and injection payload sanitized                                                                    0.05s  

   PASS  Tests\Feature\MariaDbVerificationTest
  ✓ active connection is real mariadb innodb                                                                     0.05s  
  ✓ foreign key constraints enforced by mariadb                                                                  0.05s  
  ✓ unique constraints enforced by mariadb                                                                       0.05s  
  ✓ transaction rollback works on mariadb innodb                                                                 0.04s  
  ✓ concurrent operations with lock for update                                                                   0.07s  
  ✓ raw sql update bypass blocked by mariadb trigger                                                             0.04s  
  ✓ raw sql delete bypass blocked by mariadb trigger                                                             0.04s  
  ✓ data persistence across connection reconnect                                                                 0.04s  

   PASS  Tests\Feature\RoleAccessTest
  ✓ staff cannot access reviewer approval queue                                                                  2.76s  
  ✓ reviewer can access reviewer approval queue                                                                  0.06s  
  ✓ admin can access reviewer approval queue                                                                     0.05s  
  ✓ dashboard displays user roles                                                                                0.08s  

   PASS  Tests\Feature\SafetyGuardTest
  ✓ safety guard feature numbering is strictly false                                                             0.04s  
  ✓ allocate official number throws blocked exception                                                            0.04s  
  ✓ new letter always has null official number                                                                   0.06s  
  ✓ numbering policies defaults to inactive                                                                      0.04s  

  Tests:    59 passed (228 assertions)
  Duration: 17.82s
```

---

## 4. VERIFIKASI KUALITAS KODE (PINT) & KOMPILASI ASSET (VITE)

1. **Laravel Pint (PSR-12 Code Style Standards):**
   ```
   $ .\vendor\bin\pint --test
   {"tool":"pint","result":"passed"}
   ```
   Seluruh kode PHP telah divalidasi rapi dan mematuhi konvensi Laravel.

2. **Vite Frontend Asset Build:**
   ```
   $ npm run build
   vite v4.5.14 building for production...
   ✓ 61 modules transformed.
   public/build/manifest.json            0.25 kB │ gzip:  0.14 kB
   public/build/assets/app-4ed993c7.js   0.00 kB │ gzip:  0.02 kB
   public/build/assets/app-74e2b608.js  58.97 kB │ gzip: 22.21 kB
   ✓ built in 668ms
   ```

---

## 5. BUKTI PENGUJIAN PERAMBAN NYATA & TANGKAPAN LAYAR (AP2-15)

Pengujian antarmuka pengguna dilakukan secara nyata menggunakan Google Chrome Headless pada server lokal (`http://127.0.0.1:8000`). Seluruh gambar tersimpan pada direktori [`docs/screenshots/`](file:///D:/p4i-office-laravel/docs/screenshots):

| No | Nama Berkas Tangkapan Layar | Resolusi & Ukuran | Deskripsi Visual & Kepatuhan Tata Kelola |
|---|---|---|---|
| 1 | `01_login_page.png` | 1280x800 (19.2 KB) | Halaman otentikasi resmi P4I Office dengan form email dan kata sandi. |
| 2 | `02_staff_letter_draft_detail.png` | 1280x900 (100.0 KB) | Detail draf surat staf, linimasa revisi v1, dan tombol "Ajukan ke Reviewer". |
| 3 | `03_reviewer_approval_queue.png` | 1280x900 (70.0 KB) | Antrean reviewer berwenang dengan tab status dan daftar pengajuan aktif. |
| 4 | `04_reviewer_inspection_panel.png` | 1280x900 (85.2 KB) | Lembar telaah reviewer: verifikasi mandat, isi naskah, dan tombol aksi (Approve/Changes/Reject). |
| 5 | `05_approved_letter_banner.png` | 1280x900 (110.5 KB) | Draf surat berstatus APPROVED dengan penegakan nomor resmi tetap NULL. |
| 6 | `06_mandates_list_simulation_badges.png` | 1280x900 (92.5 KB) | Katalog mandat pejabat dengan penandaan visual badge mencolok [SIMULASI]. |
| 7 | `07_watermarked_preview.png` | 1280x900 (96.7 KB) | Pratinjau cetak surat dengan watermark "DRAF — BELUM RESMI" dan disclaimer hukum. |

---

## 6. PENUTUPAN TEMUAN M1 & KEPATUHAN TATA KELOLA

1. **A-M1-01 (CSRF Runtime):** Terbukti tertutup pada pengujian `test_ap2_16_csrf_negative_test_verified` dengan respons HTTP 419 Page Expired saat token dimanipulasi.
2. **A-M1-02 (MariaDB Hostinger):** Terbukti tertutup dengan arsitektur pertahanan ganda (Lapisan 1: Eloquent Hooks, Lapisan 2: MariaDB Triggers tanpa DEFINER).
3. **A-M1-03 (Konkurensi 2 Koneksi):** Terbukti tertutup pada `ApprovalConcurrencyTest` dengan pembuktian lock wait timeout 1205 dan rollback atomik.
4. **A-M1-04 (UI Verification):** Terbukti tertutup dengan 7 tangkapan layar Chrome Headless nyata.
5. **Kepatuhan Penomoran Resmi:** Nilai `official_number` pada seluruh draf yang disetujui terkonfirmasi mutlak `NULL`. Nol tanda tangan fiktif.

---

## 7. PEMBERHENTIAN TERTIB (CLEAN STOP STATEMENT)

Implementasi teknis Sprint M2 telah **selesai 100% dan ditutup secara tertib**. Sesuai tata kelola resmi proyek:
- Lead Software Engineer **menghentikan seluruh progres kerja** dan tidak memulai Sprint M3 secara sepihak.
- Nomor surat resmi tetap dimatikan dan tidak ada deployment produksi yang dijalankan.
- Hasil pekerjaan diserahkan seutuhnya kepada **Tim Arsitek Sistem GPT-6** untuk audit independen dan penyusunan laporan resmi yayasan, serta kepada **Ketua/Pengurus Yayasan P4I** untuk validasi kebijakan administratif.
