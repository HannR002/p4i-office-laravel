# BUKTI VERIFIKASI TEKNIS AKHIR & KESIAPAN STAGING HOSTINGER — SPRINT M5B-A

**Sistem:** P4I Office v1.0  
**Sprint:** M5B-A (*Final Evidence Verification & Hostinger Staging Preparation*)  
**Workspace Aplikasi & Pengujian:** `D:\p4i-office-laravel`  
**Workspace Sinkronisasi Dokumentasi:** `D:\p4i-office`  
**Branch Sprint M5B-A:** `feat/sprint-m5b-a-evidence-verification`  
**Baseline Commit Sprint M5A:** `387bfc7d08a49b2b7055d6a6ce4cdd3ec06048c1` (`feat/sprint-m5a-staging-readiness`)  
**Model Implementer (Lead Software Engineer & Technical Test Engineer):** Argon — Reasoning Medium  
**Tim Perancangan, Software Architect, Auditor & Penyusun Laporan Resmi:** GPT-6  
**Pemegang Otoritas Administratif:** Ketua dan Dewan Pengurus Yayasan P4I  
**Status Akhir:** **`LOCAL PREPARATION COMPLETE — HOSTINGER STAGING BLOCKED`**

---

## 1. PEMBAGIAN TUGAS & KOREKSI ATRIBUSI PELAKSANA

1. **Batas Peran:**  
   Dokumen ini merupakan catatan bukti teknis implementasi dan verifikasi (*engineering & testing evidence*) yang disusun oleh **Argon — Reasoning Medium** selaku *Lead Software Engineer* dan *Technical Test Engineer*. Kewenangan atas perancangan sistem, SRS, keputusan arsitektur, audit independen, dan laporan resmi P4I Office sepenuhnya berada pada **GPT-6**, sedangkan kewenangan administratif, SOP penomoran, SK Mandat pejabat, dan izin server berada pada **Ketua/Pengurus Yayasan P4I**.
2. **Koreksi Atribusi Dokumentasi Sprint M5A:**  
   Sesuai instruksi Sprint M5B-A, atribusi pelaksana pada 8 dokumen Sprint M5A (`docs/sprint-m5a-plan.md`, `docs/implementation-evidence-sprint-m5a.md`, `docs/m4b-acceptance-closure.md`, `docs/hostinger-staging-deployment-plan.md`, `docs/staging-compatibility-matrix.md`, `docs/staging-uat-test-cases.md`, `docs/staging-rollback-and-backup.md`, dan `docs/staging-go-no-go.md`) telah dikoreksi dari `Gemini` menjadi **`Argon — Reasoning Medium`** tanpa mengubah hasil pengujian, commit, maupun riwayat Git historis.

---

## 2. BAGIAN A — AUDIT BASELINE REPOSITORI AKTUAL

Pemeriksaan langsung terhadap source code dan basis data aktual pada commit `387bfc7d08a49b2b7055d6a6ce4cdd3ec06048c1` menghasilkan fakta teknis berikut:

