# BUKTI IMPLEMENTASI TEKNIS — SPRINT M4A
## DOCUMENT EXPORT, PRIVATE DIGITAL ARCHIVE & INTEGRITY VERIFICATION

**Sistem:** P4I Office v1.0  
**Sprint:** M4A (Document Export, Private Digital Archive & Integrity Verification)  
**Branch:** `feat/sprint-m4a-document-archive`  
**Implementer & Technical Test Engineer:** Gemini Flash 3.8 — Reasoning High  
**Software Architect, QA Auditor & Penyusun Laporan Resmi:** GPT-6  
**Otoritas Administratif:** Ketua dan Dewan Pengurus Yayasan P4I  
**Tanggal Pengujian:** 01 November 2026  
**Status Kesiapan Operasional:** **`BLOCKED`** (Pilar 1, 2, 3, 4, 7, 8 Memerlukan Penetapan SK Definitif Pengurus)

---

## 1. RINGKASAN EKSEKUTIF IMPLEMENTASI

Pada Sprint M4A, sistem P4I Office telah melengkapi fondasi kearsipan naskah dinas dengan modul ekspor dokumen ganda (**Draf PDF ber-watermark** dan **DOCX OpenXML terstruktur**) yang disimpan secara terisolasi pada direktori penyimpanan privat di luar *public web root*. 

Seluruh naskah dinas ekspor diikat secara kriptografis menggunakan *hash checksum* **SHA-256**, dilindungi dari *path traversal*, menerapkan pembatasan *multi-tenant* dan klasifikasi **RAHASIA/CONFIDENTIAL**, serta diaudit setiap kali diunduh atau diekspor.

### Invarian Keamanan Keras (Hard Safety Invariants):
1. **Nomor Surat Resmi Tetap Nonaktif:** `config('features.official_numbering') = false` dan kolom basis data `letters.official_number = NULL`.
2. **Label Simulasi Draf Mutlak:** Seluruh nomor yang tercetak pada dokumen berlabel tegas `[SIMULASI] DRAF/P4I/{TAHUN}/REV-{VERSI}`.
3. **Tanpa Tanda Tangan & Stempel Fiktif:** Blok tanda tangan dan stempel resmi dinonaktifkan dengan penanda `[TANDA TANGAN BELUM DITERBITKAN]`.
4. **Watermark Draf Permanen:** Dokumen PDF memuat watermark diagonal transparan `DRAF — BELUM RESMI — TIDAK BERLAKU SEBAGAI SURAT KELUAR` pada setiap halaman.

---

## 2. ARSITEKTUR KOMPONEN YANG DIIMPLEMENTASIKAN

### 2.1. Konfigurasi Penyimpanan Privat (`config/filesystems.php`)
```php
'private_archives' => [
    'driver' => 'local',
    'root' => storage_path('app/private_archives'),
    'visibility' => 'private',
    'throw' => true,
],
```
- Berkas fisik diletakkan pada `storage/app/private_archives/{organization_id}/{year}/{format}/{uuid}.{ext}`.
- Direktori ini berada di luar jangkauan HTTP web server secara langsung, mencegah *unauthorized direct downloading* atau kebocoran URL.

### 2.2. Skema Basis Data (`database/migrations/2026_11_01_000005_create_document_exports_table.php`)
Tabel `document_exports` menyimpan metadata setiap proses ekspor dokumen:
- `id`: UUID (Primary Key).
- `organization_id`: UUID Foreign Key (Tenancy Scoping).
- `letter_id`: UUID Foreign Key ke `letters`.
- `revision_id`: UUID Foreign Key ke `letter_revisions`.
- `revision_version`: Versi revisi naskah saat diekspor.
- `content_hash`: SHA-256 dari konten JSON revisi naskah.
- `format`: `PDF` atau `DOCX`.
- `file_path`: Lokasi relatif pada disk `private_archives`.
- `file_hash`: SHA-256 dari berkas fisik biner.
- `file_size`: Ukuran fisik berkas dalam byte.
- `mime_type`: MIME type berkas (`application/pdf` atau `application/vnd.openxmlformats-officedocument.wordprocessingml.document`).
- `status`: `PENDING`, `READY`, atau `ERROR`.
- `idempotency_key`: Kunci idempotensi unik untuk mencegah duplikasi ekspor berulang.
- `exported_by`: UUID Foreign Key ke `users`.
- `download_count`: Frekuensi unduhan berkas.
- `last_downloaded_at`: Cap waktu unduhan terakhir.

