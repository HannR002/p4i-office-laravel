# Hasil Pengujian Penerimaan Pengguna (UAT 15 Skenario — Sprint M5B-B)

**Proyek:** P4I Office v1.0  
**Sprint:** M5B-B (Controlled Hostinger Staging Verification & User Acceptance Testing)  
**Acuan Spesifikasi:** `P4I_Office_Addendum_SRS_Sprint_M5B_B_2026-11-01.docx` — Bagian G (`STB-13` & `STB-14`)  
**Implementer Teknis:** Argon — Reasoning Medium  
**Tim Perancangan & Auditor:** GPT-6  
**Otoritas Pelaksana UAT Manusia:** Staf & Pengurus Yayasan P4I yang Ditunjuk  
**Tanggal Laporan:** 2026-11-01  

---

## 1. Pernyataan Kejujuran Status UAT (`STB-13`)

> [!IMPORTANT]
> **PEMISAHAN TEGAS ANTARA PENGUJIAN OTOMATIS DAN UAT MANUSIA:**
> 1. **Simulasi End-to-End Otomatis (PHPUnit Feature Test):** **`PASS` (15/15 Skenario)** — Diverifikasi melalui [`StagingUatPreparationTest::test_stg_14_complete_15_scenario_uat_workflow_executes_end_to_end`](file:///d:/p4i-office-laravel/tests/Feature/StagingUatPreparationTest.php#L178-L370) menggunakan data sintetis berlabel uji.
> 2. **User Acceptance Test (UAT) Manual oleh Staf & Pengurus Yayasan P4I:** **`NOT RUN / BLOCKED`** — Belum dilaksanakan oleh pengguna manusia karena server staging Hostinger belum diaktifkan (menunggu izin pemilik proyek pada Gate `STB-02`). **Pengujian otomatis PHPUnit TIDAK menggantikan UAT manusia.**

---

## 2. Matriks 15 Skenario UAT (Otomatis Lokal vs Manual Pengguna P4I)

| Kode | Skenario UAT (Sesuai Bagian G Addendum SRS) | Peran Penguji | Bukti Pengujian Otomatis Lokal (`StagingUatPreparationTest`) | Status Otomatis (PHPUnit) | Status UAT Manual (Staf/Pengurus P4I) |
| :---: | :--- | :--- | :--- | :---: | :---: |
| **UAT-01** | **Login dan Logout Pengguna** | Staf (`staf.uat@p4i.or.id`) | `POST /login` -> redirect `/dashboard` (`assertAuthenticatedAs`); di akhir sesi `POST /logout` -> redirect `/login` (`assertGuest`). | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-02** | **Pembuatan Draf Surat & Pemilihan Templat** | Staf Konseptor | Memilih templat aktif `Templat Surat Keterangan UAT` (`KET-UAT`) dan mengisi variabel `nama_kegiatan` & `lokasi`. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-03** | **Penyimpanan Draf Awal (Versi 1 Immutable)** | Staf Konseptor | `POST /letters` menyimpan surat berstatus `DRAFT`, `current_revision_no = 1`, dan `official_number = NULL`. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-04** | **Revisi Draf Surat (Versi 2 Immutable)** | Staf Konseptor | `PUT /letters/{letter}` dengan `expected_revision_no = 1` membentuk Revisi v2 tanpa mengubah rekaman Revisi v1. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-05** | **Pengajuan Pemeriksaan Draf** | Staf Konseptor | `POST /letters/{letter}/submit` mengubah status surat dari `DRAFT` menjadi `SUBMITTED` dan membuat `ApprovalRequest`. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-06** | **Klaim & Telaah oleh Pejabat Bermandat** | Pejabat Pemeriksa (`reviewer.uat@p4i.or.id`) | `POST /approvals/{letter}/claim` memvalidasi mandat aktif (`SK-SIMULASI-UAT/P4I/2026/01`) dan mengubah status menjadi `IN_REVIEW`. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-07** | **Pengembalian untuk Revisi (`CHANGES_REQUESTED`)** | Pejabat Pemeriksa | `POST /approvals/{letter}/decision` dengan `decision = CHANGES_REQUESTED` beserta alasan mengembalikan status surat ke `CHANGES_REQUESTED`. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-08** | **Perbaikan Draf (Versi 3) & Pengajuan Ulang** | Staf Konseptor | `PUT /letters/{letter}` membentuk Revisi v3 (`current_revision_no = 3`) lalu `POST /letters/{letter}/submit` mengajukan ulang ke `SUBMITTED`. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-09** | **Persetujuan Substansi Draf (`APPROVED` Tanpa Nomor Resmi)** | Pejabat Pemeriksa | Klaim ulang & `POST /approvals/{letter}/decision` (`APPROVED`) dengan verifikasi `expected_version` & `expected_sha256`. Status menjadi `APPROVED` dan **`official_number` tetap `NULL`**. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-10** | **Ekspor dan Unduhan PDF & DOCX Berlabel `DRAF`** | Staf Konseptor | `POST /letters/{letter}/export` (`PDF` & `DOCX`) dan `GET /archives/{export}/download` mengembalikan HTTP `200` dengan nama berkas berawalan `DRAF_` dan watermark `DRAF`. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-11** | **Buku Agenda (Surat Keluar & Masuk) serta Pencarian Arsip** | Staf Konseptor | `GET /archives?search=Kesbangpol` menampilkan arsip; `GET /agenda/outgoing` menampilkan draf surat keluar; `POST /agenda/incoming` & `GET /agenda/incoming` mencatat dan menampilkan surat masuk beragenda internal. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-12** | **Penolakan Akses Dokumen `RAHASIA` bagi Pengguna Tak Berwenang** | Reviewer Tanpa Mandat (`unauth.uat@p4i.or.id`) | Akses `GET /letters/{secretLetter}` dan `GET /archives/{secretExport}/download` pada surat berklasifikasi `RAHASIA` ditolak dengan HTTP `403 Forbidden`. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-13** | **Pemeriksaan Audit Trail Aktivitas oleh Administrator** | Administrator (`admin.uat@p4i.or.id`) | `GET /archives/admin` menampilkan jejak audit immutable `DOCUMENT_EXPORTED` dan `DOCUMENT_DOWNLOADED` beserta status integritas arsip. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-14** | **Pengujian Backup dan Pemulihan Arsip Digital** | Administrator Sistem | Simulasi kehilangan berkas terdeteksi oleh `verifyArchiveIntegrity()` (`all_intact = false`, `missing_files = 1`), dan setelah pemulihan dari salinan backup kembali menjadi `all_intact = true` dengan kecocokan SHA-256. | `PASS` | `NOT RUN` (`BLOCKED`) |
| **UAT-15** | **Penolakan Penerbitan Nomor Surat Resmi (`STB-14`)** | Sistem / Layanan Penomoran | `config('features.official_numbering_active') === false`; pemanggilan `LetterNumberingService::allocateOfficialNumber()` ditolak keras dengan `OfficialNumberingBlockedException`. | `PASS` | `NOT RUN` (`BLOCKED`) |

---

## 3. Lembar Berita Acara & Tanda Tangan UAT Manual (Siap Digunakan Saat Staging Dibuka)

Lembar berikut disediakan bagi Staf dan Pengurus Yayasan P4I saat melaksanakan UAT manual di lingkungan staging:

| Peran UAT | Nama Pejabat / Staf P4I | Akun Uji Staging | Tanggal Pelaksanaan | Keputusan (`DITERIMA` / `REVISI`) | Tanda Tangan |
| :--- | :--- | :--- | :---: | :---: | :--- |
| **Staf Konseptor Persuratan** | *(Belum Dilaksanakan — Menunggu Staging)* | `staf.uat@p4i.or.id` | `-` | `NOT RUN` | |
| **Pejabat Pemeriksa Bermandat** | *(Belum Dilaksanakan — Menunggu Staging)* | `reviewer.uat@p4i.or.id` | `-` | `NOT RUN` | |
| **Administrator Sistem P4I** | *(Belum Dilaksanakan — Menunggu Staging)* | `admin.uat@p4i.or.id` | `-` | `NOT RUN` | |
| **Pengesah Administratif (Ketua/Pengurus P4I)** | *(Belum Dilaksanakan — Menunggu Staging)* | `-` | `-` | `NOT RUN` | |

