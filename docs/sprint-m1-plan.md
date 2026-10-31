# RANCANGAN TEKNIS DAN RENCANA PELAKSANAAN SPRINT M1
## SISTEM PERSURATAN ELEKTRONIK P4I OFFICE (v1.0)
### Modul: Letter Draft Management & Immutable Revision

- **Penyusun / Implementer:** Lead Software Engineer & Technical Test Engineer (Gemini Flash 3.8 Reasoning High)
- **Arsitek & Auditor Independen:** Software Architect & System Analyst (GPT-6)
- **Otoritas Administratif & Kebijakan:** Ketua & Dewan Pengurus Yayasan P4I
- **Workspace:** `D:\p4i-office-laravel`
- **Target Deployment:** Hostinger Business Web Hosting
- **Status Dokumen:** Technical Implementation Plan — Siap Ditinjau GPT-6 & Disahkan Pengurus

---

## 1. TATA KELOLA DAN BATAS KEWENANGAN

1. **Struktur Peran Resmi:**
   - **GPT-6:** Bertindak sebagai Software Architect, System Analyst, penyusun dokumen SRS resmi, pengendali mutu/lingkup kerja, auditor independen, dan penyusun laporan resmi proyek.
   - **Gemini Flash 3.8:** Bertindak sebagai Lead Software Engineer dan Technical Test Engineer yang bertugas mengeksekusi coding, migrasi skema database, pengujian otomatis, resolusi bug, dan penyediaan bukti teknis terverifikasi.
   - **Ketua / Pengurus Yayasan P4I:** Memegang wewenang administratif tunggal dan mutlak atas penetapan SOP, penerbitan nomor surat resmi, struktur mandat pejabat penandatangan, dan validasi legalitas yayasan.

2. **Batasan Mutlak Sprint M1 (Safety Guard):**
   - **Alokasi Nomor Surat Resmi DILARANG KERAS:** Seluruh surat pada Sprint M1 berstatus `DRAFT` dengan atribut `official_number = NULL`.
   - **Fitur Penomoran Resmi Wajib OFF:** Flag konfigurasi `official_numbering_active = false`. Kebijakan penomoran pada tabel `numbering_policies` memiliki `is_active = 0`.
   - **Tanpa Tanda Tangan & Cap Digital:** Tidak diperbolehkan membuat tanda tangan simulasi, gambar stempel yayasan, atau nomor surat fiktif pada antarmuka maupun dokumen.
   - **Watermark Pratinjau Wajib:** Tampilan pratinjau wajib menyertakan watermark diagonal `"DRAF — BELUM RESMI"` dengan klausul penafian hukum (*legal disclaimer*).
   - **Batas Lingkup:** Implementasi berhenti tuntas pada Sprint M1. Tidak diperkenankan melanjutkan ke alur persetujuan (*approval workflow*) atau penandatanganan Sprint M2 secara sepihak tanpa instruksi resmi GPT-6.

---

## 2. RUANG LINGKUP FITUR SPRINT M1

Sprint M1 mencakup modul pembuatan, penyuntingan, dan pengelolaan draf surat dengan prinsip integritas data tak terubahkan (*immutable history*):

1. **Katalog Templat Dinamis (`letter_templates` & `TemplateEngineService`):**
   - Templat surat terstruktur per jenis surat (`letter_types`) dengan isolasi tenant yayasan.
   - Skema formulir dinamis berbasis JSON (`form_schema`) untuk menentukan variabel input.
   - Mesin substitusi placeholder aman: variabel `{{nama_variabel}}` diinterpolasi menggunakan sanitasi `htmlspecialchars()` tanpa menggunakan `eval()` atau parsing Blade mentah.
   - Hanya templat dengan status `is_active = true` yang dapat digunakan untuk pembuatan draf baru.

2. **Pembuatan Draf Baru (Create Draft — v1):**
   - Eksekusi atomik dalam transaksi database tunggal:
     - Header surat pada tabel `letters` (`status = DRAFT`, `current_revision_no = 1`, `official_number = NULL`).
     - Baris revisi pertama pada tabel `letter_revisions` (`version = 1`, payload JSON, SHA-256 hash konten terenkripsi).
     - Riwayat status pada tabel `letter_status_history` (`from_status = NULL`, `to_status = DRAFT`).
     - Jejak audit pada tabel `audit_events` (`event_type = LETTER_CREATED`).

3. **Penyuntingan Draf & Penambahan Riwayat Revisi (Append-Only Revisions — v2, v3, dst.):**
   - Revisi masa lalu bersifat mutlak tak terubahkan (*strictly immutable*).
   - Penyuntingan draf menambahkan baris baru pada tabel `letter_revisions` dengan `version = v + 1`.
   - Menggunakan mekanisme kontrol konkurensi ganda:
     - **Pessimistic Row Locking:** `lockForUpdate()` pada baris `letters` saat transaksi revisi berlangsung.
     - **Optimistic Concurrency Control:** Verifikasi token revisi dasar (`expected_revision_no`). Jika revisi di database telah berubah, sistem menolak penyimpanan dan menghasilkan respons HTTP 409 Conflict.
   - Pembaruan header surat (`letters`) dengan metadata revisi terbaru dan pencatatan audit `LETTER_REVISED`.

