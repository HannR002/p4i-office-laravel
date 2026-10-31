# LAPORAN PENGUJIAN CADANGAN & PEMULIHAN ARSIP DIGITAL (BACKUP & RESTORE)
## DIGITAL ARCHIVE BACKUP & RESTORE VERIFICATION REPORT

**Sistem:** P4I Office v1.0  
**Modul:** Digital Archive & Disaster Recovery  
**Sprint:** M4A (Gate M4A-16)  
**Implementer & Test Engineer:** Gemini Flash 3.8 — Reasoning High  
**QA Auditor & Software Architect:** GPT-6  
**Otoritas Administratif:** Ketua dan Dewan Pengurus Yayasan P4I  
**Tanggal Verifikasi:** 01 November 2026  
**Status Pengujian:** **`PASS (VERIFIED 100%)`**

---

## 1. TUJUAN PENGUJIAN

Pengujian ini bertujuan untuk memverifikasi secara empiris bahwa arsip naskah dinas digital P4I Office—yang terdiri atas dua komponen terikat (**metadata tabel basis data MariaDB** dan **berkas fisik biner pada disk privat**)—dapat dicadangkan (*backup*) dan dipulihkan (*restore*) secara utuh tanpa terjadi kerusakan data, korupsi biner, atau ketidakcocokan nilai checksum SHA-256.

---

## 2. ARSITEKTUR KETERIKATAN DATA (DUAL-LAYER ARCHIVE)

Arsip digital P4I Office dirancang dengan prinsip keterikatan dua lapis (*dual-layer coupling*):
1. **Lapis Logika (Metadata Database):** Menyimpan relasi surat, revisi, pembuat, waktu ekspor, klasifikasi, status, dan nilai hash kanonikal (`file_hash` SHA-256).
2. **Lapis Fisik (Private Filesystem):** Menyimpan berkas biner `.pdf` dan `.docx` pada direktori `storage/app/private_archives`.

> [!IMPORTANT]
> Pemulihan bencana dinyatakan berhasil hanya jika berkas fisik yang dipulihkan menghasilkan hash SHA-256 yang identik 100% dengan metadata yang tersimpan di basis data MariaDB.

---

## 3. SKENARIO DAN PROSEDUR PENGUJIAN (GATE M4A-16)

Pengujian dilakukan melalui rangkaian pengujian otomatis `PrivateArchiveSecurityTest::test_m4a_16_archive_backup_and_restore_simulation`:

### Langkah 1: Penetapan Baseline Sebelum Bencana
- Naskah dinas dibuat dan diekspor ke format DOCX.
- Berkas fisik berukuran 9.2 KB tersimpan di disk privat dengan hash SHA-256: `32df012d28746ac4441cab50179f9b0506ccf9705ee657f18f56291aab61f583`.
- Catatan metadata `DocumentExport` berstatus `READY`.

### Langkah 2: Prosedur Pencadangan (Backup)
- Metadata basis data dicadangkan melalui *dump snapshot*.
- Berkas fisik diekstraksi ke direktori cadangan aman (*backup repository*).

### Langkah 3: Simulasi Bencana Kehilangan Data (Disaster Event)
- Berkas fisik pada disk privat `storage/app/private_archives` dihapus secara total (`$disk->delete($export->file_path)`).
- Pengecekan keberadaan berkas: `$disk->exists($export->file_path) == false`.
- Pada kondisi ini, sistem menolak unduhan dengan HTTP 404 (berkas fisik hilang).

### Langkah 4: Prosedur Pemulihan (Restoration)
- Berkas biner cadangan disalin kembali ke lokasi path aslinya di disk privat `private_archives`.
- Direktori cadangan sementara dibersihkan.

### Langkah 5: Verifikasi Integritas Pasca-Pemulihan
- Perhitungan ulang hash biner berkas fisik:
  `$restoredHash = hash_file('sha256', $disk->path($export->file_path))`.
- Evaluasi kesamaan: `$restoredHash === $originalHash` (**Cocok 100%**).
- Eksekusi audit integritas massal `verifyArchiveIntegrity()`:
  - `total_files`: 1
  - `valid_files`: 1
  - `corrupted_files`: 0
  - `missing_files`: 0
  - `all_intact`: `true`

---

## 4. METRIK RECOVERY OBJECTIVE PADA HOSTINGER BUSINESS HOSTING

Sesuai laporan kapabilitas teknis hosting pada Sprint M3B dan M3C:

| Parameter DR | Target Yayasan P4I | Realisasi Hostinger Business Web Hosting | Catatan Evaluasi Teknis |
|---|---|---|---|
| **RPO (Recovery Point Objective)** | <= 24 Jam | **24 Jam** | Snapshot harian otomatis oleh hPanel Hostinger. |
| **RTO (Recovery Time Objective)** | <= 60 Menit | **15 - 30 Menit** | Waktu ekstraksi arsip ZIP dan impor database `.sql`. |
| **Point-in-Time Recovery (PITR)** | Diharapkan | **NOT SUPPORTED (TIDAK DIDUKUNG)** | *Binary logging* (binlog) dinonaktifkan pada shared hosting. Yayasan tidak dapat memulihkan data ke menit/detik spesifik. |

---

## 5. REKOMENDASI STANDAR OPERASIONAL PROSEDUR (SOP) PENGURUS

Berdasarkan hasil pengujian teknis ini, direkomendasikan kepada Dewan Pengurus Yayasan P4I:
1. **Pencadangan Ganda Mingguan:** Selain mengandalkan snapshot harian Hostinger, administrator yayasan disarankan mengunduh berkas dump database dan arsip ZIP direktori `storage/app/private_archives` setiap akhir pekan ke media penyimpanan luar (*cold storage*).
2. **Audit Integritas Berkala:** Menjalankan fitur *Audit Integritas SHA-256* pada menu `/archives/admin` secara berkala (minimal sebulan sekali) untuk memastikan tidak ada degradasi bit (*bit rot*) pada media penyimpanan hosting.
3. **Penyimpanan Terenkripsi:** Cadangan luar wajib dilindungi kata sandi kuat dan disimpan di lingkungan terenkripsi.

---

## 6. KESIMPULAN

Uji coba cadangan dan pemulihan arsip digital Sprint M4A dinyatakan **LULUS (PASS)**. Mekanisme verifikasi integritas hash SHA-256 terbukti mampu mendeteksi kehilangan maupun pemulihan berkas fisik secara presisi tanpa ada anomali metadata.
