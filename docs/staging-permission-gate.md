# Gerbang Izin Hostinger (Hostinger Permission Gate — Sprint M5B-B)

**Proyek:** P4I Office v1.0  
**Sprint:** M5B-B (Controlled Hostinger Staging Verification & User Acceptance Testing)  
**Acuan Spesifikasi:** `P4I_Office_Addendum_SRS_Sprint_M5B_B_2026-11-01.docx` — Bagian E & Gate `STB-02`  
**Implementer Teknis:** Argon — Reasoning Medium  
**Tim Perancangan & Auditor:** GPT-6  
**Otoritas Pemberi Izin:** Ketua dan Pengurus Yayasan P4I  
**Tanggal Pemeriksaan:** 2026-11-01  

---

## 1. Keputusan Gerbang Otorisasi (Permission Gate Decision)

> [!IMPORTANT]
> **STATUS GERBANG OTORISASI:**  
> **`LOCAL PREPARATION COMPLETE — STAGING AUTHORIZATION PENDING`**

Sesuai mandat tegas pada **Bagian E (Gerbang Izin Hostinger — Wajib Sebelum Tahap P1)** dan **Bagian I (Aturan Keamanan Operasional)** dalam dokumen `P4I_Office_Addendum_SRS_Sprint_M5B_B_2026-11-01.docx`, implementer **dilarang membuat perubahan pada server Hostinger** sebelum seluruh 7 butir izin eksplisit dari pemilik proyek (Ketua/Pengurus Yayasan P4I) tersedia dan tercatat.

Hasil pemeriksaan menunjukkan bahwa **izin tertulis/eksplisit dari Ketua/Pengurus Yayasan P4I untuk mengeksekusi perubahan pada akun Hostinger belum diberikan**. Oleh karena itu, eksekusi teknis berhenti secara tertib pada **Tahap P0 (Persiapan Lokal & Verifikasi Otomatis Pra-Staging)** tanpa melakukan mutasi apa pun pada akun Hostinger.

---

## 2. Daftar Periksa 7 Izin Wajib Pemilik Proyek (Section E)

| No | Tindakan Staging pada Hostinger yang Memerlukan Izin Eksplisit | Status Izin Saat Ini | Bukti / Catatan Verifikasi | Tindakan Implementer |
| :-: | :--- | :---: | :--- | :--- |
| **1** | **Membuat atau mengubah subdomain staging** (mis. `staging-office.p4i.or.id`) | `BELUM DIBERIKAN` (`PENDING`) | Belum ada instruksi/persetujuan nama domain/subdomain staging dari Ketua/Pengurus Yayasan P4I. | Tidak memanggil alat MCP `hostinger-domains` / `hostinger-dns` / `hostinger-hosting` untuk membuat subdomain. |
| **2** | **Membuat atau mengubah database MariaDB staging** | `BELUM DIBERIKAN` (`PENDING`) | Kredensial, nama database, dan alokasi pengguna MariaDB staging di hPanel belum ditetapkan oleh pemilik proyek. | Tidak membuat database pada Hostinger; pengujian dilakukan pada MariaDB lokal (`10.4.32-MariaDB`). |
| **3** | **Mengunggah kode aplikasi ke server Hostinger** | `BELUM DIBERIKAN` (`PENDING`) | Jalur deployment dan izin unggah kode ke direktori akun Hostinger belum disahkan. | Kode tetap berada di repositori lokal `D:\p4i-office-laravel`; paket rilis disiapkan di Tahap P0. |
| **4** | **Menyimpan berkas `.env` staging pada server** | `BELUM DIBERIKAN` (`PENDING`) | Konfigurasi `.env` di server memerlukan kredensial database dan domain staging yang sah dari pemilik proyek. | Hanya menyiapkan templat aman `.env.staging.example` tanpa rahasia nyata. |
| **5** | **Menjalankan migrasi dan seeder pada database staging** | `BELUM DIBERIKAN` (`PENDING`) | Eksekusi `php artisan migrate --force` dan `DatabaseSeeder` pada server baru diizinkan setelah DB staging disiapkan. | Migrasi & seeder diverifikasi penuh pada MariaDB lokal. |
| **6** | **Menambahkan cron job scheduler pada Hostinger** | `BELUM DIBERIKAN` (`PENDING`) | Pendaftaran cron `* * * * * php artisan schedule:run` pada hPanel memerlukan otorisasi pemilik akun hosting. | Eksekusi `schedule:run` dan lock `withoutOverlapping()` diverifikasi melalui pengujian otomatis lokal. |
| **7** | **Menguji penyimpanan privat pada direktori server** | `BELUM DIBERIKAN` (`PENDING`) | Pembuatan direktori `storage/app/private/archives` pada filesystem Hostinger menunggu unggahan aplikasi diizinkan. | Isolasi direktori privat di luar `public/` dan blokir akses URL langsung diverifikasi pada lingkungan lokal. |

---

## 3. Bukti Kepatuhan Tanpa Akses Tanpa Izin (Zero Unauthorized Hostinger Mutation)

1. **Tidak Ada Panggilan Mutasi MCP Hostinger:** Selama Sprint M5B-B, implementer tidak mengeksekusi operasi pembuatan subdomain, perubahan DNS, pembuatan database, maupun unggah berkas ke server Hostinger.
2. **Pemeriksaan Otomatis pada `p4i:staging-preflight`:** Perintah [`StagingPreflightCommand`](file:///d:/p4i-office-laravel/app/Console/Commands/StagingPreflightCommand.php) memeriksa variabel lingkungan `HOSTINGER_STAGING_AUTHORIZED` (default `false`) dan melaporkan:
   - `"hostinger_authorization_gate": "PENDING"`
   - `"staging_authorization_status": "LOCAL_PREPARATION_COMPLETE_STAGING_AUTHORIZATION_PENDING"`
   - `"staging_deployment_status": "LOCAL_PREPARATION_COMPLETE_HOSTINGER_STAGING_BLOCKED"`

---

## 4. Prosedur Pembukaan Gerbang Menuju Tahap P1 (Unblocking Procedure)

Untuk melanjutkan dari Tahap P0 ke **Tahap P1 (Eksekusi Terkendali pada Server Hostinger Business Web Hosting)**, Ketua/Pengurus Yayasan P4I cukup memberikan konfirmasi tertulis atas 7 butir berikut:

1. **Subdomain Staging Resmi:** Konfirmasi nama subdomain (contoh: `staging-office.p4i.or.id`) dan penautan Document Root ke `.../p4i-office/public`.
2. **Database MariaDB Staging:** Penyediaan `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` khusus staging (terpisah dari website utama/produksi).
3. **Izin Unggah Kode & `.env`:** Persetujuan penempatan kode aplikasi pada direktori privat di luar `public_html` dan konfigurasi `.env` dengan `OFFICIAL_NUMBERING_ACTIVE=false`.
4. **Izin Eksekusi Migrasi & Seeder Sintetis:** Persetujuan menjalankan `php artisan migrate --force` (termasuk 4 trigger immutability) dan `StagingUatSeeder` berisi data sintetis berlabel `CONTOH / DATA UJI`.
5. **Izin Konfigurasi Cron Job hPanel:** Persetujuan mendaftarkan `* * * * * /usr/bin/php /home/<akun_hosting>/p4i-office/artisan schedule:run >> /dev/null 2>&1`.
6. **Penunjukan Peserta UAT Manusia:** Penetapan nama staf konseptor, pejabat pemeriksa bermandat, dan administrator Yayasan P4I yang akan menjalankan 15 skenario UAT manual pada server staging.

