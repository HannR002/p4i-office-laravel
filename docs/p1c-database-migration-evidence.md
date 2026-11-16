# Bukti Teknis Migrasi Database & Verifikasi Trigger Immutability (Tahap P1-C)

**Proyek:** P4I Office v1.0  
**Tahap:** P1-C (Controlled Laravel Staging Deployment — One-Shot Execution)  
**Implementer Teknis:** Argon — Reasoning High  
**Tim Perancangan & Auditor:** GPT-6  
**Otoritas Administratif:** Ketua dan Pengurus Yayasan P4I  
**Target Database:** `u239415845_p4i_staging` (Hostinger MariaDB `11.8.9-MariaDB-log` / InnoDB)  
**Tanggal Evaluasi:** 16 November 2026  

---

## 1. Ringkasan Status Migrasi & Keamanan Database

| Komponen | Status Lokal | Status Hostinger Staging | Catatan Kepatuhan |
| :--- | :---: | :---: | :--- |
| **Hak Istimewa `TRIGGER`** | `PASS` | `PASS` (`Trigger: 1`) | User `u239415845_p4i_staging` memiliki hak penuh membuat trigger. |
| **9 Migrasi Aplikasi** | `Ran` (9/9) | `Ran` (9/9) | Seluruh migrasi berhasil dijalankan via SSH CLI. |
| **Skema Tabel Lengkap** | 27 tabel aktif | 27 tabel aktif | Seluruh tabel skema persuratan, audit, dan ekspor tercipta. |
| **4 Trigger Immutability** | `AKTIF & TERUJI` | `AKTIF & TERUJI` | Terverifikasi aktif via `information_schema.TRIGGERS` & uji mutasi. |
| **Isolasi Database Produksi** | `100% TERISOLASI`| `100% TERISOLASI` | Zero connection ke `u239415845_ojs`, `u239415845_publisher`, atau `u239415845_publisher_new`. |

---

## 2. Rincian Eksekusi 9 Migrasi Database di Server Hostinger

Perintah migrasi dijalankan melalui SSH menggunakan runtime PHP 8.3 Hostinger:
`/opt/alt/php83/usr/bin/php artisan migrate --force`

Hasil log eksekusi aktual:
```text
INFO  Preparing database.
Creating migration table ....................................... 8.17ms DONE

INFO  Running migrations.
2014_10_12_100000_create_password_reset_tokens_table ........... 2.75ms DONE
2019_08_19_000000_create_failed_jobs_table .................... 10.46ms DONE
2019_12_14_000001_create_personal_access_tokens_table .......... 8.61ms DONE
2026_10_30_000001_create_p4i_office_schema ......................... 2s DONE
2026_11_01_000001_enhance_sprint_m2_tables .................... 42.17ms DONE
2026_11_01_000002_create_sprint_m3a_tables ................... 725.51ms DONE
2026_11_01_000003_enhance_sprint_m3b_tables .................. 296.95ms DONE
2026_11_01_000004_enhance_sprint_m3c_security_tables .......... 83.50ms DONE
2026_11_01_000005_create_document_exports_table .............. 259.69ms DONE
```

Daftar 27 Tabel yang Tercipta pada Skema `u239415845_p4i_staging`:
1. `agenda_policies`
2. `approval_requests`
3. `approval_steps`
4. `audit_events`
5. `document_exports`
6. `failed_jobs`
7. `incoming_letters`
8. `legacy_register_batches`
9. `legacy_register_rows`
10. `letters`
11. `letter_revisions`
12. `letter_status_history`
13. `letter_templates`
14. `letter_types`
15. `migrations`
16. `numbering_policies`
17. `numbering_simulations`
18. `numbering_simulation_counters`
19. `organizations`
20. `org_units`
21. `password_reset_tokens`
22. `personal_access_tokens`
23. `reconciliation_audit_logs`
24. `roles`
25. `signatory_mandates`
26. `users`
27. `user_roles`

---

## 3. Verifikasi Empat (4) Trigger Immutability MariaDB

