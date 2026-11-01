# BERITA ACARA PENUTUPAN & AUDIT TEKNIS SPRINT M4B (M4B ACCEPTANCE CLOSURE)

**Sistem:** P4I Office v1.0  
**Sprint:** M5A — Tahap 1, 2, dan 3 (Audit & Penutupan Temuan Sprint M4B)  
**Branch:** `feat/sprint-m5a-staging-readiness`  
**Baseline Commit M4B:** `a53ee3dc51ba1f59d40d2a5b82be045f2c5255d2`  
**Implementer & Technical Test Engineer:** Argon — Reasoning Medium (Lead Software Engineer)  
**Software Architect, QA Auditor & Penyusun Laporan Resmi:** GPT-6  
**Otoritas Administratif:** Ketua dan Dewan Pengurus Yayasan P4I  
**Tanggal Verifikasi:** 01 November 2026  

---

## 1. AUDIT BASELINE REPOSITORI SPRINT M4B

Pemeriksaan langsung terhadap repositori aktual `D:\p4i-office-laravel` pada awal Sprint M5A membuktikan:
- **Branch Baseline:** `feat/sprint-m4b-secure-archive`
- **Commit SHA Baseline:** `a53ee3dc51ba1f59d40d2a5b82be045f2c5255d2`
- **Hasil Regresi Awal M4B:** 133 tests passed (583 assertions), exit code `0`.
- **Invarian Keselamatan:** `config('features.official_numbering_active') = false` dan seluruh baris `letters.official_number = NULL`.

---

## 2. KOREKSI PEMETAAN MATRIKS PENERIMAAN SPRINT M4B (GATE STG-02)

Pada dokumentasi matriks Sprint M4B sebelumnya (`docs/m4b-test-matrix.md`), terdapat ketidaksesuaian nomor urut definisi pada **M4B-13**, **M4B-14**, dan **M4B-16** dibandingkan definisi asli pada rencana arsitektur (`docs/sprint-m4b-plan.md`).

Tanpa mengubah definisi ID pengujian asli agar terlihat lulus, berikut adalah **pemetaan penerimaan Sprint M4B yang telah dikoreksi secara jujur dan akurat**:

| Gate ID | Definisi Asli SRS Sprint M4B | Bukti Aktual & Referensi Kode/Dokumen | Status Aktual |
|---|---|---|---|
| **M4B-01** | Audit Baseline & Integritas Repositori Bersih | Commit `a53ee3dc51ba1f59d40d2a5b82be045f2c5255d2`, 110->133 tests pass | **PASS** |
| **M4B-02** | Isolasi Dokumen RAHASIA: Reviewer Tanpa Mandat Ditolak | `ConfidentialArchiveAuthorizationTest` (`test_m4b_02_*`) | **PASS** |
| **M4B-03** | Penolakan Mandat Tidak Sah (Expired, Revoked, Beda Org/Tipe/Unit) | `ConfidentialArchiveAuthorizationTest` (`test_m4b_03_*`) | **PASS** |
| **M4B-04** | Konsistensi Otorisasi Naskah Rahasia di Seluruh Lapisan | `MandateValidationService::canAccessConfidential` & `applyConfidentialityScope` pada `LetterPolicy`, `DocumentExportService`, `DocumentArchiveController`, `LetterController`, dan `ApprovalController` (`test_stg_04_*`) | **PASS** |
| **M4B-05** | PDF Multi-Halaman (>= 3 Hal) dengan Watermark di Setiap Halaman | `MultiPageDocumentValidationTest` & `sample_3page_draft.pdf` | **PASS** |
| **M4B-06** | DOCX Multi-Halaman Valid, OpenXML Clean, dan Macro-Free | `MultiPageDocumentValidationTest` & `sample_3page_draft.docx` | **PASS** |
| **M4B-07** | Kesetaraan Isi & Metadata antara PDF dan DOCX | `MultiPageDocumentValidationTest::test_m4b_07_*` | **PASS** |
| **M4B-08** | Sanitasi XSS, Template Injection, PHP Eval, dan Remote SSRF | `MultiPageDocumentValidationTest::test_m4b_08_*` (`isRemoteEnabled=false`, `isPhpEnabled=false`) | **PASS** |
| **M4B-09** | Ekspor Simultan (Concurrency) & Kunci Idempotensi Aman | `ArchiveTransactionConcurrencyTest::test_m4b_09_*` | **PASS** |
| **M4B-10** | Crash Recovery & Pencegahan Berkas Yatim (Termasuk Proses Berhenti Mendadak) | `DocumentExportService::reconcileInterruptedExports` & `ArchiveTransactionConcurrencyTest::test_stg_05_*` | **PASS** |
| **M4B-11** | Pemulihan Multi-Berkas MariaDB & Arsip Fisik Terintegrasi | `IntegratedArchiveRestoreTest::test_m4b_11_*` | **PASS** |
| **M4B-12** | Verifikasi Checksum SHA-256 Backup & Metrik Pemulihan | `IntegratedArchiveRestoreTest::test_m4b_12_*` | **PASS** |
| **M4B-13** *(Asli)* | **Rekonsiliasi Acceptance Gates Sprint M4A** | Dokumen `docs/m4a-acceptance-reconciliation.md` memetakan dan menutup seluruh 6 temuan penerimaan bersyarat Sprint M4A | **PASS** |
| **M4B-14** *(Asli)* | **Pengujian Hostinger Staging dengan Izin, atau Status BLOCKED** | Izin akses ke server Hostinger belum diberikan oleh pemilik proyek; pengujian langsung di server Hostinger berstatus **BLOCKED**, sedangkan evaluasi kesiapan lokal terverifikasi mengembalikan status `BLOCKED` | **BLOCKED (By Governance Rule)** |
| **M4B-15** | Penomoran Resmi Tetap OFF (`false`) dan `official_number = NULL` | `IntegratedArchiveRestoreTest::test_m4b_15_*` & `SafetyGuardTest` | **PASS** |
| **M4B-16** *(Asli)* | **Seluruh Pengujian, Bukti Fisik, dan Blocker Terdokumentasi Lengkap** | 4 berkas biner pada `docs/evidence/sprint-m4b/`, 8 dokumen teknis, serta daftar blocker organisasi terdokumentasi utuh | **PASS** |

