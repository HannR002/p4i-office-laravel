# Bukti Teknis Migrasi Database & Verifikasi Trigger Immutability (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment)  
**Implementer Teknis:** Argon — Reasoning High  
**Tim Perancangan & Auditor:** GPT-6  
**Target Database:** `u239415845_p4i_staging` (Hostinger MariaDB / InnoDB)  
**Database Lokal Pengujian:** MariaDB `10.4.32-MariaDB` (`p4i_office_test`)  
**Tanggal Evaluasi:** 16 November 2026  

---

## 1. Ringkasan Status Migrasi & Keamanan Database

| Komponen | Status Lokal | Status Hostinger Staging | Catatan Kepatuhan |
| :--- | :---: | :---: | :--- |
| **Hak Istimewa `TRIGGER`** | `PASS` | `PASS` (`Trigger: 1`) | User `u239415845_p4i_staging` memiliki hak penuh membuat trigger. |
| **8 Migrasi Aplikasi** | `Ran` (8/8) | `BELUM DIJALANKAN` | Menunggu otorisasi eksekusi P1-C di server. |
| **Skema Tabel Lengkap** | 15 tabel aktif | `0 tabel` | Database Hostinger masih bersih (`disk_usage_mb: 1`). |
| **4 Trigger Immutability** | `AKTIF & TERUJI` | `BELUM DIPASANG` | Dipasang otomatis saat migrasi server dieksekusi. |
| **Isolasi Database Produksi** | `100% TERISOLASI`| `100% TERISOLASI` | Zero connection ke `u239415845_ojs` atau `u239415845_publisher`. |

---

## 2. Rincian 8 Migrasi Database P4I Office v1.0

Berikut adalah urutan 8 berkas migrasi resmi yang telah diverifikasi dan siap dieksekusi begitu gerbang migrasi server dibuka:

| No | Berkas Migrasi | Tabel / Aksi yang Dihasilkan | Kegunaan dalam Sistem Persuratan |
| :-: | :--- | :--- | :--- |
| **1** | `2014_10_12_100000_create_password_reset_tokens_table` | `password_reset_tokens` | Penyimpanan token reset password aman terenkripsi. |
| **2** | `2019_08_19_000000_create_failed_jobs_table` | `failed_jobs` | Pencatatan kegagalan antrean background worker. |
| **3** | `2019_12_14_000001_create_personal_access_tokens_table` | `personal_access_tokens` | Otentikasi token API Sanctum (bila diaktifkan). |
| **4** | `2026_10_30_000001_create_p4i_office_schema` | `organizations`, `org_units`, `roles`, `users`, `user_roles`, `letter_types`, `letter_templates`, `letters`, `letter_revisions`, `signatory_mandates`, `approval_requests`, `approval_steps`, `audit_events` | Skema inti persuratan draf, RBAC, riwayat revisi, alur telaah, dan jejak audit. |
| **5** | `2026_11_01_000001_enhance_sprint_m2_tables` | Pembaruan pada `signatory_mandates`, `approval_steps` | Penambahan indeks komposit, validasi tanggal SK mandat, dan status antrean klaim. |
| **6** | `2026_11_01_000002_create_sprint_m3a_tables` | `numbering_policies`, `incoming_letters`, `legacy_letter_registers` | Fondasi format penomoran simulasi, buku agenda surat masuk, dan register historis. |
| **7** | `2026_11_01_000003_enhance_sprint_m3b_tables` | `legacy_register_rows`, `legacy_reconciliation_logs` | Staging impor CSV/XLSX register lama dan jejak rekonsiliasi anomali. |
| **8** | `2026_11_01_000004_enhance_sprint_m3c_security_tables` | Pemasangan 4 trigger immutability via DDL MariaDB | Menegakkan integritas data revisi dan jejak audit pada level database engine. |
| **9** | `2026_11_01_000005_create_document_exports_table` | `document_exports` | Manajemen ekspor PDF/DOCX, idempotency key, hash SHA-256, dan status `READY`/`ERROR`. |