| Komponen Audit | Klaim Baseline Sprint M5A | Bukti & Hasil Pemeriksaan Aktual pada Source Code | Status |
|---|---|---|---|
| **Git Commit & Branch** | `387bfc7d08a49b2b7055d6a6ce4cdd3ec06048c1` (`feat/sprint-m5a-staging-readiness`) | `git rev-parse HEAD` cocok 100% (`387bfc7d08a49b2b7055d6a6ce4cdd3ec06048c1`); working tree bersih sebelum cabang `feat/sprint-m5b-a-evidence-verification` dibuat. | **PASS** |
| **Runtime & Dependensi** | Laravel 13, PHP 8.3, Livewire 4, Dompdf, PHPWord | PHP `8.3.36` CLI (ZTS Visual C++ 2019 x64), `laravel/framework` `v13.39.0`, `livewire/livewire` `v4.4.8`, `dompdf/dompdf`, `phpoffice/phpword` terinstal dan terkunci di `composer.lock`. | **PASS** |
| **Konfigurasi Laravel & Keamanan** | `official_numbering_active = false`, `private_archives` terisolasi | `config/features.php` menetapkan `'official_numbering_active' => env('FEATURE_NUMBERING_ACTIVE', false)` (`false`). `config/filesystems.php` menetapkan disk `private_archives` pada `storage_path('app/private_archives')` dengan `'visibility' => 'private'` di luar `public/`. | **PASS** |
| **Database, Migrations & Triggers** | MariaDB/InnoDB, seluruh migrasi aktif, 4 trigger imutabilitas | MariaDB `10.4.32-MariaDB`, engine `InnoDB` (`SUPPORTED`), 8 berkas migrasi berstatus `Ran`, 15 tabel domain terbentuk, dan 4 trigger imutabilitas (`trg_letter_revisions_no_update`, `trg_letter_revisions_no_delete`, `trg_audit_events_no_update`, `trg_audit_events_no_delete`) berstatus `ACTIVE`. | **PASS** |
| **Jadwal Scheduler Laravel 13 (`schedule:list`)** | `p4i:reconcile-exports` terjadwal setiap 15 menit | **Temuan Audit:** Pada commit `387bfc7`, jadwal hanya ditulis di `app/Console/Kernel.php`, sedangkan Laravel 13 (`bootstrap/app.php`) membaca jadwal dari `routes/console.php`, sehingga `php artisan schedule:list` awalnya menampilkan `No scheduled tasks have been defined`.<br>**Perbaikan M5B-A:** Jadwal didaftarkan pada `routes/console.php` sehingga `php artisan schedule:list` menampilkan `*/15 * * * * php artisan p4i:reconcile-exports --stale-minutes=15`. | **PASS** *(Diperbaiki pada M5B-A)* |
| **Regression Test Suite** | 138 passed, 672 assertions | Eksekusi ulang pada commit `387bfc7` menghasilkan **138 passed (673 assertions)**. Setelah penambahan 2 pengujian verifikasi mendalam Sprint M5B-A (`test_m5b_a_*`), hasil akhir adalah **140 passed (722 assertions)** dengan `exit code 0`. | **PASS** |

---

## 3. BAGIAN B — VERIFIKASI MENDALAM & RENDER VISUAL 4 DOKUMEN SINTETIS (PDF & DOCX)

Verifikasi dokumen pada Sprint M5B-A tidak hanya memeriksa keberadaan tag page-break atau checksum SHA-256, melainkan melakukan:
1. **Dekode Objek Halaman & Aliran Konten PDF (`/Type /Page` -> `/Contents` -> `gzuncompress` FlateDecode Stream)** untuk mengekstrak seluruh baris teks yang ter-render pada setiap halaman secara terpisah.
2. **Inspeksi Struktur Dom/OpenXML (`word/document.xml`, `word/header1.xml`, `word/footer1.xml`) & Pembacaan Objek `PhpOffice\PhpWord\IOFactory::load()`** untuk memverifikasi isi setiap halaman logis, baris/kolom tabel `<w:tbl>`, serta ketiadaan makro.
3. **Render Visual Halaman demi Halaman (PNG Screenshots)** menggunakan pembaca PDF dan *headless browser* Chrome ke direktori `docs/evidence/sprint-m5b-a/`.

### 3.1 Tabel Ringkasan Verifikasi 4 Berkas Sintetis (`docs/evidence/sprint-m4b/`)

| Nama Berkas | Ukuran Aktual | Checksum SHA-256 Aktual | Jumlah Halaman Ter-render | Watermark di Setiap Halaman | Tabel Data & Posisi Halaman | Bukti Render Visual & Laporan Struktur | Status |
|---|---|---|---|---|---|---|---|
| `sample_1page_draft.pdf` | `3.628 bytes` | `f905a022dd9de52a257dd60200aa9a4e13a979e1fda0f14f85e15aa3beb1e8c2` | **1 Halaman** (`Page Obj #6` -> `Stream Obj #7`, 37 baris teks) | **Ya** (Diagonal: `DRAF — BELUM RESMI` + Footer: `DRAF SIMULASI P4I`) | Tidak ada tabel (Surat Undangan 1 Hal) | Terverifikasi visual & OCR halaman 1 + `document_verification_report.json` | **PASS** |
| `sample_1page_draft.docx` | `9.592 bytes` | `5e6e25f762d60076c243be3ac3cffcf62b544a9559b0c7606cddf0bae893a2e8` | **1 Halaman** (1 seksi, 0 page break, 16 entri ZIP OpenXML, macro-free) | **Ya** (`word/header1.xml` & `word/footer1.xml` berulang) | Tidak ada tabel (Surat Undangan 1 Hal) | `docs/evidence/sprint-m5b-a/render_docx_1page_p1.png` (`63.334 bytes`) | **PASS** |
| `sample_3page_draft.pdf` | `7.822 bytes` | `fc6ea2b3312c80b9d7d3e8711eeaad37da4842eef5e1fd610bd7ba5a39eb5268` | **3 Halaman** (`Page Obj #6->#7`, `Obj #14->#15`, `Obj #16->#17`) | **Ya** (Tercetak pada Stream Hal 1, Hal 2, dan Hal 3) | **Ya — Halaman 2** (Tabel 7 baris x 6 kolom: Header + 6 program strategis IKU) | Terverifikasi visual & OCR Halaman 1, 2, 3 + `document_verification_report.json` | **PASS** |
| `sample_3page_draft.docx` | `11.225 bytes` | `36c74ec71ed6c5e787e882224c29c087d1ba205484b040bd998afe02fed7cb58` | **3 Halaman** (1 seksi, 2 `<w:br w:type="page"/>`, 16 entri ZIP, macro-free) | **Ya** (`word/header1.xml` & `word/footer1.xml` pada Hal 1, 2, 3) | **Ya — Halaman 2** (`<w:tbl>` 7 `<w:tr>` x 6 `<w:tc>` identik dengan PDF) | `render_docx_3page_p1.png`, `render_docx_3page_p2.png`, `render_docx_3page_p3.png` | **PASS** |

