<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MariaDbVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * D-01: Verifikasi bahwa koneksi database aktif adalah MariaDB/MySQL dengan engine InnoDB.
     */
    public function test_active_connection_is_real_mariadb_innodb(): void
    {
        $connection = DB::connection();
        $this->assertEquals('mysql', $connection->getDriverName(), 'Database driver must be mysql (MariaDB/MySQL PDO)');

        $versionRow = DB::select('SELECT VERSION() as ver, @@innodb_version as innodb_ver')[0];
        $this->assertNotEmpty($versionRow->ver, 'Version string must not be empty');
        $this->assertNotEmpty($versionRow->innodb_ver, 'InnoDB version string must not be empty');

        // Verifikasi semua 15 tabel menggunakan engine InnoDB
        $tables = [
            'organizations', 'org_units', 'users', 'roles', 'user_roles',
            'numbering_policies', 'letter_types', 'letter_templates', 'letters',
            'letter_revisions', 'signatory_mandates', 'approval_requests',
            'approval_steps', 'letter_status_history', 'audit_events',
        ];

        $databaseName = $connection->getDatabaseName();
        $engineRows = DB::select(
            'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ('.
            implode(',', array_fill(0, count($tables), '?')).')',
            array_merge([$databaseName], $tables)
        );

        $this->assertCount(15, $engineRows, 'All 15 tables must exist in the database');
        foreach ($engineRows as $row) {
            $this->assertEquals('InnoDB', $row->ENGINE, "Table {$row->TABLE_NAME} must use InnoDB storage engine");
        }
    }

    /**
     * D-02: Verifikasi bahwa Foreign Key Constraint ditegakkan oleh MariaDB engine.
     */
    public function test_foreign_key_constraints_enforced_by_mariadb(): void
    {
        $this->expectException(QueryException::class);

        // Mencoba insert org_unit dengan organization_id acak yang tidak ada di tabel organizations
        DB::table('org_units')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => (string) Str::uuid(), // Non-existent foreign key
            'name' => 'Unit Ilegal',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * D-03: Verifikasi bahwa Unique Constraint ditegakkan oleh MariaDB engine.
     */
    public function test_unique_constraints_enforced_by_mariadb(): void
    {
        $org = Organization::create([
            'legal_name' => 'Yayasan P4I',
            'acronym' => 'P4I',
        ]);

        User::create([
            'organization_id' => $org->id,
            'email' => 'duplicate@p4i.or.id',
            'display_name' => 'User Pertama',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);

        $this->expectException(QueryException::class);

        // Mencoba insert pengguna kedua dengan email yang sama
        User::create([
            'organization_id' => $org->id,
            'email' => 'duplicate@p4i.or.id',
            'display_name' => 'User Duplikat',
            'password' => 'secret456',
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * D-04: Verifikasi bahwa Transaction Rollback berfungsi normal pada MariaDB InnoDB.
     */
    public function test_transaction_rollback_works_on_mariadb_innodb(): void
    {
        $email = 'rollback_test_'.uniqid().'@p4i.or.id';

        $org = Organization::create([
            'legal_name' => 'Yayasan P4I',
            'acronym' => 'P4I',
        ]);

        try {
            DB::transaction(function () use ($org, $email) {
                User::create([
                    'organization_id' => $org->id,
                    'email' => $email,
                    'display_name' => 'Rollback User',
                    'password' => 'secret',
                    'status' => 'ACTIVE',
                ]);

                // Paksa lempar exception untuk memicu rollback
                throw new \RuntimeException('Intentional transaction abort');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $userInDb = User::where('email', $email)->first();
        $this->assertNull($userInDb, 'User created in aborted transaction must not exist in database after rollback');
    }

    /**
     * D-05: Verifikasi operasi konkurensi dengan Pessimistic Row Locking (lockForUpdate).
     */
    public function test_concurrent_operations_with_lock_for_update(): void
    {
        $org = Organization::create([
            'legal_name' => 'Yayasan P4I',
            'acronym' => 'P4I',
        ]);

        $user = User::create([
            'organization_id' => $org->id,
            'email' => 'author_'.uniqid().'@p4i.or.id',
            'display_name' => 'Letter Author',
            'password' => 'secret',
            'status' => 'ACTIVE',
        ]);

        $type = LetterType::create([
            'organization_id' => $org->id,
            'code' => 'TEST-'.uniqid(),
            'label' => 'Jenis Uji',
        ]);

        $letter = Letter::create([
            'organization_id' => $org->id,
            'letter_type_id' => $type->id,
            'author_id' => $user->id,
            'subject' => 'Surat Uji Konkurensi',
            'recipient_name' => 'Penerima Uji',
            'planned_date' => now()->toDateString(),
            'status' => 'DRAFT',
            'current_revision_no' => 1,
        ]);

        // Uji transaksi dengan lockForUpdate
        $lockedLetter = DB::transaction(function () use ($letter) {
            return Letter::where('id', $letter->id)
                ->lockForUpdate()
                ->first();
        });

        $this->assertNotNull($lockedLetter);
        $this->assertEquals($letter->id, $lockedLetter->id);
    }

    /**
     * E-01: Verifikasi bahwa MariaDB Triggers menggagalkan pembaruan langsung (raw SQL bypass) pada letter_revisions.
     */
    public function test_raw_sql_update_bypass_blocked_by_mariadb_trigger(): void
    {
        $org = Organization::create(['legal_name' => 'Yayasan P4I', 'acronym' => 'P4I']);
        $user = User::create([
            'organization_id' => $org->id,
            'email' => 'immut_'.uniqid().'@p4i.or.id',
            'display_name' => 'Immutability Tester',
            'password' => 'secret',
            'status' => 'ACTIVE',
        ]);
        $type = LetterType::create(['organization_id' => $org->id, 'code' => 'IMM-'.uniqid(), 'label' => 'Uji']);
        $letter = Letter::create([
            'organization_id' => $org->id,
            'letter_type_id' => $type->id,
            'author_id' => $user->id,
            'subject' => 'Draf Asli',
            'recipient_name' => 'Penerima',
            'planned_date' => now()->toDateString(),
            'status' => 'DRAFT',
        ]);

        $rev = LetterRevision::create([
            'letter_id' => $letter->id,
            'version' => 1,
            'subject' => 'Subjek Draf Revisi 1',
            'recipient_name' => 'Penerima',
            'classification' => 'INTERNAL',
            'planned_date' => now()->toDateString(),
            'content' => ['body' => 'Isi draf pertama'],
            'content_sha256' => hash('sha256', 'Isi draf pertama'),
            'created_by' => $user->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Letter revisions are strictly immutable and cannot be updated.');

        // Mencoba bypass model Eloquent menggunakan raw SQL UPDATE langsung ke database
        DB::statement("UPDATE letter_revisions SET subject = 'SUBJEK DIPALSUKAN' WHERE id = ?", [$rev->id]);
    }

    /**
     * E-02: Verifikasi bahwa MariaDB Triggers menggagalkan penghapusan langsung (raw SQL bypass) pada audit_events.
     */
    public function test_raw_sql_delete_bypass_blocked_by_mariadb_trigger(): void
    {
        $org = Organization::create(['legal_name' => 'Yayasan P4I', 'acronym' => 'P4I']);

        $audit = AuditEvent::create([
            'organization_id' => $org->id,
            'event_type' => 'TEST_EVENT',
            'entity_type' => 'LETTER',
            'entity_id' => (string) Str::uuid(),
            'event_metadata' => ['action' => 'INITIAL_CREATION'],
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Audit events are strictly append-only and cannot be deleted.');

        // Mencoba bypass model Eloquent menggunakan raw SQL DELETE langsung ke database
        DB::statement('DELETE FROM audit_events WHERE id = ?', [$audit->id]);
    }

    /**
     * E-03: Verifikasi persistensi data setelah siklus reconnect database.
     */
    public function test_data_persistence_across_connection_reconnect(): void
    {
        $org = Organization::create([
            'legal_name' => 'Yayasan Persistensi P4I',
            'acronym' => 'YP4I',
        ]);

        $orgId = $org->id;

        // Commit transaksi aktif agar data ditulis ke InnoDB disk
        DB::commit();

        // Disconnect dan purge koneksi PDO
        DB::purge('mysql');
        DB::reconnect('mysql');

        // Ambil kembali data dari koneksi baru
        $reconnectedOrg = Organization::find($orgId);
        $this->assertNotNull($reconnectedOrg);
        $this->assertEquals('Yayasan Persistensi P4I', $reconnectedOrg->legal_name);

        // Bersihkan data uji
        $reconnectedOrg->delete();
        DB::commit();
    }
}
