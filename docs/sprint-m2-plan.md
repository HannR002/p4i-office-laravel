# RANCANGAN TEKNIS DAN RENCANA PELAKSANAAN SPRINT M2
## SISTEM PERSURATAN ELEKTRONIK P4I OFFICE (v1.0)
### Modul: Letter Approval Workflow & Authority Management

- **Penyusun / Implementer:** Lead Software Engineer & Technical Test Engineer (Gemini Flash 3.8 Reasoning High)
- **Arsitek & Auditor Independen:** Software Architect & System Analyst (GPT-6)
- **Otoritas Administratif & Kebijakan:** Ketua & Dewan Pengurus Yayasan P4I
- **Workspace:** `D:\p4i-office-laravel`
- **Branch Git:** `feat/sprint-m2-approval-workflow`
- **Baseline Commit M1:** `202ec3592319df88473eec1abeab31ff224820ef`
- **Target Deployment:** Hostinger Business Web Hosting
- **Status Dokumen:** Technical Implementation Plan — Diserahkan untuk Evaluasi GPT-6

---

## 1. TATA KELOLA DAN BATAS KEWENANGAN PROYEK

1. **Struktur Peran Resmi Proyek:**
   - **GPT-6:** Bertindak sebagai Software Architect, System Analyst, penyusun dokumen SRS resmi, pengendali mutu dan ruang lingkup, auditor independen, serta penulis seluruh laporan resmi proyek.
   - **Gemini Flash 3.8:** Bertindak sebagai Lead Software Engineer dan Technical Test Engineer yang bertanggung jawab atas penulisan kode sumber, migrasi skema database, pengujian otomatis, resolusi error, dan penyediaan bukti teknis terverifikasi.
   - **Ketua & Pengurus Yayasan P4I:** Pemegang wewenang administratif tunggal dan mutlak atas penetapan SOP persuratan, alokasi nomor surat resmi, pengesahan mandat penandatangan, dan validasi legalitas yayasan.

2. **Batasan Mutlak Sprint M2 (Safety Guard):**
   - **Alokasi Nomor Surat Resmi DILARANG KERAS:** Kolom `official_number` pada seluruh draf yang dibuat maupun disetujui bernilai mutlak `NULL`.
   - **Fitur Penomoran Wajib Nonaktif:** Flag konfigurasi `official_numbering_active = false`. Seluruh kebijakan pada `numbering_policies` berstatus `is_active = false`.
   - **Tanpa Tanda Tangan & Cap Digital:** Tidak diperbolehkan membuat tanda tangan simulasi, gambar stempel yayasan, atau nomor surat fiktif pada antarmuka maupun dokumen cetak.
   - **Status APPROVED Bukanlah ISSUED:** Keputusan `APPROVED` semata-mata mengikat substansi naskah draf secara internal. Status ini tidak memberikan nomor surat dan tidak menerbitkan naskah dinas resmi.
   - **Pemberhentian Tertib (*Clean Stop*):** Seluruh pekerjaan dihentikan tuntas pada batas modul persetujuan Sprint M2. Dilarang memulai alur penomoran definitif atau tanda tangan digital Sprint M3 secara sepihak.

---

## 2. AUDIT BASELINE DAN PENUTUPAN TEMUAN SPRINT M1

### 2.1 Hasil Audit Baseline
- **Commit Baseline M1:** `202ec3592319df88473eec1abeab31ff224820ef` (Branch: `feat/sprint-m1-letter-draft-management`).
- **Verifikasi Regresi Awal:** Menjalankan `php artisan test` pada baseline menghasilkan **43 Tests Passed (162 Assertions), 0 Errors, 0 Failures**.
- **Integritas Imutabilitas:** Trigger mesin MariaDB InnoDB (`SIGNAL SQLSTATE '45000'`) dan model hooks Eloquent terbukti aktif melindungi keutuhan riwayat draf v1, v2, dst.

### 2.2 Rencana Penutupan Temuan Audit M1 (A-M1-01 s/d A-M1-04)
1. **A-M1-01 — Pengujian CSRF pada Runtime Nyata:**
   - Melakukan pengujian mutasi HTTP POST dengan mengevaluasi middleware CSRF secara aktif (`$this->app['env'] = 'production'` dan verifikasi pada runtime server web) untuk membuktikan penolakan dengan respons HTTP 419 Page Expired.
2. **A-M1-02 — Kesiapan MariaDB Hostinger:**
   - Mempertahankan trigger mesin InnoDB pada lingkungan lokal dan menyediakan arsitektur pertahanan berlapis (*dual-layer defense*) pada aplikasi serta rencana mitigasi database staging.
3. **A-M1-03 — Pengujian Konkurensi Dua Koneksi Independen:**
   - Menguji penguncian baris (`lockForUpdate`), pencegahan keputusan ganda, transaksi rollback, dan idempotency pada dua koneksi database PDO independen (`mysql` dan `mysql2`).
