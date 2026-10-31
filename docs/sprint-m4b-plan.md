# RENCANA KERJA IMPLEMENTASI & PENGUJIAN SPRINT M4B
## SECURE ARCHIVE, EXPORT VALIDATION & STAGING READINESS

**Sistem:** P4I Office v1.0  
**Sprint:** M4B (Secure Archive, Export Validation & Staging Readiness)  
**Branch:** `feat/sprint-m4b-secure-archive`  
**Implementer & Technical Test Engineer:** Gemini Flash 3.8 — Reasoning High  
**Software Architect, QA Auditor & Penyusun Laporan Resmi:** GPT-6  
**Otoritas Administratif:** Ketua dan Dewan Pengurus Yayasan P4I  
**Tanggal:** 01 November 2026  
**Status Dokumen:** **DRAFT DISETUJUI UNTUK EKSEKUSI TEKNIS**

---

## 1. LATAR BELAKANG & TUJUAN SPRINT M4B

Pada Sprint M4A, fondasi ekspor dokumen naskah dinas (PDF dan DOCX) serta penyimpanan privat dengan verifikasi checksum SHA-256 telah berhasil dibangun (110 tests passed, 461 assertions). Namun, hasil audit internal dan review arsitektur GPT-6 mengidentifikasi beberapa area kritis yang wajib diperbaiki dan divalidasi sebelum sistem dinyatakan siap untuk tahap *staging*:

1. **Pengetatan Otorisasi Dokumen RAHASIA (Confidentiality Refactoring):**
   Pada Sprint M4A, hak akses dokumen rahasia masih mengevaluasi role global (`REVIEWER`, `SYSTEM_ADMIN`, `PENGURUS_YAYASAN`). Peran teknis tidak otomatis memberikan kewenangan membaca atau mengunduh seluruh dokumen rahasia. Akses reviewer harus diikat secara ketat pada **Mandat Aktif Pejabat (`SignatoryMandate`)** yang sah:
   - Status mandat `ACTIVE` dan belum dicabut (`revoked_at IS NULL`).
   - Masih dalam masa berlaku (`valid_from <= today <= valid_to`).
   - Sesuai dengan organisasi (`organization_id`).
   - Sesuai dengan jenis surat (`letter_type_id IS NULL` atau cocok) dan unit kerja (`unit_id IS NULL` atau cocok).
   - Memiliki hak telaah (`can_review == true`).
   - Penolakan tegas untuk reviewer tanpa mandat, mandat kedaluwarsa, mandat dicabut, atau mandat organisasi lain.
2. **Validasi Fisik Berkas Biner (PDF & DOCX Multi-Halaman):**
   Tidak hanya menguji dokumen 1 halaman sintetis, tetapi memproduksi dan memvalidasi berkas fisik biner multi-halaman (minimal 3 halaman) dengan:
   - Watermark draf diagonal permanen pada seluruh halaman (halaman 1, 2, 3+).
   - Tata letak kop, margin, penomoran halaman, dan penafian hukum.
   - Ketahanan terhadap karakter khusus/Unicode dan teks panjang.
   - Kepastian ketiadaan makro VBA, tanda tangan, stempel resmi, dan nomor keluar.
   - Penyimpanan artefak uji asli pada `docs/evidence/sprint-m4b/`.
3. **Ketahanan Transaksi & Konkurensi Ekspor:**
   Memverifikasi proses ekspor simultan (*concurrent exports*), idempotensi, pemulihan dari kegagalan (*crash recovery*), dan pencegahan berkas yatim (*orphan files*).
4. **Cadangan & Pemulihan Terintegrasi Multi-Berkas:**
   Menguji pemulihan terintegrasi yang melibatkan basis data MariaDB beserta minimal 3 berkas arsip fisik berbeda pada lingkungan pengujian bersih.
5. **Audit Kesiapan Lingkungan Staging Hostinger:**
   Menyusun matriks evaluasi kelayakan hosting dan mempertahankan status **`BLOCKED`** hingga ada penetapan izin resmi dari Dewan Pengurus Yayasan P4I.

---

## 2. PEMBAGIAN PERAN & TATA KELOLA ORGANISASI

- **GPT-6:** Bertindak sebagai System Architect, QA Auditor independen, penilai kesesuaian SRS, dan penyusun laporan resmi perkembangan proyek kepada pengurus yayasan.
- **Gemini (Lead Software Engineer & Technical Test Engineer):** Bertanggung jawab atas penulisan kode, refactoring kebijakan otorisasi, migrasi, pengujian otomatis, dan penyediaan bukti teknis empiris yang dapat direproduksi (*reproducible evidence*).
- **Ketua & Dewan Pengurus Yayasan P4I:** Memiliki kewenangan administratif mutlak dalam penetapan SOP, penandatanganan SK Mandat, dan persetujuan pengaktifan penomoran surat keluar resmi.

---

## 3. TAHAPAN EKSEKUSI TEKNIS SPRINT M4B

