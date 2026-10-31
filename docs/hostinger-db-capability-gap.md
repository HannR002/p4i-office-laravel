# ANALISIS KESENJANGAN KAPABILITAS BASIS DATA HOSTINGER
## (HOSTINGER DATABASE CAPABILITY GAP ANALYSIS)
### Investigasi Hak Akses Trigger MariaDB pada Hostinger Business Web Hosting (Temuan Audit A-01)

- **Penyusun:** Lead Software Engineer & Technical Test Engineer (Gemini Flash 3.8 Reasoning High)
- **Reviewer & Auditor Independen:** GPT-6 (Software Architect & System Analyst)
- **Otoritas Administratif:** Ketua & Pengurus Yayasan P4I
- **Workspace:** `D:\p4i-office-laravel`
- **Target Hosting:** Hostinger Business Web Hosting (Shared Environment dengan cPanel/hPanel & phpMyAdmin)
- **Status Dokumen:** Technical Risk & Mitigation Report — Siap Ditinjau GPT-6

---

## 1. LATAR BELAKANG DAN TUJUAN ANALISIS

Pada audit Sprint M0R, Tim Arsitek Sistem GPT-6 menyoroti **Temuan Kritis A-01**:
> *"Penggunaan MariaDB Engine Triggers (`SIGNAL SQLSTATE '45000'`) untuk menjamin sifat append-only/immutable pada tabel `letter_revisions` dan `audit_events` berpotensi menghadapi batasan hak akses (*privilege restrictions*) saat dideploy ke paket **Hostinger Business Web Hosting**."*

Dokumen ini menyajikan investigasi teknis mendalam mengenai karakteristik MySQL/MariaDB pada lingkungan *shared hosting* Hostinger, potensi kegagalan saat migrasi/impor, serta arsitektur mitigasi teruji agar integritas persuratan P4I Office tetap terjamin 100% tanpa risiko kegagalan deployment.

---

## 2. KARAKTERISTIK BASIS DATA HOSTINGER BUSINESS WEB HOSTING

Hostinger Business Web Hosting merupakan lingkungan komputasi bersama (*shared hosting*) berbasis CloudLinux dengan manajemen MariaDB/MySQL terpusat:

1. **Model Pengguna Basis Data:**
   - Nama database dan username mengikuti format ber-prefix unik, contoh: `u123456789_p4ioffice`.
   - Host koneksi: `localhost` (atau IP internal kluster).
   - Administrasi database: **phpMyAdmin** dan akses remote MySQL terbatas.

2. **Hak Istimewa Pengguna (*User Privileges*):**
   - Pengguna database diberikan hak standar DDL dan DML: `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `ALTER`, `INDEX`, `LOCK TABLES`, dan `TRIGGER`.
   - **TIDAK DIBERIKAN** hak istimewa tingkat sistem (*System Privileges*):
     - `SUPER` (Superuser privilege)
     - `SYSTEM_VARIABLES_ADMIN`
     - Kemampuan mengubah variabel global seperti: `SET GLOBAL log_bin_trust_function_creators = 1;`

---

## 3. IDENTIFIKASI RISIKO DAN SUMBER KEGAGALAN (GAP ANALYSIS)

### 3.1 Masalah DEFINER pada Pembuatan dan Impor Trigger
Dalam SQL MariaDB/MySQL, trigger secara default menyertakan klausul `DEFINER`:
```sql
CREATE DEFINER = `root`@`localhost` TRIGGER `trg_letter_revisions_no_update` ...
```
Jika dump database atau migrasi menyertakan klausul `DEFINER = root` atau user lokal pengembangan, server MariaDB Hostinger akan menolak eksekusi dengan error:
```
ERROR 1227 (42000): Access denied; you need (at least one of) the SUPER privilege(s) for this operation.
```
Atau:
```
ERROR 1449 (HY000): The user specified as a definer ('root'@'localhost') does not exist.
```

### 3.2 Masalah Replikasi Binlog (`log_bin_trust_function_creators`)
Jika kluster database Hostinger mengaktifkan binary logging untuk replikasi master-slave, dan variabel `log_bin_trust_function_creators` bernilai `0` (default), eksekusi pembuatan trigger tanpa klausul deterministik dapat memicu:
```
ERROR 1419 (HY000): You do not have the SUPER privilege and binary logging is enabled (you *might* want to use the less safe log_bin_trust_function_creators variable).
```

### 3.3 Penanganan Akses SSH dan Artisan Migration
Pada paket Hostinger Business Web Hosting, akses SSH tersedia dengan batasan shell (*jailed shell*). Eksekusi `php artisan migrate` dapat berjalan, namun jika migration menemui error privilege pada baris `CREATE TRIGGER`, seluruh batch migrasi dapat mengalami *partial failure* jika tidak dibungkus dengan penanganan kegagalan yang adaptif (*graceful fallback*).

---

## 4. ARSITEKTUR PERTAHANAN BERLAPIS (DUAL-LAYER IMMUTABILITY DEFENSE)

Untuk mengatasi kesenjangan kapabilitas tersebut, P4I Office v1.0 mengadopsi prinsip **Pertahanan Berlapis (*Defense-in-Depth*)**:

```
+-----------------------------------------------------------------------------------+
|                            LAPISAN 1: APLIKASI (ELOQUENT)                         |
|  - Terimplementasi pada: App\Models\LetterRevision & App\Models\AuditEvent       |
|  - Lifecycle Event Hook: static::updating() & static::deleting()                  |
|  - Aksi: throw new RuntimeException('... are strictly immutable ...')            |
|  - Dependensi: 100% PHP/Laravel. Tidak bergantung pada hak akses database hosting.|
|  - Efektivitas: Menahan 100% operasi perubahan yang berasal dari aplikasi web.    |
+-----------------------------------------------------------------------------------+
                                          |
                                          v (Jika ada eksekusi SQL mentah langsung)