### 3.1 Bukti Query `information_schema.TRIGGERS`
Query SQL langsung pada database server:
```sql
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_TIMING 
FROM information_schema.TRIGGERS 
WHERE TRIGGER_SCHEMA = 'u239415845_p4i_staging';
```
Hasil kembalian server MariaDB (JSON):
```json
[
    {
        "TRIGGER_NAME": "trg_letter_revisions_no_update",
        "EVENT_MANIPULATION": "UPDATE",
        "EVENT_OBJECT_TABLE": "letter_revisions",
        "ACTION_TIMING": "BEFORE"
    },
    {
        "TRIGGER_NAME": "trg_letter_revisions_no_delete",
        "EVENT_MANIPULATION": "DELETE",
        "EVENT_OBJECT_TABLE": "letter_revisions",
        "ACTION_TIMING": "BEFORE"
    },
    {
        "TRIGGER_NAME": "trg_audit_events_no_update",
        "EVENT_MANIPULATION": "UPDATE",
        "EVENT_OBJECT_TABLE": "audit_events",
        "ACTION_TIMING": "BEFORE"
    },
    {
        "TRIGGER_NAME": "trg_audit_events_no_delete",
        "EVENT_MANIPULATION": "DELETE",
        "EVENT_OBJECT_TABLE": "audit_events",
        "ACTION_TIMING": "BEFORE"
    }
]
```

### 3.2 Uji Penolakan Mutasi Riil (Transactional Mutation & Rollback Test)
Pengujian penolakan mutasi dilakukan secara terisolasi pada server Hostinger dengan menyisipkan baris uji coba dalam transaksi lalu mengeksekusi operasi `UPDATE` dan `DELETE`:

```php
// Test 1: trg_audit_events_no_update & trg_audit_events_no_delete
// Hasil eksekusi:
UPDATE -> BLOCKED: SQLSTATE[45000]: <<Unknown error>>: 1644 Audit events are strictly append-only and cannot be updated.
DELETE -> BLOCKED: SQLSTATE[45000]: <<Unknown error>>: 1644 Audit events are strictly append-only and cannot be deleted.

// Test 2: trg_letter_revisions_no_update & trg_letter_revisions_no_delete
// Hasil eksekusi:
UPDATE -> BLOCKED: SQLSTATE[45000]: <<Unknown error>>: 1644 Letter revisions are strictly immutable and cannot be updated.
DELETE -> BLOCKED: SQLSTATE[45000]: <<Unknown error>>: 1644 Letter revisions are strictly immutable and cannot be deleted.
```

Seluruh transaksi di-rollback secara otomatis sehingga tidak meninggalkan data uji coba pada skema staging.

---

## 4. Pemasangan Data Sintetis & Audit Keamanan Invarian

### 4.1 Eksekusi Seeder Staging
Perintah: `/opt/alt/php83/usr/bin/php artisan db:seed --class=StagingUatSeeder --force`
Hasil: `Database\Seeders\StagingUatSeeder DONE (1,294 ms)`.

Pengguna sintetis yang aktif pada database staging:
1. `admin@p4i.or.id` — Administrator Yayasan
2. `pengurus@p4i.or.id` — Drs. H. Mulyadi, M.M. (Ketua Yayasan)
3. `staf@p4i.or.id` — Staf Sekretariat P4I
4. `reviewer@p4i.or.id` — Dr. Penelaah Riset, M.Si.
5. `staf.uat@p4i.or.id` — Rina Kartika (Staf UAT)
6. `reviewer.uat@p4i.or.id` — Dr. Bambang Sutrisno (Pemeriksa Bermandat UAT)
7. `unauth.uat@p4i.or.id` — Dedi Kurniawan (Reviewer Tanpa Mandat UAT)
8. `admin.uat@p4i.or.id` — Agus Setiawan (Admin UAT)

### 4.2 Invarian Keselamatan Persuratan
- Total surat terdaftar: **3 naskah**
- Surat dengan nomor resmi (`official_number IS NOT NULL`): **0 naskah**
- Surat berstatus `ISSUED`: **0 naskah**
- Nilai `config('features.official_numbering_active')`: **`false`** (Nonaktif mutlak).