---

## 3. TEMUAN AUDIT TAHAP 2 (ARTEFAK BINER) & PERBAIKAN AKTUAL

Selama audit mendalam Tahap 2 terhadap kode `DocumentExportService` dan keempat berkas di `docs/evidence/sprint-m4b/`, ditemukan dan diperbaiki 3 celah kualitas:

1. **Interpolasi Tabel pada PDF (`buildPdfHtml`):**
   - *Temuan:* Variabel `$tablesHtml` dibangun di dalam `buildPdfHtml()`, namun belum disisipkan ke dalam `<div class="content-box">` pada heredoc HTML PDF sehingga tabel matriks IKU hanya muncul pada berkas DOCX dan belum muncul pada aliran halaman PDF.
   - *Perbaikan:* `buildPdfHtml()` dan `renderDocx()` kini mendukung penanda posisi tabel inline `[TABLE:0]` maupun penyisipan otomatis di akhir paragraf, sehingga tabel matriks IKU ter-render secara identik pada Halaman 2 baik di `sample_3page_draft.pdf` maupun `sample_3page_draft.docx`.
2. **Konsistensi Metadata Revisi & Penafian pada Body DOCX:**
   - *Temuan:* Pada PDF terdapat kotak penafian body yang memuat `Diekspor oleh: ... | Waktu: ... | Revisi Ke-1`, sedangkan pada DOCX informasi tersebut hanya sebagian tampil di footer.
   - *Perbaikan:* `renderDocx()` kini menyertakan blok `PERHATIAN KEARSIPAN` beserta `Diekspor oleh`, `Waktu`, dan `Revisi Ke-` secara eksplisit di bagian bawah naskah selain header watermark dan footer berulang.
3. **Validasi Pembaca Dokumen OpenXML (`PhpWord\IOFactory::load`) & XML Escaping:**
   - *Perbaikan:* Mengaktifkan `\PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true)` dengan teks bersih dan memvalidasi bahwa berkas `.docx` dapat dibuka serta di-parse kembali oleh pembaca dokumen `IOFactory::load($path, 'Word2007')`.

### Tabel Verifikasi Biner Aktual Pasca-Perbaikan (`docs/evidence/sprint-m4b/`)

