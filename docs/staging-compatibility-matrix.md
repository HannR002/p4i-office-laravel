# MATRIKS KOMPATIBILITAS DATABASE & INFRASTRUKTUR HOSTINGER
## SPRINT M5A — TAHAP 5: AUDIT KOMPATIBILITAS & RENCANA KONTINGENSI TRIGGER

**Sistem:** P4I Office v1.0  
**Target Infrastruktur:** Hostinger Business Web Hosting (Linux Cloud / LiteSpeed Enterprise + MariaDB)  
**Penyusun Teknis:** Gemini (Lead Software Engineer)  
**Evaluator Arsitektur:** GPT-6 (Software Architect & QA Auditor)  
**Otoritas Administratif:** Ketua dan Dewan Pengurus Yayasan P4I  
**Tanggal:** 01 November 2026  

---

## 1. MATRIKS PEMERIKSAAN KOMPATIBILITAS (CHECKLIST 10 PARAMETER)

Pemeriksaan kesiapan lokal dilaksanakan melalui perintah otomatis `php artisan p4i:staging-preflight --json`, sedangkan verifikasi langsung di server Hostinger ditandai sebagai **`BLOCKED`** hingga izin akses staging diberikan oleh pemilik proyek.

| No | Parameter Pemeriksaan | Spesifikasi Kebutuhan P4I Office | Hasil Verifikasi Lokal (`p4i:staging-preflight`) | Status di Server Hostinger Staging | Metode Verifikasi Saat Izin Staging Diberikan |
|---|---|---|---|---|---|
| **1** | **Versi PHP & Ekstensi** | PHP `^8.3.0` (`8.3.x` / `8.4.x`) + ekstensi: `pdo_mysql`, `mbstring`, `xml`, `zip`, `gd`, `openssl`, `fileinfo` | **PASS** (PHP `8.3.36`, 7/7 ekstensi aktif) | **BLOCKED (Menunggu Izin)** | `php -v && php -m` via SSH Hostinger + `php artisan p4i:staging-preflight` |
| **2** | **Versi MariaDB & InnoDB** | MariaDB >= 10.4 dengan penyimpanan default `InnoDB` | **PASS** (`10.4.32-MariaDB`, InnoDB `DEFAULT`) | **BLOCKED (Menunggu Izin)** | `SELECT VERSION(); SHOW ENGINES;` pada database staging |
| **3** | **Hak `CREATE TRIGGER`** | Hak `TRIGGER` untuk 4 trigger imutabilitas (`letter_revisions` & `audit_events`) | **PASS** (4/4 trigger aktif di `p4i_office_dev` & `p4i_office_test`) | **BLOCKED (Menunggu Izin)** | Uji `php artisan migrate` dan kueri `information_schema.TRIGGERS` |
| **4** | **Foreign Keys & Transaksi ACID** | `ON DELETE RESTRICT/CASCADE` dan row-level locking `SELECT ... FOR UPDATE` | **PASS** (15 tabel InnoDB teruji konkuren pada `ApprovalConcurrencyTest`) | **BLOCKED (Menunggu Izin)** | Eksekusi test konkurensi persetujuan & alokasi pada DB staging |
| **5** | **Timeout Database & PHP** | `max_execution_time >= 60s` untuk ekspor multi-halaman & impor XLSX; `wait_timeout >= 60s` | **PASS** (Ekspor 3 hal selesai dalam `< 1.2 detik`) | **BLOCKED (Menunggu Izin)** | Periksa `SHOW VARIABLES LIKE '%timeout%';` dan `ini_get('max_execution_time')` |
| **6** | **Ukuran Database & Kuota Storage** | Estimasi DB `< 250 MB/tahun`; arsip PDF/DOCX `< 2 GB/tahun` (Hostinger Business menyediakan 50–100 GB NVMe) | **PASS** (Rata-rata PDF 3 hal = `7.8 KB`, DOCX 3 hal = `11.2 KB`) | **BLOCKED (Menunggu Izin)** | Pantau melalui `GET /archives/admin` dan kuota disk `df -h` |
| **7** | **Koneksi Environment** | Koneksi lokal `127.0.0.1:3306` / Unix socket tanpa latensi jaringan lintas data center | **PASS** (PDO MySQL strict mode aktif) | **BLOCKED (Menunggu Izin)** | Verifikasi `php artisan db:monitor` pada staging |
| **8** | **Migrasi Laravel Reproducible** | 6 berkas migrasi berurutan (`0001` s/d `2026_11_01_100001`) berjalan idempoten dari nol | **PASS** (`migrate:fresh` berhasil 100% tanpa error) | **BLOCKED (Menunggu Izin)** | `php artisan migrate --force` pada DB staging kosong |
| **9** | **Backup & Restore** | Ekspor SQL dump + tar/zip direktori `private_archives` dengan verifikasi SHA-256 | **PASS** (`BackupRecoveryTest` & `IntegratedArchiveRestoreTest` lulus 100%) | **BLOCKED (Menunggu Izin)** | Uji skrip backup/restore pada direktori staging |
| **10** | **Ketersediaan SSH & Composer** | Paket Hostinger Business mendukung akses SSH (port 65002) dan biner `composer` v2 | **PASS** (`composer.lock` terkunci dan deterministik) | **BLOCKED (Menunggu Izin)** | Uji login SSH port 65002 dan `composer --version` |

