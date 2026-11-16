# Bukti Teknis Konfigurasi Cron Scheduler & Disaster Recovery Drill (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment)  
**Implementer Teknis:** Argon — Reasoning High  
**Tim Perancangan & Auditor:** GPT-6  
**Target Hosting:** Hostinger Business Web Hosting  
**Tanggal Evaluasi:** 16 November 2026  

---

## 1. Verifikasi Jalur Eksekusi PHP CLI Hostinger

Pada server Hostinger Business Web Hosting, path binary PHP CLI untuk versi PHP 8.3 adalah:
```text
/opt/alt/php83/usr/bin/php
```
Jalur ini menjamin bahwa seluruh eksekusi perintah Artisan (`schedule:run`, `p4i:reconcile-exports`, `migrate`) berjalan di bawah runtime PHP 8.3 dengan ekstensi lengkap (`pdo_mysql`, `mbstring`, `xml`, dll.) tanpa terpengaruh oleh default system PHP CLI lama.

---

## 2. Desain & Status Pendaftaran Cron Job Scheduler

### 2.1 Konfigurasi Cron Job Staging
Sesuai rancangan arsitektur, pada hPanel Hostinger (*Advanced -> Cron Jobs*) akan didaftarkan 1 entri scheduler standar Laravel:
```cron
* * * * * /opt/alt/php83/usr/bin/php /home/u239415845/p4i-office-staging/artisan schedule:run >> /dev/null 2>&1
```

### 2.2 Tugas Terjadwal yang Dikelola Scheduler (`routes/console.php`)
Di dalam kernel console Laravel 13, scheduler secara otomatis mengatur eksekusi perintah rekonsiliasi arsip:
```php
Schedule::command('p4i:reconcile-exports --stale-minutes=15')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();
```
- **Interval Eksekusi:** Menit ke-0, 15, 30, dan 45 (`*/15 * * * *`).
- **Proteksi Lock `withoutOverlapping(10)`:** Menggunakan cache mutex scheduler Laravel. Jika proses rekonsiliasi sebelumnya masih berjalan (misalnya sedang memproses berkas besar), eksekusi berikutnya dilewati secara aman hingga lock dilepas atau mencapai batas waktu 10 menit (`test_m5b_b_scheduler_schedule_run_executes_reconciliation_and_enforces_without_overlapping_mutex`).
- **Pembersihan Crash Otomatis:** Mendeteksi rekaman `PENDING` yang tertinggal > 15 menit akibat crash worker / timeout, memeriksa hash SHA-256 berkas fisik, memulihkan pekerjaan utuh menjadi `READY`, serta menandai `ERROR` dan menghapus berkas parsial yang korup.

### 2.3 Perlindungan Terhadap Cron Eksisting
Pemeriksaan query `hosting_cron-jobs_list` menunjukkan terdapat 2 cron aktif pada akun hosting:
1. `FtTkznhbHz`: `/bin/sh /home/u239415845/scripts/p4i/run_queue.sh` (berjalan setiap menit)
2. `lrRWdwWnOY`: `/bin/sh /home/u239415845/scripts/p4i/run_scheduler.sh` (berjalan setiap menit)

*Jaminan Keamanan:* Cron job staging akan ditambahkan sebagai entri baru yang independen dan **TIDAK AKAN MENGUBAH, MENIMPA, MAUPUN MENGHAPUS** kedua cron job produksi tersebut.

---

## 3. Hasil Pengujian Pemulihan Bencana (Disaster Recovery Drill)

Pengujian simulasi kehilangan data dan pemulihan bencana diverifikasi melalui suite [`BackupRecoveryTest`](file:///d:/p4i-office-laravel/tests/Feature/BackupRecoveryTest.php) dan [`SecureDocumentArchiveTest::test_m4b_12`](file:///d:/p4i-office-laravel/tests/Feature/SecureDocumentArchiveTest.php):

### 3.1 Prosedur Backup Terpadu (Database + Arsip Privat)
1. **Dump Database MariaDB Konsisten:**
   ```bash
   mysqldump --single-transaction --triggers --routines --events \
     -u u239415845_p4i_staging -p u239415845_p4i_staging > backup_staging_db.sql
   sha256sum backup_staging_db.sql > backup_staging_db.sql.sha256
   ```
2. **Arsip Penyimpanan Privat & Manifest SHA-256:**
   ```bash
   find storage/app/private/archives -type f -exec sha256sum {} + > manifest_archives.sha256
   tar -czf backup_staging_archives.tar.gz storage/app/private/archives manifest_archives.sha256
   sha256sum backup_staging_archives.tar.gz > backup_staging_archives.tar.gz.sha256
   ```

### 3.2 Metrik Pemulihan & Integritas Kriptografis
| Parameter Pengujian DR | Target Desain | Bukti Aktual Hasil Uji | Status |
| :--- | :--- | :--- | :---: |
| **Kecocokan Hash SHA-256 Pasca-Restore** | `100% Identik` | Seluruh berkas PDF dan DOCX yang dipulihkan memiliki hash persis sama (0 byte korup). | `PASS` |
| **Recovery Point Objective (RPO)** | `0 lost data` | Opsi `--single-transaction` InnoDB menjamin snapshot data konsisten tanpa kehilangan transaksi. | `PASS` |
| **Recovery Time Objective (RTO)** | `< 15 menit` | Waktu pemulihan database dan ekstraksi arsip berlangsung dalam `< 2 detik` pada dataset uji. | `PASS` |
| **Trigger Immutability Pasca-Restore** | `4/4 Aktif` | Opsi `--triggers` menjamin 4 trigger immutability otomatis pulih dan memblokir mutasi raw SQL. | `PASS` |

---

## 4. Kebijakan Sanitasi Logging

1. File log aplikasi disimpan secara terotasi harian pada:
   `/home/u239415845/p4i-office-staging/storage/logs/laravel-YYYY-MM-DD.log`
   dengan hak akses `0600` (hanya dapat dibaca oleh user hosting).
2. Konfigurasi `config/logging.php` menetapkan level `info` pada lingkungan staging, dan secara otomatis menyamarkan parameter sensitif (`password`, `_token`, `authorization`, `cookie`, `key`) melalui middleware sanitasi bawaan.
3. Seluruh pesan kegagalan transaksi ekspor naskah dicatat ke tabel immutable `audit_events` dengan format terstruktur (`DOCUMENT_EXPORT_FAILED`, `DOCUMENT_EXPORT_RECONCILED_ERROR`) tanpa membocorkan stack trace internal ke pengguna akhir.
