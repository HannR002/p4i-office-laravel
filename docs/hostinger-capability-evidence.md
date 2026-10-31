# BUKTI KEMAMPUAN LINGKUNGAN HOSTINGER (HOSTINGER CAPABILITY EVIDENCE)
## P4I Office v1.0: Audit Arsitektur, Keterbatasan Shared Hosting, dan Batas Wewenang Staging

**Workspace:** `D:\p4i-office-laravel`  
**Implementer / Technical Test Engineer:** Gemini Flash 3.8 — Reasoning High  
**Auditor Teknis & Software Architect:** GPT-6  
**Otoritas Administratif:** Ketua dan Dewan Pengurus Yayasan P4I  
**Paket Hosting Sasaran:** Hostinger Business Web Hosting  
**Status Evaluasi Lingkungan:** **`BLOCKED / NOT VERIFIED (MENUNGGU IZIN & SERVER STAGING RESMI)`**

---

## 1. TUJUAN AUDIT LINGKUNGAN HOSTINGER

Dokumen ini menyajikan analisis arsitektur mendalam mengenai kesesuaian teknis aplikasi P4I Office v1.0 (Laravel 13 + PHP 8.3 + MariaDB InnoDB) terhadap batasan teknis paket **Hostinger Business Web Hosting**, sekaligus menegaskan batas wewenang pengembang bahwa pengujian lingkungan live tidak dapat diklaim telah lulus (*READY*) tanpa adanya pengujian langsung pada server staging resmi yang diizinkan oleh pemilik yayasan.

---

## 2. MATRIKS ANALISIS KEMAMPUAN & KETERBATASAN HOSTINGER

Berikut adalah evaluasi perbandingan antara kebutuhan aplikasi P4I Office dengan karakteristik arsitektur Hostinger Business Web Hosting:

| Parameter Lingkungan | Kebutuhan Sistem P4I Office v1.0 | Dukungan Hostinger Business Web Hosting | Analisis Dampak & Mitigasi Arsitektur | Status Kesiapan |
| :--- | :--- | :--- | :--- | :---: |
| **Versi PHP Runtime** | PHP 8.3 / PHP 8.4 | Mendukung PHP 8.1, 8.2, 8.3, 8.4 (dapat dipilih via hPanel) | Kompatibel penuh dengan sintaks dan dependensi Laravel 13. | **COMPATIBLE** |
| **Ekstensi PHP Wajib** | `pdo_mysql`, `zip`, `xml`, `mbstring`, `bcmath`, `fileinfo`, `sodium` | Ekstensi aktif secara default pada shared build PHP Hostinger | Parser XLSX native (`ZipArchive` & `SimpleXMLElement`) dapat berjalan tanpa instalasi manual. | **COMPATIBLE** |
| **Batas Memori PHP (Memory Limit)** | Minimal 128 MB (Rekomendasi: 256 MB) | Paket Business menyediakan alokasi RAM 1.5 GB dan `memory_limit` 256–512 MB | Mencukupi untuk pembacaan berkas spreadsheet hingga batas 5.000 baris. | **COMPATIBLE** |
| **Batas Waktu Eksekusi (Max Execution Time)** | 60–120 detik untuk proses impor massal | Default FastCGI: 60 detik; dapat disesuaikan hingga 120 detik via `.user.ini` | Impor data dipecah dalam ukuran batch wajar; upload dibatasi maksimal 5 MB. | **COMPATIBLE** |
| **Database Storage Engine** | MariaDB 10.4+ / MySQL 8.0 dengan InnoDB | MariaDB/MySQL InnoDB didukung penuh | Transaksi ACID, foreign key constraints, dan row-level locking (`lockForUpdate()`) berjalan normal. | **COMPATIBLE** |
| **Hak Istimewa Database (Privileges)** | `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `INDEX`, `ALTER` | Disediakan lengkap untuk database milik akun pengguna | Seluruh migrasi tabel dan indeks berjalan lancar. | **COMPATIBLE** |
| **Hak `CREATE TRIGGER` / `SUPER`** | Dibutuhkan jika menerapkan MariaDB Trigger untuk cegah raw SQL bypass | **DIBATASI / TIDAK DIIZINKAN** pada sebagian besar shared hosting Hostinger | **Mitigasi:** Arsitektur P4I Office memprioritaskan keamanan revisi immutable pada Eloquent Model Events dan Policies di layer aplikasi. | **MITIGATED** |
| **Binary Logging (Replikasi & PITR)** | Dibutuhkan untuk Continuous Point-in-Time Recovery | **TIDAK DIDUKUNG** (`log_bin = OFF`) | **Mitigasi:** Mengandalkan prosedur pencadangan harian snapshot SQL dump terjadwal (RPO 24 jam). | **NOT SUPPORTED** |
| **Struktur Direktori & Web Root** | Folder aplikasi di luar web root; `public/` sebagai web root | Hostinger menggunakan `public_html/` sebagai root HTTP | Struktur aplikasi ditempatkan di `/home/u123/p4i-office` dan `public/` di-symlink ke `public_html/`. Berkas `.env` aman di luar web root. | **COMPATIBLE** |
| **Penjadwal Tugas (Cron Jobs)** | Menjalankan scheduler harian/menit | hPanel menyediakan fitur Scheduled Tasks / Cron | Dapat memanggil `php /home/u123/p4i-office/artisan schedule:run >> /dev/null 2>&1` setiap menit. | **COMPATIBLE** |
| **Daemon Queue Worker** | Menjalankan antrean latar belakang berkelanjutan | Shared hosting **tidak mengizinkan proses daemon permanen** (supervisor) | Antrean dikonfigurasi menggunakan driver `sync` (sinkron) sehingga tidak membutuhkan worker daemon mandiri. | **COMPATIBLE** |
| **Pengujian Live di Server Staging Berizin** | Pengujian migrasi, upload, dan sesi nyata di Hostinger | **Belum tersedia server staging resmi yang diizinkan pemilik** | Sesuai aturan GPT-6: Dilarang mengklaim Hostinger READY tanpa uji live di server staging resmi. | **BLOCKED** |

---

## 3. STRUKTUR DEPLOYMENT AMAN PADA HOSTINGER

Untuk menjamin kerahasiaan berkas konfigurasi `.env`, arsip kearsipan, dan database cadangan pada server Hostinger:

```
/home/u123456789/
│
├── p4i-office/                       <-- Direktori Aplikasi (DI LUAR JANGKAUAN WEB BROWSER)
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── storage/
│   ├── vendor/
│   ├── .env                         <-- AMAN: Tidak dapat diakses via HTTP/HTTPS
│   └── artisan
│
├── backups/                         <-- Direktori Cadangan Database Snapshot
│   └── db_20261101.sql.gz           <-- Di luar jangkauan HTTP server
│
└── public_html/                     <-- Web Root Publik Hostinger
    ├── index.php                    <-- Memanggil ../p4i-office/public/index.php
    ├── build/                       <-- Berkas aset Vite terkompilasi
    ├── .htaccess                    <-- Proteksi rewrite Apache/LiteSpeed
    └── favicon.ico