---

## 2. ANALISIS KHUSUS HAK `CREATE TRIGGER` & PROPOSAL KONTINGENSI ARSITEKTUR

### A. Kondisi Saat Ini (Pertahanan Berlapis / Defense-in-Depth)
P4I Office saat ini menerapkan proteksi imutabilitas ganda pada tabel `letter_revisions` dan `audit_events`:
1. **Lapisan Aplikasi (Eloquent Model Guard):**  
   Pada `app/Models/LetterRevision.php` dan `app/Models/AuditEvent.php`, event `updating` dan `deleting` secara tegas melempar `DomainException` (*"Revisi surat bersifat immutable..."* / *"Log audit bersifat immutable..."*).
2. **Lapisan Database (MariaDB Trigger Guard):**  
   Pada migrasi `0002_01_01_000000_create_immutability_triggers.php`, dipasang 4 trigger database (`trg_letter_revisions_no_update`, `trg_letter_revisions_no_delete`, `trg_audit_events_no_update`, `trg_audit_events_no_delete`) yang melempar `SIGNAL SQLSTATE '45000'` apabila ada upaya `UPDATE` atau `DELETE` langsung melalui SQL mentah (`DB::table(...)`).

### B. Risiko pada Shared/Business Hosting
Pada sebagian konfigurasi MariaDB shared hosting yang mengaktifkan binary logging dengan `log_bin_trust_function_creators = OFF`, perintah `CREATE TRIGGER` oleh pengguna database non-SUPER dapat ditolak dengan error:
`SQLSTATE[HY000]: General error: 1419 You do not have the SUPER privilege and binary logging is enabled`.
*(Catatan: Pada banyak server Hostinger MariaDB 10.11 terbaru, hak `TRIGGER` sudah diberikan secara default kepada user database yang dibuat via hPanel, namun hal ini wajib diverifikasi saat staging dibuka).*

### C. Proposal Alternatif untuk Evaluasi GPT-6 (Apabila `CREATE TRIGGER` Ditolak di Hostinger)

Sesuai instruksi tata kelola Sprint M5A, **kami TIDAK menghapus atau mengubah migrasi trigger yang ada**. Apabila pada saat pengujian staging nanti server Hostinger menolak pembuatan trigger, berikut adalah **3 opsi proposal arsitektur** yang diajukan kepada **GPT-6** untuk dievaluasi:

| Opsi Proposal | Mekanisme Teknis | Kelebihan | Risiko yang Tersisa (Residual Risk) | Pengujian yang Diperlukan |
|---|---|---|---|---|
| **Opsi A (Rekomendasi Utama): Aktivasi Hak Trigger via Dukungan Hostinger / hPanel** | Meminta dukungan teknis Hostinger mengaktifkan `TRIGGER` privilege pada user DB atau menyetel `log_bin_trust_function_creators=1`. | Mempertahankan 100% arsitektur asli tanpa perubahan kode. | Bergantung pada kebijakan cluster database shared Hostinger. | Eksekusi ulang `php artisan migrate` dan `DatabaseSchemaTest`. |
| **Opsi B: Pemisahan User Database (Dual DB Credentials / Least-Privilege SQL User)** | Aplikasi web sehari-hari menggunakan user DB dengan hak terbatas (`SELECT, INSERT` saja pada `letter_revisions` dan `audit_events`, tanpa hak `UPDATE/DELETE` pada tabel tersebut) atau mengintersepsi kueri SQL mentah pada `Connection::beforeExecuting`. | Mencegah modifikasi baris bahkan jika Eloquent dilewati (`DB::table()`). | Pada shared hosting, hPanel sering kali hanya menyediakan izin level-database (bukan level-tabel per kolom/tabel). | Pengujian penolakan `DB::table('letter_revisions')->update(...)` dan `delete(...)` tanpa trigger. |
| **Opsi C: Rantai Hash Kriptografis Berantai (*Hash-Chained Audit & Revision Ledger*) + Pengecek Integritas Terjadwal** | Setiap baris `letter_revisions` dan `audit_events` menyimpan `previous_row_hash` + `row_hmac_sha256` (menggunakan kunci rahasia aplikasi), dikombinasikan dengan Eloquent Guard + listener `DB::listen` yang memblokir statement `UPDATE/DELETE` terhadap kedua tabel tersebut. | 100% independen dari hak `CREATE TRIGGER` database; setiap manipulasi langsung di luar aplikasi langsung terdeteksi secara matematis. | Memerlukan penambahan kolom `row_signature` dan migrasi aditif setelah disetujui GPT-6. | Uji deteksi perubahan 1 karakter via phpMyAdmin dan uji blokir query builder `DB::table()->update()`. |

**Keputusan Saat Ini:**  
Kode migrasi trigger pada `database/migrations/0002_01_01_000000_create_immutability_triggers.php` **dipertahankan utuh 100%**. Keputusan pemilihan Opsi A/B/C menunggu hasil uji koneksi nyata di Hostinger Staging dan persetujuan arsitektur dari GPT-6.