4. **A-M1-04 — Verifikasi Browser Nyata (UI Screenshots):**
   - Menjalankan Google Chrome Headless pada antarmuka aplikasi lokal dan mengambil tangkapan layar beresolusi tinggi tanpa data sensitif untuk antrean reviewer, lembar keputusan, linimasa riwayat, dan katalog mandat.

---

## 3. ARSITEKTUR MESIN STATUS (STATE MACHINE) & ALUR PERSETUJUAN

### 3.1 Diagram Transisi Status Surat
```
                   +-------------------------------------------------+
                   |                                                 |
                   v                                                 |
            +-------------+       (Ajukan / Submit)           +---------------+
            |    DRAFT    | --------------------------------> |   SUBMITTED   |
            +-------------+                                   +---------------+
                   ^                                                  |
                   | (Revisi Baru                                     | (Klaim /
                   |  v+1)                                            |  Start Review)
                   |                                                  v
            +-------------------+     (Minta Perbaikan)       +---------------+
            | CHANGES_REQUESTED | <-------------------------- |   IN_REVIEW   |
            +-------------------+                             +---------------+
                                                                |           |
                                              (Setujui /        |           | (Tolak /
                                               Approve)         |           |  Reject)
                                                                v           v
                                                          +----------+ +----------+
                                                          | APPROVED | | REJECTED |
                                                          +----------+ +----------+
```

### 3.2 Aturan Validasi Transisi
1. **`DRAFT → SUBMITTED` (Submit Draf):**
   - Hanya dapat diajukan oleh pembuat draf (*author*) dalam organisasi yang sama.
   - Draf wajib memiliki revisi aktif dengan konten yang tidak kosong.
   - Templat surat wajib dalam kondisi aktif (`is_active = true`).
   - Idempoten: pengajuan ulang dengan `idempotency_key` yang sama mengembalikan permohonan persetujuan yang sudah ada tanpa duplikasi.
   - Mengunci draf dari penyuntingan langsung selama berada dalam proses pemeriksaan.
2. **`SUBMITTED → IN_REVIEW` (Mulai Pemeriksaan / Claim):**
   - Dilakukan oleh reviewer yang memiliki mandat aktif untuk jenis surat terkait.
   - Menggunakan `lockForUpdate()` pada baris `letters` dan `approval_requests` untuk mencegah perebutan klaim konkuren oleh dua reviewer berbeda.
   - Pembuat draf dilarang keras mengklaim atau menelaah pengajuannya sendiri (*Self-Review Prevention*).
3. **`IN_REVIEW → APPROVED` (Persetujuan Draf):**
   - Membutuhkan mandat dengan cakupan `can_approve = true`.
   - Mengikat secara absolut: `letter_id`, `revision_id`, `version`, `content_sha256`, `reviewer_id`, `mandate_id`, dan `workflow_version`.
   - Verifikasi versi dan hash integritas: jika draf telah berubah atau hash tidak sesuai, sistem menolak dengan HTTP 409 Conflict.
   - `official_number` tetap bernilai `NULL`.
4. **`IN_REVIEW → CHANGES_REQUESTED` (Permintaan Perbaikan):**
   - Reviewer wajib mencantumkan alasan / catatan perbaikan secara tertulis (non-empty reason).
   - Membuka kunci draf bagi staf pembuat untuk melakukan revisi v2, v3, dst.
5. **`CHANGES_REQUESTED → DRAFT` (Penyesuaian Draf Baru):**
   - Ketika staf pembuat menyimpan revisi baru, status surat otomatis kembali menjadi `DRAFT` dan siap diajukan ulang ke alur pemeriksaan.
6. **`IN_REVIEW → REJECTED` (Penolakan Definitif):**
   - Reviewer wajib mencantumkan alasan penolakan yang sah.
   - Mengunci draf secara permanen dari mutasi atau revisi lebih lanjut.

---

## 4. MODEL KEWENANGAN DAN VALIDASI MANDAT (AUTHORITY MANAGEMENT)

1. **Pemisahan Peran Teknis dan Mandat Statuter:**
   - Peran `SYSTEM_ADMIN` tidak otomatis berhak menyetujui surat dinas. Persetujuan surat mewajibkan adanya ketetapan mandat aktif pada tabel `signatory_mandates`.
2. **Pencegahan Persetujuan Sendiri (*Self-Approval Prevention*):**
   - Pengguna dilarang keras menyetujui pengajuannya sendiri, baik pengguna bertindak sebagai staf, reviewer, maupun pengurus.
