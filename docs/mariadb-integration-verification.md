# LAPORAN VERIFIKASI INTEGRASI MARIADB INNODB NYATA
## P4I Office v1.0 — Sprint M0.1: Database Engine Verification & Hardening

- **Pelaksana Teknis:** Gemini Flash 3.8 — Lead Software Engineer
- **Auditor Independen:** GPT-6
- **Kewenangan Administratif:** Ketua / Pengurus Yayasan P4I
- **Workspace:** `D:\p4i-office-laravel`
- **Tanggal Audit:** 31 Oktober 2026

---

### 1. Bukti Konektivitas & Spesifikasi Mesin Database

Pengujian database pada Sprint M0.1 dilakukan langsung terhadap instance **MariaDB nyata**, bukan in-memory SQLite atau tiruan (*mock*).

```text
VERSION():            10.4.32-MariaDB
VERSION_COMMENT:      mariadb.org binary distribution
INNODB_VERSION:       10.4.32
DEFAULT_ENGINE:       InnoDB
HOST:                 127.0.0.1 (localhost:3306)
DRIVER:               mysql (PHP PDO MySQL Connector)
CHARSET / COLLATION:  utf8mb4 / utf8mb4_unicode_ci
```

#### Isolasi Lingkungan Database
Sesuai arahan tata kelola untuk tidak menyentuh database aplikasi lain pada host lokal:
- Database Development: `p4i_office_dev`
- Database Automated Testing: `p4i_office_test`
- Database lain pada server MariaDB (`db_p4i_yayasan`, `p4i_publisher`, `nga_helpdesk`, dll.) diproteksi dan sama sekali tidak diakses.

---

### 2. Verifikasi 15 Tabel Relasional (Storage Engine: InnoDB)

Hasil query terhadap `information_schema.TABLES` pada database `p4i_office_dev` membuktikan seluruh 15 tabel relasional terbuat menggunakan **InnoDB**:

| No | Nama Tabel | Storage Engine | Tipe Primary Key | Relasi Foreign Key / Constraints | Status |
| :---: | :--- | :---: | :---: | :--- | :---: |
| 1 | `organizations` | **InnoDB** | `CHAR(36)` (UUID) | Root tenant entity | **PASS** |
| 2 | `org_units` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `organizations`, self-ref `parent_id`, UNIQUE(`org_id`, `name`) | **PASS** |
| 3 | `users` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `organizations`, `org_units`, UNIQUE(`email`) | **PASS** |
| 4 | `roles` | **InnoDB** | `CHAR(36)` (UUID) | UNIQUE(`role_key`) | **PASS** |
| 5 | `user_roles` | **InnoDB** | Composite PK | Composite PK(`user_id`, `role_id`), FK ke `users`, `roles` | **PASS** |
| 6 | `numbering_policies` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `organizations`, UNIQUE(`org_id`, `series_key`), Default `is_active=0` | **PASS** |
| 7 | `letter_types` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `organizations`, `numbering_policies`, UNIQUE(`org_id`, `code`) | **PASS** |
| 8 | `letter_templates` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `organizations`, `letter_types`, UNIQUE(`letter_type_id`, `version`) | **PASS** |
| 9 | `letters` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `organizations`, `org_units`, `letter_types`, `letter_templates`, `users` | **PASS** |
| 10 | `letter_revisions` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `letters`, `letter_templates`, `users`, UNIQUE(`letter_id`, `version`) | **PASS** |
| 11 | `signatory_mandates` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `organizations`, `users`, `letter_types`, `org_units` | **PASS** |
| 12 | `approval_requests` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `organizations`, `letters`, `letter_revisions`, `users` | **PASS** |
| 13 | `approval_steps` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `approval_requests`, `letters`, `letter_revisions`, `users`, `signatory_mandates` | **PASS** |
| 14 | `letter_status_history` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `letters`, `letter_revisions`, `users` | **PASS** |
| 15 | `audit_events` | **InnoDB** | `CHAR(36)` (UUID) | FK ke `organizations`, `users` | **PASS** |

