# Bukti Verifikasi Engine Ekspor Naskah PDF & DOCX (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment)  
**Implementer Teknis:** Argon — Reasoning High  
**Tim Perancangan & Auditor:** GPT-6  
**Target Hosting:** Hostinger Business Web Hosting (PHP 8.3)  
**Tanggal Evaluasi:** 16 November 2026  

---

## 1. Kompatibilitas Pure PHP pada Shared Hosting Hostinger

Pada paket Hostinger Business Web Hosting, fungsi CLI sistem seperti `exec`, `shell_exec`, `system`, dan `passthru` dinonaktifkan secara ketat pada konfigurasi PHP web (`disable_functions`). Oleh karena itu, modul generator naskah P4I Office v1.0 dibangun **100% menggunakan library PHP murni**:
- **Generator PDF:** `barryvdh/laravel-dompdf: ^3.1` (menggunakan engine DomPDF murni tanpa dependensi `wkhtmltopdf` atau headless Chrome).
- **Generator DOCX:** `phpoffice/phpword: ^1.3` (menggunakan generator OOXML dan `ZipArchive` bawaan PHP).
- **Ekstensi PHP Wajib di Server:** `gd`, `zip`, `xml`, `mbstring`, `fileinfo`, `dom` seluruhnya terverifikasi berstatus `enabled` pada runtime PHP 8.3 Hostinger.

---

## 2. Hasil Verifikasi Biner & Semantik Naskah Ekspor

Pengujian ekspor dilakukan terhadap dua sampel naskah uji (1 halaman dan 3 halaman) dengan hasil biner dan integritas kriptografis sebagai berikut:

| Parameter | Sampel 1 Halaman (Surat Keterangan) | Sampel 3 Halaman (Perjanjian Kerjasama Riset) |
| :--- | :--- | :--- |
| **Format PDF — Ukuran & Header** | `3.550 bytes`, `%PDF-1.7` ... `%%EOF` | `7.498 bytes`, `%PDF-1.7` ... `%%EOF` |
| **Digest SHA-256 PDF** | `ad4aa5b11ae10cbffc2ee64906a237eb3a84eb79cfd86411fe52f36f66347f89` | `49395d84d69352656960c91ba4183d2ba85e2b02a248f2fa44a49931b67f1396` |
| **Watermark PDF** | Diagonal `DRAF — BELUM MEMILIKI NOMOR SURAT RESMI` (Rotasi -45°, abu-abu lembut) | Watermark hadir pada setiap halaman (Halaman 1, 2, dan 3) |
| **Nomor Surat pada PDF** | `[BELUM BERNOMOR — DRAF]` | `[BELUM BERNOMOR — DRAF]` |
| **Penomoran Halaman PDF** | `Halaman 1 dari 1` | `Halaman 1 dari 3`, `Halaman 2 dari 3`, `Halaman 3 dari 3` |
| **Format DOCX — Validasi OOXML** | Arsip ZIP valid, XML valid, bebas makro (`.docx`) | Arsip ZIP valid, XML valid, bebas makro (`.docx`) |
| **Digest SHA-256 DOCX** | `d601b0f0a4ae8e8f85f341d3c0617ee9252329388339bb5c99451fe8cb82103f` | `b856a94f6f432724f8007a51804f5e7146d6166ef6324a3070be6cf26bf45495` |
| **Label Peringatan DOCX** | Header & banner `DRAF — BELUM MEMILIKI NOMOR SURAT RESMI` | Header & banner hadir di seluruh halaman tanpa pergeseran margin |

---

## 3. Bukti Verifikasi Visual PNG 150 DPI Tanpa Cacat Tata Letak

Naskah DOCX telah dikonversi secara presisi dan diperiksa secara visual pada resolusi 150 DPI:
- **Sampel 1 Halaman:** [`docs/evidence/sprint-m5b-a/render_docx_1page_p1.png`](file:///d:/p4i-office-laravel/docs/evidence/sprint-m5b-a/render_docx_1page_p1.png)
  - Margin 2,5 cm simetris, kop surat proporsional, teks justifikasi rapi, dan tidak ada elemen yang melompat ke halaman kedua.
- **Sampel 3 Halaman:**
  - Halaman 1: [`render_docx_3page_p1.png`](file:///d:/p4i-office-laravel/docs/evidence/sprint-m5b-a/render_docx_3page_p1.png) — Pembuka naskah dan identitas para pihak.
  - Halaman 2: [`render_docx_3page_p2.png`](file:///d:/p4i-office-laravel/docs/evidence/sprint-m5b-a/render_docx_3page_p2.png) — Pasal-pasal substansi kerjasama, penomoran ayat berurutan, batas tabel rapi.
  - Halaman 3: [`render_docx_3page_p3.png`](file:///d:/p4i-office-laravel/docs/evidence/sprint-m5b-a/render_docx_3page_p3.png) — Ketentuan penutup, kolom tanda tangan ganda tanpa tumpang tindih, dan zero trailing blank page.

---

## 4. Keamanan Penyimpanan & Otorisasi Unduhan

1. **Penyimpanan Terisolasi:**
   Seluruh berkas hasil ekspor disimpan pada disk privat `private_archives` dengan pola jalur:
   `storage/app/private/archives/<organization_id>/<year>/<format>/<uuid>.<ext>`
   dengan izin akses `0750` di luar Document Root web.
2. **Idempotensi & Transaksi Kriptografis:**
   - Permintaan ekspor berulang dengan kunci idempotensi yang sama (`idempotency_key`) mengembalikan rekaman `DocumentExport` yang sudah ada tanpa melakukan ekspor ganda (`test_m4b_09`).
   - Penulisan metadata status `READY` hanya terjadi setelah berkas sementara (`.tmp`) selesai ditulis dan diverifikasi kecocokan hash SHA-256 secara atomik di dalam `DB::transaction()`.
3. **Penyaringan Klasifikasi RAHASIA:**
   - Reviewer tanpa SK Mandat yang sah langsung ditolak saat mencoba mengunduh berkas (`HTTP 403 Forbidden`).