### 3.2 Rincian Isi Per Halaman hasil Render & Ekstraksi Stream (`sample_3page_draft.pdf` & `sample_3page_draft.docx`)

- **Halaman 1 (PDF `Page Obj #6 / Stream #7` & DOCX Halaman Logis 1):**
  - **Kop Yayasan:** `P4I | YAYASAN PUSAT PENDIDIKAN DAN PENELITIAN PEMBANGUNAN INDONESIA | Gedung P4I Lantai 3, Jl. Pembangunan Nasional, Jakarta`.
  - **Metadata Naskah:** `Nomor : [SIMULASI] DRAF/P4I/2026/REV-1` (bukan nomor surat resmi), `Tanggal : Jakarta, 10 November 2026`, `Sifat : TERBATAS`, `Perihal : Laporan Evaluasi Komprehensif dan Rekomendasi Program Pengabdian Masyarakat 2026`.
  - **Tujuan:** `Kepada Yth. Dewan Pembina dan Pengawas Yayasan P4I di Tempat`.
  - **Isi Bagian I:** `BAGIAN I: PENDAHULUAN DAN LATAR BELAKANG EVALUASI TAHUNAN` beserta 3 paragraf pembuka evaluasi 4 pilar utama.
  - **Watermark Halaman 1:** Diagonal `DRAF — BELUM RESMI / TIDAK BERLAKU SEBAGAI SURAT RESMI` dan Footer `DRAF SIMULASI P4I — TIDAK BERLAKU SEBAGAI SURAT RESMI — NOMOR RESMI STRICTLY NULL` (pada DOCX melalui `word/header1.xml` & `word/footer1.xml`).
- **Halaman 2 (PDF `Page Obj #14 / Stream #15` & DOCX Halaman Logis 2):**
  - **Isi Bagian II:** `BAGIAN II: MATRIKS CAPAIAN INDIKATOR KINERJA UTAMA (IKU)` beserta paragraf pengantar matriks triwulan I–IV 2026.
  - **Tabel Matriks IKU (7 Baris x 6 Kolom — Ter-render Penuh di Halaman 2):**
    1. Header: `No` | `Program Strategis` | `Target Output` | `Realisasi` | `Persentase` | `Keterangan`
    2. Baris 1: `1` | `Pelatihan Tata Kelola Arsip Digital` | `20 Lembaga Mitra` | `22 Lembaga Mitra` | `110%` | `Melampaui Target`
    3. Baris 2: `2` | `Pendampingan Desa Cerdas P4I` | `5 Kabupaten` | `5 Kabupaten` | `100%` | `Tercapai Sesuai Rencana`
    4. Baris 3: `3` | `Publikasi Jurnal Riset Kebijakan` | `12 Artikel Ilmiah` | `14 Artikel Ilmiah` | `116%` | `Terakreditasi Sinta 2`
    5. Baris 4: `4` | `Audit Kepatuhan SOP Administrasi` | `4 Triwulan` | `4 Triwulan` | `100%` | `Kepatuhan 98.4%`
    6. Baris 5: `5` | `Pemberdayaan UMKM Binaan Yayasan` | `50 Kelompok Usaha` | `48 Kelompok Usaha` | `96%` | `2 Kelompok Dalam Pendampingan`
    7. Baris 6: `6` | `Lokakarya Nasional Pembangunan 2026` | `300 Peserta` | `345 Peserta` | `115%` | `Terselenggara Sukses`
  - **Paragraf Penutup Tabel:** `Seluruh data di atas telah diverifikasi secara berjenjang oleh masing-masing kepala divisi pelaksana dan didukung oleh berita acara serah terima kegiatan.`
  - **Watermark Halaman 2:** Diagonal `DRAF — BELUM RESMI / TIDAK BERLAKU SEBAGAI SURAT RESMI` dan Footer `DRAF SIMULASI P4I — TIDAK BERLAKU SEBAGAI SURAT RESMI — NOMOR RESMI STRICTLY NULL`.