4. **Keamanan Imutabilitas Berlapis Dua (*Dual-Layer Immutability Defense*):**
   - **Lapisan 1 (Aplikasi / Eloquent Hooks):** Model `LetterRevision` dan `AuditEvent` memblokir operasi `updating` dan `deleting` pada siklus hidup Eloquent dengan melempar pengecualian `RuntimeException`.
   - **Lapisan 2 (Database Engine Triggers):** Empat trigger MariaDB InnoDB (`trg_letter_revisions_no_update`, `trg_letter_revisions_no_delete`, `trg_audit_events_no_update`, `trg_audit_events_no_delete`) memblokir manipulasi via raw SQL menggunakan sinyal error `SIGNAL SQLSTATE '45000'`.

5. **Pratinjau Draf Terproteksi (Watermarked Preview):**
   - Halaman pratinjau siap cetak dengan tanda air diagonal *"DRAF — BELUM RESMI"*.
   - Kotak informasi nomor surat menegaskan *"BELUM DITERBITKAN (DRAF)"*.
   - Pencatatan log audit `LETTER_PREVIEWED` setiap kali dokumen dibuka/dipratinjau.

6. **Keamanan Akses, Multi-Tenant, dan Kerahasiaan:**
   - **Multi-Tenant Isolation:** Surat hanya dapat dilihat dan dikelola oleh pengguna dalam `organization_id` yang sama.
   - **Confidentiality Policy:** Surat berklasifikasi `RAHASIA` hanya dapat dibuka oleh pembuat surat (*author*) dan administrator/pengurus yayasan (`SYSTEM_ADMIN`, `PENGURUS_YAYASAN`). Staf lain dalam organisasi yang sama diblokir (*HTTP 403 Forbidden*).
   - **Real-Time Session Revocation:** Middleware `active.user` memverifikasi status akun pengguna secara *fresh* dari database pada setiap request. Akun berstatus `DISABLED` langsung dicabut sesinya dan dialihkan ke login.
   - **Perlindungan CSRF & Sanitasi XSS:** Seluruh mutasi HTTP POST/PUT/PATCH dilindungi token CSRF (HTTP 419 pada request ilegal), dan payload pengguna disanitasi dari injeksi tag skrip berbahaya.

---

## 3. ARSITEKTUR TEKNOLOGI & SKEMA BASIS DATA

### 3.1 Stack Teknologi
- **Framework:** Laravel 13.39.0 (PHP 8.3.36)
- **Frontend Engine:** Laravel Blade + Livewire 4.4.8 + Tailwind CSS (Vite Build)
- **Database Engine:** MariaDB 10.4.32 (InnoDB Storage Engine dengan utf8mb4)
- **Autentikasi & Otorisasi:** Laravel Session-based Auth + Laravel Policies (`LetterPolicy`, `LetterTemplatePolicy`) + Middleware `EnsureUserIsActive`.

### 3.2 Relasi Entitas Inti Sprint M1
```
+-------------------+       +-----------------------+       +-------------------------+
|   organizations   |<----->|       org_units       |<----->|          users          |
+-------------------+       +-----------------------+       +-------------------------+
          ^                             ^                                ^
          |                             |                                |
          v                             v                                v
+-------------------+       +-----------------------+       +-------------------------+
|   letter_types    |<----->|   letter_templates    |       |       user_roles        |
+-------------------+       +-----------------------+       +-------------------------+
          ^                             ^                                ^
          |                             |                                |
          v                             v                                v
+-------------------------------------------------------------------------------------+
|                                       letters                                       |
| (id, org_id, type_id, author_id, subject, status=DRAFT, current_rev=1, num=NULL)    |
+-------------------------------------------------------------------------------------+
          |                                     |                                |
          v                                     v                                v
+-----------------------+       +-------------------------------+       +-------------+
|   letter_revisions    |       |     letter_status_history     |       | audit_events|
| (v1, v2, ..., SHA256) |       | (DRAFT -> DRAFT, timestamp)   |       | (APPEND-ONLY|
+-----------------------+       +-------------------------------+       +-------------+
```

---

## 4. RENCANA MATRIKS GERBANG KEBERTERIMAAN (M1-01 s/d M1-16)

