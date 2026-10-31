# MATRIKS PENGUJIAN SPRINT M3A (M3A TEST MATRIX)
## P4I Office v1.0 — Numbering Foundation, Legacy Register Validation & Agenda Management

- **Tanggal Dokumen:** 01 November 2026
- **Lead Software Engineer & Technical Test Engineer:** Gemini Flash 3.8 — Reasoning High
- **Software Architect, System Analyst & Auditor:** GPT-6
- **Otoritas Administratif:** Ketua & Dewan Pengurus Yayasan P4I
- **Workspace:** `D:\p4i-office-laravel` (Sinkronisasi: `D:\p4i-office`)
- **Status Evaluasi:** **100% TERPENUHI (PASS - 20 DARI 20 GATES TERUJI)**

---

## 1. REKAPITULASI STATUS PENERIMAAN GERBANG NR-01 s.d. NR-20

| Gate ID | Kriteria Pengujian / Uraian Kebutuhan | File Uji / Bukti Teknis | Status |
|:---|:---|:---|:---:|
| **NR-01** | Pembuatan Draf Numbering Policy tanpa Aktivasi Langsung | `NumberingSimulationTest::test_nr_01_admin_can_create_draft_policy_without_activating` | **PASS** |
| **NR-02** | Pratinjau String Nomor Simulasi Bertanda `[SIMULASI]` | `NumberingSimulationTest::test_nr_02_simulation_preview_always_labeled_simulasi_and_isolated` | **PASS** |
| **NR-03** | Format Pola Penomoran Dinamis (Bukan Hardcoded) | `NumberingSimulationTest::test_nr_03_format_derived_dynamically_from_pattern` | **PASS** |
| **NR-04** | Validasi Format Angka Romawi Bulan & Transisi Periode | `NumberingSimulationTest::test_nr_04_roman_month_conversion_and_validation` | **PASS** |
| **NR-05** | Impor Register Lama ke Lingkungan Staging Terisolasi | `LegacyRegisterImportTest::test_nr_05_and_07_csv_import_staging_and_audit_metadata` | **PASS** |
| **NR-06** | Deteksi Anomali Nomor Kosong, Duplikasi, dan Tanggal | `LegacyRegisterImportTest::test_nr_06_anomaly_and_conflict_detection` | **PASS** |
| **NR-07** | Pencatatan Metadata Sumber (Filename, Hash, Reviewer) | `LegacyRegisterImportTest::test_nr_05_and_07_csv_import_staging_and_audit_metadata` | **PASS** |
| **NR-08** | Blokade Baseline Tak Terverifikasi dari Seeding Counter | `LegacyRegisterImportTest::test_nr_08_unverified_legacy_batch_cannot_seed_counter` | **PASS** |
| **NR-09** | Konkurensi Alokasi Simultan ($\ge 20$ Request Paralel) | `NumberingConcurrencyParallelTest::test_nr_09_at_least_20_parallel_simulation_allocations_no_duplicate_sequence` | **PASS** |
| **NR-10** | Idempotensi Alokasi Menggunakan `idempotency_key` | `NumberingSimulationTest::test_nr_10_idempotent_allocation_does_not_double_increment` | **PASS** |
| **NR-11** | Buku Agenda Surat Keluar dengan Filter & Pencarian | `AgendaBookTest::test_nr_11_outgoing_agenda_book_filtering_and_search` | **PASS** |
| **NR-12** | Pemisahan Nomor Surat Luar vs Nomor Agenda Internal | `AgendaBookTest::test_nr_12_incoming_letter_separates_sender_number_and_internal_agenda` | **PASS** |
| **NR-13** | Pencatatan Audit Trail Immutable untuk Setiap Mutasi | `tests/Feature/AgendaBookTest.php`, `LegacyRegisterImportTest.php` | **PASS** |
| **NR-14** | Proteksi Otorisasi RBAC & Multi-Tenant Isolation | `AgendaBookTest::test_nr_14_rbac_and_multi_tenant_isolation` | **PASS** |
| **NR-15** | Invarian `official_number` Tetap NULL & Flag Nomor OFF | `NumberingSimulationTest::test_nr_15_official_number_remains_strictly_null` | **PASS** |
| **NR-16** | Dashboard Kesiapan Persuratan Berstatus `BLOCKED` | `LegacyRegisterImportTest::test_nr_16_readiness_report_displays_blocked_status` | **PASS** |
| **NR-17** | Validasi Keamanan Berkas & Sanitasi Formula CSV Injection | `LegacyRegisterImportTest::test_nr_17_file_upload_security_and_csv_injection_sanitization` | **PASS** |
| **NR-18** | Rollback Transaksional MariaDB InnoDB pada Kegagalan | `NumberingConcurrencyParallelTest::test_nr_18_mariadb_innodb_rollback_preserves_counter_state` | **PASS** |
| **NR-19** | Branding Visual Lencana `[SIMULASI]` & Watermark UI | Verifikasi Tangkapan Layar Google Chrome Headless | **PASS** |
| **NR-20** | Kepatuhan Regresi, Laravel Pint & Asset Bundle Vite | Artisan Test (75 passed), Pint (0 violation), Vite build (sukses) | **PASS** |