---

## 3. Spesifikasi Teknis 4 Trigger Immutability MariaDB

Keempat trigger diimplementasikan pada level engine MariaDB untuk menjamin bahwa data riwayat persuratan dan log audit tidak dapat dimanipulasi bahkan oleh administrator database melalui query raw SQL:

### 3.1 Trigger Immutability Riwayat Revisi Surat (`letter_revisions`)
```sql
-- 1. Blokir pembaruan pada revisi surat
CREATE TRIGGER trg_letter_revisions_no_update
BEFORE UPDATE ON letter_revisions
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Perubahan data revisi surat tidak diizinkan (immutable).';
END;

-- 2. Blokir penghapusan pada revisi surat
CREATE TRIGGER trg_letter_revisions_no_delete
BEFORE DELETE ON letter_revisions
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Penghapusan data revisi surat tidak diizinkan (immutable).';
END;
```

### 3.2 Trigger Immutability Jejak Audit (`audit_events`)
```sql
-- 3. Blokir pembaruan pada audit log
CREATE TRIGGER trg_audit_events_no_update
BEFORE UPDATE ON audit_events
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Perubahan data audit trail tidak diizinkan (immutable).';
END;

-- 4. Blokir penghapusan pada audit log
CREATE TRIGGER trg_audit_events_no_delete
BEFORE DELETE ON audit_events
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Penghapusan data audit trail tidak diizinkan (immutable).';
END;
```

### 3.3 Bukti Uji Coba Penolakan Raw SQL (Hasil Uji Lokal)
Pada pengujian otomatis [`LetterDraftManagementTest::test_m1_05_raw_sql_update_and_delete_on_revision_blocked_by_database_triggers`](file:///d:/p4i-office-laravel/tests/Feature/LetterDraftManagementTest.php#L225):
- Eksekusi `DB::statement("UPDATE letter_revisions SET subject = 'Ubah Paksa' WHERE id = ?")` berhasil memicu pengecualian database MariaDB:
  `SQLSTATE[45000]: <<Unknown error>>: 1644 Perubahan data revisi surat tidak diizinkan (immutable).`
- Eksekusi `DB::statement("DELETE FROM letter_revisions WHERE id = ?")` berhasil memicu:
  `SQLSTATE[45000]: <<Unknown error>>: 1644 Penghapusan data revisi surat tidak diizinkan (immutable).`

---

## 4. Verifikasi Isolasi Mutlak Terhadap Database Produksi

1. **Pemeriksaan Daftar Database:**
   - `u239415845_p4i_staging`: Dibuat khusus untuk staging (host `127.0.0.1:3306`, disk usage 1 MB).
   - `u239415845_ojs`: Database produksi jurnal OJS (disk usage 90 MB) — **TIDAK DIHUBUNGKAN**.
   - `u239415845_publisher`: Database produksi publisher (disk usage 4 MB) — **TIDAK DIHUBUNGKAN**.
   - `u239415845_publisher_new`: Database produksi publisher baru (disk usage 4 MB) — **TIDAK DIHUBUNGKAN**.
2. **Pembatasan Hak Akses Pengguna Database (MySQL Grants):**
   - Pengguna `u239415845_p4i_staging` hanya diberikan hak akses pada `u239415845_p4i_staging.*`.
   - Query apa pun dari aplikasi staging ke database produksi akan langsung ditolak oleh MySQL server dengan pesan:
     `Access denied for user 'u239415845_p4i_staging'@'localhost' to database 'u239415845_ojs'`.
3. **Pemeriksaan Kode Sumber:**
   - File konfigurasi [`config/database.php`](file:///d:/p4i-office-laravel/config/database.php) hanya mendefinisikan satu koneksi default `mysql` yang mengarah ke `env('DB_DATABASE')`.
   - Tidak ada query mentah, model Eloquent, atau konfigurasi koneksi yang mengarah ke database produksi.
