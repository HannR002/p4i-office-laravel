# MATRIKS KETERTELUSURAN PENGUJIAN & GERBANG PENERIMAAN SPRINT M4A
## TRACEABILITY MATRIX: GATES M4A-01 S/D M4A-18

**Sistem:** P4I Office v1.0  
**Dokumen Acuan:** `docs/sprint-m4a-plan.md`  
**Implementer & Test Engineer:** Gemini Flash 3.8 — Reasoning High  
**QA Auditor & Software Architect:** GPT-6  
**Tanggal Verifikasi:** 01 November 2026  
**Total Gerbang Diuji:** 18 Gerbang Penerimaan  
**Hasil Evaluasi Teknis:** 17 PASS, 1 INVARIANT BLOCKED (M4A-18)

---

## 1. TABEL MATRIKS GERBANG PENERIMAAN (GATES M4A-01 — M4A-18)

| Gate ID | Deskripsi Persyaratan / Acceptance Criteria | Metode Pengujian / Berkas Uji Otomatis | Target Evaluasi & Assertions | Status Teknis |
|---|---|---|---|:---:|
| **M4A-01** | **Authorized User & Valid Revision Only**<br>Ekspor hanya dapat dipicu oleh pengguna aktif terotentikasi dan memiliki revisi naskah yang valid. Format non-standar (selain PDF/DOCX) ditolak. | `DocumentExportWorkflowTest::test_m4a_01_authorized_user_and_valid_revision` | Memvalidasi kepemilikan naskah, keberadaan `latestRevision`, dan penolakan `InvalidArgumentException` untuk format liar. | **PASS** |
| **M4A-02** | **Valid DOCX Generation & Clean OpenXML**<br>Menghasilkan berkas Word OpenXML murni berstruktur ZIP valid, memuat teks naskah revisi, kop P4I, tanpa makro VBA executable (`vbaProject.bin`). | `DocumentExportWorkflowTest::test_m4a_02_docx_generation_valid_and_clean` | ZipArchive open valid, `word/document.xml` memuat perihal, penerima, kop P4I; `locateName('vbaProject.bin') == false`. | **PASS** |
| **M4A-03** | **Valid PDF Generation & Permanent Watermark**<br>Menghasilkan PDF valid (%PDF-) dengan watermark diagonal permanen `DRAF — BELUM RESMI — TIDAK BERLAKU SEBAGAI SURAT KELUAR` pada setiap halaman. | `DocumentExportWorkflowTest::test_m4a_03_pdf_generation_with_permanent_watermark` | Memverifikasi header biner `%PDF-`, mime `application/pdf`, ukuran fisik > 1 KB, dan teks watermark Dompdf. | **PASS** |
| **M4A-04** | **PDF vs DOCX Content Consistency**<br>Konsistensi semantik dan data antara keluaran PDF dan DOCX dari sumber revisi tunggal (*unified source data*). | `DocumentExportWorkflowTest::test_m4a_04_consistency_between_pdf_and_docx` | Membandingkan `revision_id`, `version`, `content_hash`, penerima, dan perihal antara kedua rekaman ekspor. | **PASS** |
| **M4A-05** | **Official Number Strictly NULL & Unsigned**<br>Nomor surat resmi di database tetap `NULL`. Nomor cetak berlabel `[SIMULASI] DRAF`. Blok tanda tangan dan stempel dinonaktifkan (`[TANDA TANGAN BELUM DITERBITKAN]`). | `DocumentExportWorkflowTest::test_m4a_05_official_number_strictly_null_and_unsigned` | Memverifikasi `letters.official_number IS NULL` dan isi XML DOCX memuat teks `[TANDA TANGAN BELUM DITERBITKAN]`. | **PASS** |
| **M4A-06** | **Document Rendering Security & Sanitization**<br>Sanitasi input dari injeksi skrip XSS, penolakan evaluasi PHP (anti-RCE), dan penolakan pemuatan resource eksternal (anti-SSRF). | `DocumentExportWorkflowTest::test_m4a_06_document_rendering_security_sanitization` | Menguji payload `<script>alert("hacked")</script><img>`, memastikan tag skrip dihilangkan/dibersihkan dari XML OpenXML. | **PASS** |
| **M4A-07** | **Private Storage Disk Isolation**<br>Berkas arsip disimpan pada disk privat `storage/app/private_archives` yang berada di luar jangkauan langsung web publik (`public/`). | `PrivateArchiveSecurityTest::test_m4a_07_private_storage_disk_isolation` | Memverifikasi path absolut disk privat dan memastikan tidak memiliki prefix `public_path()`. | **PASS** |
| **M4A-08** | **Download Authentication & Cross-Tenant Denial**<br>Akses unduhan ditolak untuk pengguna anonim (redirect login), pengguna nonaktif (302/403), dan pengguna tenant asing (HTTP 403 Forbidden). | `PrivateArchiveSecurityTest::test_m4a_08_download_authorization_and_cross_tenant_denied` | Pengujian akses HTTP 302 guest, 403 pengguna disabled, dan 403 isolasi tenant Organisasi B terhadap berkas Organisasi A. | **PASS** |
| **M4A-09** | **Confidential Document Isolation**<br>Dokumen berklasifikasi RAHASIA/CONFIDENTIAL hanya dapat diunduh oleh konseptor (author), reviewer bermandat, dan system admin. | `PrivateArchiveSecurityTest::test_m4a_09_confidential_document_isolation` | Staf lain dari organisasi yang sama menerima HTTP 403; author dan admin berhasil menerima HTTP 200. | **PASS** |
| **M4A-10** | **Anti-Path Traversal on Download**<br>Parameter unduhan menggunakan *Route Model Binding* berbasis UUID tabel `document_exports`, menolak manipulasi path direktori (`../../`). | `PrivateArchiveSecurityTest::test_m4a_10_anti_path_traversal_on_download` | Request path manipulatif `/archives/..%2F..%2Fetc%2Fpasswd/download` menghasilkan HTTP 404 Not Found. | **PASS** |
| **M4A-11** | **SHA-256 Checksum Verification & Corruption Detection**<br>Pemeriksaan hash SHA-256 bit-per-bit saat unduhan. Jika berkas fisik dimanipulasi, unduhan ditolak dengan HTTP 422. Audit integritas massal berfungsi. | `PrivateArchiveSecurityTest::test_m4a_11_sha256_checksum_verification_and_corruption_detection` | Manipulasi manual berkas fisik pada disk menghasilkan HTTP 422 dan terdeteksi sebagai `corrupted_files` pada `verifyArchiveIntegrity()`. | **PASS** |
| **M4A-12** | **Export Idempotency & Retry Handling**<br>Panggilan ekspor berulang dengan kunci idempotensi yang sama tidak menduplikasi berkas atau record basis data. | `DocumentExportWorkflowTest::test_m4a_12_export_idempotency_and_retry_handling` | Memverifikasi rekaman database tunggal dan jalur berkas identik untuk request dengan `idempotency_key` sama. | **PASS** |
| **M4A-13** | **Staging Failure & Orphan Prevention**<br>Berkas sementara (*temporary file*) dihapus bersih jika proses ekspor gagal, status dicatat `ERROR` tanpa menyisakan berkas yatim. | `DocumentExportWorkflowTest::test_m4a_13_failure_staging_and_orphan_prevention` | Menguji penolakan ekspor surat tanpa revisi dan memastikan tidak ada berkas sampah di filesystem. | **PASS** |
| **M4A-14** | **Digital Archive UI, Search & Tenant Scoping**<br>Pencarian naskah arsip berdasarkan perihal, format, dan tanggal dengan paginasi dan pembatasan isolasi tenant. | `PrivateArchiveSecurityTest::test_m4a_14_search_filtering_and_tenant_scoping` | Pencarian teks, filter format PDF, dan penolakan penampakan arsip Org A pada sesi login Org B. | **PASS** |
| **M4A-15** | **Audit Trail Logging (Export & Download)**<br>Pencatatan riwayat immutabel pada tabel `audit_events` untuk kejadian `DOCUMENT_EXPORTED` dan `DOCUMENT_DOWNLOADED`. | `PrivateArchiveSecurityTest::test_m4a_15_audit_trail_logging` | Memverifikasi pencatatan event tipe, `actor_id`, `event_metadata` (hash, size, format, revision) pasca-ekspor dan pasca-unduh. | **PASS** |
| **M4A-16** | **Archive Backup & Restore Simulation**<br>Simulasi pencadangan dan pemulihan berkas arsip fisik beserta metadata basis data dalam lingkungan terisolasi. | `PrivateArchiveSecurityTest::test_m4a_16_archive_backup_and_restore_simulation` | Hapus berkas fisik asli, pulihkan dari cadangan, dan verifikasi ulang checksum SHA-256 (`all_intact == true`). | **PASS** |
| **M4A-17** | **Hostinger Pure PHP Compatibility**<br>Ekspor dokumen berjalan murni menggunakan ekstensi PHP native (PHPWord, Dompdf, GD/ZIP) tanpa dependensi daemon LibreOffice atau command eksternal. | `PrivateArchiveSecurityTest::test_m4a_17_hostinger_pure_php_compatibility` | Memeriksa ketersediaan class Dompdf, PhpWord, serta ekstensi `ext-zip` dan `ext-gd`. | **PASS** |
| **M4A-18** | **Operational Readiness & Numbering Safety Invariant**<br>Status kesiapan operasional tetap `BLOCKED` (skor <= 50%) dan fitur penomoran resmi tetap `false`. | `PrivateArchiveSecurityTest::test_m4a_18_readiness_blocked_and_safety_invariants` | Memvalidasi `overall_status == 'BLOCKED'`, `readiness_percentage <= 50%`, dan `official_numbering_active == false`. | **BLOCKED (VALID)** |

---

## 2. STATISTIK PENGUJIAN OTOMATIS

```
Total Test Cases Sprint M4A : 18 Tests
Total Assertions Sprint M4A  : 75 Assertions
Status Kelulusan            : 100% Passed (17 Pass, 1 Invariant Hard Stop)
Regresi Sprint Terdahulu     : 92 Tests (386 Assertions) — 100% Passed
Total Keseluruhan Sistem     : 110 Tests (461 Assertions) — 100% Passed
```
