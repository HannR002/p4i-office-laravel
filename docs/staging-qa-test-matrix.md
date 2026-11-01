# Matriks Pengujian QA Teknis Staging (Sprint M5B-B — Tahap P1 & Verifikasi Lokal)

**Proyek:** P4I Office v1.0  
**Sprint:** M5B-B (Controlled Hostinger Staging Verification & User Acceptance Testing)  
**Acuan Spesifikasi:** `P4I_Office_Addendum_SRS_Sprint_M5B_B_2026-11-01.docx` — Bagian F (`STB-03` s.d. `STB-11`)  
**Implementer Teknis:** Argon — Reasoning Medium  
**Tim Perancangan & Auditor:** GPT-6  
**Tanggal Pengujian:** 2026-11-01  

---

## 1. Pemisahan Jujur: Pengujian Lokal vs Pengujian Server Hostinger

Sesuai keputusan Gerbang Izin Hostinger (`STB-02`: `LOCAL PREPARATION COMPLETE — STAGING AUTHORIZATION PENDING`), seluruh skenario teknis Tahap P1 telah diverifikasi penuh pada lingkungan pengujian lokal (`PHP 8.3.36` + `MariaDB 10.4.32-MariaDB`), sedangkan eksekusi langsung pada server **Hostinger Business Web Hosting** dicatat secara jujur sebagai **`BLOCKED`** menunggu izin tertulis pemilik proyek.

---

## 2. Matriks Verifikasi Runtime PHP, MariaDB, dan Keamanan (Section F.1 – F.3)

| Area Uji | Gate ID | Item Pemeriksaan | Metode Verifikasi | Bukti Aktual Lokal | Status Lokal | Status Server Hostinger |
| :--- | :---: | :--- | :--- | :--- | :---: | :---: |
| **Runtime PHP & Composer** | `STB-01` | Versi PHP >= 8.2, 14 ekstensi wajib aktif, `composer.lock` sinkron, `memory_limit` & `max_execution_time` terukur | `php artisan p4i:staging-preflight --json` & `StagingUatPreparationTest` | PHP `8.3.36`, 14/14 ekstensi `true`, `composer_lock_present: true` | `PASS` | `BLOCKED` (Menunggu Izin `STB-02`) |
| **Database MariaDB / InnoDB** | `STB-03` | Engine `InnoDB`, 8 migrasi berjalan, foreign key & indeks aktif, `SELECT ... FOR UPDATE`, 4 trigger immutability mencegah `UPDATE`/`DELETE` pada `letter_revisions` dan `audit_events` | `php artisan migrate:status`, `LetterDraftManagementTest::test_m1_05`, `p4i:staging-preflight` | MariaDB `10.4.32-MariaDB`, `innodb_supported: true`, 4/4 trigger aktif & memblokir mutasi SQL mentah | `PASS` | `BLOCKED` (Menunggu Izin `STB-02`) |
| **HTTPS, Cookie Sesi & Keamanan** | `STB-04` | Cookie `HttpOnly`, `SameSite=lax`, `Secure` (staging), proteksi CSRF (`419`), throttling login (`429`), pencabutan sesi akun `DISABLED` | `LetterDraftManagementTest::test_m1_01`, `test_m1_10`, `SecurityHardeningAndAuditClosureTest` | Request POST tanpa CSRF ditolak `419`, akun nonaktif dicabut sesinya, brute-force dibatasi | `PASS` | `BLOCKED` (Menunggu Izin `STB-02`) |
| **Isolasi Penyimpanan Privat** | `STB-05` | Direktori `storage/app/private/archives` berada di luar `public/`, tidak ada akses URL langsung tanpa autentikasi & otorisasi | `SecureDocumentArchiveTest` & `p4i:staging-preflight` | `private_storage_outside_public: true`; unduhan tanpa sesi atau lintas organisasi ditolak `403` | `PASS` | `BLOCKED` (Menunggu Izin `STB-02`) |

---

## 3. Verifikasi Scheduler & Lock `withoutOverlapping()` (Section F.4 — `STB-06`)

