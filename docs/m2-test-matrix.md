# MATRIKS PENGUJIAN DAN GERBANG KEBERTERIMAAN SPRINT M2
## P4I OFFICE (v1.0) — ACCEPTANCE GATES AP2-01 s/d AP2-18

- **Pelaksana & Penguji:** Gemini Flash 3.8 — Reasoning High (Lead Software Engineer & Technical Test Engineer)
- **Reviewer & Auditor Independen:** GPT-6 (Software Architect & System Analyst)
- **Pemegang Keputusan Administratif:** Ketua & Pengurus Yayasan P4I
- **Workspace:** `D:\p4i-office-laravel`
- **Environment:** PHP 8.3.36 | Laravel 13.39.0 | MariaDB 10.4.32 (InnoDB) | PHPUnit 11.5.55
- **Tanggal Eksekusi:** 1 November 2026
- **Status Akhir:** **100% PASS (59 Tests, 228 Assertions, 0 Failures, 0 Errors)**

---

## 1. IKHTISAR HASIL PENGUJIAN OTOMATIS SUITE SPRINT M2

```
PHPUnit Automated Test Suite Execution Summary:
----------------------------------------------------------------------
Total Test Classes Executed : 10 Classes
Total Tests Run             : 59 Tests
Total Assertions Satisfied  : 228 Assertions
Failures                    : 0
Errors                      : 0
Execution Duration          : 17.82s
Suite Status                : 100% PASS
----------------------------------------------------------------------
```

---

## 2. TABEL PEMETAAN GERBANG KEBERTERIMAAN (AP2-01 s/d AP2-18)

