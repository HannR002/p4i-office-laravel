# RENCANA KERJA IMPLEMENTASI & VERIFIKASI SPRINT M5A
## HOSTINGER STAGING READINESS, ACCEPTANCE CLOSURE & UAT PREPARATION

**Sistem:** P4I Office v1.0  
**Sprint:** M5A (Hostinger Staging Readiness, Acceptance Closure & UAT Preparation)  
**Branch:** `feat/sprint-m5a-staging-readiness`  
**Workspace:** `D:\p4i-office-laravel` (Sinkronisasi Dokumentasi: `D:\p4i-office`)  
**Tim Perancangan, Software Architect, Auditor & Penyusun Laporan Resmi:** GPT-6  
**Implementer & Technical Test Engineer:** Gemini (Lead Software Engineer)  
**Otoritas Administratif:** Ketua dan Dewan Pengurus Yayasan P4I  
**Tanggal:** 01 November 2026  

---

## 1. TATA KELOLA DAN BATAS KEWENANGAN

- **GPT-6:** Bertanggung jawab atas perancangan sistem, SRS (`P4I_Office_Addendum_SRS_Sprint_M5A_2026-11-01.docx`), arsitektur, audit independen, keputusan penerimaan teknis, dan penyusunan laporan resmi.
- **Gemini IDE:** Bertanggung jawab atas implementasi kode, pengujian otomatis, perbaikan temuan audit, dokumentasi teknis, dan penyediaan bukti empiris yang dapat direproduksi.
- **Ketua dan Pengurus Yayasan P4I:** Menentukan kebijakan organisasi, pengesahan SOP penomoran, SK Mandat pejabat, persetujuan lingkungan staging Hostinger, dan izin operasional produksi.
- **Prinsip Kepatuhan:** Tidak mengklaim sistem siap produksi atau telah disahkan yayasan hanya berdasarkan kelulusan pengujian otomatis lokal. Tidak mengakses atau mengubah Hostinger tanpa izin eksplisit tertulis dari pemilik proyek.

---

## 2. TAHAPAN EKSEKUSI SPRINT M5A

### Tahap 1 — Audit Repositori Aktual & Penutupan Sprint M4B
1. Verifikasi baseline commit `a53ee3dc51ba1f59d40d2a5b82be045f2c5255d2` pada branch `feat/sprint-m4b-secure-archive`.
2. Eksekusi ulang PHPUnit, Laravel Pint, dan Vite build.
3. Audit menyeluruh terhadap `DocumentExportService`, `DocumentArchiveController`, seluruh `Policies`, `routes/web.php`, serta pengujian keamanan arsip.
4. Perbaikan pemetaan matriks penerimaan Sprint M4B tanpa mengubah definisi asli ID pengujian:
   - **M4B-13 asli:** Rekonsiliasi acceptance gates Sprint M4A.
   - **M4B-14 asli:** Pengujian Hostinger staging dengan izin, atau berstatus `BLOCKED` jika izin belum tersedia.
   - **M4B-16 asli:** Seluruh pengujian, bukti, dan blocker terdokumentasi lengkap.
5. Penyusunan dokumen `docs/m4b-acceptance-closure.md` berdasarkan bukti aktual.

### Tahap 2 — Audit & Perbaikan Artefak Dokumen Biner (PDF & DOCX)
1. Inspeksi biner dan struktur terhadap `sample_1page_draft.pdf`, `sample_1page_draft.docx`, `sample_3page_draft.pdf`, dan `sample_3page_draft.docx`.
2. Perbaikan temuan audit pada `DocumentExportService`:
   - Menyertakan `$tablesHtml` ke dalam body HTML PDF (`buildPdfHtml`) serta mendukung penempatan tabel pada halaman sasaran agar PDF dan DOCX 100% konsisten secara visual dan struktural.
   - Menyertakan blok penafian dan metadata ekspor (`Revisi Ke-`, `Diekspor oleh`, `Waktu`) pada body DOCX agar identik dengan PDF.
   - Mengaktifkan `PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true)` dengan teks murni agar struktur OpenXML selalu valid tanpa risiko *double-escaping*.
3. Regenerasi keempat berkas bukti fisik pada `docs/evidence/sprint-m4b/` dan verifikasi ulang jumlah halaman, watermark di setiap halaman, struktur OpenXML, ketiadaan makro/tanda tangan/stempel/nomor resmi, serta kecocokan hash SHA-256 dengan metadata database.

### Tahap 3 — Verifikasi & Mekanisme Rekonsiliasi Crash Recovery Mendadak
1. Audit klaim `try...catch` pada `DocumentExportService`: blok `catch (\Throwable)` hanya menangani exception dalam siklus hidup PHP normal, namun tidak terpanggil apabila proses PHP berhenti mendadak (`SIGKILL`, *out-of-memory*, *worker timeout*, atau *power loss*).
2. Implementasi metode `DocumentExportService::reconcileInterruptedExports(int $staleThresholdMinutes = 15): array` dan perintah Artisan `p4i:reconcile-exports`:
   - Mendeteksi ekspor berstatus `PENDING` yang melewati batas waktu (*stale PENDING*).
   - Memeriksa berkas sementara (`p4i_export_*` di direktori temp) dan berkas hasil ekspor di `private_archives`.
   - Memverifikasi checksum SHA-256 dan struktur biner berkas.
   - Memulihkan pekerjaan yang sebenarnya telah selesai menulis berkas utuh ber-hash cocok, atau menandai `ERROR` dan membersihkan berkas parsial/yatim untuk pekerjaan yang terputus di tengah penulisan.
   - Melindungi berkas valid milik proses `READY` maupun proses `PENDING` aktif yang masih berjalan (di bawah ambang *stale*).
   - Memeriksa seluruh record berstatus `READY` dan menjamin tidak ada metadata `READY` yang menunjuk ke berkas hilang atau korup (transisi otomatis ke `ERROR` dengan jejak audit `DOCUMENT_EXPORT_RECONCILED`).
