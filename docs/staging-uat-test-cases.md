# SKENARIO & MATRIKS PENGUJIAN UAT (USER ACCEPTANCE TEST)
## SPRINT M5A — TAHAP 6: PERSIAPAN UAT STAF DAN PENGURUS YAYASAN P4I

**Sistem:** P4I Office v1.0  
**Penyusun Teknis:** Gemini (Lead Software Engineer & Technical Test Engineer)  
**Reviewer & Auditor:** GPT-6 (Software Architect & QA Auditor)  
**Peserta UAT Sasaran:** Staf Konseptor, Pejabat Pemeriksa Bermandat, Administrator, dan Pengurus Yayasan P4I  
**Tanggal:** 01 November 2026  

---

## 1. KETENTUAN PELAKSANAAN UAT & ISOLASI DATA SINTETIS

1. **Penggunaan Data Sintetis Wajib:**  
   Seluruh pengujian UAT wajib menggunakan akun pengguna sintetis dan naskah draf simulasi. Tidak diperkenankan memasukkan data pribadi sensitif atau menerbitkan surat keluar resmi selama masa UAT.
2. **Pemisahan Status Verifikasi:**  
   Untuk menjaga kejujuran pelaporan teknis, setiap skenario UAT memiliki dua kolom status:
   - **Status Verifikasi Otomatis Lokal (`StagingUatPreparationTest`):** Hasil eksekusi nyata pada lingkungan pengujian lokal MariaDB (`PASS`).
   - **Status Eksekusi UAT di Server Hostinger Staging:** Ditandai **`BLOCKED (Menunggu Izin Staging)`** / **`NOT RUN (Oleh Staf P4I di Staging)`** karena akses ke server Hostinger belum dibuka oleh pemilik proyek.

---

## 2. AKUN PENGGUNA SINTETIS UAT

| Peran Pengguna | Email Sintetis UAT | Unit Kerja Sintetis | Kewenangan Mandat Sintetis |
|---|---|---|---|
| **Staf Konseptor** | `staf.uat@p4i.or.id` | Divisi Riset dan Inovasi | Pembuat & pengaju draf surat |
| **Pemeriksa Bermandat** | `reviewer.uat@p4i.or.id` | Divisi Riset dan Inovasi | SK Simulasi `SK-SIMULASI-UAT/P4I/2026/01` (`ACTIVE`, `can_review=true`, `can_approve=true`) |
| **Reviewer Tanpa Mandat** | `unauth.uat@p4i.or.id` | Divisi SDM dan Umum | Memiliki role `REVIEWER`, namun **tanpa** SK Mandat aktif pada Divisi Riset |
| **Administrator Sistem** | `admin.uat@p4i.or.id` | Divisi Riset dan Inovasi | Pemantauan audit trail & verifikasi integritas arsip |

*(Catatan: Kata sandi akun sintetis hanya digunakan dalam ruang lingkup pengujian otomatis terisolasi dan tidak pernah menggunakan kredensial asli pengurus).*

---

## 3. DAFTAR 15 SKENARIO UAT WAJIB (UAT-01 S/D UAT-15)