| Gerbang | Deskripsi Persyaratan (SRS Sprint M2) | File & Metode Uji | Assertions | Status |
|---|---|---|---|:---:|
| **AP2-01** | Pengajuan draf surat terkini berhasil; permohonan persetujuan bersifat idempoten terhadap pengajuan berulang dengan kunci yang sama. | `LetterApprovalWorkflowTest`:<br>`test_ap2_01_submit_latest_revision_and_idempotency` | 5 assertions | **PASS** |
| **AP2-02** | Pengguna anonim, akun DISABLED, serta upaya akses lintas organisasi ditolak oleh otorisasi backend. | `LetterApprovalWorkflowTest`:<br>`test_ap2_02_anonymous_disabled_and_cross_organization_denied` | 4 assertions | **PASS** |
| **AP2-03** | Pengguna berstatus reviewer yang tidak memiliki catatan mandat aktif pada jenis surat terkait ditolak saat mencoba menelaah draf. | `LetterApprovalWorkflowTest`:<br>`test_ap2_03_reviewer_without_active_mandate_denied` | 1 assertion | **PASS** |
| **AP2-04** | Reviewer sah dengan mandat aktif dapat mengklaim (claim) dan menyetujui (approve) draf surat pada versi yang tepat. | `LetterApprovalWorkflowTest`:<br>`test_ap2_04_valid_reviewer_can_claim_and_approve_exact_revision` | 6 assertions | **PASS** |
| **AP2-05** | Upaya persetujuan menggunakan versi revisi usang atau hash konten yang tidak cocok ditolak sistem dengan respons 409 Conflict. | `LetterApprovalWorkflowTest`:<br>`test_ap2_05_stale_revision_or_hash_mismatch_rejected_with_409` | 2 assertions | **PASS** |
| **AP2-06** | Self-approval ditolak: pembuat draf dilarang keras memeriksa atau menyetujui pengajuannya sendiri, bahkan jika memiliki peran administrator. | `LetterApprovalWorkflowTest`:<br>`test_ap2_06_self_approval_rejected_even_for_admin` | 2 assertions | **PASS** |
| **AP2-07** | Mandat yang telah kadaluarsa (expired), dicabut (revoked), atau berasal dari organisasi lain ditolak oleh sistem. | `LetterApprovalWorkflowTest`:<br>`test_ap2_07_expired_revoked_and_foreign_mandates_denied` | 2 assertions | **PASS** |
| **AP2-08** | Permintaan perbaikan (changes requested) mewajibkan alasan tertulis dan membuka siklus revisi baru bagi pembuat draf. | `LetterApprovalWorkflowTest`:<br>`test_ap2_08_request_changes_requires_reason_and_unlocks_new_revision` | 6 assertions | **PASS** |
| **AP2-09** | Penolakan draf (reject) mewajibkan alasan tertulis dan mengunci draf secara permanen dari penyuntingan atau mutasi berikutnya. | `LetterApprovalWorkflowTest`:<br>`test_ap2_09_reject_requires_reason_and_locks_subsequent_mutation` | 4 assertions | **PASS** |
| **AP2-10** | Dua reviewer konkuren pada dua koneksi database independen (`mysql` vs `mysql2`) tidak dapat menghasilkan keputusan ganda yang saling bertentangan. | `ApprovalConcurrencyTest`:<br>`test_ap2_10_concurrent_reviewers_cannot_both_finalize` | 3 assertions | **PASS** |
| **AP2-11** | Retry request pengajuan atau keputusan persetujuan tidak menduplikasi record permohonan atau langkah persetujuan (*idempotency*). | `LetterApprovalWorkflowTest`:<br>`test_ap2_11_retries_do_not_double_submit_or_double_decide` | 3 assertions | **PASS** |
| **AP2-12** | Seluruh perubahan status dan keputusan terekam secara atomik pada `letter_status_history` dan `audit_events`. | `LetterApprovalWorkflowTest`:<br>`test_ap2_12_audit_trail_and_status_history_atomic` | 6 assertions | **PASS** |
| **AP2-13** | Dokumen klasifikasi RAHASIA terisolasi: tidak tampil di antrean reviewer biasa; hanya pembuat, pengurus, atau reviewer bermandat yang berhak mengakses. | `LetterApprovalWorkflowTest`:<br>`test_ap2_13_confidential_docs_and_reviewer_queue_isolated` | 4 assertions | **PASS** |
| **AP2-14** | Integrasi MariaDB nyata dan pembuktian rollback transaksi: kegagalan di tengah proses persetujuan membersihkan perubahan tanpa menyisakan baris yatim. | `ApprovalConcurrencyTest`:<br>`test_ap2_14_real_mariadb_rollback_preserves_state_consistency` | 7 assertions | **PASS** |
| **AP2-15** | Alur antarmuka diuji melalui peramban web Google Chrome Headless; tangkapan layar antrean, formulir keputusan, dan linimasa tersimpan bersih. | Verifikasi Visual Chrome Headless (`docs/screenshots/*.png`) | 7 File PNG | **PASS** |
| **AP2-16** | Pengujian CSRF negatif diverifikasi pada runtime yang mengevaluasi proteksi CSRF secara aktif; request ilegal menghasilkan respons HTTP 419 Page Expired. | `LetterApprovalWorkflowTest`:<br>`test_ap2_16_csrf_negative_test_verified` | 1 assertion | **PASS** |
| **AP2-17** | Seluruh rangkaian uji otomatis (artisan test), verifikasi gaya penulisan kode (Laravel Pint), dan kompilasi frontend (npm run build) berhasil 100%. | Suite Regresi, `pint --test`, `npm run build` | 59 Tests / Clean Lint | **PASS** |
| **AP2-18** | Safety guard nomor surat resmi: kolom `official_number` mutlak bernilai NULL setelah disetujui; fitur penomoran resmi tetap OFF tanpa tanda tangan final. | `SafetyGuardTest` & `LetterApprovalWorkflowTest::test_ap2_18_*` | 6 assertions | **PASS** |

---

## 3. RINCIAN BUKTI TEKNIS PER GERBANG KEBERTERIMAAN

### AP2-01: Submit Idempotent
- **Metode:** Mengirimkan parameter `idempotency_key` pada `ApprovalWorkflowService::submit()`.
- **Hasil:** Request pertama menghasilkan baris `approval_requests` baru berstatus `PENDING`. Request kedua dengan kunci yang sama mengembalikan rekaman awal yang sama tanpa penambahan baris (count tetap 1).

