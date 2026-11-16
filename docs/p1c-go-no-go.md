# Matriks Evaluasi Keputusan Go/No-Go Deployment Staging (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment — One-Shot Execution)  
**Implementer Teknis (Lead Software Engineer & Technical Test Engineer):** Argon — Reasoning High  
**System Architect, SRS, QA Auditor & Laporan Resmi:** GPT-6  
**Otoritas Administratif & Pemilik Proyek:** Ketua dan Pengurus Yayasan P4I  
**Target Infrastruktur:** Hostinger Business Web Hosting  
**Website Staging:** `https://staging-office.p4ijournal.org`  
**Database Staging:** `u239415845_p4i_staging`  
**Tanggal Evaluasi:** 16 November 2026  

---

## 1. Keputusan Akhir Evaluasi (Executive Decision)

> [!IMPORTANT]
> **KEPUTUSAN EVALUASI RESMI TAHAP P1-C:**  
> **`STAGING DEPLOYED — TECHNICAL VERIFICATION PASSED — MANUAL UAT PENDING`**
>
> - **Kesiapan Teknis & Rilis Lokal:** **`GO / PASSED (100%)`** — 142 pengujian otomatis (`763 assertions`), Pint, dan Vite lulus 100%. Release tarball SHA-256 terverifikasi identik.
> - **Deployment Server Hostinger:** **`GO / COMPLETED & VERIFIED`** — Kunci SSH terautentikasi, split-directory terpasang, 86 paket composer terpasang, 9 migrasi database berjalan, 27 tabel aktif, 4 trigger MariaDB aktif dan memblokir mutasi raw SQL.
> - **Verifikasi Fungsional & Live HTTP:** **`GO / PASSED`** — Login staf UAT berhasil (`200 OK`), pratinjau surat berwatermark draf berhasil, ekspor dan unduhan PDF serta DOCX berhasil dengan SHA-256 identik dengan database.
> - **Cron Scheduler Staging:** **`GO / ACTIVE`** — Job `pRk1dnIGfn` terdaftar di Hostinger hPanel tanpa mengganggu 2 cron job produksi.
> - **Kesiapan Pengujian Pengguna (Manual UAT):** **`READY / PENDING MANUAL SIGN-OFF`** — Sistem siap diuji secara manual oleh Pengurus dan Staf Yayasan P4I di `https://staging-office.p4ijournal.org/login`.
> - **Penerbitan Nomor Surat Resmi:** **`STRICT NO-GO (DILARANG MUTLAK)`** — Tetap terkunci (`official_numbering_active = false`, `letters.official_number = NULL`).

---

## 2. Matriks Evaluasi Kepatuhan Keras (Hard Safety Invariants)

| Parameter Kepatuhan | Kriteria Syarat | Hasil Pemeriksaan Nyata | Status Evaluasi |
| :--- | :--- | :--- | :---: |
| **1. Nomor Surat Resmi** | Wajib `NULL` di seluruh tabel | Query `SELECT COUNT(official_number) FROM letters` = `0` | `PASS` |
| **2. Fitur Penomoran Resmi** | Wajib `false` pada konfigurasi runtime | `config('features.official_numbering_active') === false` | `PASS` |
| **3. Watermark Dokumen** | Hadir di pratinjau, PDF, dan DOCX | Terbukti memuat `DRAF — BELUM RESMI` di web, PDF stream, & XML DOCX | `PASS` |
| **4. Isolasi Berkas Privat** | `.env`, `.git`, `storage` privat tidak bocor | Akses HTTP ke `.env` & `.git` = `403`; storage privat = `404` | `PASS` |
| **5. Isolasi Sistem Produksi** | Zero impact ke website & database produksi | Website & database OJS/Publisher 100% tidak tersentuh | `PASS` |
| **6. Immutability Triggers** | 4 trigger MariaDB aktif & memblokir mutasi | Uji coba `UPDATE` & `DELETE` ditolak dengan SQLSTATE `45000` / Error `1644` | `PASS` |
| **7. Pencegahan Email Nyata** | Outbound mail dicegat di log server | `config('mail.default') === 'log'` | `PASS` |

---

## 3. Akun Uji Coba untuk Pelaksanaan Manual UAT

Pengurus dan Staf Yayasan P4I dapat langsung login ke `https://staging-office.p4ijournal.org/login` dengan akun sintetis:

| No | Peran / Pengguna | Email | Kata Sandi | Skenario Pengujian Utama |
| :-: | :--- | :--- | :--- | :--- |
| **1** | **Staf Konseptor (UAT)** | `staf.uat@p4i.or.id` | `UatPassword123!` | Pembuatan draf surat, revisi naskah, pratinjau watermark, pengajuan telaah. |
| **2** | **Pemeriksa Bermandat (UAT)** | `reviewer.uat@p4i.or.id` | `UatPassword123!` | Persetujuan draf (*Approve*), permintaan perubahan (*Request Changes*), ekspor naskah. |
| **3** | **Reviewer Tanpa Mandat (UAT)** | `unauth.uat@p4i.or.id` | `UatPassword123!` | Pengujian proteksi dokumen `RAHASIA` (Wajib ditolak aksesnya). |
| **4** | **Administrator Yayasan (UAT)** | `admin.uat@p4i.or.id` | `UatPassword123!` | Pengaturan katalog templat, audit log persuratan, simulasi format nomor. |

---

## 4. Rekomendasi Langkah Selanjutnya

1. **Kepada GPT-6 (Software Architect & QA Auditor):**
   - Meninjau berkas manifest audit `docs/p1c-final-verification.json` dan bukti komprehensif `docs/p1c-one-shot-evidence.md`.
   - Mengesahkan penerimaan teknis Tahap P1-C dan menyusun Berita Acara Pelaksanaan UAT.
2. **Kepada Ketua dan Pengurus Yayasan P4I:**
   - Melaksanakan pengujian langsung pada peramban melalui URL staging `https://staging-office.p4ijournal.org/login`.
   - Memberikan umpan balik atau persetujuan hasil UAT manual sebelum perencanaan aktivasi penomoran resmi di masa depan.
