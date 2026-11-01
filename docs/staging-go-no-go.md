# MATRIKS EVALUASI GO / NO-GO STAGING & PRODUKSI P4I OFFICE
## SPRINT M5A — ACCEPTANCE GATES STG-01 S/D STG-16 & KEPUTUSAN TATA KELOLA

**Sistem:** P4I Office v1.0  
**Penyusun Teknis:** Argon — Reasoning Medium (Lead Software Engineer & Technical Test Engineer)  
**Software Architect, QA Auditor & Penyusun Laporan Resmi:** GPT-6  
**Otoritas Keputusan Go/No-Go:** Ketua dan Dewan Pengurus Yayasan P4I  
**Tanggal Evaluasi:** 01 November 2026  
**Keputusan Status Saat Ini:** **`LOCAL PREPARATION COMPLETE — HOSTINGER STAGING BLOCKED`**  

---

## 1. EVALUASI GERBANG PENERIMAAN SPRINT M5A (STG-01 S/D STG-16)

Sesuai instruksi tata kelola Sprint M5A, definisi ID penerimaan **STG-01 sampai STG-16** dipertahankan sesuai SRS Sprint M5A tanpa manipulasi. Gerbang yang membutuhkan eksekusi langsung pada server Hostinger dicatat secara jujur sebagai **`BLOCKED`** karena izin eksplisit dari pemilik proyek belum diberikan.

| Gate ID | Definisi Resmi SRS Sprint M5A | Bukti Teknis & Referensi Verifikasi | Hasil Aktual | Status Gate |
|---|---|---|---|---|
| **STG-01** | Baseline M4B dan pengujian regresi diperiksa. | Branch baseline `feat/sprint-m4b-secure-archive` (`a53ee3dc51ba1f59d40d2a5b82be045f2c5255d2`) diverifikasi; seluruh test suite regresi lulus (`exit code 0`). | Terverifikasi bersih dan bebas regresi. | **PASS** |
| **STG-02** | Matriks M4B-13/14/16 diperbaiki. | Pemetaan asli M4B-13 (rekonsiliasi M4A), M4B-14 (staging Hostinger `BLOCKED`), dan M4B-16 (dokumentasi lengkap) dikoreksi pada `docs/m4b-test-matrix.md` dan `docs/m4b-acceptance-closure.md`. | Definisi ID asli dipulihkan secara transparan. | **PASS** |
| **STG-03** | PDF/DOCX asli diperiksa secara biner dan visual. | Keempat berkas di `docs/evidence/sprint-m4b/` diperiksa stream per halaman (`gzuncompress`), tabel di halaman 2, watermark & footer berulang, serta diuji baca melalui `PhpWord\IOFactory::load()`. | 1-page = 1 hal, 3-page = 3 hal, tabel & revisi konsisten 100%, macro-free, SHA-256 cocok. | **PASS** |
| **STG-04** | Seluruh akses arsip terlindungi. | `MandateValidationService::canAccessConfidential` & `applyConfidentialityScope` diterapkan konsisten pada `LetterPolicy`, `DocumentExportService`, `DocumentArchiveController`, `LetterController`, dan `ApprovalController`. | Diuji pada `ConfidentialArchiveAuthorizationTest` (11 tests pass). | **PASS** |
| **STG-05** | Crash recovery mendadak teruji. | `DocumentExportService::reconcileInterruptedExports()` & `php artisan p4i:reconcile-exports` mendeteksi `PENDING` macet, memulihkan berkas utuh ber-hash cocok, menghapus berkas parsial/temp yatim, melindungi berkas valid, dan menandai `ERROR` jika `READY` kehilangan berkas fisik. | Diuji pada `ArchiveTransactionConcurrencyTest` (`test_stg_05_*` pass). | **PASS** |
| **STG-06** | Lingkungan staging Laravel terisolasi dirancang. | Topologi direktori `p4i_staging_core` di luar `public_html`, database staging terpisah, dan konfigurasi `.env.staging` dirancang lengkap pada `docs/hostinger-staging-deployment-plan.md`. | Dokumen rancangan arsitektur staging lengkap. | **PASS** |
| **STG-07** | Izin eksplisit hosting diperiksa sebelum perubahan. | Tidak ada koneksi SSH/FTP/DB yang dilakukan ke Hostinger; seluruh pekerjaan dilakukan 100% di repositori lokal menunggu persetujuan tertulis pemilik proyek. | Tidak ada perubahan tanpa izin pada Hostinger. | **PASS** |
| **STG-08** | PHP, Composer dan dependensi staging diuji atau BLOCKED. | Lokal: Teruji via `php artisan p4i:staging-preflight` (PHP `8.3.36`, 7/7 ekstensi aktif). Server Hostinger: Belum dapat diuji langsung karena menunggu izin akses staging. | Lokal: PASS.<br>Server Hostinger: **BLOCKED**. | **BLOCKED** *(Menunggu Izin Staging)* |
| **STG-09** | MariaDB migrations dan trigger diuji atau BLOCKED. | Lokal: 15 tabel InnoDB & 4 trigger imutabilitas aktif. Proposal kontingensi (Opsi A/B/C) disiapkan di `docs/staging-compatibility-matrix.md`. Server Hostinger: Menunggu izin akses staging. | Lokal: PASS.<br>Server Hostinger: **BLOCKED**. | **BLOCKED** *(Menunggu Izin Staging)* |
| **STG-10** | HTTPS, document root dan private storage diuji atau BLOCKED. | Lokal: `private_archives` terverifikasi di luar `public_path()`. Konfigurasi `.htaccess`, SSL, dan `usePublicPath` telah disiapkan, namun belum diuji pada domain HTTPS Hostinger. | Lokal: PASS.<br>Server Hostinger: **BLOCKED**. | **BLOCKED** *(Menunggu Izin Staging)* |
| **STG-11** | Workflow dan ekspor dokumen diuji pada HTTP staging atau BLOCKED. | Lokal: Seluruh workflow HTTP diuji pada `StagingUatPreparationTest`. Server HTTP Staging Hostinger: Menunggu deployment staging disetujui. | Lokal: PASS.<br>Server Hostinger: **BLOCKED**. | **BLOCKED** *(Menunggu Izin Staging)* |
| **STG-12** | Cron, memory limit, timeout dan monitoring diuji atau BLOCKED. | Lokal: `p4i:reconcile-exports` terjadwal di `Console\Kernel` setiap 15 menit dan lulus uji CLI. Server Cron hPanel Hostinger: Menunggu izin akses staging. | Lokal: PASS.<br>Server Hostinger: **BLOCKED**. | **BLOCKED** *(Menunggu Izin Staging)* |
| **STG-13** | Backup/restore database dan file terverifikasi. | Siklus backup, deteksi korupsi/kehilangan berkas, dan restore database + file fisik terverifikasi pada `BackupRecoveryTest`, `IntegratedArchiveRestoreTest`, dan `StagingUatPreparationTest` (UAT-14). | Terverifikasi 100% pada lingkungan pengujian lokal. | **PASS** *(Lokal)* / **BLOCKED** *(Hostinger)* |
| **STG-14** | Skenario UAT lengkap dan dapat dieksekusi. | 15 skenario UAT terdokumentasi di `docs/staging-uat-test-cases.md` dan seluruhnya dapat dieksekusi otomatis melalui `StagingUatPreparationTest` (`PASS`). | 15/15 skenario UAT terverifikasi otomatis dengan data sintetis. | **PASS** |
| **STG-15** | Penomoran resmi tetap OFF, NULL, dan seluruh ekspor DRAF. | `config('features.official_numbering_active') === false`, `letters.official_number === NULL` (0 baris non-null), seluruh PDF/DOCX berwatermark `DRAF — BELUM RESMI`. | Invarian keselamatan terjaga 100%. | **PASS** |
| **STG-16** | Paket deployment/rollback serta go/no-go tersedia. | Seluruh 8 dokumen rencana deployment, kompatibilitas, UAT, backup/rollback, dan matriks Go/No-Go tersedia lengkap di `docs/`. | Paket dokumentasi lengkap dan disinkronkan. | **PASS** |