### AP2-04 & AP2-18: Persetujuan Sah dan Safety Guard
- **Metode:** Reviewer sah mengeksekusi `approve()` pada draf yang sedang `IN_REVIEW`.
- **Hasil:** Status surat berubah menjadi `APPROVED`. Baris `approval_steps` mengikat `reviewer_id`, `mandate_id`, `decision = 'APPROVED'`, `version = 1`, dan `content_sha256`. Kolom `letters.official_number` terkonfirmasi mutlak bernilai `NULL`. Halaman pratinjau tetap menampilkan tanda air *"DRAF — BELUM RESMI"* dan nomor surat *"BELUM DITERBITKAN (DRAF)"*.

### AP2-05: Penolakan Stale Revision (HTTP 409 Conflict)
- **Metode:** Pengurus mengajukan approval dengan parameter `expected_version` yang tidak cocok dengan versi terkini, atau `expected_sha256` yang tidak sesuai dengan hash integritas database.
- **Hasil:** Sistem melempar pengecualian `StaleRevisionException` yang mengembalikan kode status HTTP 409 Conflict.

### AP2-06: Penegakan Larangan Self-Approval
- **Metode:** Pengguna berstatus administrator membuat surat draf dan mencoba memeriksa surat pengajuannya sendiri.
- **Hasil:** Sistem melempar `AccessDeniedHttpException` dengan pesan *"Pembuat draf dilarang keras menelaah atau menyetujui pengajuannya sendiri (Self-approval ditolak)"*.

### AP2-08 & AP2-09: Request Changes dan Penolakan (Reject)
- **Metode:** Reviewer mengirimkan keputusan `CHANGES_REQUESTED` atau `REJECTED`.
- **Hasil:** Upaya mengirimkan form dengan alasan kosong ditolak dengan `ValidationException`. Pada `CHANGES_REQUESTED`, draf terbuka kembali untuk disunting staf ke revisi v2 (yang mereset status kembali ke `DRAFT`). Pada `REJECTED`, draf terkunci permanen dari segala upaya revisi baru.

### AP2-10: Penguncian Konkurensi Baris MariaDB (Dua Koneksi Independen)
- **Metode:** Koneksi PDO 1 (`mysql`) mengunci baris dengan `SELECT ... FOR UPDATE` dalam transaksi. Koneksi PDO 2 (`mysql2`) mencoba mengakses baris yang sama dengan `lockForUpdate()`.
- **Hasil:** Koneksi 2 terblokir selama batas waktu `innodb_lock_wait_timeout = 1` dan menghasilkan error MariaDB `1205 Lock wait timeout exceeded`. Setelah Koneksi 1 melakukan commit `APPROVED`, Koneksi 2 melihat status akhir yang konsisten dan terhindar dari *conflicting double-decision*.

### AP2-14: Pembuktian Rollback Transaksi MariaDB
- **Metode:** Simulasi kegagalan sistem (`RuntimeException`) di tengah-tengah transaksi persetujuan setelah update parsial dieksekusi.
- **Hasil:** Seluruh perubahan di-rollback otomatis oleh MariaDB InnoDB. Status surat tetap berada pada `IN_REVIEW`, dan tidak ada rekaman langkah persetujuan yatim pada tabel `approval_steps` (count = 0).

### AP2-15: Pengujian Peramban Nyata Google Chrome Headless
- **Metode:** Menjalankan `chrome.exe --headless=new` pada server lokal `http://127.0.0.1:8000`.
- **Hasil:** Tersimpan 7 berkas tangkapan layar beresolusi tinggi pada `docs/screenshots/`:
  1. `01_login_page.png`
  2. `02_staff_letter_draft_detail.png`
  3. `03_reviewer_approval_queue.png`
  4. `04_reviewer_inspection_panel.png`
  5. `05_approved_letter_banner.png`
  6. `06_mandates_list_simulation_badges.png`
  7. `07_watermarked_preview.png`

### AP2-16: Pengujian Negatif CSRF pada Runtime Proteksi Aktif
- **Metode:** Mengirimkan request mutasi HTTP POST `/letters/{id}/submit` dengan cookie sesi aktif namun token CSRF dimanipulasi (`invalid-token`) pada runtime yang mengevaluasi CSRF secara nyata (`PreventRequestForgery`).
- **Hasil:** Sistem melempar `TokenMismatchException` dan menghasilkan respons HTTP 419 Page Expired.