---

### 3. Integritas Data & Proteksi Immutability Berlapis (*Defense-in-Depth*)

Sesuai instruksi Section E: *"Jangan menganggap Eloquent hooks sebagai satu-satunya mekanisme integritas data. Evaluasi proteksi database, pembatasan privilege dan pengujian akses langsung yang dapat melewati model."*

Sistem menerapkan **dua lapis proteksi immutability**:

```
[Permintaan Mutasi / Update / Delete]
                 │
                 ▼
 ┌────────────────────────────────────────┐
 │ Lapis 1: Eloquent Model Observers      │  ──▶ Melempar ModelImmutableException
 │          (LetterRevision & AuditEvent) │      (Lapisan Aplikasi Laravel)
 └────────────────────────────────────────┘
                 │ (Jika dibypass via raw SQL / DB::statement)
                 ▼
 ┌────────────────────────────────────────┐
 │ Lapis 2: MariaDB Database Triggers     │  ──▶ SIGNAL SQLSTATE '45000'
 │          (BEFORE UPDATE / DELETE)      │      (Lapisan Mesin InnoDB)
 └────────────────────────────────────────┘
```

#### DDL Trigger MariaDB Aktual yang Diterapkan:
1. `trg_letter_revisions_no_update`: Menggagalkan segala operasi `UPDATE` pada `letter_revisions` dengan pesan *'Letter revisions are strictly immutable and cannot be updated.'*.
2. `trg_letter_revisions_no_delete`: Menggagalkan segala operasi `DELETE` pada `letter_revisions` dengan pesan *'Letter revisions are strictly immutable and cannot be deleted.'*.
3. `trg_audit_events_no_update`: Menggagalkan segala operasi `UPDATE` pada `audit_events` dengan pesan *'Audit events are strictly append-only and cannot be updated.'*.
4. `trg_audit_events_no_delete`: Menggagalkan segala operasi `DELETE` pada `audit_events` dengan pesan *'Audit events are strictly append-only and cannot be deleted.'*.

---

### 4. Hasil Uji Otomatis MariaDB (`MariaDbVerificationTest`)

Suite pengujian `tests/Feature/MariaDbVerificationTest.php` dirancang khusus untuk memvalidasi persyaratan Section D dan Section E:

```text
PASS  Tests\Feature\MariaDbVerificationTest
✓ active connection is real mariadb innodb                         (Assert driver mysql, verifikasi InnoDB pada 15 tabel)
✓ foreign key constraints enforced by mariadb                      (Assert QueryException / Errno 1452 pada invalid FK)
✓ unique constraints enforced by mariadb                           (Assert QueryException / Errno 1062 pada duplicate email)
✓ transaction rollback works on mariadb innodb                     (Assert data batal ditulis saat exception terjadi)
✓ concurrent operations with lock for update                       (Assert pessimistic row locking SELECT FOR UPDATE sukses)
✓ raw sql update bypass blocked by mariadb trigger                 (Assert SQLSTATE 45000 saat membypass Eloquent model)
✓ raw sql delete bypass blocked by mariadb trigger                 (Assert SQLSTATE 45000 saat membypass Eloquent model)
✓ data persistence across connection reconnect                     (Assert commit, purge PDO, reconnect, data tetap utuh)

Tests: 8 passed (30 assertions)
Duration: 3.92s
```

---

### 5. Kesimpulan Audit Database

1. Database MariaDB 10.4+ InnoDB terbukti aktif dan terintegrasi penuh dengan Laravel 13.
2. Tidak ada ketergantungan pada SQLite untuk pengujian integrasi database.
3. Immutability revisi surat dan audit log terlindungi secara fisik pada level mesin database InnoDB, sehingga tidak dapat dimanipulasi bahkan oleh query SQL langsung (*raw SQL bypass proof*).
