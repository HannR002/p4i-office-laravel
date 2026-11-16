# Bukti Teknis Konfigurasi Cron Scheduler & Disaster Recovery Drill (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment — One-Shot Execution)  
**Implementer Teknis:** Argon — Reasoning High  
**Tim Perancangan & Auditor:** GPT-6  
**Otoritas Administratif:** Ketua dan Pengurus Yayasan P4I  
**Target Hosting:** Hostinger Business Web Hosting (Akun: `u239415845`)  
**Tanggal Evaluasi:** 16 November 2026  

---

## 1. Verifikasi Jalur Eksekusi PHP CLI Hostinger

Pada server Hostinger Business Web Hosting, path binary PHP CLI untuk versi PHP 8.3 adalah:
```text
/opt/alt/php83/usr/bin/php
```
Jalur ini menjamin bahwa seluruh eksekusi perintah Artisan (`schedule:run`, `p4i:reconcile-exports`, `migrate`) berjalan di bawah runtime PHP 8.3 dengan ekstensi lengkap (`pdo_mysql`, `mbstring`, `xml`, dll.) tanpa terpengaruh oleh default system PHP CLI lama.

---

## 2. Pendaftaran Riil Cron Job Scheduler di Hostinger

### 2.1 Konfigurasi Cron Job Staging yang Terdaftar
Pendaftaran dilakukan melalui konektor API MCP Hostinger (`hosting_cron-jobs_create`):
- **UID:** `pRk1dnIGfn`
- **Username:** `u239415845`
- **Jadwal (Time):** `* * * * *` (Setiap menit)
- **Perintah (Command):**
  ```bash
  /opt/alt/php83/usr/bin/php /home/u239415845/p4i-office-staging/artisan schedule:run >> /home/u239415845/p4i-office-staging/storage/logs/scheduler.log 2>&1
  ```
- **Status:** `ACTIVE`

### 2.2 Bukti Daftar Cron Akun Hostinger (`hosting_cron-jobs_list`)
Pemeriksaan ulang daftar cron membuktikan 3 cron job aktif, di mana dua cron job produksi sebelumnya 100% utuh:
```json
[
  {
    "uid": "FtTkznhbHz",
    "username": "u239415845",
    "time": "* * * * *",
    "command": "/bin/sh /home/u239415845/scripts/p4i/run_queue.sh"
  },
  {
    "uid": "lrRWdwWnOY",
    "username": "u239415845",
    "time": "* * * * *",
    "command": "/bin/sh /home/u239415845/scripts/p4i/run_scheduler.sh"
  },
  {
    "uid": "pRk1dnIGfn",
    "username": "u239415845",
    "time": "* * * * *",
    "command": "/opt/alt/php83/usr/bin/php /home/u239415845/p4i-office-staging/artisan schedule:run >> /home/u239415845/p4i-office-staging/storage/logs/scheduler.log 2>&1"
  }
]
```

### 2.3 Uji Coba Manual Eksekusi Scheduler
Pengujian manual via SSH:
```bash
/opt/alt/php83/usr/bin/php /home/u239415845/p4i-office-staging/artisan schedule:run
```
Hasil:
```text
INFO  No scheduled commands are ready to run.
```
Exit code: `0` (Berhasil tanpa error).

---

## 3. Tugas Terjadwal yang Dikelola Scheduler (`routes/console.php`)

Di dalam kernel console Laravel 13, scheduler secara otomatis mengatur eksekusi perintah rekonsiliasi arsip:
```php
Schedule::command('p4i:reconcile-exports --stale-minutes=15')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();
```
- **Interval Eksekusi:** Menit ke-0, 15, 30, dan 45 (`*/15 * * * *`).
- **Proteksi Lock `withoutOverlapping(10)`:** Menggunakan cache mutex scheduler Laravel. Jika proses rekonsiliasi sebelumnya masih berjalan, eksekusi berikutnya dilewati secara aman hingga lock dilepas atau mencapai batas waktu 10 menit.
- **Pembersihan Crash Otomatis:** Mendeteksi rekaman `PENDING` yang tertinggal > 15 menit akibat crash worker / timeout, memeriksa hash SHA-256 berkas fisik, memulihkan pekerjaan utuh menjadi `READY`, serta menandai `ERROR` dan menghapus berkas parsial yang korup.

---

## 4. Kebijakan Sanitasi Logging

1. File log scheduler tersimpan di direktori privat: `/home/u239415845/p4i-office-staging/storage/logs/scheduler.log`.
2. Format logging daily: `/home/u239415845/p4i-office-staging/storage/logs/laravel-YYYY-MM-DD.log`.
3. Sanitasi rahasia: Tidak ada kata sandi, token, atau kunci privat SSH yang dicatat dalam log aplikasi.
