# Rencana Eksekusi Teknis Sprint M5B-B: Controlled Hostinger Staging Verification & User Acceptance Testing

**Proyek:** P4I Office v1.0  
**Sprint:** M5B-B (Controlled Hostinger Staging Verification & User Acceptance Testing)  
**Acuan Spesifikasi:** `P4I_Office_Addendum_SRS_Sprint_M5B_B_2026-11-01.docx`  
**Model Implementer (Lead Software Engineer & Technical Test Engineer):** Argon — Reasoning Medium  
**Tim Perancangan, Software Architect, Auditor & Penyusun Laporan Resmi:** GPT-6  
**Otoritas Administratif & Pemilik Proyek:** Ketua/Pengurus Yayasan P4I  
**Repositori Utama:** `D:\p4i-office-laravel`  
**Branch Aktif:** `feat/sprint-m5b-b-staging-verification`  
**Baseline Commit (Sprint M5B-A):** `51664ba4d076d356bc05b8262cab0855f16dd20c`  
**Tanggal Penyusunan:** 2026-11-01  

---

## 1. Tata Kelola & Pembagian Tanggung Jawab

| Peran | Entitas | Tanggung Jawab Utama |
| :--- | :--- | :--- |
| **Perancang Sistem, Software Architect, Auditor & Penyusun Laporan Resmi** | **GPT-6** | Menentukan rancangan sistem, menyusun SRS dan addendum, mengevaluasi keputusan arsitektur, mengaudit bukti teknis secara independen, dan menyusun laporan resmi proyek. |
| **Implementer Teknis (Lead Software Engineer & Technical Test Engineer)** | **Argon — Reasoning Medium** | Menulis dan memperbaiki kode, menjalankan pengujian otomatis, mengidentifikasi bug, dan menyusun catatan bukti teknis apa adanya (bukan laporan pengesahan resmi). |
| **Otoritas Administratif & Pemilik Proyek** | **Ketua/Pengurus Yayasan P4I** | Menyetujui kebijakan persuratan, menetapkan mandat pejabat, memberikan izin eksplisit akses/perubahan hosting, melaksanakan UAT manusia, serta memutuskan aktivasi operasional. |

---

## 2. Hasil Audit Baseline (Section C)

Sebelum memulai pekerjaan Sprint M5B-B, audit ulang dilakukan terhadap kondisi aktual source code pada commit baseline `51664ba4d076d356bc05b8262cab0855f16dd20c`:

1. **Git & Konfigurasi Dasar:**
   - Repositori bersih pada branch `feat/sprint-m5b-a-evidence-verification` (commit `51664ba4d076d356bc05b8262cab0855f16dd20c`).
   - `.env` tidak dilacak di Git (`git ls-files .env` kosong); `.env.example` dan `.env.staging.example` bebas dari rahasia nyata.
   - `config/features.php` menetapkan `official_numbering_active = false`.
2. **Skema & Trigger Database MariaDB:**
   - 8 migrasi berstatus `Ran` pada MariaDB 10.4.32-MariaDB (`InnoDB`).
   - 4 trigger immutability aktif pada database: `trg_letter_revisions_no_update`, `trg_letter_revisions_no_delete`, `trg_audit_events_no_update`, `trg_audit_events_no_delete`.
3. **Arsip Digital, Transaksi & Rekonsiliasi Crash:**
   - `DocumentExportService` menerapkan `DB::transaction()` + `lockForUpdate()`, penulisan berkas sementara (`.tmp`), verifikasi hash SHA-256 sebelum pemindahan, serta pencatatan pre-commit metadata (`file_path`, `file_hash`) sebelum status `READY`.
   - `ReconcileExportsCommand` (`p4i:reconcile-exports --stale-minutes=15`) terdaftar di `routes/console.php` dengan jadwal `everyFifteenMinutes()->withoutOverlapping(10)`.
4. **Invarian Pengaman Operasional:**
   - `config('features.official_numbering_active') === false`.
   - `SELECT COUNT(*) FROM letters WHERE official_number IS NOT NULL` = `0`.
   - Seluruh ekspor PDF dan DOCX menyertakan watermark dan banner `DRAF — BELUM MEMILIKI NOMOR SURAT RESMI`.

---

## 3. Tahapan Pelaksanaan Sprint M5B-B