---

## 2. DETAIL VERIFIKASI PER GERBANG PENERIMAAN

### NR-01: Pembuatan Draf Numbering Policy tanpa Aktivasi
- **Metode Pengujian:** HTTP POST ke `/numbering/policies` oleh peran Administrator.
- **Hasil Aktual:** Record tersimpan di tabel `numbering_policies` dengan kolom `is_active = 0` (false) dan `status = 'DRAFT'`. Sistem menolak aktivasi langsung sebelum ada pengesahan SK resmi.
- **Kesimpulan:** **PASS**.

### NR-02: Pratinjau Nomor Simulasi Berlabel `[SIMULASI]`
- **Metode Pengujian:** Evaluasi `NumberingService::previewSimulation()` dan HTTP POST ke `/numbering/preview`.
- **Hasil Aktual:** Setiap output string penomoran selalu memiliki prefiks `[SIMULASI] ` (contoh: `[SIMULASI] 001/P4I-TEST/XI/2026`). String ini diisolasi dan tidak pernah disalin ke `letters.official_number`.
- **Kesimpulan:** **PASS**.

### NR-03: Format Dinamis Berdasarkan Pola Kebijakan
- **Metode Pengujian:** Pengujian konfigurasi pola kustom: `P4I/{year}/{month}/{code}-{seq:05}`.
- **Hasil Aktual:** Sistem menggantikan token `{year}`, `{month}`, `{code}`, dan `{seq:05}` secara dinamis menghasilkan `[SIMULASI] P4I/2026/08/TGS-00042` tanpa ada hardcoding dalam kode aplikasi.
- **Kesimpulan:** **PASS**.

### NR-04: Konversi Bulan Romawi & Validasi Rentang
- **Metode Pengujian:** Uji pemetaan integer 1..12 ke karakter Romawi `I` s.d. `XII` serta penolakan nilai di luar rentang (misal: bulan 13).
- **Hasil Aktual:** Seluruh 12 bulan terkonversi tepat. Input bulan 13 melempar `InvalidArgumentException`.
- **Kesimpulan:** **PASS**.

### NR-05 & NR-07: Impor Register Berkas Historis ke Staging Terisolasi
- **Metode Pengujian:** Unggah berkas CSV `register_2024.csv` via HTTP POST `/legacy-registers`.
- **Hasil Aktual:** Batch tersimpan pada tabel `legacy_register_batches` dengan pencatatan `source_hash` SHA-256 (64 karakter heksadesimal), user pemuat, waktu unggah, dan baris dimuat ke `legacy_register_rows` tanpa menyentuh tabel operasional persuratan.
- **Kesimpulan:** **PASS**.

### NR-06: Deteksi Anomali & Konflik Register
- **Metode Pengujian:** Unggah CSV sintetis bermuatan: nomor kosong, nomor duplikat, dan tanggal masa depan (2099-01-01).
- **Hasil Aktual:** Sistem secara otomatis mendeteksi dan mengkategorikan anomali menjadi `EMPTY_NUMBER`, `DUPLICATE_NUMBER`, dan `DATE_INCONSISTENCY`, serta menandai batch dengan status `HAS_CONFLICTS`.
- **Kesimpulan:** **PASS**.

### NR-08: Blokade Baseline Tak Terverifikasi dari Seeding Counter
- **Metode Pengujian:** Percobaan pemanggilan `NumberingService::seedFromLegacyBatch()` pada batch berstatus `STAGED` / `UNVERIFIED`.
- **Hasil Aktual:** Sistem melempar `DomainException` dengan pesan: *"Data register historis belum terverifikasi secara sah. Baseline historis dilarang digunakan."*
- **Kesimpulan:** **PASS**.

### NR-09: Uji Konkurensi Alokasi Simultan ($\ge 20$ Request Paralel)
- **Metode Pengujian:** Eksekusi 25 request alokasi simultan pada MariaDB InnoDB dengan penguncian baris pesimistik (`lockForUpdate()`), serta uji kontensi kunci antar 2 koneksi PDO independen (`mysql` vs `mysql2`).
- **Hasil Aktual:** Seluruh 25 alokasi menghasilkan nomor urut unik `1, 2, ..., 25` tanpa satupun duplikasi atau benturan. Koneksi kedua yang mencoba mengakses saat baris sedang dikunci oleh koneksi pertama berhasil mendeteksi *lock wait timeout*.
- **Kesimpulan:** **PASS**.

