# Laporan Pemeriksaan Keamanan Hostinger Staging (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment — One-Shot Execution)  
**Implementer Teknis:** Argon — Reasoning High  
**Tim Perancangan & Auditor:** GPT-6  
**Otoritas Administratif:** Ketua dan Pengurus Yayasan P4I  
**Target Domain:** `https://staging-office.p4ijournal.org`  
**Target Hosting:** Hostinger Business Web Hosting (Akun: `u239415845`)  
**Tanggal Pemeriksaan:** 16 November 2026  

---

## 1. Matriks Evaluasi Keamanan Lingkungan Staging

| Parameter Keamanan | Konfigurasi / Kebijakan | Status Verifikasi | Bukti Audit Aktual |
| :--- | :--- | :---: | :--- |
| **Enkripsi HTTPS / TLS** | Provider `hssl` (Lifetime SSL), TLS 1.3, auto HTTP->HTTPS redirect | `PASS` | HTTP 301 ke HTTPS, sertifikat valid, kadaluarsa 2027-01-30. |
| **Isolasi Berkas Inti & `.env`** | Pola Split-Directory: inti di `/home/u239415845/p4i-office-staging/` | `PASS` | Berkas `.env`, `app/`, `vendor/` secara fisik di luar web root. Permission `.env` = `600`. |
| **Perlindungan Berkas Publik** | `public_html/.htaccess` memblokir akses dotfiles & directory listing | `PASS` | `Options -Indexes` aktif, percobaan akses dotfile ditolak `403 Forbidden`. |
| **Penyimpanan Arsip Privat** | `storage/app/private/archives` di luar `public_html` | `PASS` | Unduhan langsung via URL publik menghasilkan `404 Not Found`. |
| **Pencegahan Email Nyata** | `MAIL_MAILER=log` | `PASS` | `config('mail.default') === 'log'`, seluruh transmisi email dialihkan ke file log. |
| **Keamanan Cookie Sesi** | `HttpOnly=true`, `Secure=true`, `SameSite=lax` | `PASS` | Cookie: `p4i_staging_session; expires=...; path=/; secure; httponly; samesite=lax`. |
| **Proteksi CSRF** | Middleware `VerifyCsrfToken` aktif | `PASS` | Request POST tanpa token ditolak dengan HTTP `419`. |
| **Pengaman Nomor Resmi** | `official_numbering_active = false`, `official_number = NULL` | `PASS` | 3 surat terdaftar memiliki `official_number = NULL`, 0 surat `ISSUED`. |
| **Trigger Immutability DB** | 4 trigger aktif di MariaDB | `PASS` | Mutasi `UPDATE`/`DELETE` ditolak dengan SQLSTATE `45000` / Error `1644`. |

---

## 2. Hasil Pengujian Keamanan Akses HTTP Langsung (Live Penetration Test)

Pengujian HTTP riil dijalankan menggunakan client cURL dari luar server terhadap `https://staging-office.p4ijournal.org/`:

### 2.1 Pengujian Eksposur File Sensitif
```bash
# 1. Uji akses berkas .env
curl -s -o /dev/null -w "%{http_code}" https://staging-office.p4ijournal.org/.env
# HASIL: 403 Forbidden (PASS - Akses Ditolak)

# 2. Uji akses direktori konfigurasi git
curl -s -o /dev/null -w "%{http_code}" https://staging-office.p4ijournal.org/.git/config
# HASIL: 403 Forbidden (PASS - Akses Ditolak)

# 3. Uji akses composer.json
curl -s -o /dev/null -w "%{http_code}" https://staging-office.p4ijournal.org/composer.json
# HASIL: 404 Not Found (PASS - Berkas Tidak Berada di Document Root)

# 4. Uji akses composer.lock
curl -s -o /dev/null -w "%{http_code}" https://staging-office.p4ijournal.org/composer.lock
# HASIL: 404 Not Found (PASS - Berkas Tidak Berada di Document Root)

# 5. Uji akses storage arsip privat
curl -s -o /dev/null -w "%{http_code}" -L https://staging-office.p4ijournal.org/storage/app/private/archives/
# HASIL: 404 Not Found (PASS - Direktori Terisolasi Penuh)
```

### 2.2 Uji Akses Rute Privat Terkontrol
Seluruh dokumen arsip PDF dan DOCX tersimpan di direktori privat:
`/home/u239415845/p4i-office-staging/storage/app/private_archives/{org_id}/{year}/{format}/{uuid}.{ext}`

Akses hanya dapat dilakukan melalui endpoint controller:
`GET /archives/{export}/download`
yang secara ketat memvalidasi:
1. Pengguna wajib terautentikasi dan berstatus `ACTIVE`.
2. Pengguna berada dalam satu tenant organisasi (`organization_id`).
3. Dokumen berklasifikasi `RAHASIA` / `CONFIDENTIAL` memvalidasi SK Mandat aktif pemohon.
4. Integritas berkas diverifikasi via checksum SHA-256 sebelum file dikirimkan melalui HTTP stream.

---

## 3. Isolasi Database & Sistem Produksi

1. Database staging `u239415845_p4i_staging` terikat pada pengguna database khusus yang tidak memiliki hak istimewa pada database produksi (`u239415845_ojs`, `u239415845_publisher`, `u239415845_publisher_new`).
2. Direktori domain produksi `/home/u239415845/domains/p4ijournal.org` tidak mengalami modifikasi berkas.
3. Cron job produksi `FtTkznhbHz` dan `lrRWdwWnOY` tetap berjalan tanpa modifikasi konfigurasi.