- **Halaman 3 (PDF `Page Obj #16 / Stream #17` & DOCX Halaman Logis 3):**
  - **Isi Bagian III:** `BAGIAN III: REKOMENDASI KEBIJAKAN DAN LANGKAH TINDAK LANJUT STRATEGIS` beserta butir rekomendasi `1.`, `2.`, `3.` dan paragraf penutup.
  - **Blok Penandatangan (Tanpa Tanda Tangan/Stempel Aktif):** `Dewan Pengurus Yayasan P4I`, `[TANDA TANGAN BELUM DITERBITKAN]`, `Drs. H. Mulyadi, M.M.`, `Ketua Yayasan`.
  - **Kotak Penafian & Metadata Revisi:** `PERHATIAN KEARSIPAN: Dokumen ini berstatus DRAF SIMULASI dan belum diterbitkan secara resmi oleh Yayasan P4I. Segala bentuk penomoran resmi, tanda tangan, dan stempel dinonaktifkan. Diekspor oleh: Administrator Yayasan | Waktu: 01/11/2026 07:11:17 WIB | Revisi Ke-1`.
  - **Watermark Halaman 3:** Diagonal `DRAF — BELUM RESMI / TIDAK BERLAKU SEBAGAI SURAT RESMI` dan Footer `DRAF SIMULASI P4I — TIDAK BERLAKU SEBAGAI SURAT RESMI — NOMOR RESMI STRICTLY NULL`.

---

## 4. BAGIAN C — AUDIT CRASH RECOVERY (`ReconcileExportsCommand`, JADWAL, TRANSAKSI, METADATA & CLEANUP)

### 4.1 Audit Komponen Crash Recovery & Perbaikan Teknis pada Sprint M5B-A

1. **Perintah Konsol (`app/Console/Commands/ReconcileExportsCommand.php`):**
   - Signature: `p4i:reconcile-exports {--stale-minutes=15} {--org=}`
   - Memanggil `DocumentExportService::reconcileInterruptedExports($staleMinutes, $organizationId)` dan mencetak laporan JSON terstruktur (`recovered_pending`, `failed_stale_pending`, `broken_ready_marked_error`, `orphan_storage_files_removed`, `stale_temp_files_removed`, `reconciled_at`).
2. **Jadwal Eksekusi (`routes/console.php` & `app/Console/Kernel.php`):**
   - Didaftarkan pada `routes/console.php` dengan `Schedule::command('p4i:reconcile-exports --stale-minutes=15')->everyFifteenMinutes()->withoutOverlapping();`.
   - Terverifikasi melalui `php artisan schedule:list`:
     `*/15 * * * *  php artisan p4i:reconcile-exports --stale-minutes=15`
3. **Pre-Commit Metadata & Transaksi Berbasis Row Lock (`app/Services/DocumentExportService.php`):**
   - **Pre-Commit Metadata pada `exportLetter()`:** Segera setelah berkas sementara `$tempPath` selesai di-render dan dihitung `hash_file('sha256', $tempPath)`-nya, metadata `file_path`, `file_hash`, `file_size`, dan `mime_type` disimpan ke baris `PENDING` sebelum penyalinan biner ke disk `private_archives`. Dengan demikian, apabila pekerja PHP berhenti mendadak (`SIGKILL`/OOM) tepat setelah menulis berkas utuh ke `private_archives` namun sebelum status berubah menjadi `READY`, `reconcileInterruptedExports()` memiliki acuan `file_hash` SHA-256 yang sah untuk memverifikasi keutuhan berkas dan memulihkannya ke `READY`.
   - **Transaksi Atomik & Pessimistic Locking (`DB::transaction` + `lockForUpdate()`):** Seluruh perubahan status (`PENDING -> READY`, `PENDING -> ERROR`, dan `READY -> ERROR`) beserta pencatatan `AuditEvent` (`DOCUMENT_EXPORTED`, `DOCUMENT_EXPORT_FAILED`, `DOCUMENT_EXPORT_RECOVERED`, `DOCUMENT_EXPORT_RECONCILED_ERROR`, `DOCUMENT_EXPORT_BROKEN_READY`) dibungkus di dalam `DB::transaction()` menggunakan `DocumentExport::where('id', ...)->lockForUpdate()->first()` untuk mencegah *race condition* antar proses konkuren.