3. **Validasi Mandat Berlapis (`MandateValidationService`):**
   - Status mandat aktif (`status = 'ACTIVE'`, `revoked_at IS NULL`).
   - Batas temporal berlaku: `valid_from <= today <= valid_to`.
   - Kesesuaian organisasi (`organization_id`).
   - Kesesuaian jenis surat (`letter_type_id IS NULL` atau sama dengan jenis surat).
   - Kesesuaian cakupan aksi (`can_review` atau `can_approve`).
4. **Pemberian Label Mandat Simulasi (M2-FR-17):**
   - Seluruh mandat pengujian lokal ditandai dengan flag `is_simulation = true` dan nomor ketetapan berawalan `[SIMULASI]` untuk mencegah kerancuan dengan penetapan hukum yayasan sejati.

---

## 5. RENCANA MATRIKS GERBANG KEBERTERIMAAN (AP2-01 s/d AP2-18)

| Gerbang | Persyaratan Spesifikasi | Metode & Target Pengujian |
|---|---|---|
| **AP2-01** | Submit revisi terbaru draf dan request bersifat idempotent | `LetterApprovalWorkflowTest::test_ap2_01_*` |
| **AP2-02** | Pengguna anonim, akun disabled, dan akses lintas organisasi ditolak | `LetterApprovalWorkflowTest::test_ap2_02_*` |
| **AP2-03** | Reviewer tanpa mandat aktif ditolak menelaah/menyetujui draf | `LetterApprovalWorkflowTest::test_ap2_03_*` |
| **AP2-04** | Reviewer sah dapat memeriksa dan menyetujui revisi draf yang tepat | `LetterApprovalWorkflowTest::test_ap2_04_*` |
| **AP2-05** | Penyetujuan dengan versi usang atau hash tidak cocok ditolak (409) | `LetterApprovalWorkflowTest::test_ap2_05_*` |
| **AP2-06** | Self-approval ditolak (pembuat draf dilarang menyetujui pengajuannya) | `LetterApprovalWorkflowTest::test_ap2_06_*` |
| **AP2-07** | Mandat expired, revoked, atau berbeda organisasi ditolak | `LetterApprovalWorkflowTest::test_ap2_07_*` |
| **AP2-08** | Request changes memerlukan alasan dan membuka siklus revisi baru | `LetterApprovalWorkflowTest::test_ap2_08_*` |
| **AP2-09** | Reject memerlukan alasan dan mengunci draf dari mutasi | `LetterApprovalWorkflowTest::test_ap2_09_*` |
| **AP2-10** | Dua reviewer konkuren tidak dapat menghasilkan keputusan ganda | `ApprovalConcurrencyTest::test_ap2_10_*` |
| **AP2-11** | Retry pengajuan / keputusan tidak menduplikasi record | `LetterApprovalWorkflowTest::test_ap2_11_*` |
| **AP2-12** | Jejak audit dan status history tercatat atomik pada tiap transisi | `LetterApprovalWorkflowTest::test_ap2_12_*` |
| **AP2-13** | Dokumen rahasia dan antrean reviewer terisolasi | `LetterApprovalWorkflowTest::test_ap2_13_*` |
| **AP2-14** | Integrasi MariaDB nyata dan pembuktian rollback transaksi | `ApprovalConcurrencyTest::test_ap2_14_*` |
| **AP2-15** | Workflow diuji via browser sebenarnya dan tangkapan layar tersimpan | Verifikasi Headless Chrome UI Screenshots |
| **AP2-16** | Negative CSRF test diverifikasi dengan proteksi aktif (HTTP 419) | `LetterApprovalWorkflowTest::test_ap2_16_*` |
| **AP2-17** | PHPUnit, Laravel Pint, dan kompilasi Vite frontend lulus 100% | `php artisan test`, `pint --test`, `npm run build` |
| **AP2-18** | Penomoran resmi tetap OFF, official_number tetap NULL, nol tanda tangan | `SafetyGuardTest` & `test_ap2_18_*` |

---

## 6. JADWAL PELAKSANAAN & DISTRIBUSI ARTEFAK

1. Penulisan Service & Model Migrations.
2. Penulisan Controller, Policy, dan Route Web.
3. Pembuatan Antarmuka Blade (Reviewer Queue, Review Panel, Mandate List, History Timeline).
4. Eksekusi Pengujian Otomatis Feature & Concurrency Tests.
5. Pengambilan Tangkapan Layar Browser Google Chrome Headless.
6. Penyusunan 4 Dokumen Laporan Resmi Sprint M2:
   - `docs/sprint-m2-plan.md`
   - `docs/implementation-evidence-sprint-m2.md`
   - `docs/m2-test-matrix.md`
   - `docs/mandate-policy-gap.md`
7. Sinkronisasi artefak ke kedua direktori kerja (`D:\p4i-office-laravel\docs/` dan `D:\p4i-office\docs/`).
8. Penghentian kerja tertib (*Clean Stop*) untuk evaluasi independen GPT-6.