```

---

## 4. PENYEBAB PEMBLOKIRAN PILAR 7 PADA READINESS REPORT

Dalam evaluasi `ReadinessService`, Pilar 7 (*HOSTINGER_ENVIRONMENT_CHECK*) secara tegas berstatus **`BLOCKED`** dengan pertimbangan:

1. **Prinsip Kejujuran Teknis:**  
   Pengujian fungsional dan konkurensi paralel selama ini dijalankan pada lingkungan lokal MariaDB 10.4.32. Meskipun arsitektur telah dirancang kompatibel dengan shared hosting, pengujian langsung terhadap lingkungan Hostinger nyata belum pernah dieksekusi.
2. **Ketiadaan Server Staging Resmi:**  
   Pemilik Yayasan P4I belum menyediakan akun atau subdomain staging resmi (misalnya `staging-office.p4i.or.id`) beserta kredensial akses yang sah untuk pengujian penetrasi dan uji beban.
3. **Kepatuhan Terhadap Batas Kewenangan:**  
   Gemini IDE dilarang melakukan deployment secara sepihak ke server produksi Hostinger tanpa audit tertulis dari **GPT-6** dan persetujuan tertulis dari **Ketua Yayasan P4I**.

---

## 5. KESIMPULAN & TAHAPAN SELANJUTNYA

Secara rancang bangun arsitektural (*by design*), P4I Office v1.0 telah 100% siap dijalankan pada batasan teknis Hostinger Business Web Hosting (menggunakan mitigasi aplikasi untuk trigger, driver sync untuk queue, dan snapshot dump untuk backup).

Namun demikian, status operasional pilar hosting **tetap dilaporkan BLOCKED** hingga:
- Dewan Pengurus Yayasan P4I menyediakan akses resmi ke server staging Hostinger.
- Dilakukan verifikasi empiris langsung atas proses migrasi skema, symlink document root, dan eksekusi cron harian di server hosting tersebut.
