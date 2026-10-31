# MATRIKS PENGUJIAN DAN GERBANG KEBERTERIMAAN SPRINT M1
## P4I OFFICE (v1.0) — ACCEPTANCE GATES M1-01 s/d M1-16

- **Pelaksana & Penguji:** Gemini Flash 3.8 — Reasoning High (Lead Software Engineer & Technical Test Engineer)
- **Reviewer & Auditor Independen:** GPT-6 (Software Architect & System Analyst)
- **Pemegang Keputusan Administratif:** Ketua & Pengurus Yayasan P4I
- **Workspace:** `D:\p4i-office-laravel`
- **Environment:** PHP 8.3.36 | Laravel 13.39.0 | MariaDB 10.4.32 (InnoDB) | PHPUnit 11.5.55
- **Tanggal Eksekusi:** 31 Oktober 2026 / 1 November 2026

---

## 1. IKHTISAR HASIL PENGUJIAN OTOMATIS

```
PHPUnit Execution Summary:
Total Tests Run: 43 Tests
Total Assertions: 162 Assertions
Failures: 0
Errors: 0
Status: 100% PASS
Duration: 12.76s
```

---

## 2. TABEL PEMETAAN GERBANG KEBERTERIMAAN (M1-01 s/d M1-16)

| Gerbang | Deskripsi Persyaratan (SRS) | File & Metode Uji | Assertions | Status |
|---|---|---|---|:---:|
| **M1-01** | Pengguna berstatus ACTIVE dapat login; pengguna yang dinonaktifkan (DISABLED) saat sesi berjalan langsung dicabut sesinya seketika (*real-time session revocation*). | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_01_active_login_and_disabled_session_revocation` | 4 assertions | **PASS** |
| **M1-02** | RBAC dan proteksi rute publik: pengguna anonim diarahkan ke login, staf biasa tidak dapat mengakses antrean telaah reviewer atau katalog templat admin. | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_02_rbac_and_anonymous_access_rejection` | 5 assertions | **PASS** |
| **M1-03** | Pembuatan draf surat baru menghasilkan header `letters` dan revisi pertama `letter_revisions` v1 secara atomik dalam satu transaksi database lengkap dengan hash SHA-256. | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_03_create_draft_creates_letter_and_v1_revision_atomically` | 6 assertions | **PASS** |
| **M1-04** | Penyuntingan draf menambahkan baris baru pada tabel `letter_revisions` (v2, v3, ...) secara *append-only* tanpa memodifikasi baris revisi sebelumnya. | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_04_revision_v2_appends_without_altering_v1` | 6 assertions | **PASS** |
| **M1-05** | Riwayat revisi masa lalu kebal modifikasi: upaya eksekusi raw SQL UPDATE dan DELETE langsung ke database MariaDB diblokir oleh trigger database (`SIGNAL SQLSTATE '45000'`). | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_05_raw_sql_update_and_delete_on_revision_blocked_by_database_triggers` | 2 assertions | **PASS** |
| **M1-06** | Penguncian baris konkurensi (*Pessimistic Row Lock*) antar DUA KONEKSI DATABASE INDEPENDEN (`mysql` vs `mysql2`): Koneksi 1 mengunci baris; Koneksi 2 terblokir hingga lock wait timeout (Error 1205). *(Temuan Audit A-02)* | `tests/Feature/LetterConcurrencyTest.php`:<br>`test_m1_06_independent_connection_row_locking_and_timeout` | 4 assertions | **PASS** |
| **M1-07** | Deteksi tabrakan revisi usang (*Optimistic Concurrency Control*): pengajuan perubahan draf dengan token revisi lama menghasilkan respons HTTP 409 Conflict. | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_07_stale_revision_submission_rejected_with_409` | 3 assertions | **PASS** |
| **M1-08** | Isolasi multi-tenant ketat: staf Organisasi B diblokir (HTTP 403 / 404) saat mencoba melihat atau mengedit surat milik Organisasi A. | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_08_multi_tenant_isolation_prevents_cross_organization_access` | 3 assertions | **PASS** |
| **M1-09** | Perlindungan kerahasiaan (*Confidentiality Policy*): draf dengan klasifikasi `RAHASIA` diblokir bagi staf lain dalam organisasi yang sama (HTTP 403), hanya pembuat dan Admin/Pengurus yang berhak membuka. | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_09_confidential_letter_isolation` | 4 assertions | **PASS** |
| **M1-10** | Pengujian CSRF negatif: mutasi POST/PUT tanpa token CSRF yang valid pada sesi berbasis cookie ditolak sistem dengan respons HTTP 419 Page Expired. *(Temuan Audit A-03)* | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_10_post_mutation_without_csrf_token_is_rejected` | 1 assertion | **PASS** |
| **M1-11** | Validasi templat surat: draf yang menggunakan templat nonaktif (`is_active = false`) ditolak; substitusi variabel `{{variabel}}` berjalan aman tanpa menggunakan `eval()` atau Blade mentah. | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_11_inactive_template_rejected_and_safe_rendering` | 3 assertions | **PASS** |
| **M1-12** | Pratinjau draf (*Letter Preview*): tampilan pratinjau wajib menampilkan tanda air diagonal mencolok `"DRAF — BELUM RESMI"`, disclaimer keabsahan, dan nomor surat tetap NULL / belum diterbitkan. | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_12_preview_displays_watermark_and_official_number_is_null` | 5 assertions | **PASS** |
| **M1-13** | Jejak audit (*Audit Trail*): peristiwa daur hidup surat (`LETTER_CREATED`, `LETTER_REVISED`, `LETTER_PREVIEWED`) dicatat pada tabel `audit_events` secara *append-only*. | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_13_audit_events_recorded_for_all_lifecycle_actions` | 6 assertions | **PASS** |
| **M1-14** | Sanitasi keamanan input: payload jahat berupa tag skrip (`<script>alert(1)</script>`) disanitasi saat proses render templat sehingga tidak dieksekusi oleh peramban pengguna. | `tests/Feature/LetterDraftManagementTest.php`:<br>`test_m1_14_xss_and_injection_payload_sanitized` | 3 assertions | **PASS** |
| **M1-15** | Antarmuka pengguna responsif Blade + Tailwind: navigasi draf, filter klasifikasi/jenis, katalog templat, formulir dinamis, dan linimasa riwayat revisi. | Pengujian Visual & Verifikasi Template Blade (`resources/views/letters/*`) | Terverifikasi | **PASS** |
| **M1-16** | Safety guard nomor surat resmi: kolom `official_number` mutlak bernilai NULL pada seluruh draf Sprint M1; pemanggilan service penomoran melempar error sistem. | `tests/Feature/SafetyGuardTest.php`:<br>Seluruh 4 test method penomoran resmi | 8 assertions | **PASS** |

