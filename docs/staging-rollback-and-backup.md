# PROSEDUR PENCADANGAN (BACKUP), PEMULIHAN (RESTORE) & ROLLBACK STAGING
## SPRINT M5A — PAKET OPERASIONAL STAGING HOSTINGER BUSINESS WEB HOSTING

**Sistem:** P4I Office v1.0  
**Target Infrastruktur:** Hostinger Business Web Hosting  
**Penyusun Teknis:** Argon — Reasoning Medium (Lead Software Engineer)  
**Evaluator & Auditor:** GPT-6 (Software Architect & QA Auditor)  
**Otoritas Administratif:** Ketua dan Dewan Pengurus Yayasan P4I  
**Tanggal:** 01 November 2026  

---

## 1. PRINSIP KETERPADUAN CADANGAN (DATABASE + ARSIP PRIVAT)

Pada P4I Office, data persuratan terdiri dari dua komponen yang saling terikat secara kriptografis:
1. **Basis Data Relasional MariaDB (`15 Tabel InnoDB`):** Menyimpan naskah surat, riwayat revisi *immutable*, mandat, langkah persetujuan, log audit, serta metadata arsip `document_exports` (termasuk kolom `file_path` dan `file_hash` SHA-256).
2. **Penyimpanan Fisik Arsip Privat (`storage/app/private_archives/`):** Menyimpan berkas biner PDF dan DOCX hasil ekspor dalam struktur `{organization_id}/{year}/{format}/{export_id}.{ext}`.

Oleh karena itu, setiap pencadangan (*backup*) dan pemulihan (*restore*) **wajib menyertakan kedua komponen tersebut secara sinkron** dan diverifikasi menggunakan checksum SHA-256.

---

## 2. PROSEDUR PENCADANGAN (BACKUP) PRA-PERUBAHAN DI STAGING

Sebelum melakukan pembaruan kode atau migrasi pada lingkungan staging Hostinger (setelah izin akses diberikan), jalankan prosedur pencadangan berikut via SSH:

```bash
#!/usr/bin/env bash
set -euo pipefail

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_ROOT="$HOME/p4i_staging_backups/$TIMESTAMP"
CORE_DIR="$HOME/p4i_staging_core"

mkdir -p "$BACKUP_ROOT"

# 1. Aktifkan mode pemeliharaan sementara agar tidak ada transaksi paruh jalan
php "$CORE_DIR/artisan" down --retry=60

# 2.Rekonsiliasi ekspor yang menggantung sebelum snapshot diambil
php "$CORE_DIR/artisan" p4i:reconcile-exports --stale-minutes=15

# 3. Backup Database MariaDB Staging (termasuk triggers dan routines)
mysqldump --single-transaction --routines --triggers \
  -u "<DB_STAGING_USER_PLACEHOLDER>" -p"<DB_STAGING_PASSWORD_PLACEHOLDER>" \
  "<DB_STAGING_NAME_PLACEHOLDER>" > "$BACKUP_ROOT/db_staging_$TIMESTAMP.sql"

sha256sum "$BACKUP_ROOT/db_staging_$TIMESTAMP.sql" > "$BACKUP_ROOT/db_staging_$TIMESTAMP.sql.sha256"

# 4. Backup Direktori Arsip Privat (storage/app/private_archives)
tar -czf "$BACKUP_ROOT/private_archives_$TIMESTAMP.tar.gz" \
  -C "$CORE_DIR/storage/app" private_archives

sha256sum "$BACKUP_ROOT/private_archives_$TIMESTAMP.tar.gz" > "$BACKUP_ROOT/private_archives_$TIMESTAMP.tar.gz.sha256"

# 5. Catat Commit SHA Git saat ini untuk referensi Rollback Kode
git -C "$CORE_DIR" rev-parse HEAD > "$BACKUP_ROOT/git_commit_sha.txt"

# 6. Nonaktifkan mode pemeliharaan
php "$CORE_DIR/artisan" up
```

---

## 3. PROSEDUR PEMULIHAN TERINTEGRASI (RESTORE) & VERIFIKASI SHA-256

Apabila diperlukan pemulihan dari cadangan (*disaster recovery*):

```bash
#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="$HOME/p4i_staging_backups/<TIMESTAMP_TARGET>"
CORE_DIR="$HOME/p4i_staging_core"

# 1. Verifikasi keutuhan berkas cadangan menggunakan checksum SHA-256
cd "$BACKUP_DIR"
sha256sum -c db_staging_*.sql.sha256
sha256sum -c private_archives_*.tar.gz.sha256

# 2. Aktifkan mode pemeliharaan
php "$CORE_DIR/artisan" down --retry=60

# 3. Pulihkan Database MariaDB Staging
mysql -u "<DB_STAGING_USER_PLACEHOLDER>" -p"<DB_STAGING_PASSWORD_PLACEHOLDER>" \
  "<DB_STAGING_NAME_PLACEHOLDER>" < "$BACKUP_DIR"/db_staging_*.sql

# 4. Pulihkan Direktori Arsip Privat
rm -rf "$CORE_DIR/storage/app/private_archives"
mkdir -p "$CORE_DIR/storage/app"
tar -xzf "$BACKUP_DIR"/private_archives_*.tar.gz -C "$CORE_DIR/storage/app"
chmod -R 750 "$CORE_DIR/storage/app/private_archives"

# 5. Jalankan Verifikasi Preflight & Rekonsiliasi Integritas Arsip
php "$CORE_DIR/artisan" p4i:staging-preflight
php "$CORE_DIR/artisan" p4i:reconcile-exports --stale-minutes=15

# 6. Buka kembali layanan staging
php "$CORE_DIR/artisan" up
```

---

## 4. PROSEDUR ROLLBACK CEPAT (APABILA DEPLOYMENT STAGING GAGAL)

Apabila setelah pembaruan kode atau migrasi pada staging ditemukan kegagalan pada pemeriksaan `p4i:staging-preflight` atau skenario UAT:

1. **Rollback Kode Aplikasi & Vendor:**
   ```bash
   cd ~/p4i_staging_core
   php artisan down
   git checkout $(cat "$BACKUP_DIR/git_commit_sha.txt")
   composer install --no-dev --optimize-autoloader --no-interaction
   ```
2. **Rollback Skema Database (Jika Migrasi Baru Dijalankan):**
   - Jika migrasi bersifat aditif dan belum mengubah data, jalankan `php artisan migrate:rollback --step=1 --force`.
   - Jika perlu kembali ke titik data persis sebelum deployment, pulihkan dump SQL menggunakan Langkah 3 pada Prosedur Restore di atas.
3. **Pembersihan Cache & Verifikasi:**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan p4i:staging-preflight
   php artisan up
   ```

---

## 5. BUKTI PENGUJIAN PEMULIHAN LOKAL (GATE STG-13)

Prosedur pencadangan, deteksi korupsi/kehilangan berkas fisik, serta pemulihan database dan arsip privat telah diuji secara otomatis pada lingkungan lokal melalui:
1. **`tests/Feature/BackupRecoveryTest.php`:** Menguji pembuatan dump SQL, verifikasi checksum SHA-256, pemulihan database, serta metrik RPO/RTO (`3 passed`).
2. **`tests/Feature/IntegratedArchiveRestoreTest.php`:** Menguji siklus pencadangan dan pemulihan multi-berkas lintas organisasi beserta verifikasi SHA-256 (`4 passed`).
3. **`tests/Feature/StagingUatPreparationTest.php` (Skenario UAT-14):** Menguji simulasi kehilangan berkas arsip, deteksi `all_intact = false`, restorasi fisik, dan verifikasi kembali `all_intact = true`.