4. **Aturan Cleanup & Perlindungan Berkas Valid:**
   - Menghapus berkas sementara `p4i_export_*` di `sys_get_temp_dir()` yang melampaui ambang waktu *stale*.
   - Menghapus berkas parsial/yatim di `private_archives` yang tidak lulus validasi struktur biner (`isValidExportBinary()`: `%PDF-` + `%%EOF` untuk PDF; struktur ZIP OpenXML `word/document.xml` untuk DOCX) atau tidak cocok hash SHA-256-nya.
   - **Melindungi** berkas milik record `READY` yang valid serta berkas milik proses `PENDING` aktif yang masih berjalan (usia `< staleThresholdMinutes`).
   - Memeriksa seluruh record `READY` dan mengubah statusnya menjadi `ERROR` (`DOCUMENT_EXPORT_BROKEN_READY`) apabila berkas fisiknya hilang atau terdeteksi mengalami perubahan byte/korupsi SHA-256.

### 4.2 Pembedaan Tegas: Pengujian Exception Biasa vs Penghentian Proses PHP Mendadak

| Aspek Perbandingan | Pengujian Exception Biasa (*In-Process Exception*) | Pengujian Penghentian Proses PHP Mendadak (*Sudden Process Kill / Crash*) |
|---|---|---|
| **Penyebab Kegagalan** | Validasi gagal, revisi tidak ditemukan, atau kesalahan pustaka yang melempar `Exception` / `Error` di dalam siklus hidup PHP normal. | Pekerja PHP dihentikan paksa oleh sistem operasi/web server (`SIGKILL` / `exit(137)` / *Out-of-Memory* / *Hard Timeout*) di tengah penulisan berkas. |
| **Apakah Blok `catch (\Throwable)` Dieksekusi?** | **Ya.** Blok `catch` di dalam `exportLetter()` langsung dieksekusi pada saat itu juga. | **Tidak.** Proses berhenti seketika; blok `catch` dan `finally` dilewati seluruhnya. |
| **Kondisi Database & Disk Sesaat Setelah Kejadian** | Baris `document_exports` langsung berstatus `ERROR`, berkas sementara dihapus oleh `catch`, dan `DOCUMENT_EXPORT_FAILED` langsung tercatat. | Baris `document_exports` **tertinggal dalam status `PENDING`**, serta berkas sementara (`p4i_export_*`) dan berkas parsial di `private_archives` **masih tertinggal di disk**. |
| **Mekanisme Pemulihan** | Ditangani secara sinkron oleh `try...catch` pada `exportLetter()`. | Ditangani secara asinkron oleh `php artisan p4i:reconcile-exports` (`reconcileInterruptedExports()`) yang memindai record *stale* `PENDING`, memverifikasi biner/hash, menghapus berkas parsial/temp, dan mengunci baris DB (`lockForUpdate()`) untuk mengubah status ke `ERROR` (atau `READY` jika biner telah utuh). |
| **Metode Pengujian Otomatis di Repositori** | `ArchiveTransactionConcurrencyTest::test_m4b_10_export_failure_prevents_orphan_file_and_records_audit` & `test_m4b_10_crash_recovery_updates_status_error_and_audits` | 1. `test_stg_05_sudden_process_crash_reconciles_stale_pending_and_protects_valid_files` (6 skenario state simultan)<br>2. `test_m5b_a_real_php_subprocess_abrupt_termination_and_schedule_reconciliation` (**Menjalankan subproses CLI PHP terpisah via `Symfony\Component\Process\Process` yang menulis berkas parsial lalu keluar mendadak dengan `exit(137)`, membuktikan status tertinggal `PENDING`, lalu direkonsiliasi hingga tuntas oleh `p4i:reconcile-exports`**) |

---

## 5. BAGIAN D — CHECKLIST DEPLOYMENT STAGING HOSTINGER (PEMERIKSAAN LOKAL SAJA)

> **Kepatuhan Pengamanan Mutlak:** Seluruh pemeriksaan di bawah ini dilakukan 100% secara lokal. **Tidak ada koneksi SSH, FTP, database, atau pengubahan apa pun yang dilakukan ke Hostinger sebelum izin tertulis eksplisit dari pemilik proyek (Ketua/Pengurus Yayasan P4I).**