3. Penambahan pengujian otomatis komprehensif untuk skenario penghentian mendadak proses PHP.

### Tahap 4 — Perancangan Arsitektur Hostinger Staging Terisolasi
1. Penyusunan `docs/hostinger-staging-deployment-plan.md` dan `docs/staging-rollback-and-backup.md`.
2. Perancangan topologi subdomain/website terpisah, isolasi `document_root` ke `public/` (atau struktur induk terpisah dari `public_html`), database MariaDB staging terpisah, konfigurasi `.env` khusus staging, proteksi akses HTTP Basic Auth / IP Whitelist pada staging, SSL/TLS, cron job rekonsiliasi, serta prosedur backup dan rollback.
3. Penegakan aturan ketat: **Tidak mengakses, mengubah, atau mengunggah apa pun ke Hostinger sebelum ada izin eksplisit dari pemilik proyek.**

### Tahap 5 — Matriks Kompatibilitas Database & Infrastruktur Hostinger
1. Penyusunan `docs/staging-compatibility-matrix.md` mencakup pemeriksaan PHP 8.3/8.4, ekstensi wajib, MariaDB InnoDB, hak `CREATE TRIGGER`, foreign keys, transaksi ACID, batas *timeout* & memori, ukuran database, migrasi Laravel, serta SSH/Composer.
2. Penyusunan rencana kontingensi teknis apabila akun shared hosting membatasi hak `CREATE TRIGGER` (tanpa menghapus proteksi integritas secara sepihak; disajikan sebagai proposal arsitektur untuk dievaluasi GPT-6).
3. Implementasi perintah Artisan pemeriksa pra-staging (`p4i:staging-preflight`) untuk memverifikasi kapabilitas database dan runtime secara otomatis.

### Tahap 6 — Persiapan Skenario UAT (User Acceptance Test) & Go/No-Go
1. Penyusunan `docs/staging-uat-test-cases.md` memuat 15 skenario UAT end-to-end dengan data sintetis dan verifikasi otomatis lokal (`StagingUatPreparationTest`).
2. Penyusunan `docs/staging-go-no-go.md` dan `docs/implementation-evidence-sprint-m5a.md` yang memetakan gerbang penerimaan **STG-01 s/d STG-16** secara jujur (PASS untuk verifikasi lokal yang terbukti, dan BLOCKED untuk gerbang yang membutuhkan eksekusi langsung pada server Hostinger yang belum diizinkan).

---

## 3. MATRIKS GERBANG PENERIMAAN SPRINT M5A (STG-01 S/D STG-16)

| Gate ID | Definisi Resmi SRS Sprint M5A | Target Status |
|---|---|---|
| **STG-01** | Baseline M4B dan pengujian regresi diperiksa. | **PASS** |
| **STG-02** | Matriks M4B-13/14/16 diperbaiki. | **PASS** |
| **STG-03** | PDF/DOCX asli diperiksa secara biner dan visual. | **PASS** |
| **STG-04** | Seluruh akses arsip terlindungi. | **PASS** |
| **STG-05** | Crash recovery mendadak teruji. | **PASS** |
| **STG-06** | Lingkungan staging Laravel terisolasi dirancang. | **PASS** |
| **STG-07** | Izin eksplisit hosting diperiksa sebelum perubahan. | **PASS** |
| **STG-08** | PHP, Composer dan dependensi staging diuji atau BLOCKED. | **BLOCKED (Menunggu Izin Staging Hostinger)** |
| **STG-09** | MariaDB migrations dan trigger diuji atau BLOCKED. | **BLOCKED (Menunggu Izin Staging Hostinger)** |
| **STG-10** | HTTPS, document root dan private storage diuji atau BLOCKED. | **BLOCKED (Menunggu Izin Staging Hostinger)** |
| **STG-11** | Workflow dan ekspor dokumen diuji pada HTTP staging atau BLOCKED. | **BLOCKED (Menunggu Izin Staging Hostinger)** |
| **STG-12** | Cron, memory limit, timeout dan monitoring diuji atau BLOCKED. | **BLOCKED (Menunggu Izin Staging Hostinger)** |
| **STG-13** | Backup/restore database dan file terverifikasi. | **PASS (Terverifikasi Lokal) / BLOCKED di Hostinger** |
| **STG-14** | Skenario UAT lengkap dan dapat dieksekusi. | **PASS** |
| **STG-15** | Penomoran resmi tetap OFF, NULL, dan seluruh ekspor DRAF. | **PASS** |
| **STG-16** | Paket deployment/rollback serta go/no-go tersedia. | **PASS** |

---

## 4. INVARIAN KESELAMATAN (SAFETY GUARDS)

1. `official_numbering_active = false` (Penomoran resmi tetap OFF).
2. `letters.official_number = NULL` pada seluruh baris tabel `letters`.
3. Seluruh hasil ekspor dokumen wajib berlabel `DRAF` dengan watermark di setiap halaman.
4. Tidak ada koneksi, unggahan, atau perubahan ke server Hostinger sebelum izin eksplisit diberikan.
5. Status akhir apabila pekerjaan lokal selesai tanpa izin akses Hostinger:
   **`LOCAL PREPARATION COMPLETE — HOSTINGER STAGING BLOCKED`**