---

## 2. DAFTAR BLOCKER & KEPUTUSAN YANG MEMBUTUHKAN PENGURUS P4I ATAU GPT-6

### A. Keputusan yang Membutuhkan Pemilik Proyek / Pengurus Yayasan P4I:
1. **Izin Eksplisit Pembukaan Lingkungan Staging Hostinger (Blocker untuk STG-08 s/d STG-12):**  
   Persetujuan tertulis untuk membuat subdomain staging terpisah (`staging-office...`) dan database MariaDB staging terpisah pada akun Hostinger Business Web Hosting.
2. **Pengesahan Administratif SOP Penomoran & Register Historis (Blocker untuk Produksi):**  
   - Pengesahan SOP Tata Naskah Dinas & Format Nomor Resmi Yayasan P4I.
   - Berita Acara Rekonsiliasi Register Surat Lama (penetapan batas nomor terakhir sebelum cutover).
   - Penerbitan SK Mandat Pejabat Pemeriksa dan Penandatangan definitif.

### B. Keputusan Teknis yang Membutuhkan Evaluasi Arsitek & Auditor GPT-6:
1. **Audit Independen Penutupan Sprint M4B & Persiapan M5A:**  
   Evaluasi atas perbaikan `DocumentExportService`, mekanisme rekonsiliasi `reconcileInterruptedExports()`, dan paket persiapan staging.
2. **Keputusan Kontingensi Hak `CREATE TRIGGER` di Hostinger (Jika Ditolak Server Shared Hosting):**  
   Penetapan pilihan antara Opsi A (aktivasi hak trigger via dukungan Hostinger), Opsi B (pembatasan hak user SQL), atau Opsi C (*hash-chained ledger*) sebagaimana dirinci pada `docs/staging-compatibility-matrix.md`.

---

## 3. REKOMENDASI KEPUTUSAN GO / NO-GO

| Target Lingkungan | Status Rekomendasi | Alasan Teknis & Tata Kelola |
|---|---|---|
| **Persiapan Teknis Lokal (Sprint M5A)** | **GO (COMPLETE)** | 138 pengujian otomatis (687 assertions) lulus 100%, biner PDF/DOCX tervalidasi, crash recovery teruji, dan invarian keamanan utuh. |
| **Deployment ke Hostinger Staging** | **NO-GO / BLOCKED (Menunggu Izin)** | Secara teknis siap di-deploy mengikuti `docs/hostinger-staging-deployment-plan.md`, namun **wajib menunggu izin eksplisit pemilik proyek** dan hasil review GPT-6. |
| **Aktivasi Produksi & Nomor Surat Resmi** | **STRICTLY NO-GO / BLOCKED** | Staging UAT belum dijalankan pada server Hostinger dan SOP/SK Mandat resmi dari Dewan Pengurus Yayasan P4I belum diterbitkan. |

