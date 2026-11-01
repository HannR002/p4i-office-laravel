# Bukti Pengujian Backup dan Restore Database + Arsip Privat (Sprint M5B-B)

**Proyek:** P4I Office v1.0  
**Sprint:** M5B-B (Controlled Hostinger Staging Verification & User Acceptance Testing)  
**Acuan Spesifikasi:** `P4I_Office_Addendum_SRS_Sprint_M5B_B_2026-11-01.docx` — Bagian D.10, Bagian G (UAT-14), dan Gate `STB-12`  
**Implementer Teknis:** Argon — Reasoning Medium  
**Tim Perancangan & Auditor:** GPT-6  
**Tanggal Pengujian:** 2026-11-01  

---

## 1. Ringkasan Eksekutif Pengujian Backup & Restore (`STB-12`)

Sistem P4I Office v1.0 menyimpan state persuratan pada **dua komponen yang saling terikat secara kriptografis**:
1. **Database Relasional MariaDB (`InnoDB`):** Menyimpan tabel `letters`, `letter_revisions`, `document_exports` (termasuk kolom `file_path` dan `file_hash` SHA-256), serta `audit_events`.
2. **Penyimpanan Fisik Privat (`storage/app/private/archives`):** Menyimpan berkas fisik PDF dan DOCX di luar `public/`.

Pengujian pemulihan bencana (*Disaster Recovery / Backup & Restore Drill*) telah diverifikasi secara otomatis pada lingkungan lokal dan diukur waktu pemulihannya, sedangkan eksekusi pada server Hostinger dicatat sebagai **`BLOCKED`** menunggu otorisasi pemilik proyek (`STB-02`).

---

## 2. Prosedur Standar Backup & Restore Terpadu

### 2.1 Tahap Backup (Database + Arsip Privat + Manifest SHA-256)
1. **Dump Konsisten Database MariaDB (Termasuk Trigger Immutability):**
   ```bash
   mysqldump --single-transaction --triggers --routines --events \
     -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > backup_p4i_db.sql
   sha256sum backup_p4i_db.sql > backup_p4i_db.sql.sha256
   ```
2. **Backup Berkas Arsip Privat & Daftar Checksum SHA-256:**
   ```bash
   find storage/app/private/archives -type f -exec sha256sum {} + > backup_archives_manifest.sha256
   tar -czf backup_p4i_archives.tar.gz storage/app/private/archives backup_archives_manifest.sha256
   sha256sum backup_p4i_archives.tar.gz > backup_p4i_archives.tar.gz.sha256
   ```

### 2.2 Tahap Restore & Verifikasi Pasca-Pemulihan
1. **Verifikasi Checksum Paket Backup Sebelum Restore:**
   ```bash
   sha256sum -c backup_p4i_db.sql.sha256
   sha256sum -c backup_p4i_archives.tar.gz.sha256
   ```
2. **Pemulihan Database & Trigger:**
   ```bash
   mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < backup_p4i_db.sql
   ```
3. **Pemulihan Berkas Arsip Privat:**
   ```bash
   tar -xzf backup_p4i_archives.tar.gz -C /home/<akun_hosting>/p4i-office/
   ```
4. **Verifikasi Integritas Silang (Database vs Berkas Fisik):**
   - Menjalankan `php artisan p4i:staging-preflight --json` untuk memastikan ke-4 trigger immutability kembali aktif dan `safety_invariants_intact = true`.
   - Menjalankan pemeriksaan integritas arsip (`DocumentExportService::verifyArchiveIntegrity`) untuk memastikan **setiap baris `READY` pada tabel `document_exports` memiliki berkas fisik yang ada di disk dan hash SHA-256 berkas fisiknya persis sama dengan `document_exports.file_hash`**.

---

## 3. Hasil Simulasi & Bukti Teknis Lokal (`STB-12`)

Pengujian otomatis dilakukan pada dua suite pengujian:
1. [`SecureDocumentArchiveTest::test_m4b_12_archive_backup_and_restore_preserves_sha256_integrity`](file:///d:/p4i-office-laravel/tests/Feature/SecureDocumentArchiveTest.php)
2. [`StagingUatPreparationTest::test_stg_14_complete_15_scenario_uat_workflow_executes_end_to_end`](file:///d:/p4i-office-laravel/tests/Feature/StagingUatPreparationTest.php#L345-L360) (Skenario `UAT-14`)

| Langkah Uji Pemulihan | Kondisi / Tindakan | Hasil Verifikasi `verifyArchiveIntegrity()` | Status |
| :--- | :--- | :--- | :---: |
| **1. State Awal Sebelum Insiden** | Dokumen PDF dan DOCX diekspor ke `storage/app/private/archives/<org_id>/2026/PDF|DOCX/` dan disalin ke paket cadangan beserta manifest SHA-256. | `total_checked: 2`, `intact_files: 2`, `missing_files: 0`, `corrupted_files: 0`, `all_intact: true` | `PASS` |
| **2. Simulasi Kehilangan / Korupsi Berkas** | Berkas fisik PDF di `private_archives` dihapus secara paksa untuk menyimulasikan kegagalan media penyimpanan. | `all_intact: false`, `missing_files: 1` (Sistem langsung mendeteksi berkas hilang dan menolak unduhan berkas rusak/hilang). | `PASS` |
| **3. Eksekusi Restore dari Backup** | Berkas dipulihkan dari salinan backup ke jalur `storage/app/private/archives/...` dan dibandingkan ulang terhadap `document_exports.file_hash`. | `all_intact: true`, `missing_files: 0`, `corrupted_files: 0`; hash SHA-256 pasca-restore **100% identik** dengan hash sebelum insiden. | `PASS` |
| **4. Verifikasi Trigger & Invarian Pasca-Restore** | Pemeriksaan `p4i:staging-preflight` setelah pemulihan. | 4/4 trigger immutability aktif, `official_numbering_active: false`, `non_null_official_numbers: 0`. | `PASS` |

---

## 4. Metrik Pemulihan & Status Lingkungan

| Metrik / Parameter | Nilai Aktual pada Pengujian Lokal | Target Staging Hostinger | Status Verifikasi |
| :--- | :--- | :--- | :--- |
| **Kecocokan Hash SHA-256 Pasca-Restore** | `100%` (0 byte selisih pada PDF & DOCX) | `100%` | `PASS` (Lokal) |
| **Durasi Eksekusi Backup + Restore (RTO Uji)** | `< 2 detik` (Dataset Uji Sintetis) | `< 15 menit` | `PASS` (Lokal) |
| **Kehilangan Data Transaksi Terkomit (RPO Uji)** | `0 rekaman` (`--single-transaction` InnoDB) | `0 rekaman` pada snapshot terakhir | `PASS` (Lokal) |
| **Eksekusi Langsung pada Server Hostinger** | Menunggu otorisasi pemilik proyek (`STB-02`) | Wajib diuji saat Tahap P1 dibuka | `BLOCKED` |

