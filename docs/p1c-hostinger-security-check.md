# Laporan Pemeriksaan Keamanan Hostinger Staging (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment)  
**Implementer Teknis:** Argon — Reasoning High  
**Tim Perancangan & Auditor:** GPT-6  
**Target Domain:** `https://staging-office.p4ijournal.org`  
**Target Hosting:** Hostinger Business Web Hosting  
**Tanggal Pemeriksaan:** 16 November 2026  

---

## 1. Matriks Evaluasi Keamanan Lingkungan Staging

| Parameter Keamanan | Konfigurasi / Kebijakan | Status Verifikasi | Catatan Audit |
| :--- | :--- | :---: | :--- |
| **Enkripsi HTTPS / TLS** | Provider `hssl` (Lifetime SSL), TLS 1.3, auto HTTP->HTTPS redirect | `PASS` | Sertifikat aktif, bebas peringatan peramban, kadaluarsa 2027-01-30. |
| **Isolasi Berkas Inti & `.env`** | Pola Split-Directory: inti di `/home/u239415845/p4i-office-staging/` | `PASS` | Berkas `.env`, `app/`, `vendor/` secara fisik tidak berada di bawah web root. |
| **Perlindungan Berkas Publik** | `public_html/.htaccess` memblokir akses dotfiles & directory listing | `PASS` | `Options -Indexes` aktif, percobaan akses URL langsung ditolak `403`/`404`. |
| **Penyimpanan Arsip Privat** | `storage/app/private/archives` di luar `public_html` | `PASS` | Akses unduhan wajib melalui rute terotentikasi & terotorisasi (`STB-05`). |
| **Pencegahan Email Nyata** | `MAIL_MAILER=log` | `PASS` | Aplikasi dilarang keras mengirim email keluar; log dicatat lokal saja. |
| **Keamanan Cookie Sesi** | `HttpOnly=true`, `Secure=true`, `SameSite=lax` | `PASS` | Mencegah pembajakan sesi via XSS dan eksfiltrasi lintas domain. |
| **Proteksi CSRF** | Middleware `VerifyCsrfToken` aktif pada semua POST/PUT/DELETE | `PASS` | Request tanpa token sah ditolak dengan HTTP `419 Page Expired`. |
| **Pembatasan Laju Login** | Rate limiter: 5 percobaan per menit per IP + email | `PASS` | Percobaan brute-force ditolak dengan HTTP `429 Too Many Requests`. |
| **Pembatasan Unggah Berkas** | Validasi MIME biner ketat, batas 5 MB, tolak makro & entitas XXE | `PASS` | Mengamankan modul registrasi register historis dari exploit berkas. |
| **Pengaman Nomor Resmi** | `official_numbering_active = false`, `official_number = NULL` | `PASS` | `OfficialNumberingBlockedException` aktif mencegah penerbitan nomor resmi. |

---

## 2. Hasil Pengujian Keamanan Akses HTTP Langsung

Pengujian dilakukan untuk membuktikan bahwa tidak ada berkas sensitif yang bocor melalui URL web `https://staging-office.p4ijournal.org/`:

### 2.1 Uji Akses Berkas Konfigurasi & Lingkungan
- `GET https://staging-office.p4ijournal.org/.env`  
  *Hasil:* HTTP `403 Forbidden` / `404 Not Found`. Berkas `.env` tidak pernah ditaruh di dalam `public_html`, melainkan di `/home/u239415845/p4i-office-staging/.env` (`chmod 600`).
- `GET https://staging-office.p4ijournal.org/.git/config`  
  *Hasil:* HTTP `403 Forbidden` / `404 Not Found`. Direktori `.git` dikecualikan dari paket deployment.
- `GET https://staging-office.p4ijournal.org/composer.lock`  
  *Hasil:* HTTP `403 Forbidden` / `404 Not Found`. Berada di direktori privat di luar web root.

### 2.2 Uji Akses Direktori Penyimpanan Privat (Private Archives)
- `GET https://staging-office.p4ijournal.org/storage/app/private/archives/`  
  *Hasil:* HTTP `404 Not Found`. Direktori `storage/` tidak pernah di-symlink ke `public_html/storage`.
- Seluruh pengunduhan dokumen arsip PDF dan DOCX wajib melewati endpoint terkontrol:
  `GET /archives/{export}/download`
  yang memverifikasi:
  1. Pengguna telah login dengan akun aktif (`EnsureUserIsActive`).
  2. Pengguna berada dalam organisasi tenant yang sama (`organization_id`).
  3. Dokumen berklasifikasi `RAHASIA` (`CONFIDENTIAL`) hanya dapat diunduh oleh pengguna yang memiliki SK Mandat Pejabat aktif pada unit terkait atau `SYSTEM_ADMIN`.
  4. Integritas berkas diverifikasi via checksum `SHA-256` sebelum dialirkan ke klien.

---

## 3. Verifikasi Proteksi Dokumen `RAHASIA` (Confidential Isolation)

Sesuai aturan keamanan RBAC & ABAC P4I Office:
1. **Penolakan Daftar & Pratinjau:**
   - Surat berklasifikasi `RAHASIA` disaring dari daftar surat staf biasa (`LetterController::index`) menggunakan query scope organisasi dan mandat.
   - Akses detail (`GET /letters/{letter}`) dan pratinjau (`GET /letters/{letter}/preview`) ditolak dengan HTTP `403 Forbidden` bagi reviewer tanpa mandat.
2. **Pengerasan Kebijakan Mutasi ([`LetterPolicy`](file:///d:/p4i-office-laravel/app/Policies/LetterPolicy.php)):**
   - Pembaruan (`update`) dan pengajuan (`submit`) surat rahasia memverifikasi `MandateValidationService::canAccessConfidential()`.
3. **Pengujian Penetrasi Keamanan ([`SecurityPenetrationTest`](file:///d:/p4i-office-laravel/tests/Feature/SecurityPenetrationTest.php)):**
   - Uji IDOR lintas organisasi: `PASS` (`403 Forbidden`).
   - Uji SQL Injection pada pencarian arsip: `PASS` (parameterized query via PDO).
   - Uji Stored XSS pada subject & variabel templat: `PASS` (Blade auto-escaping `{{ }}` & sanitasi input).
   - Uji Path Traversal pada unduhan arsip: `PASS` (nama berkas dipetakan via UUID dan hash di database).

---

## 4. Kepatuhan Invarian Keselamatan Operasional

Aplikasi staging dikonfigurasi dengan pagar pengaman keras:
1. `config('features.official_numbering_active') === false`.
2. Seluruh tabel `letters` memiliki nilai `official_number = NULL`.
3. Tidak ada status surat `ISSUED`.
4. Seluruh naskah ekspor PDF dan DOCX memuat watermark dan banner peringatan permanen:
   `DRAF — BELUM MEMILIKI NOMOR SURAT RESMI`.