| Nama Berkas | Ukuran (Bytes) | Checksum SHA-256 Aktual | Jumlah Halaman | Watermark Tiap Halaman | Tabel Ter-render | Status OpenXML / Reader |
|---|---|---|---|---|---|---|
| `sample_1page_draft.pdf` | `3.628` | `f905a022dd9de52a257dd60200aa9a4e13a979e1fda0f14f85e15aa3beb1e8c2` | **1 Halaman** (`/Type /Page` = 1) | **Ya** (Stream #0) | Tidak (Surat 1 Hal) | Valid PDF 1.7 |
| `sample_1page_draft.docx` | `9.592` | `5e6e25f762d60076c243be3ac3cffcf62b544a9559b0c7606cddf0bae893a2e8` | **1 Halaman** (0 page break) | **Ya** (`header1.xml` + `footer1.xml`) | Tidak (Surat 1 Hal) | Valid OpenXML (16 entries, `IOFactory::load` OK) |
| `sample_3page_draft.pdf` | `7.822` | `fc6ea2b3312c80b9d7d3e8711eeaad37da4842eef5e1fd610bd7ba5a39eb5268` | **3 Halaman** (`/Type /Page` = 3) | **Ya** (Stream #0, #1, #2) | **Ya** (Halaman 2 / Stream #1) | Valid PDF 1.7 |
| `sample_3page_draft.docx` | `11.225` | `36c74ec71ed6c5e787e882224c29c087d1ba205484b040bd998afe02fed7cb58` | **3 Halaman** (2 page breaks) | **Ya** (`header1.xml` + `footer1.xml`) | **Ya** (1 `<w:tbl>` di Hal 2) | Valid OpenXML (16 entries, `IOFactory::load` OK) |

---

## 4. TEMUAN AUDIT TAHAP 3 (CRASH RECOVERY MENDADAK) & PERBAIKAN AKTUAL

1. **Evaluasi Klaim `try...catch`:**
   Blok `try...catch (\Throwable $e)` pada `DocumentExportService::exportLetter()` hanya menangkap kegagalan yang terjadi di dalam eksekusi normal PHP. Apabila proses worker PHP mati mendadak akibat `SIGKILL`, *out-of-memory* (OOM), *timeout* pada shared hosting, atau pemadaman server, blok `catch` tidak akan pernah dieksekusi. Akibatnya, baris database dapat tertinggal dalam status `PENDING` selamanya, berkas sementara `p4i_export_*` tertinggal di direktori temp, atau berkas parsial tertinggal di `private_archives`.
2. **Implementasi Mekanisme Rekonsiliasi (`DocumentExportService::reconcileInterruptedExports` & `php artisan p4i:reconcile-exports`):**
   - Mendeteksi baris `document_exports` berstatus `PENDING` yang melampaui ambang waktu (*stale threshold*, default 15 menit).
   - Memeriksa berkas sementara `p4i_export_*` di `sys_get_temp_dir()` dan menghapus yang telah melewati ambang waktu.
   - Memeriksa berkas fisik di `private_archives` menggunakan `isValidExportBinary()` (memastikan header `%PDF-` dan trailer `%%EOF` untuk PDF, atau struktur ZIP OpenXML `word/document.xml` untuk DOCX) serta kecocokan `file_hash` SHA-256.
   - Apabila berkas fisik telah selesai ditulis secara utuh dan hash SHA-256 cocok, status dipulihkan secara aman menjadi `READY` (`DOCUMENT_EXPORT_RECOVERED`). Apabila berkas parsial/rusak atau belum selesai, berkas parsial dihapus dan status ditandai `ERROR` (`DOCUMENT_EXPORT_RECONCILED_ERROR`).
   - Melindungi seluruh berkas milik record `READY` yang sah maupun proses `PENDING` aktif yang baru berjalan (di bawah ambang batas waktu).
   - Memeriksa seluruh record `READY` dan memastikan **tidak ada metadata `READY` yang menunjuk ke berkas hilang atau korup** (diubah ke `ERROR` dengan audit `DOCUMENT_EXPORT_BROKEN_READY`).

---

## 5. TEMUAN TAMBAHAN OTORISASI RUTE & KUERI (GATE STG-04)

Selama pemeriksaan menyeluruh terhadap seluruh Controller dan Policy:
- Ditemukan bahwa subquery penyaringan dokumen rahasia pada `DocumentArchiveController@index` dan `LetterController@index` sebelumnya belum menyertakan filter `unit_id` pada mandat, dan `ApprovalController@index` masih menggunakan pemeriksaan peran lama.
- **Perbaikan:** Dibuat metode terpusat `MandateValidationService::applyConfidentialityScope($query, User $user)` yang memeriksa `status = ACTIVE`, `revoked_at IS NULL`, masa berlaku `valid_from/valid_to`, `letter_type_id`, serta `unit_id`, dan diterapkan secara seragam pada `DocumentArchiveController`, `LetterController`, dan `ApprovalController`.

Dengan seluruh perbaikan dan bukti otomatis di atas, temuan Sprint M4B dinyatakan **ditutup secara teknis (CLOSED)** dan siap dinilai oleh auditor GPT-6.