### 2.3. Domain Service (`app/Services/DocumentExportService.php`)
1. **`exportLetter(User $user, Letter $letter, string $format, ?string $idempotencyKey)`**:
   - Memvalidasi *multi-tenant* dan hak akses klasifikasi **RAHASIA**.
   - Memeriksa idempotensi untuk mencegah duplikasi ekspor.
   - Membuat rekaman berstatus `PENDING` di basis data.
   - Merender naskah ke berkas sementara (*temporary file*) menggunakan *unified source context*.
   - Menghitung checksum SHA-256 dan ukuran biner.
   - Memindahkan berkas ke direktori penyimpanan privat secara atomik.
   - Memperbarui status ke `READY` dan mencatat audit event `DOCUMENT_EXPORTED`.
   - Menghapus berkas sementara dan mencatat status `ERROR` jika terjadi kegagalan (*orphan prevention*).
2. **`renderPdf(array $ctx, string $outputPath)`**:
   - Menggunakan pustaka resmi `dompdf/dompdf` (^3.1.6).
   - Pengerasan keamanan: `isRemoteEnabled = false` (anti-SSRF), `isPhpEnabled = false` (anti-RCE), `chroot = base_path()`.
   - Menghasilkan kop resmi P4I, watermark draf permanen pada setiap halaman, penafian hukum (*legal disclaimer*), dan blok tanda tangan belum terbit.
3. **`renderDocx(array $ctx, string $outputPath)`**:
   - Menggunakan pustaka resmi `phpoffice/phpword` (^1.4.0).
   - Menghasilkan arsip OpenXML murni tanpa makro (`vbaProject.bin` tidak ada).
   - Header memuat teks watermark merah `DRAF — BELUM RESMI — TIDAK BERLAKU SEBAGAI SURAT KELUAR`.
   - Sanitasi teks terhadap tag injeksi `<script>` dan HTML.
4. **`downloadExport(User $user, DocumentExport $export)`**:
   - Memeriksa status berkas (`READY`), isolasi *tenant*, dan izin kerahasiaan (*confidentiality*).
   - Memverifikasi keberadaan fisik berkas pada disk privat.
   - **Verifikasi Integritas SHA-256 Bit-per-Bit:** Menghitung ulang hash berkas fisik dan membandingkannya dengan `file_hash` di database. Jika terjadi korupsi data atau manipulasi berkas di disk, unduhan langsung ditolak (HTTP 422).
   - Mencatat audit event `DOCUMENT_DOWNLOADED` dan meningkatkan `download_count`.
   - Mengalirkan (*stream download*) berkas melalui respon terotentikasi.
5. **`verifyArchiveIntegrity(User $user)`**:
   - Menjalankan audit menyeluruh terhadap seluruh berkas arsip fisik organisasi terhadap catatan hash basis data.

---

## 3. HASIL PENGUJIAN OTOMATIS (TEST SUITES EVIDENCE)

Pengujian dilakukan menggunakan lingkungan uji MariaDB InnoDB aktual pada database `p4i_office_test` melalui PHP 8.3.36 CLI.

### 3.1. Hasil Ringkasan Pengujian Kumulatif
```
Tests:    110 passed (461 assertions)
Duration: 82.82s
```
- **Total Test Suites:** 15 file test feature & unit.
- **Sprint M4A New Tests:** 18 tests, 75 assertions.
- **Regresi Sprint M0 s/d M3C:** 92 tests, 386 assertions (Semua PASS 100%).