```
┌────────────────────────────────────────────────────────────────────────┐
│ TAHAP 1: Baseline Audit & Verifikasi Lingkungan (110 Tests Pass)       │
└───────────────────────────────────┬────────────────────────────────────┘
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ TAHAP 2: Refactoring Otorisasi Dokumen RAHASIA (Mandate-Scoped)        │
│          - Sinkronisasi LetterPolicy, DocumentExportService, Controller│
│          - Penolakan Reviewer Tanpa Mandat / Mandat Basi / Beda Unit   │
└───────────────────────────────────┬────────────────────────────────────┘
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ TAHAP 3: Validasi Berkas Biner PDF & DOCX (Single & Multi-Halaman)     │
│          - Uji Dokumen >= 3 Halaman, Watermark di Setiap Halaman       │
│          - Simpan Berkas Artefak Asli di docs/evidence/sprint-m4b/     │
└───────────────────────────────────┬────────────────────────────────────┘
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ TAHAP 4: Transaksi Ekspor, Konkurensi & Pencegahan File Yatim          │
│          - Idempotency Lock, Crash Recovery, Atomicity                 │
└───────────────────────────────────┬────────────────────────────────────┘
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ TAHAP 5: Cadangan & Pemulihan Terintegrasi Multi-Berkas (Integrity)    │
│          - Validasi Dump DB + 3 Berkas Fisik Berbeda via SHA-256       │
└───────────────────────────────────┬────────────────────────────────────┘
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ TAHAP 6: Audit Kesiapan Staging Hostinger & Dokumentasi Lengkap        │
│          - Status Kesiapan Tetap BLOCKED, Clean Stop                   │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 4. MATRIKS GERBANG PENERIMAAN (ACCEPTANCE GATES M4B-01 S/D M4B-16)

| Gate ID | Target Persyaratan | Metode Verifikasi |
|---|---|---|
| **M4B-01** | Baseline, branch `feat/sprint-m4b-secure-archive`, commit, dan 110 regression tests terverifikasi. | Eksekusi `git status`, `git log`, dan `php artisan test`. |
| **M4B-02** | Role REVIEWER tanpa mandat sah dilarang mengakses dokumen rahasia (export, view, download). | Test Feature: Negative test reviewer tanpa record `signatory_mandates`. |
| **M4B-03** | Mandat expired, revoked, salah organisasi, atau salah jenis surat ditolak secara ketat. | Test Feature: Negative test berbagai anomali status mandat pejabat. |
| **M4B-04** | Otorisasi konsisten di seluruh lapisan (Policy, Export Service, Download Controller, Index). | Audit kode & unit test across all access points. |
| **M4B-05** | PDF minimal 3 halaman memuat watermark diagonal draf permanen pada setiap halaman. | Ekstraksi biner PDF & pemeriksaan visual artefak multi-halaman. |
| **M4B-06** | DOCX minimal 3 halaman valid, OpenXML clean, dan bebas makro VBA (`vbaProject.bin`). | Pembedahan arsip ZipArchive OpenXML & validasi struktur XML. |
| **M4B-07** | Kesetaraan isi naskah antara PDF dan DOCX dari sumber revisi yang sama terbukti empiris. | Perbandingan semantik teks, metadata, dan versi revisi pada artefak. |
| **M4B-08** | Template injection, tag `<script>`, evaluasi PHP, dan remote SSRF ditolak dan disanitasi. | Uji penetrasi input dengan skrip aktif dan URL remote SSRF. |
| **M4B-09** | Ekspor simultan (*concurrent exports*) dan kunci idempotensi aman dari duplikasi berkas. | Uji konkurensi database row locking & idempotency validation. |
| **M4B-10** | Pemulihan dari kegagalan ekspor (*crash recovery*) tidak meninggalkan berkas yatim. | Simulasi exception saat render dan verifikasi pembersihan filesystem. |
| **M4B-11** | Pemulihan multi-berkas MariaDB dan berkas fisik arsip sukses dengan integritas 100%. | Backup snapshot, pembersihan lingkungan uji, pemulihan, dan audit SHA-256. |
| **M4B-12** | Cadangan memiliki checksum SHA-256 dan metrik pemulihan yang dapat dipertanggungjawabkan. | Perhitungan hash biner sebelum bencana dan setelah pemulihan. |
| **M4B-13** | Rekonsiliasi pemetaan gerbang M4A dipetakan ulang secara transparan sesuai SRS asli. | Penyusunan dokumen `docs/m4a-acceptance-reconciliation.md`. |
| **M4B-14** | Kesiapan hosting Hostinger dinilai komprehensif dan status dipertahankan `BLOCKED`. | Dokumen `docs/hostinger-staging-readiness.md`. |
| **M4B-15** | Invarian keselamatan: `official_numbering = false`, `letters.official_number = NULL`. | Automated safety guard test & database query assertion. |
| **M4B-16** | Dokumentasi teknis, matriks uji, artefak berkas, dan tangkapan layar lengkap. | Verifikasi seluruh 8 dokumen deliverables di direktori `docs/`. |

---

## 5. INVARIAN KESELAMATAN (SAFETY GUARDS)

Selama Sprint M4B berlangsung:
1. `config('features.official_numbering') = false` (TIDAK BOLEH DIAKTIFKAN).
2. `letters.official_number = NULL` (TIDAK BOLEH ADA PENOMORAN RESMI KELUAR).
3. Penomoran pada naskah draf berlabel tegas `[SIMULASI] DRAF/P4I/...`.
4. Blok tanda tangan dan stempel dinonaktifkan (`[TANDA TANGAN BELUM DITERBITKAN]`).
5. Tidak ada deployment langsung atau manipulasi pada akun hosting Hostinger produksi.
6. Status kesiapan operasional (*readiness status*) wajib tetap **`BLOCKED`**.