Instruksi lengkap per langkah tersedia pada `docs/hostinger-staging-deployment-plan.md` dan `docs/staging-rollback-and-backup.md`. Berikut adalah checklist verifikasi kesiapan staging beserta status aktualnya:

| No | Area Kesiapan Staging | Instruksi & Konfigurasi yang Disiapkan | Status Verifikasi Lokal | Status Eksekusi di Server Hostinger |
|---|---|---|---|---|
| **1** | **Subdomain / Website Staging Terpisah** | Penempatan inti aplikasi di `/home/<USER>/p4i_staging_core/` (di luar web root) dan pengikatan `domains/staging-office.<DOMAIN>/public_html/index.php` menggunakan `$app->usePublicPath(__DIR__)`. | **PASS** *(Arsitektur & skrip siap di `docs/hostinger-staging-deployment-plan.md`)* | **BLOCKED** *(Menunggu Izin Pemilik)* |
| **2** | **Database MariaDB Baru Terpisah** | Pembuatan database MariaDB khusus staging (`<DB_STAGING_NAME_PLACEHOLDER>`) dengan kredensial terisolasi, terpisah total dari website/database aktif yayasan. | **PASS** *(Diuji pada `p4i_office_dev` & `p4i_office_test` MariaDB 10.4.32 InnoDB)* | **BLOCKED** *(Menunggu Izin Pemilik)* |
| **3** | **SSL / HTTPS & Keamanan Sesi** | *Force HTTPS* di hPanel, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_ENCRYPT=true`, `APP_DEBUG=false`, HTTP Basic Auth `.htaccess`, dan header `X-Robots-Tag: noindex, nofollow`. | **PASS** *(Konfigurasi `.env.staging` & `.htaccess` siap; `/dev-login` terkunci hanya untuk `local`)* | **BLOCKED** *(Menunggu Izin Pemilik)* |
| **4** | **Isolasi Private Storage** | Direktori `storage/app/private_archives` (`chmod 750`) berada di dalam `p4i_staging_core` (3 level di atas `public_html`), hanya diakses melalui `DocumentArchiveController@download` setelah lolos otorisasi RBAC/Mandat dan verifikasi SHA-256. | **PASS** *(Teruji pada `PrivateArchiveSecurityTest`, `ConfidentialArchiveAuthorizationTest`, & `p4i:staging-preflight`)* | **BLOCKED** *(Menunggu Izin Pemilik)* |
| **5** | **Scheduler & Cron Job** | Pendaftaran `Schedule::command('p4i:reconcile-exports --stale-minutes=15')->everyFifteenMinutes()->withoutOverlapping()` pada `routes/console.php` serta entri Cron hPanel `*/15 * * * * /usr/bin/php .../artisan p4i:reconcile-exports --stale-minutes=15`. | **PASS** (`php artisan schedule:list` & `p4i:reconcile-exports` terverifikasi `exit code 0`) | **BLOCKED** *(Menunggu Izin Pemilik)* |
| **6** | **Migrasi Database & Trigger Imutabilitas** | Eksekusi `php artisan migrate --force` (8 migrasi: 15 tabel InnoDB + 4 trigger imutabilitas `letter_revisions` & `audit_events`) dan pemeriksaan `php artisan p4i:staging-preflight` + 3 opsi kontingensi trigger di `docs/staging-compatibility-matrix.md`. | **PASS** (`migrate:status` & `p4i:staging-preflight` lulus 100%) | **BLOCKED** *(Menunggu Izin Pemilik)* |
| **7** | **Prosedur Backup, Restore & Rollback** | Skrip pencadangan terpadu (`mysqldump --single-transaction --routines --triggers` + `tar -czf private_archives` + verifikasi `sha256sum -c`) dan rollback cepat (`git checkout <sha>` + restore dump SQL). | **PASS** (`BackupRecoveryTest` & `IntegratedArchiveRestoreTest` lulus 100%) | **BLOCKED** *(Menunggu Izin Pemilik)* |

---

## 6. BAGIAN E — MATRIKS 15 SKENARIO UAT (PEMBEDAAN TES OTOMATIS VS UAT AKTUAL STAF/PENGURUS)

Sesuai ketentuan tata kelola, tabel di bawah ini mempertahankan ke-15 skenario UAT wajib dan **membedakan secara tegas** antara hasil pengujian otomatis (`StagingUatPreparationTest` menggunakan PHPUnit pada lingkungan lokal) dengan pelaksanaan UAT aktual oleh manusia (staf/pengurus Yayasan P4I pada server staging Hostinger):

| ID UAT | Skenario UAT | Peran Penguji | Status Tes Otomatis Lokal (`StagingUatPreparationTest`) | Status UAT Aktual oleh Staf/Pengurus P4I | Keterangan Bukti Aktual |
|---|---|---|---|---|---|
| **UAT-01** | Staf login ke sistem | Staf Konseptor | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`POST /login`); UAT manusia menunggu deployment staging. |
| **UAT-02** | Membuat surat baru dari templat aktif | Staf Konseptor | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`GET /letters/create`); UAT manusia menunggu staging. |
| **UAT-03** | Menyimpan draf surat (`v1`, `official_number = NULL`) | Staf Konseptor | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`POST /letters`); UAT manusia menunggu staging. |
| **UAT-04** | Merevisi surat (`v2` append-only, `v1` tetap utuh) | Staf Konseptor | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`PUT /letters/{id}`); UAT manusia menunggu staging. |
| **UAT-05** | Mengajukan draf untuk pemeriksaan (`SUBMITTED`) | Staf Konseptor | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`POST /letters/{id}/submit`); UAT manusia menunggu staging. |
| **UAT-06** | Reviewer bermandat mengklaim & memeriksa draf (`IN_REVIEW`) | Pemeriksa Bermandat | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`POST /approvals/{id}/claim`); UAT manusia menunggu staging. |
| **UAT-07** | Reviewer meminta revisi (`CHANGES_REQUESTED` + alasan wajib) | Pemeriksa Bermandat | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`POST /approvals/{id}/decision`); UAT manusia menunggu staging. |
| **UAT-08** | Staf memperbaiki surat (`v3`) dan mengajukan ulang (`SUBMITTED`) | Staf Konseptor | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`PUT /letters/{id}` + `submit`); UAT manusia menunggu staging. |
| **UAT-09** | Reviewer menyetujui isi draf (`APPROVED`, nomor resmi tetap `NULL`) | Pemeriksa Bermandat | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`POST /approvals/{id}/decision`); UAT manusia menunggu staging. |
| **UAT-10** | Staf mengunduh PDF & DOCX berlabel `DRAF` berwatermark | Staf Konseptor | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`POST /letters/{id}/export` & `GET /archives/{id}/download`). |
| **UAT-11** | Pengguna mencari & memfilter surat di arsip privat | Staf Konseptor | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`GET /archives?search=...&format=PDF`). |
| **UAT-12** | Pengguna tidak berwenang ditolak mengakses surat `RAHASIA` (HTTP 403) | Reviewer Tanpa Mandat | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis pada `/letters/{id}`, `/archives`, dan `/archives/{id}/download`. |
| **UAT-13** | Administrator memeriksa riwayat audit & metrik arsip | Administrator Sistem | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`GET /archives/admin`). |
| **UAT-14** | Simulasi kehilangan berkas, deteksi integritas, dan pemulihan arsip | Administrator Sistem | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`verifyArchiveIntegrity`: `false` -> restore -> `true`). |
| **UAT-15** | Sistem menolak penerbitan nomor surat resmi (`OfficialNumberingBlockedException`) | Semua Peran / Sistem | **PASS** | **NOT RUN** *(BLOCKED)* | Teruji otomatis (`official_numbering_active = false`, `official_number = NULL`). |

---

## 7. BAGIAN F — MATRIKS VERIFIKASI SPRINT M5B-A & DAFTAR BLOCKER

### 7.1 Matriks Verifikasi Akhir Sprint M5B-A

| ID Verifikasi | Ruang Lingkup Pemeriksaan Sprint M5B-A | Metode & Bukti Teknis Aktual | Status |
|---|---|---|---|
| **M5BA-01** | Koreksi atribusi pelaksana pada dokumentasi Sprint M5A menjadi `Argon — Reasoning Medium` tanpa mengubah hasil/commit historis | 8 dokumen Sprint M5A diperbarui pada bagian atribusi pelaksana; riwayat commit `387bfc7d08a49b2b7055d6a6ce4cdd3ec06048c1` tetap utuh. | **PASS** |
| **M5BA-02** | Audit baseline Git, dependency, konfigurasi Laravel 13, MariaDB, migrasi, PHPWord, Dompdf, private storage, dan regression tests | `php artisan p4i:staging-preflight` (`OK`), `php artisan migrate:status` (8 `Ran`), `php artisan test` (**140 passed, 722 assertions**), `pint --test` (`passed`), `npm run build` (`built in 1.42s`). | **PASS** |
| **M5BA-03** | Inspeksi & render visual 4 berkas PDF/DOCX sintetis (isi per halaman, tabel Halaman 2, jumlah halaman, watermark berulang) | Dekode stream FlateDecode per objek halaman PDF, inspeksi DOM/PhpWord per halaman logis DOCX, laporan `document_verification_report.json`, 4 citra visual `render_docx_*.png`, dan `MultiPageDocumentValidationTest::test_m5b_a_*`. | **PASS** |
| **M5BA-04** | Audit crash recovery (`ReconcileExportsCommand`, jadwal `schedule:list`, transaksi `lockForUpdate`, pre-commit metadata, cleanup, dan subproses mati mendadak) | Perbaikan `routes/console.php` (`schedule:list` aktif `*/15 * * * *`), transaksi atomik `lockForUpdate()` di `DocumentExportService`, serta pengujian subproses `exit(137)` pada `ArchiveTransactionConcurrencyTest::test_m5b_a_*`. | **PASS** |
| **M5BA-05** | Kesiapan instruksi deployment staging Hostinger (subdomain terpisah, DB MariaDB baru, SSL, private storage, scheduler, migrasi, backup & rollback) secara lokal | Seluruh instruksi, konfigurasi `.env.staging`, dan prosedur rollback lengkap; tidak ada akses/perubahan ke Hostinger sebelum izin pemilik. | **PASS** *(Lokal)* |
| **M5BA-06** | Pemeliharaan 15 skenario UAT dengan pembedaan tegas antara tes otomatis dan UAT manusia | 15/15 skenario lulus tes otomatis (`PASS` di `StagingUatPreparationTest`) dan dicatat jujur sebagai `NOT RUN (BLOCKED)` untuk UAT manusia oleh staf/pengurus P4I. | **PASS** *(Lokal)* / **NOT RUN** *(UAT Manusia)* |
| **M5BA-07** | Deployment aktual & eksekusi pengujian HTTP/DB/Cron langsung pada server Hostinger Staging | Menunggu izin tertulis eksplisit dari pemilik proyek (Ketua/Pengurus Yayasan P4I). | **BLOCKED** |
| **M5BA-08** | Pengamanan mutlak: `official_numbering_active = false`, `letters.official_number = NULL`, tanpa penerbitan surat resmi, tanpa kredensial di Git/laporan | Terverifikasi melalui `SafetyGuardTest`, `StagingPreflightCommand`, dan audit isi repositori. | **PASS** |

### 7.2 Daftar Blocker Aktual (Blocker List)

1. **`BLK-STG-01` (Status: `BLOCKED` — Otoritas Pemilik Proyek / Pengurus Yayasan P4I):**  
   Izin tertulis eksplisit dari pemilik akun Hostinger Business Web Hosting (Ketua/Pengurus Yayasan P4I) untuk membuat subdomain staging terpisah dan database MariaDB staging baru belum diberikan. Sesuai pengamanan mutlak, tim engineer berhenti setelah persiapan lokal dan tidak menyentuh server Hostinger.
2. **`BLK-STG-02` (Status: `NOT RUN / BLOCKED` — Pelaksanaan UAT Manusia oleh Staf & Pengurus P4I):**  
   Pelaksanaan ke-15 skenario UAT secara langsung oleh staf konseptor, pejabat pemeriksa, dan pengurus Yayasan P4I melalui browser pada lingkungan staging belum dapat dijalankan (`NOT RUN`) karena bergantung pada pembukaan `BLK-STG-01`.
3. **`BLK-STG-03` (Status: `BLOCKED` — Verifikasi Hak `CREATE TRIGGER` pada Shared Database Hostinger):**  
   Verifikasi apakah user database MariaDB pada paket Hostinger Business mengizinkan pembuatan 4 trigger imutabilitas secara langsung baru dapat dipastikan saat `php artisan migrate --force` dijalankan di staging. Apabila dibatasi oleh server shared hosting, tersedia 3 opsi kontingensi pada `docs/staging-compatibility-matrix.md` untuk diputuskan oleh **GPT-6**.
4. **`BLK-PROD-01` (Status: `BLOCKED` — Otoritas Administratif Yayasan P4I untuk Produksi):**  
   Pengaktifan penomoran surat resmi (`official_numbering_active`) dan penerbitan surat keluar resmi tetap terkunci mati (`false` & `NULL`) hingga SOP Penomoran Resmi, Berita Acara Rekonsiliasi Register Lama, dan SK Mandat Pejabat Definitif disahkan secara tertulis oleh Ketua dan Dewan Pengurus Yayasan P4I.