### NR-10: Idempotensi Alokasi Nomor Simulasi
- **Metode Pengujian:** Pemanggilan alokasi berulang dengan menyertakan `idempotency_key` yang sama.
- **Hasil Aktual:** Pemanggilan kedua mengembalikan objek simulasi yang sama persis tanpa menaikkan counter nomor di basis data.
- **Kesimpulan:** **PASS**.

### NR-11: Buku Agenda Surat Keluar
- **Metode Pengujian:** Filter multi-kriteria pada `/agenda/outgoing` (status `APPROVED`, subjek, rentang tanggal).
- **Hasil Aktual:** Query pagination menyaring data secara presisi dan menampilkan indikator `[NULL - NON-RESMI]` pada kolom nomor surat resmi.
- **Kesimpulan:** **PASS**.

### NR-12: Buku Agenda Surat Masuk & Pemisahan Nomor
- **Metode Pengujian:** Pendaftaran surat masuk pihak eksternal melalui `/agenda/incoming`.
- **Hasil Aktual:** Nomor surat asli instansi pengirim (`B-998/KOMINFO/DJAI/11/2026`) disimpan pada kolom `sender_letter_number`, sedangkan sistem menerbitkan nomor agenda internal mandiri (`AG-IN-2026-0001`). Kedua nomor terbukti berbeda secara tegas.
- **Kesimpulan:** **PASS**.

### NR-13: Pencatatan Audit Trail Immutable
- **Metode Pengujian:** Verifikasi tabel `audit_events` setelah setiap transaksi penomoran, impor, dan agenda.
- **Hasil Aktual:** Event tercatat dengan tipe `NUMBERING_POLICY_DRAFT_CREATED`, `NUMBERING_SIMULATION_ALLOCATED`, `LEGACY_REGISTER_BATCH_IMPORTED`, dan `INCOMING_LETTER_REGISTERED` dengan payload JSON utuh.
- **Kesimpulan:** **PASS**.

### NR-14: Otorisasi RBAC & Multi-Tenant Isolation
- **Metode Pengujian:** Staf biasa mencoba membuat kebijakan penomoran (ditolak HTTP 403), dan Organisasi B mencoba melihat agenda surat Organisasi A (tidak ada data bocor).
- **Hasil Aktual:** Seluruh batas keamanan peran dan tenant ditegakkan secara mutlak.
- **Kesimpulan:** **PASS**.

### NR-15: Invarian Nomor Resmi Tetap NULL
- **Metode Pengujian:** Verifikasi kolom `letters.official_number` setelah alokasi simulasi pada surat berstatus `APPROVED`.
- **Hasil Aktual:** Kolom `official_number` secara konsisten bernilai `NULL`.
- **Kesimpulan:** **PASS**.

### NR-16: Laporan Kesiapan Persuratan Berstatus `BLOCKED`
- **Metode Pengujian:** Evaluasi `ReadinessService::evaluateReadiness()` dan inspeksi view `/readiness`.
- **Hasil Aktual:** Dashboard menampilkan status `BLOCKED (DIBLOKIR DARI PRODUKSI)` dengan alasan objektif: SK pengurus belum disahkan, register historis berstatus konflik, dan mandat masih berupa simulasi teknis.
- **Kesimpulan:** **PASS**.

### NR-17: Keamanan Unggah Berkas & Sanitasi CSV
- **Metode Pengujian:** Percobaan unggah berkas `.exe` (ditolak dengan `InvalidArgumentException`) dan uji pembersihan karakter pemicu formula injection (`=`, `+`, `-`, `@`).
- **Hasil Aktual:** Sistem menolak berkas non-CSV/TXT dan membersihkan formula berbahaya.
- **Kesimpulan:** **PASS**.

### NR-18: Rollback Transaksional MariaDB InnoDB
- **Metode Pengujian:** Menjalankan transaksi MariaDB yang menaikkan counter ke 999 namun dilemparkan exception sebelum commit.
- **Hasil Aktual:** MariaDB melakukan rollback penuh. Nilai counter di basis data kembali utuh ke nilai sebelum transaksi (1), dan alokasi berikutnya memperoleh sequence 2 secara sah.
- **Kesimpulan:** **PASS**.

### NR-19: Branding Visual Lencana Simulasi
- **Metode Pengujian:** Evaluasi tampilan peramban nyata melalui Google Chrome Headless pada halaman kebijakan, staging register, buku agenda, dan kesiapan persuratan.
- **Hasil Aktual:** Seluruh nomor dan halaman menyertakan watermark visual `[SIMULASI]` dan peringatan nonresmi.
- **Kesimpulan:** **PASS**.

### NR-20: Kepatuhan Regresi, Pint, dan Vite
- **Hasil Aktual:**
  - `php artisan test`: **75 passed (318 assertions), 0 failures, 0 errors**.
  - `vendor/bin/pint --test`: **PASSED (0 violations)**.
  - `npm run build`: **Vite build sukses (704ms)**.
- **Kesimpulan:** **PASS**.
