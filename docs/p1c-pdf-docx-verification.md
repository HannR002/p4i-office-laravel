# Bukti Verifikasi Engine Ekspor Naskah PDF & DOCX di Server Staging (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment — One-Shot Execution)  
**Implementer Teknis:** Argon — Reasoning High  
**Tim Perancangan & Auditor:** GPT-6  
**Otoritas Administratif:** Ketua dan Pengurus Yayasan P4I  
**Target Hosting:** Hostinger Business Web Hosting (PHP 8.3.36 / LiteSpeed)  
**Website Staging:** `https://staging-office.p4ijournal.org`  
**Tanggal Evaluasi:** 16 November 2026  

---

## 1. Kompatibilitas Pure PHP pada Shared Hosting Hostinger

Pada paket Hostinger Business Web Hosting, fungsi CLI sistem seperti `exec`, `shell_exec`, `system`, dan `passthru` dinonaktifkan secara ketat pada konfigurasi PHP web (`disable_functions`). Oleh karena itu, modul generator naskah P4I Office v1.0 dibangun **100% menggunakan library PHP murni**:
- **Generator PDF:** `dompdf/dompdf: ^3.1` (menggunakan engine DomPDF murni tanpa dependensi `wkhtmltopdf` atau headless Chrome).
- **Generator DOCX:** `phpoffice/phpword: ^1.4` (menggunakan generator OOXML dan `ZipArchive` bawaan PHP).
- **Ekstensi PHP Wajib di Server:** `gd`, `zip`, `xml`, `mbstring`, `fileinfo`, `dom` seluruhnya terverifikasi aktif pada runtime PHP 8.3.36 Hostinger.

---

## 2. Hasil Ekspor & Unduhan Nyata pada Server Staging (Live Evidence)

Pengujian end-to-end dilakukan terhadap naskah surat magang (`01a1e3e0-35d8-72b4-bec9-0df1c54c69e7`) menggunakan sesi staf UAT terotentikasi (`staf.uat@p4i.or.id`):

| Parameter Uji | Ekspor PDF Staging | Ekspor DOCX Staging |
| :--- | :--- | :--- |
| **ID Rekaman Database** | `01a1e3e7-6bb2-70dc-8c0d-92fad3414b62` | `01a1e3e8-29c4-7155-aeef-1ed4d8fb35c9` |
| **Jalur Penyimpanan Privat** | `.../private_archives/.../2026/PDF/{uuid}.pdf` | `.../private_archives/.../2026/DOCX/{uuid}.docx` |
| **Status Pembuatan** | `READY` (HTTP 302 Redirect) | `READY` (HTTP 302 Redirect) |
| **Status Unduhan HTTP** | `HTTP/1.1 200 OK` | `HTTP/1.1 200 OK` |
| **Ukuran Berkas Riil** | `3.225 bytes` | `9.325 bytes` |
| **Magic Header Biner** | `%PDF-` | `50-4B-03-04` (PK ZIP / OOXML) |
| **Digest SHA-256 DB** | `92df438223aae532b8c665d0639eba80a87fbd4d7b38fe5f0ef00bd1fc19ce48` | `50767b2e11a8372e070784684b2c7e3f8fbd9ea73dc42eedf69d36aacfbb9881` |
| **Digest SHA-256 Unduhan** | `92df438223aae532b8c665d0639eba80a87fbd4d7b38fe5f0ef00bd1fc19ce48` | `50767b2e11a8372e070784684b2c7e3f8fbd9ea73dc42eedf69d36aacfbb9881` |
| **Status Kesesuaian Hash** | **IDENTIK 100% (MATCH)** | **IDENTIK 100% (MATCH)** |

---

## 3. Verifikasi Watermark & Teks Penyangkal (Disclaimer)

### 3.1 Verifikasi Stream PDF (Dekompresi Zlib Riil)
Analisis dekompresi zlib FlateDecode stream pada berkas PDF yang diunduh dari server staging:
- Ukuran stream terdekompresi: `3.900 byte`
- Teks `DRAF`: **Hadir (`True`)**
- Teks `BELUM RESMI`: **Hadir (`True`)**
- Teks `TIDAK BERLAKU`: **Hadir (`True`)**

### 3.2 Verifikasi OOXML DOCX (Inspeksi XML Arsip Riil)
Analisis biner arsip DOCX yang diunduh membuktikan integrasi watermark pada seluruh komponen dokumen:
- `word/document.xml`: Memuat tanda status draf dan disclaimer yayasan (*"belum diterbitkan secara resmi"*).
- `word/header1.xml`: Memuat tanda watermark `DRAF` dan `BELUM RESMI`.
- `word/footer1.xml`: Memuat tanda penomoran draf simulasi.

---

## 4. Keamanan Penyimpanan & Otorisasi Unduhan

1. **Penyimpanan Terisolasi:**  
   Seluruh berkas hasil ekspor disimpan pada disk privat `private_archives` di `/home/u239415845/p4i-office-staging/storage/app/private_archives/` dengan izin akses `0775` di luar Document Root web, bebas dari symlink publik. Percobaan akses langsung melalui URL menghasilkan HTTP `404 Not Found`.
2. **Idempotensi & Transaksi Kriptografis:**  
   Ekspor menggunakan kunci idempotensi unik (`idempotency_key`), mencegah duplikasi berkas fisik maupun duplikasi rekaman database. Checksum SHA-256 dihitung secara atomik sebelum status disetel ke `READY`.
3. **Penyaringan Klasifikasi RAHASIA:**  
   Pengunduhan memvalidasi kepemilikan tenant organisasi (`organization_id`), status akun aktif (`ACTIVE`), serta mandat pejabat bila surat berkategori `CONFIDENTIAL` / `RAHASIA`.