| ID UAT | Skenario Pengujian | Aktor | Langkah Eksekusi & Rute HTTP | Hasil yang Diharapkan | Verifikasi Otomatis Lokal | Status di Hostinger Staging |
|---|---|---|---|---|---|---|
| **UAT-01** | **Staf Login** | Staf Konseptor | Buka `/login`, masukkan email & kata sandi sintetis (`POST /login`). | Sesi terotentikasi, diarahkan ke `/dashboard`, nama & peran tampil benar. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-02** | **Membuat Surat Baru dari Templat** | Staf Konseptor | Buka `/letters/create`, pilih jenis surat `KET-UAT`, templat aktif, isi variabel `nama_kegiatan` dan `lokasi`. | Formulir memvalidasi skema variabel templat secara dinamis. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-03** | **Menyimpan Draf** | Staf Konseptor | Kirim formulir (`POST /letters`). | Surat tersimpan dengan status `DRAFT`, revisi `v1` terbentuk dengan hash SHA-256, `official_number = NULL`. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-04** | **Merevisi Surat** | Staf Konseptor | Buka `/letters/{id}/edit`, ubah perihal/variabel dengan `expected_revision_no = 1` (`PUT /letters/{id}`). | Revisi baru `v2` terbentuk secara *append-only*, revisi `v1` tetap utuh (*immutable*). | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-05** | **Mengajukan Pemeriksaan** | Staf Konseptor | Klik tombol ajukan pemeriksaan (`POST /letters/{id}/submit`). | Status surat berubah menjadi `SUBMITTED`, permohonan `approval_requests` berstatus `PENDING` tercatat. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-06** | **Reviewer Bermandat Melakukan Review** | Pemeriksa Bermandat | Buka `/approvals`, pilih surat, klik klaim pemeriksaan (`POST /approvals/{id}/claim`). | Mandat aktif tervalidasi, status surat berubah menjadi `IN_REVIEW`. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-07** | **Reviewer Meminta Revisi** | Pemeriksa Bermandat | Pilih keputusan `CHANGES_REQUESTED` beserta alasan perbaikan (`POST /approvals/{id}/decision`). | Status surat berubah menjadi `CHANGES_REQUESTED`, alasan tercatat di riwayat langkah persetujuan. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-08** | **Staf Memperbaiki Surat** | Staf Konseptor | Simpan perbaikan draf sebagai `v3` (`PUT /letters/{id}`) lalu ajukan kembali (`POST /letters/{id}/submit`). | Revisi `v3` tersimpan dengan ringkasan perbaikan, status kembali `SUBMITTED`. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-09** | **Reviewer Menyetujui Isi Draf** | Pemeriksa Bermandat | Klaim ulang (`claim`) lalu berikan keputusan `APPROVED` (`POST /approvals/{id}/decision`). | Status draf menjadi `APPROVED`, **tetapi `official_number` tetap `NULL`** (belum menjadi surat resmi keluar). | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-10** | **Staf Mengunduh PDF/DOCX Berlabel DRAF** | Staf Konseptor | Ekspor ke PDF & DOCX (`POST /letters/{id}/export`), lalu unduh (`GET /archives/{export}/download`). | Berkas PDF dan DOCX terunduh dengan nama `DRAF_...`, memuat watermark `DRAF — BELUM RESMI` di setiap halaman. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-11** | **Pengguna Mencari Surat di Arsip** | Staf Konseptor | Buka `/archives?search=Kesbangpol&format=PDF`. | Daftar arsip menampilkan berkas yang cocok dalam lingkup organisasi pengguna. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-12** | **Pengguna Tidak Berwenang Ditolak Mengakses Surat RAHASIA** | Reviewer Tanpa Mandat | Coba akses `/letters/{secret_id}`, `/archives`, dan `/archives/{secret_export}/download` untuk surat berklasifikasi `RAHASIA`. | Surat rahasia tidak muncul di daftar `/archives` maupun `/letters`, dan akses langsung ditolak tegas dengan **HTTP 403 Forbidden**. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-13** | **Administrator Memeriksa Riwayat Audit** | Administrator Sistem | Buka `/archives/admin`. | Menampilkan metrik penyimpanan serta jejak audit `DOCUMENT_EXPORTED` dan `DOCUMENT_DOWNLOADED` yang *immutable*. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-14** | **Pengguna Menguji Pemulihan Arsip pada Lingkungan Pengujian** | Administrator Sistem | Simulasikan kehilangan berkas di disk pengujian, jalankan verifikasi integritas, pulihkan berkas dari cadangan, dan verifikasi ulang. | Sistem mendeteksi berkas hilang (`all_intact = false`), dan setelah pemulihan melaporkan `all_intact = true` (100% cocok SHA-256). | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |
| **UAT-15** | **Sistem Menolak Penerbitan Nomor Surat Resmi** | Sistem / Semua Peran | Coba panggil alokasi nomor surat resmi (`LetterNumberingService::allocateOfficialNumber()`). | Sistem melempar `OfficialNumberingBlockedException`, `official_numbering_active = false`, dan seluruh `letters.official_number = NULL`. | **PASS** | **BLOCKED** *(Menunggu Izin Staging)* |

---

## 4. CARA MENJALANKAN VERIFIKASI OTOMATIS 15 SKENARIO UAT

Seluruh 15 skenario UAT di atas telah dikodekan ke dalam pengujian otomatis end-to-end pada `tests/Feature/StagingUatPreparationTest.php` dan dapat dijalankan kapan saja melalui perintah:

```powershell
cd D:\p4i-office-laravel
& "C:\Users\Asus Gaming\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" artisan test --filter=StagingUatPreparationTest
```

Hasil eksekusi aktual:
- **Tests:** `2 passed (55 assertions)`
- **Exit Code:** `0`