Tidak hanya memeriksa daftar jadwal melalui `php artisan schedule:list`, pengujian otomatis pada [`ArchiveTransactionConcurrencyTest::test_m5b_b_scheduler_schedule_run_executes_reconciliation_and_enforces_without_overlapping_mutex`](file:///d:/p4i-office-laravel/tests/Feature/ArchiveTransactionConcurrencyTest.php#L493-L577) memverifikasi eksekusi nyata `php artisan schedule:run`:

| No | Aspek yang Diuji | Skenario & Prosedur Pengujian | Hasil Aktual | Status Lokal | Status Server Hostinger |
| :-: | :--- | :--- | :--- | :---: | :---: |
| 1 | **Pendaftaran Jadwal (`schedule:list`)** | Menjalankan `php artisan schedule:list` untuk memastikan ekspresi cron `*/15 * * * *` terdaftar untuk `p4i:reconcile-exports --stale-minutes=15`. | Terdaftar dengan interval 15 menit dan `withoutOverlapping(10)`. | `PASS` | `BLOCKED` |
| 2 | **Eksekusi Nyata `schedule:run`** | Menetapkan waktu simulasi pada batas 15 menit (`2026-11-01 12:15:00`), menyiapkan rekaman `PENDING` macet berusia 30 menit beserta berkas parsial, lalu memanggil `php artisan schedule:run`. | `schedule:run` memicu `p4i:reconcile-exports`, mengubah status rekaman macet menjadi `ERROR`, dan menghapus berkas parsial. | `PASS` | `BLOCKED` |
| 3 | **Proteksi Lock `withoutOverlapping()`** | Mengunci mutex event (`$reconcileEvent->mutex->create($reconcileEvent)`) seolah proses pertama masih berjalan, lalu memanggil `php artisan schedule:run`. | Eksekusi kedua dilewati secara aman (rekaman baru tetap `PENDING`); setelah mutex dilepas (`forget`), `schedule:run` berikutnya memproses rekaman tersebut menjadi `ERROR`. | `PASS` | `BLOCKED` |

---

## 4. Matriks Perbandingan 4 Mode Kegagalan Crash Recovery (Section F.5 — `STB-07` & `STB-08`)

Sesuai persyaratan Bagian F.5, pengujian pada [`ArchiveTransactionConcurrencyTest`](file:///d:/p4i-office-laravel/tests/Feature/ArchiveTransactionConcurrencyTest.php) membedakan secara tegas **4 mode kegagalan** yang berbeda mekanismenya:

| Mode | Jenis Kegagalan | Metode Simulasi pada Pengujian | Kondisi Sesaat Setelah Kegagalan (Sebelum Rekonsiliasi) | Tindakan Rekonsiliasi (`p4i:reconcile-exports`) | Hasil Akhir & Bukti Audit | Status Lokal |
| :-: | :--- | :--- | :--- | :--- | :--- | :---: |
| **1** | **Exception aplikasi biasa** (*Handled in-process exception*) | `test_m4b_10_export_failure_prevents_orphan_file_and_records_audit` & `test_m4b_10_crash_recovery_updates_status_error_and_audits` | Ditangkap langsung oleh blok `try...catch (\Throwable)` di dalam `DocumentExportService`: berkas `.tmp` langsung dihapus dan status diubah ke `ERROR` saat itu juga. | Tidak meninggalkan `PENDING` macet karena sudah ditangani sinkron. | Status `ERROR`, 0 berkas yatim, tercatat `DOCUMENT_EXPORT_FAILED`. | `PASS` |
| **2** | **Proses keluar mendadak dengan kode 137** (*Abrupt subprocess `exit(137)`*) | `test_m5b_a_real_php_subprocess_abrupt_termination_and_schedule_reconciliation` (Subproses PHP CLI terpisah memanggil `exit(137)` di tengah penulisan berkas) | Blok `catch` **tidak tereksekusi**. Rekaman DB tertinggal `PENDING`, berkas parsial `%PDF-1.4 TRUNCATED...` dan berkas `.tmp` tertinggal di disk. | Mendeteksi `PENDING` > 15 menit, memeriksa hash SHA-256 (tidak cocok dengan berkas parsial), menghapus berkas parsial & `.tmp`, mengubah status ke `ERROR`. | Status `ERROR`, berkas parsial & temp bersih, tercatat `DOCUMENT_EXPORT_RECONCILED_ERROR`. | `PASS` |
| **3** | **Proses dihentikan paksa oleh Sistem Operasi** (*OS-level forced kill `Process::stop(0, 9)`*) | `test_m5b_b_crash_recovery_distinguishes_os_forced_kill_and_fatal_timeout_exhaustion` (Subproses PHP dijalankan asinkron via `$process->start()`, menulis berkas parsial + marker, lalu dibunuh paksa oleh OS saat sedang aktif berjalan) | Proses mati seketika dari luar oleh sinyal OS (`TerminateProcess` / `SIGKILL`). Rekaman DB tertinggal `PENDING` dan berkas parsial tertinggal di `private_archives`. | Saat melewati ambang stale 15 menit, `p4i:reconcile-exports` memverifikasi ketidakcocokan hash SHA-256, menghapus berkas parsial, dan menandai `ERROR`. | Status `ERROR`, berkas parsial dihapus, tercatat `DOCUMENT_EXPORT_RECONCILED_ERROR`. | `PASS` |
| **4** | **Fatal error batas sumber daya / memori hosting** (*Uncatchable PHP Engine OOM Fatal Error*) | `test_m5b_b_crash_recovery_distinguishes_os_forced_kill_and_fatal_timeout_exhaustion` (Subproses PHP menulis `PENDING` + berkas parsial, menerapkan `ini_set('memory_limit', '32M')`, lalu mengalokasikan 64 MB hingga mati karena *Fatal error: Allowed memory size exhausted*) | Mesin Zend PHP berhenti mendadak dengan kode keluar non-zero sebelum penyelesaian transaksi ekspor. Rekaman DB tertinggal `PENDING` beserta berkas parsial. | Rekonsiliasi terjadwal memeriksa rekaman stale, menghapus berkas parsial yang korup, dan mengubah status ke `ERROR`. | Status `ERROR`, berkas parsial dihapus, tercatat `DOCUMENT_EXPORT_RECONCILED_ERROR`. | `PASS` |

> [!NOTE]
> **Pemulihan Pekerjaan Utuh (`RECOVERED_READY`) & Proteksi Pekerjaan Aktif (`STB-07` & `STB-08`):**  
> Selain keempat mode kegagalan parsial di atas, `test_stg_05_sudden_process_crash_reconciles_stale_pending_and_protects_valid_files` juga membuktikan bahwa:
> 1. Pekerjaan `PENDING` macet yang **telah selesai menulis berkas utuh ber-hash SHA-256 cocok** dipulihkan otomatis menjadi `READY` (`DOCUMENT_EXPORT_RECONCILED_READY`).
> 2. Pekerjaan `PENDING` aktif yang baru berjalan (< 15 menit) **tidak diganggu dan berkasnya tidak dihapus**.
> 3. Rekaman `READY` yang kehilangan berkas fisik atau mengalami korupsi hash diturunkan menjadi `ERROR` sehingga **tidak pernah ada metadata `READY` yang menunjuk ke berkas hilang/korup** (`STB-08`).

---

## 5. Verifikasi Ekspor PDF, DOCX & Isolasi Surat `RAHASIA` (Section F.6 — `STB-09`, `STB-10`, `STB-11`)

| Gate ID | Kriteria Verifikasi | Bukti Teknis Aktual (`Sprint M5B-A` & `Sprint M5B-B`) | Status Lokal |
| :---: | :--- | :--- | :---: |
| `STB-09` | **Ekspor PDF Valid & Berwatermark `DRAF`** | Berkas PDF 1 halaman (3.550 byte, SHA-256 `ad4aa5b1...`) dan 3 halaman (7.498 byte, SHA-256 `49395d84...`) memiliki header `%PDF-1.7`, trailer `%%EOF`, teks `DRAF — BELUM MEMILIKI NOMOR SURAT RESMI`, dan nomor surat `[BELUM BERNOMOR — DRAF]`. | `PASS` |
| `STB-10` | **Ekspor DOCX OOXML Valid & Berlabel `DRAF`** | Arsip ZIP OOXML utuh (`[Content_Types].xml`, `word/document.xml`, `word/header1.xml`, `word/footer1.xml`), lulus parser XML dan konversi visual ke PDF/PNG tanpa halaman kosong maupun teks terpotong. | `PASS` |
| `STB-11` | **Proteksi Dokumen `RAHASIA` (`CONFIDENTIAL`)** | Kebijakan [`LetterPolicy`](file:///d:/p4i-office-laravel/app/Policies/LetterPolicy.php) dan [`DocumentArchiveController`](file:///d:/p4i-office-laravel/app/Http/Controllers/DocumentArchiveController.php) menolak tampilan (`403`), revisi (`403`), pengajuan (`403`), dan unduhan arsip (`403`) oleh pengguna yang bukan pembuat, bukan `SYSTEM_ADMIN`, dan tidak memiliki mandat aktif pada unit terkait. | `PASS` |