### Tahap P0 — Persiapan Lokal Staging (Tanpa Menyentuh Hostinger)
1. Menyempurnakan perintah preflight [`StagingPreflightCommand`](file:///d:/p4i-office-laravel/app/Console/Commands/StagingPreflightCommand.php) (`php artisan p4i:staging-preflight --json`) agar memeriksa:
   - Keberadaan 14 ekstensi PHP wajib (`pdo_mysql`, `mbstring`, `xml`, `zip`, `gd`, `openssl`, `fileinfo`, `ctype`, `json`, `tokenizer`, `dom`, `filter`, `hash`, `session`).
   - Keberadaan `composer.lock`, pendaftaran jadwal `p4i:reconcile-exports` pada scheduler, serta status gerbang otorisasi Hostinger (`hostinger_authorization_gate = PENDING`).
   - Pemeriksaan ketat `official_numbering_active` baik dari `config('features.official_numbering_active')` maupun `env('OFFICIAL_NUMBERING_ACTIVE')`.
2. Menyusun paket persiapan staging lokal yang dapat direproduksi di [`docs/hostinger-staging-preflight.md`](file:///d:/p4i-office-laravel/docs/hostinger-staging-preflight.md) mencakup struktur direktori, document root `public`, penyimpanan privat di luar web root, prosedur `APP_KEY`, jadwal cron, dan prosedur backup/rollback.

### Gerbang Izin Hostinger (Hostinger Permission Gate — Sebelum Tahap P1)
- Memeriksa 7 butir izin eksplisit pemilik proyek (Section E) di [`docs/staging-permission-gate.md`](file:///d:/p4i-office-laravel/docs/staging-permission-gate.md).
- Karena izin eksplisit dari Ketua/Pengurus Yayasan P4I untuk membuat/mengubah subdomain staging, database MariaDB, `.env`, migrasi/seeder, cron job, dan penyimpanan privat di server Hostinger **belum diberikan**, maka eksekusi **WAJIB BERHENTI pada Tahap P0** untuk operasi server jarak jauh dengan status:
  **`LOCAL PREPARATION COMPLETE — STAGING AUTHORIZATION PENDING`**

### Tahap P1 — Pengujian Teknis Terkendali (Verifikasi Lokal Mendalam vs Status Server Hostinger)
1. **Verifikasi Scheduler & Mutex (`STB-06`, Section F.4):**
   - Menambahkan pengujian otomatis di [`ArchiveTransactionConcurrencyTest`](file:///d:/p4i-office-laravel/tests/Feature/ArchiveTransactionConcurrencyTest.php) yang tidak hanya memeriksa `schedule:list`, tetapi mengeksekusi langsung `php artisan schedule:run` pada batas interval 15 menit serta memverifikasi proteksi lock `withoutOverlapping()` agar dua eksekusi rekonsiliasi tidak bertabrakan.
2. **Verifikasi 4 Mode Kegagalan Crash Recovery (`STB-07`, Section F.5):**
   - Membedakan dan menguji secara terpisah keempat mode kegagalan:
     1. *Exception aplikasi biasa* (`try...catch` in-process).
     2. *Proses keluar dengan kode 137* (`exit(137)` pada subproses CLI sebelum blok cleanup).
     3. *Proses dihentikan paksa oleh sistem operasi* (`$process->start()` + `$process->stop(0, 9)` saat subproses sedang aktif menulis berkas parsial).
     4. *Kegagalan fatal batas sumber daya / memori hosting* (`ini_set('memory_limit', '24M')` + alokasi memori berlebih yang memicu PHP Engine Fatal Error tak tertangkap).
3. **Pemisahan Jujur Status Lokal vs Server Hostinger:**
   - Mencatat seluruh hasil uji pada [`docs/staging-qa-test-matrix.md`](file:///d:/p4i-office-laravel/docs/staging-qa-test-matrix.md) dengan pemisahan tegas antara **Verifikasi Lokal (`PASS`)** dan **Eksekusi Server Hostinger (`BLOCKED`)**.

### Tahap UAT — User Acceptance Testing 15 Skenario (Section G)
- Memperluas pengujian otomatis end-to-end 15 skenario UAT di [`StagingUatPreparationTest`](file:///d:/p4i-office-laravel/tests/Feature/StagingUatPreparationTest.php) agar mencakup siklus penuh: login & logout, pembuatan draf dari templat, revisi, pengajuan pemeriksaan, klaim & pengembalian revisi (`CHANGES_REQUESTED`), persetujuan substansi draf (`APPROVED` dengan `official_number = NULL`), ekspor & unduhan PDF/DOCX berlabel `DRAF`, pencatatan & tampilan **Buku Agenda Surat Keluar & Surat Masuk** (`/agenda/outgoing` & `/agenda/incoming`), pencarian arsip, penolakan akses surat `RAHASIA` (`403 Forbidden`), pemeriksaan audit trail oleh administrator, simulasi backup & pemulihan arsip berverifikasi SHA-256, serta penolakan penerbitan nomor surat resmi (`OfficialNumberingBlockedException`).
- Mendokumentasikan pemisahan tegas di [`docs/staging-uat-manual-results.md`](file:///d:/p4i-office-laravel/docs/staging-uat-manual-results.md) antara **Simulasi Otomatis PHPUnit (`PASS`)** dan **UAT Manual oleh Staf/Pengurus Yayasan P4I (`NOT RUN / BLOCKED`)**.

---

## 4. Pemetaan Acceptance Gates (`STB-01` s.d. `STB-16`)

| Gate ID | Deskripsi Persyaratan | Dokumen Bukti Utama |Target Status |
| :--- | :--- | :--- | :--- |
| `STB-01` | Paket konfigurasi staging siap dan bebas rahasia di Git | `docs/hostinger-staging-preflight.md` | `PASS` (Lokal) |
| `STB-02` | Izin pemilik proyek untuk perubahan Hostinger tercatat | `docs/staging-permission-gate.md` | `BLOCKED` (Menunggu Izin) |
| `STB-03` | Migrasi, foreign key, indeks, dan trigger berjalan di MariaDB staging | `docs/staging-qa-test-matrix.md` | `PASS` (Lokal) / `BLOCKED` (Hostinger) |
| `STB-04` | HTTPS, cookie sesi aman, CSRF, dan throttling login berfungsi | `docs/staging-qa-test-matrix.md` | `PASS` (Lokal) / `BLOCKED` (Hostinger) |
| `STB-05` | Penyimpanan arsip berada di luar direktori publik dan tidak dapat diakses langsung | `docs/staging-qa-test-matrix.md` | `PASS` (Lokal) / `BLOCKED` (Hostinger) |
| `STB-06` | `schedule:run` dan `p4i:reconcile-exports` berjalan dengan lock `withoutOverlapping()` | `docs/staging-qa-test-matrix.md` | `PASS` (Lokal) / `BLOCKED` (Hostinger) |
| `STB-07` | Pemulihan crash memulihkan atau menandai `ERROR` pekerjaan `PENDING` yang macet | `docs/staging-qa-test-matrix.md` | `PASS` (Lokal, 4 Mode) / `BLOCKED` (Hostinger) |
| `STB-08` | Tidak ada metadata `READY` yang menunjuk ke berkas hilang atau korup | `docs/staging-qa-test-matrix.md` | `PASS` (Lokal) |
| `STB-09` | Ekspor PDF menghasilkan berkas valid ber-hash SHA-256 dan watermark `DRAF` | `docs/staging-qa-test-matrix.md` | `PASS` (Lokal) |
| `STB-10` | Ekspor DOCX menghasilkan berkas OOXML valid ber-hash SHA-256 dan label `DRAF` | `docs/staging-qa-test-matrix.md` | `PASS` (Lokal) |
| `STB-11` | Dokumen `RAHASIA` hanya dapat diakses dan diunduh pengguna berwenang | `docs/staging-qa-test-matrix.md` | `PASS` (Lokal) |
| `STB-12` | Backup dan restore database + arsip berhasil diuji dengan verifikasi SHA-256 | `docs/staging-backup-restore-evidence.md` | `PASS` (Lokal) / `BLOCKED` (Hostinger) |
| `STB-13` | 15 skenario UAT terdokumentasi dengan jujur antara otomatis dan manual | `docs/staging-uat-manual-results.md` | `PASS` (Otomatis) / `NOT RUN` (Manual) |
| `STB-14` | `official_numbering_active = false` dan `letters.official_number` tetap `NULL` | `docs/hostinger-staging-preflight.md` | `PASS` |
| `STB-15` | Seluruh pengujian otomatis dan pemeriksaan gaya kode lulus | `docs/implementation-evidence-sprint-m5b-b.md` | `PASS` |
| `STB-16` | Matriks Go/No-Go menyajikan status aktual dan risiko residual | `docs/staging-go-no-go.md` | `PASS` |