| Kode | Kategori | Persyaratan Spesifikasi | Metode Pengujian | Target Hasil |
|---|---|---|---|---|
| **M1-01** | Keamanan Sesi | Login aktif sukses; akun DISABLED dicabut sesinya seketika (*real-time revocation*). | `LetterDraftManagementTest::test_m1_01_*` | Redirect ke `/login` & `assertGuest()` |
| **M1-02** | Otorisasi | Tamu & staf tanpa peran tidak dapat mengakses antrean pemeriksaan atau katalog admin. | `LetterDraftManagementTest::test_m1_02_*` | HTTP 302/403 Forbidden |
| **M1-03** | Persistensi Draf | Pembuatan draf baru menghasilkan baris `letters` dan `letter_revisions` v1 secara atomik. | `LetterDraftManagementTest::test_m1_03_*` | Baris v1 tercipta, hash SHA-256 valid |
| **M1-04** | Riwayat Revisi | Revisi draf menghasilkan baris v2 tanpa mengubah baris v1 (*append-only*). | `LetterDraftManagementTest::test_m1_04_*` | Count = 2, v1 utuh, current_rev = 2 |
| **M1-05** | Imutabilitas | Raw SQL UPDATE dan DELETE pada tabel `letter_revisions` diblokir oleh trigger MariaDB. | `LetterDraftManagementTest::test_m1_05_*` | Exception SQLSTATE 45000 |
| **M1-06** | Konkurensi Row | Uji penguncian baris (`lockForUpdate`) antar 2 koneksi PDO independen memicu timeout 1205. | `LetterConcurrencyTest::test_m1_06_*` | Lock wait timeout 1205 tertangkap |
| **M1-07** | Optimistic Lock| Pengajuan revisi dengan versi usang (*stale revision token*) ditolak dengan HTTP 409. | `LetterDraftManagementTest::test_m1_07_*` | HTTP 409 Conflict |
| **M1-08** | Multi-Tenancy | Pengguna dari Organisasi B tidak dapat membaca atau menyunting draf Organisasi A. | `LetterDraftManagementTest::test_m1_08_*` | HTTP 403 / 404 |
| **M1-09** | Kerahasiaan | Surat klasifikasi RAHASIA hanya dapat diakses oleh pembuat dan Admin/Pengurus. | `LetterDraftManagementTest::test_m1_09_*` | Staf lain HTTP 403; Pembuat/Admin 200 |
| **M1-10** | CSRF | Mutasi POST/PUT tanpa token CSRF yang valid pada session cookie ditolak. | `LetterDraftManagementTest::test_m1_10_*` | HTTP 419 Page Expired |
| **M1-11** | Validasi Templat| Pembuatan draf menggunakan templat nonaktif ditolak; substitusi variabel aman. | `LetterDraftManagementTest::test_m1_11_*` | ValidationException |
| **M1-12** | Pratinjau Draf | Pratinjau draf menampilkan watermark "DRAF — BELUM RESMI" dan `official_number` NULL. | `LetterDraftManagementTest::test_m1_12_*` | Watermark terpasang, nomor = NULL |
| **M1-13** | Jejak Audit | Seluruh daur hidup draf (`CREATED`, `REVISED`, `PREVIEWED`) tercatat pada `audit_events`. | `LetterDraftManagementTest::test_m1_13_*` | Peristiwa tercatat lengkap & kronologis |
| **M1-14** | Sanitasi XSS | Injeksi skrip (`<script>`) pada form variabel disanitasi saat proses rendering. | `LetterDraftManagementTest::test_m1_14_*` | HTML encoded (`&lt;script&gt;`) |
| **M1-15** | Antarmuka Blade| Antarmuka responsif Tailwind: navigasi draf, form variabel dinamis, riwayat timeline. | Verifikasi Visual & Template Blade | Komponen Blade terkompilasi Vite |
| **M1-16** | Safety Guard | Nomor surat resmi tetap OFF dan NULL; pemanggilan service penomoran melempar error. | `SafetyGuardTest::*` | Exception blocked; official_number = NULL |

---

## 5. TAHAPAN EKSEKUSI & AUDIT TRAIL

1. **Tahap 1:** Implementasi Service Layer (`TemplateEngineService`, `LetterDraftService`) dan Policy (`LetterPolicy`, `LetterTemplatePolicy`).
2. **Tahap 2:** Implementasi Controller & Route (`LetterController`, `TemplateController`, `routes/web.php`).
3. **Tahap 3:** Implementasi Antarmuka Pengguna Blade + Tailwind CSS (Daftar Draf, Form Pembuatan, Form Revisi, Detail Linimasa, Pratinjau Cetak Watermark).
4. **Tahap 4:** Pembuatan Automated Test Suite menyeluruh (43 pengujian, 162 assertions, 100% PASS).
5. **Tahap 5:** Verifikasi gaya kode Laravel Pint & kompilasi aset frontend Vite.
6. **Tahap 6:** Penyusunan laporan bukti teknis, matriks gate M1-01 s/d M1-16, dan analisis kapabilitas trigger database Hostinger.
7. **Tahap 7:** Penghentian progress secara tertib (*Clean Stop*) untuk evaluasi independen GPT-6 dan Pengurus Yayasan.
