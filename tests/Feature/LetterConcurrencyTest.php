<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\LetterDraftService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LetterConcurrencyTest extends TestCase
{
    protected Organization $org;

    protected User $author;

    protected LetterType $type;

    protected LetterTemplate $template;

    protected Letter $letter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanDatabase();

        $this->org = Organization::create([
            'legal_name' => 'Yayasan P4I Konkurensi',
            'acronym' => 'P4I-CONC',
        ]);

        $unit = OrgUnit::create([
            'organization_id' => $this->org->id,
            'name' => 'Divisi Teknologi',
        ]);

        $role = Role::create(['role_key' => 'STAFF', 'label' => 'Staf']);

        $this->author = User::create([
            'organization_id' => $this->org->id,
            'unit_id' => $unit->id,
            'email' => 'author_conc_'.uniqid().'@p4i.or.id',
            'display_name' => 'Staf Konkurensi',
            'password' => 'secret123',
            'status' => 'ACTIVE',
        ]);
        $this->author->roles()->attach($role->id);

        $this->type = LetterType::create([
            'organization_id' => $this->org->id,
            'code' => 'TYPE-CONC',
            'label' => 'Jenis Konkurensi',
            'is_active' => true,
        ]);

        $this->template = LetterTemplate::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->type->id,
            'version' => 1,
            'title' => 'Templat Konkurensi',
            'form_schema' => ['fields' => ['subjek']],
            'content_template' => '<p>{{subjek}}</p>',
            'is_active' => true,
        ]);

        $service = app(LetterDraftService::class);
        $this->letter = $service->createDraft($this->author, [
            'subject' => 'Surat Uji Dua Koneksi',
            'recipient_name' => 'Penerima Uji',
            'letter_type_id' => $this->type->id,
            'template_id' => $this->template->id,
            'classification' => 'INTERNAL',
            'planned_date' => '2026-11-20',
            'variables' => ['subjek' => 'Isi Awal'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('audit_events')->truncate();
        DB::table('letter_status_history')->truncate();
        DB::table('letter_revisions')->truncate();
        DB::table('letters')->truncate();
        DB::table('letter_templates')->truncate();
        DB::table('letter_types')->truncate();
        DB::table('user_roles')->truncate();
        DB::table('users')->truncate();
        DB::table('roles')->truncate();
        DB::table('org_units')->truncate();
        DB::table('organizations')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * M1-06 / Temuan A-02: Uji penguncian baris (Pessimistic Row Lock) antara DUA KONEKSI DATABASE INDEPENDEN.
     * Koneksi 1 mengunci baris; Koneksi 2 terblokir hingga lock wait timeout.
     */
    public function test_m1_06_independent_connection_row_locking_and_timeout(): void
    {
        $letterId = $this->letter->id;

        // Set lock wait timeout rendah (1 detik) pada MariaDB untuk pengujian timeout
        DB::connection('mysql')->statement('SET innodb_lock_wait_timeout = 2');
        DB::connection('mysql2')->statement('SET innodb_lock_wait_timeout = 1');

        // 1. Koneksi 1 memulai transaksi dan mengunci baris surat dengan lockForUpdate
        DB::connection('mysql')->beginTransaction();

        $lockedRowConn1 = DB::connection('mysql')
            ->table('letters')
            ->where('id', $letterId)
            ->lockForUpdate()
            ->first();

        $this->assertNotNull($lockedRowConn1);

        // 2. Koneksi 2 (proses/thread lain yang independen) mencoba mengubah atau mengunci baris yang sama
        $lockTimeoutOccurred = false;
        try {
            DB::connection('mysql2')->beginTransaction();

            // Upaya mengunci baris yang sedang dipegang Koneksi 1
            DB::connection('mysql2')
                ->table('letters')
                ->where('id', $letterId)
                ->lockForUpdate()
                ->first();

            DB::connection('mysql2')->commit();
        } catch (QueryException $e) {
            DB::connection('mysql2')->rollBack();
            // MariaDB Error 1205: Lock wait timeout exceeded
            if (str_contains($e->getMessage(), 'Lock wait timeout exceeded') || str_contains($e->getMessage(), '1205')) {
                $lockTimeoutOccurred = true;
            }
        }

        $this->assertTrue(
            $lockTimeoutOccurred,
            'Connection 2 must be blocked by exclusive InnoDB row lock held by Connection 1 and trigger lock wait timeout'
        );

        // 3. Koneksi 1 menyelesaikan transaksi (commit)
        DB::connection('mysql')->commit();

        // 4. Setelah Koneksi 1 melepaskan lock, Koneksi 2 sekarang dapat membaca baris tanpa terblokir
        $conn2Row = DB::connection('mysql2')
            ->table('letters')
            ->where('id', $letterId)
            ->first();

        $this->assertNotNull($conn2Row);
        $this->assertEquals($letterId, $conn2Row->id);
    }

    /**
     * Uji bahwa Rollback pada satu koneksi tidak merusak data atau meninggalkan nomor revisi terputus/yatim.
     */
    public function test_transaction_rollback_on_failed_revision_preserves_version_continuity(): void
    {
        $letterId = $this->letter->id;
        $initialRevisionCount = LetterRevision::where('letter_id', $letterId)->count();
        $this->assertEquals(1, $initialRevisionCount);

        // Simulasi transaksi revisi yang gagal di tengah proses
        try {
            DB::connection('mysql')->transaction(function () use ($letterId) {
                // Buat revisi parsial
                DB::connection('mysql')->table('letter_revisions')->insert([
                    'id' => (string) Str::uuid(),
                    'letter_id' => $letterId,
                    'version' => 2,
                    'template_id' => $this->template->id,
                    'subject' => 'Revisi Parsial Gagal',
                    'recipient_name' => 'Penerima',
                    'classification' => 'INTERNAL',
                    'planned_date' => '2026-11-20',
                    'content' => json_encode(['subjek' => 'Batal']),
                    'content_sha256' => hash('sha256', 'Batal'),
                    'created_by' => $this->author->id,
                    'created_at' => now(),
                ]);

                // Paksa kegagalan transaksi
                throw new \RuntimeException('Simulasi kegagalan sistem di tengah proses');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Periksa dari Koneksi 2: Revisi v2 TIDAK BOLEH ADA di database
        $revisionsInConn2 = DB::connection('mysql2')
            ->table('letter_revisions')
            ->where('letter_id', $letterId)
            ->get();

        $this->assertCount(1, $revisionsInConn2, 'Aborted revision v2 must not exist in database');

        // Sekarang lakukan revisi yang sah via LetterDraftService
        $service = app(LetterDraftService::class);
        $validRev2 = $service->reviseDraft($this->author, $this->letter, [
            'subject' => 'Revisi v2 Sah',
            'change_summary' => 'Revisi sah pertama setelah rollback',
        ], expectedRevisionNo: 1);

        $this->assertEquals(2, $validRev2->version);

        $this->letter->refresh();
        $this->assertEquals(2, $this->letter->current_revision_no);
        $this->assertEquals(2, LetterRevision::where('letter_id', $letterId)->count());
    }
}