### 3.2. Rincian Pengujian Sprint M4A
1. **`Tests\Feature\DocumentExportWorkflowTest` (8 tests, 39 assertions):**
   - `test_m4a_01_authorized_user_and_valid_revision`: PASS
   - `test_m4a_02_docx_generation_valid_and_clean`: PASS
   - `test_m4a_03_pdf_generation_with_permanent_watermark`: PASS
   - `test_m4a_04_consistency_between_pdf_and_docx`: PASS
   - `test_m4a_05_official_number_strictly_null_and_unsigned`: PASS
   - `test_m4a_06_document_rendering_security_sanitization`: PASS
   - `test_m4a_12_export_idempotency_and_retry_handling`: PASS
   - `test_m4a_13_failure_staging_and_orphan_prevention`: PASS

2. **`Tests\Feature\PrivateArchiveSecurityTest` (10 tests, 36 assertions):**
   - `test_m4a_07_private_storage_disk_isolation`: PASS
   - `test_m4a_08_download_authorization_and_cross_tenant_denied`: PASS
   - `test_m4a_09_confidential_document_isolation`: PASS
   - `test_m4a_10_anti_path_traversal_on_download`: PASS
   - `test_m4a_11_sha256_checksum_verification_and_corruption_detection`: PASS
   - `test_m4a_14_search_filtering_and_tenant_scoping`: PASS
   - `test_m4a_15_audit_trail_logging`: PASS
   - `test_m4a_16_archive_backup_and_restore_simulation`: PASS
   - `test_m4a_17_hostinger_pure_php_compatibility`: PASS
   - `test_m4a_18_readiness_blocked_and_safety_invariants`: PASS

---

## 4. STANDAR KODE & FRONT-END ASSETS

### 4.1. Laravel Pint (PSR-12 Standard)
```bash
vendor\bin\pint --test
```
**Hasil:** `{"tool":"pint","result":"passed"}` — Seluruh kode PHP memenuhi standar konvensi Laravel dan PSR-12 secara sempurna tanpa pelanggaran gaya kode.

### 4.2. Vite Asset Production Build
```bash
npm run build
```
**Hasil:**
- `manifest.json`: 0.25 kB
- `app-74e2b608.js`: 58.97 kB
- Status: Build bersih dalam 537 ms.

---

## 5. BUKTI TANGKAPAN LAYAR BROWSER (HEADLESS REAL PIXELS)

Seluruh tangkapan layar dihasilkan dari browser Chrome Headless nyata (`C:\Program Files\Google\Chrome\Application\chrome.exe`) pada resolusi 1280x1050:

| No | Berkas Tangkapan Layar | Deskripsi Tampilan | Ukuran Berkas |
|---|---|---|---|
| 16 | `docs/screenshots/16_letter_detail_export_actions.png` | Detail surat dinas menampilkan tombol aksi ekspor draf (PDF/DOCX) dan tabel riwayat berkas arsip terverifikasi SHA-256. | 141 KB |
| 17 | `docs/screenshots/17_digital_archive_index.png` | Halaman katalog arsip digital privat dengan filter pencarian perihal/hash, statistik kapasitas penyimpanan, dan tombol unduh aman. | 166 KB |
| 18 | `docs/screenshots/18_archive_admin_integrity_dashboard.png` | Panel administrasi pengawasan integritas arsip fisik (Disk vs Database SHA-256) dan log audit aktivitas ekspor/unduhan terbaru. | 154 KB |

---

## 6. KESIMPULAN & TAHAP BERIKUTNYA

Sprint M4A telah berhasil diimplementasikan secara komprehensif dan tervalidasi dengan 110 pengujian otomatis. Modul ekspor naskah dinas dan arsip digital privat telah siap untuk diaudit oleh GPT-6 dan Pengurus Yayasan P4I.

Sesuai aturan tata kelola:
- **Pengembangan lokal Sprint M4A selesai secara bersih (Clean Stop).**
- **Tidak ada deploy langsung ke server hosting produksi Hostinger.**
- **Nomor surat resmi tetap dinonaktifkan (`NULL`).**
- **Status kesiapan operasional tetap `BLOCKED`.**
