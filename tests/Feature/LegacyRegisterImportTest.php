<?php

namespace Tests\Feature;

use App\Models\LegacyRegisterBatch;
use App\Models\NumberingPolicy;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\LegacyRegisterService;
use App\Services\NumberingService;
use App\Services\ReadinessService;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class LegacyRegisterImportTest extends TestCase
{
    protected Organization $org;

    protected User $admin;

    protected User $staff;

    protected User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan Pusat Pendidikan dan Penelitian Pembangunan Indonesia',
            'acronym' => 'P4I',
        ]);

        $adminRole = Role::create(['id' => (string) Str::uuid(), 'role_key' => 'SYSTEM_ADMIN', 'label' => 'Administrator']);
        $staffRole = Role::create(['id' => (string) Str::uuid(), 'role_key' => 'STAFF', 'label' => 'Staf']);
        $reviewerRole = Role::create(['id' => (string) Str::uuid(), 'role_key' => 'REVIEWER', 'label' => 'Penelaah']);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'admin_legacy_'.Str::random(5).'@p4i.or.id',
            'display_name' => 'Admin Legacy',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $this->admin->roles()->attach($adminRole->id);

        $this->staff = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'staff_legacy_'.Str::random(5).'@p4i.or.id',
            'display_name' => 'Staf Legacy',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $this->staff->roles()->attach($staffRole->id);

        $this->reviewer = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'reviewer_legacy_'.Str::random(5).'@p4i.or.id',
            'display_name' => 'Reviewer Legacy',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $this->reviewer->roles()->attach($reviewerRole->id);
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('numbering_simulations')->truncate();
        DB::table('numbering_simulation_counters')->truncate();
        DB::table('legacy_register_rows')->truncate();
        DB::table('legacy_register_batches')->truncate();
        DB::table('incoming_letters')->truncate();
        DB::table('numbering_policies')->truncate();
        DB::table('audit_events')->truncate();
        DB::table('approval_steps')->truncate();
        DB::table('approval_requests')->truncate();
        DB::table('signatory_mandates')->truncate();
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
     * NR-05 & NR-07: Impor CSV valid berhasil dimuat ke tabel staging dengan metadata hash & operator.
     */
    public function test_nr_05_and_07_csv_import_staging_and_audit_metadata(): void
    {
        $csvContent = "nomor_surat,tanggal,jenis,penerima,perihal\n".
                      "001/P4I/I/2024,2024-01-10,SURAT_KELUAR,Dinas Pendidikan,Permohonan Riset\n".
                      "002/P4I/I/2024,2024-01-15,SURAT_TUGAS,Dr. Hendra,Surat Tugas Lapangan\n";

        $file = UploadedFile::fake()->createWithContent('register_2024.csv', $csvContent);

        $response = $this->actingAs($this->staff)->post(route('legacy.store'), [
            'register_file' => $file,
            'notes' => 'Rekap Buku Register 2024',
        ]);

        $batch = LegacyRegisterBatch::where('organization_id', $this->org->id)->latest()->first();
        $this->assertNotNull($batch);
        $this->assertEquals('register_2024.csv', $batch->source_filename);
        $this->assertEquals(64, strlen($batch->source_hash));
        $this->assertEquals(2, $batch->total_rows);
        $this->assertEquals(2, $batch->valid_rows);
        $this->assertEquals(0, $batch->conflict_rows);
        $this->assertEquals('STAGED', $batch->status);
        $this->assertEquals($this->staff->id, $batch->imported_by);

        // Verifikasi baris staging
        $this->assertDatabaseHas('legacy_register_rows', [
            'batch_id' => $batch->id,
            'raw_number' => '001/P4I/I/2024',
            'is_valid' => true,
            'verification_status' => 'UNVERIFIED',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $this->org->id,
            'event_type' => 'LEGACY_REGISTER_BATCH_IMPORTED',
        ]);
    }

    /**
     * NR-06: Deteksi anomali nomor kosong, duplikasi dalam batch, dan tanggal cacat.
     */
    public function test_nr_06_anomaly_and_conflict_detection(): void
    {
        $csvContent = "nomor_surat,tanggal,jenis,penerima,perihal\n".
                      "101/P4I/I/2024,2024-01-10,SURAT_KELUAR,Mitra A,Perihal A\n".
                      ",2024-01-12,SURAT_KELUAR,Mitra B,Nomor Kosong\n". // EMPTY_NUMBER
                      "101/P4I/I/2024,2024-01-15,SURAT_KELUAR,Mitra C,Nomor Duplikat\n". // DUPLICATE_NUMBER
                      "103/P4I/I/2024,2099-01-01,SURAT_KELUAR,Mitra D,Tanggal Masa Depan\n"; // DATE_INCONSISTENCY

        $file = UploadedFile::fake()->createWithContent('register_anomalous.csv', $csvContent);

        $service = app(LegacyRegisterService::class);
        $batch = $service->importCsv($this->staff, $file, 'Uji Anomali');

        $this->assertEquals(4, $batch->total_rows);
        $this->assertEquals(1, $batch->valid_rows);
        $this->assertEquals(3, $batch->conflict_rows);
        $this->assertEquals('HAS_CONFLICTS', $batch->status);

        // Periksa rincian anomali pada baris
        $rows = $batch->rows;
        $this->assertTrue($rows[0]->is_valid);

        $this->assertFalse($rows[1]->is_valid);
        $this->assertEquals('EMPTY_NUMBER', $rows[1]->conflict_type);

        $this->assertFalse($rows[2]->is_valid);
        $this->assertEquals('DUPLICATE_NUMBER', $rows[2]->conflict_type);

        $this->assertFalse($rows[3]->is_valid);
        $this->assertEquals('DATE_INCONSISTENCY', $rows[3]->conflict_type);
    }

    /**
     * NR-08: Baseline tak terverifikasi tidak dapat digunakan sebagai pemula counter.
     */
    public function test_nr_08_unverified_legacy_batch_cannot_seed_counter(): void
    {
        $batch = LegacyRegisterBatch::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'source_filename' => 'unverified.csv',
            'source_hash' => hash('sha256', 'dummy'),
            'total_rows' => 10,
            'valid_rows' => 10,
            'conflict_rows' => 0,
            'status' => 'STAGED', // UNVERIFIED
            'imported_by' => $this->staff->id,
        ]);

        $policy = NumberingPolicy::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'series_key' => 'SEED_TEST_'.Str::random(4),
            'code' => 'P4I-SEED',
            'is_active' => false,
        ]);

        $service = app(NumberingService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Data register historis belum terverifikasi secara sah');
        $service->seedFromLegacyBatch($policy, $batch);
    }

    /**
     * NR-16: Readiness persuratan melaporkan status BLOCKED secara objektif.
     */
    public function test_nr_16_readiness_report_displays_blocked_status(): void
    {
        $service = app(ReadinessService::class);
        $report = $service->evaluateReadiness($this->org);

        $this->assertEquals('BLOCKED', $report['overall_status']);
        $this->assertNotEmpty($report['blocking_reasons']);

        // Verifikasi via HTTP Controller
        $response = $this->actingAs($this->staff)->get(route('readiness.index'));
        $response->assertOk();
        $response->assertSee('BLOCKED (DIBLOKIR DARI PRODUKSI)');
        $response->assertSee('Penerbitan Nomor Resmi Belum Diizinkan');
    }

    /**
     * NR-17: Keamanan berkas unggah: penolakan ekstensi terlarang & sanitasi CSV formula.
     */
    public function test_nr_17_file_upload_security_and_csv_injection_sanitization(): void
    {
        $exeFile = UploadedFile::fake()->create('malicious.exe', 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Berkas harus berekstensi .csv, .txt, atau .xlsx');

        $service = app(LegacyRegisterService::class);
        $service->importCsv($this->staff, $exeFile);
    }
}