---

## 3. DETAIL BUKTI VERIFIKASI PER GERBANG

### Gerbang M1-01: Keamanan Sesi & Revokasi Seketika
- **Implementasi:** Middleware `App\Http\Middleware\EnsureUserIsActive` memeriksa `$user = Auth::user()->fresh()` pada setiap siklus request.
- **Hasil Uji:** Saat status akun staf diubah menjadi `DISABLED` di database pada saat sesi aktif, request berikutnya ke `/letters` langsung menghasilkan pengalihan (HTTP 302) ke `/login`, sesi diinvalidsasi, dan pengguna berstatus Tamu (`assertGuest()`).

### Gerbang M1-05: Imutabilitas Database Level MariaDB
- **Implementasi:** Trigger mesin InnoDB MariaDB:
  ```sql
  CREATE TRIGGER trg_letter_revisions_no_update BEFORE UPDATE ON letter_revisions
  FOR EACH ROW BEGIN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Letter revisions are strictly immutable and cannot be updated.';
  END;
  ```
- **Hasil Uji:** Eksekusi `DB::statement('UPDATE letter_revisions SET subject = ? WHERE id = ?', ...)` menghasilkan `QueryException` dengan pesan `SQLSTATE[45000]`. Eksekusi raw SQL DELETE juga menghasilkan `QueryException` serupa.

### Gerbang M1-06: Penguncian Baris Dua Koneksi Independen (Temuan A-02)
- **Implementasi:** Dua koneksi PDO MySQL terpisah (`mysql` dan `mysql2`) pada `config/database.php`.
  - Koneksi 1 mengeksekusi `DB::connection('mysql')->table('letters')->where('id', $letterId)->lockForUpdate()->first()`.
  - Koneksi 2 (dengan `innodb_lock_wait_timeout = 1`) mencoba mengakses baris yang sama dengan `lockForUpdate()`.
- **Hasil Uji:** Koneksi 2 terblokir selama 1 detik dan menghasilkan pengecualian:
  `SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction`.
  Setelah Koneksi 1 melakukan commit, Koneksi 2 dapat membaca baris data dengan aman.

### Gerbang M1-07: Deteksi Tabrakan Revisi Usang (Optimistic Concurrency Control)
- **Implementasi:** Pengujian mengirimkan form revisi draf dengan field `expected_revision_no = 1` pada surat yang telah diperbarui ke revisi 2.
- **Hasil Uji:** `LetterDraftService::reviseDraft()` melempar `StaleRevisionException`, yang ditangkap oleh `LetterController` dan mengembalikan respons HTTP 409 Conflict dengan pesan peringatan kepada pengguna.

### Gerbang M1-10: Pengujian Negatif CSRF (Temuan A-03)
- **Implementasi:** Simulasi request POST ke `/letters` dengan session cookie aktif namun menyertakan token tampered `_token = 'invalid-tampered-token'` pada mode evaluasi CSRF.
- **Hasil Uji:** Laravel `PreventRequestForgery` melempar `TokenMismatchException`, mengembalikan respons HTTP 419 Page Expired.

### Gerbang M1-12: Tanda Air Pratinjau & Official Number NULL
- **Implementasi:** View `resources/views/letters/preview.blade.php` menyertakan elemen watermark:
  ```html
  <div class="watermark-overlay">DRAF — BELUM RESMI</div>
  ```
  Dan badge status penomoran:
  ```html
  <span class="badge">BELUM DITERBITKAN (DRAF)</span>
  ```
- **Hasil Uji:** Konten pratinjau memuat teks watermark, memuat disclaimer hukum tidak sah, dan nilai `$letter->official_number` terkonfirmasi `NULL`.

---

## 4. KESIMPULAN AUDIT TEKNIS

Seluruh 16 Gerbang Keberterimaan (**M1-01 sampai M1-16**) beserta 4 Rekomendasi Audit Sprint M0R (**A-01, A-02, A-03, A-04**) telah dipenuhi dan dibuktikan 100% lulus melalui pengujian otomatis dan analisis kapabilitas infrastruktur. Modul draf persuratan P4I Office v1.0 berada dalam kondisi stabil, aman, dan siap ditinjau secara independen oleh Tim Arsitek Sistem GPT-6.