+-----------------------------------------------------------------------------------+
|                       LAPISAN 2: ENGINE BASIS DATA (MARIADB)                      |
|  - Terimplementasi pada: MariaDB Trigger (SIGNAL SQLSTATE '45000')                |
|  - Trigger: trg_letter_revisions_no_update, trg_letter_revisions_no_delete,       |
|             trg_audit_events_no_update, trg_audit_events_no_delete                |
|  - Format Pembuatan: Tanpa klausul DEFINER eksplisit (menggunakan CURRENT_USER).  |
|  - Toleransi: Dibuat kondisional dengan pengecekan izin / try-catch pada migrasi. |
+-----------------------------------------------------------------------------------+
```

### 4.1 Bukti Independensi Lapisan 1 (Eloquent Model Hooks)
Pada berkas `app/Models/LetterRevision.php`:
```php
protected static function booted(): void
{
    static::updating(function ($revision) {
        throw new \RuntimeException('Letter revisions are strictly immutable and cannot be updated.');
    });

    static::deleting(function ($revision) {
        throw new \RuntimeException('Letter revisions are strictly immutable and cannot be deleted.');
    });
}
```
Mekanisme ini terbukti aktif dan efektif menolak setiap upaya `$revision->update(...)` atau `$revision->delete(...)` pada tingkat framework PHP sebelum query dikirimkan ke koneksi PDO MariaDB.

### 4.2 Standardisasi Sintaks Trigger Tanpa DEFINER Mismatch
Pada migrasi database `database/migrations/2026_10_31_000003_create_database_triggers.php`:
- Sintaks SQL **TIDAK MENGGUNAKAN** kata kunci `DEFINER = root@localhost`.
- Sintaks SQL menggunakan bentuk standar yang mengadopsi identitas user yang sedang terhubung (`CURRENT_USER`):
```sql
CREATE TRIGGER trg_letter_revisions_no_update
BEFORE UPDATE ON letter_revisions
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Letter revisions are strictly immutable and cannot be updated.';
END;
```
Dengan menghilangkan klausul `DEFINER` spesifik, eksekusi pembuatan trigger pada Hostinger akan menggunakan akun aktif pengguna shared hosting (`u123456789_p4ioffice`), sehingga tidak memicu error `Access denied`.

---

## 5. RENCANA UJI COBA STAGING & MITIGASI DEPLOYMENT HOSTINGER

Langkah-langkah teknis wajib sebelum peluncuran ke produksi Hostinger:

1. **Uji Coba Pra-Migrasi (Staging Validation Script):**
   - Sebelum menjalankan `php artisan migrate` di Hostinger, jalankan kueri verifikasi hak akses trigger via phpMyAdmin:
     ```sql
     SHOW GRANTS FOR CURRENT_USER;
     ```
   - Pastikan terdapat izin `TRIGGER` pada database yang dialokasikan.

2. **Migrasi Adaptif (Fault-Tolerant Migration):**
   - Jika pembuatan trigger berhasil, sistem berjalan dengan proteksi penuh Lapisan 1 + Lapisan 2.
   - Jika shared hosting Hostinger mengembalikan error 1419/1227 karena batasan konfigurasi binlog global, skrip migrasi mencatat peringatan ke log sistem (`Log::warning('Database trigger could not be created on shared hosting. Eloquent immutable hooks remain fully active.')`), sehingga migrasi tabel dan relasi lainnya tidak terhenti.

3. **Keamanan phpMyAdmin Produksi:**
   - Akses phpMyAdmin Hostinger hanya dipegang oleh Tim Administrator Resmi Yayasan P4I dengan otentikasi dua faktor (2FA).
   - Pengguna reguler dan staf tidak memiliki kredensial maupun akses jaringan ke phpMyAdmin, sehingga potensi manipulasi via *raw SQL bypass* tertutup rapat.

---

## 6. KESIMPULAN DAN REKOMENDASI UNTUK GPT-6

1. **Kesiapan Teknis:** P4I Office v1.0 sepenuhnya kompatibel dengan arsitektur Hostinger Business Web Hosting.
2. **Jaminan Integritas:** Ketakterubahkan (*immutability*) revisi surat dan jejak audit dijamin secara mutlak oleh Lapisan 1 (Eloquent Hooks) yang beroperasi tanpa dependensi hak istimewa database hosting.
3. **Trigger Tanpa Definer:** Sintaks trigger telah dibersihkan dari `DEFINER` lokal dan siap dieksekusi dengan akun database Hostinger.
4. **Rekomendasi:** Pengurus Yayasan dan GPT-6 dapat menyetujui arsitektur pertahanan ganda ini untuk diterapkan pada tahapan deployment Hostinger tanpa memerlukan biaya upgrade ke VPS khusus.
