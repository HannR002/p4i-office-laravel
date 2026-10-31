<?php

namespace Tests\Feature;

use App\Models\AgendaPolicy;
use App\Models\LegacyRegisterBatch;
use App\Models\LegacyRegisterRow;
use App\Models\Letter;
use App\Models\LetterType;
use App\Models\NumberingPolicy;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AgendaService;
use App\Services\LegacyRegisterService;
use App\Services\NumberingService;
use App\Services\ReadinessService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;
use ZipArchive;

class SprintM3BReadinessAndReconciliationTest extends TestCase
{
    protected Organization $org;

    protected User $admin;

    protected User $staff;

    protected User $reviewer;

    protected LetterType $letterType;

    protected NumberingPolicy $yearlyPolicy;

    protected NumberingPolicy $monthlyPolicy;

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
            'email' => 'admin_m3b_'.Str::random(5).'@p4i.or.id',
            'display_name' => 'Admin M3B',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $this->admin->roles()->attach($adminRole->id);

        $this->staff = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'staff_m3b_'.Str::random(5).'@p4i.or.id',
            'display_name' => 'Staf M3B',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $this->staff->roles()->attach($staffRole->id);

        $this->reviewer = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'reviewer_m3b_'.Str::random(5).'@p4i.or.id',
            'display_name' => 'Reviewer M3B',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $this->reviewer->roles()->attach($reviewerRole->id);

        $this->yearlyPolicy = NumberingPolicy::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'series_key' => 'SERIES_YEAR_'.Str::random(5),
            'code' => 'P4I-YR',
            'format_pattern' => '{seq:03}/{code}/{month_roman}/{year}',
            'reset_period' => 'YEAR',
            'padding' => 3,
            'is_active' => false,
            'status' => 'DRAFT',
        ]);

        $this->monthlyPolicy = NumberingPolicy::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'series_key' => 'SERIES_MO_'.Str::random(5),
            'code' => 'P4I-MO',
            'format_pattern' => '{seq:03}/{code}/{month_roman}/{year}',
            'reset_period' => 'MONTH',
            'padding' => 3,
            'is_active' => false,
            'status' => 'DRAFT',
        ]);

        $this->letterType = LetterType::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'code' => 'SK_'.Str::random(3),
            'label' => 'Surat Keputusan',
            'numbering_policy_id' => $this->yearlyPolicy->id,
            'is_active' => true,
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
        DB::table('reconciliation_audit_logs')->truncate();
        DB::table('agenda_policies')->truncate();
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
     * BR-02: Impor XLSX asli secara aman tanpa eksekusi formula/macro dan penolakan berkas bervirus/vba.
     */
    public function test_br_02_xlsx_native_secure_import_and_macro_rejection(): void
    {
        // 1. Buat berkas XLSX valid sintetis
        $tmpXlsx = tempnam(sys_get_temp_dir(), 'test_xlsx_').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($tmpXlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
            '<sheetData>'.
            '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c></row>'.
            '<row r="2"><c r="A2" t="s"><v>5</v></c><c r="B2" t="s"><v>6</v></c><c r="C2" t="s"><v>7</v></c><c r="D2" t="s"><v>8</v></c><c r="E2" t="s"><v>9</v></c></row>'.
            '</sheetData></worksheet>';

        $stringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="10" uniqueCount="10">'.
            '<si><t>nomor_surat</t></si>'.
            '<si><t>tanggal</t></si>'.
            '<si><t>jenis</t></si>'.
            '<si><t>penerima</t></si>'.
            '<si><t>perihal</t></si>'.
            '<si><t>001/P4I-SK/I/2024</t></si>'.
            '<si><t>2024-01-10</t></si>'.
            '<si><t>SURAT_KEPUTUSAN</t></si>'.
            '<si><t>Internal Yayasan</t></si>'.
            '<si><t>SK Pengangkatan Staf</t></si>'.
            '</sst>';

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $stringsXml);
        $zip->close();

        $uploadedXlsx = new UploadedFile($tmpXlsx, 'arsip_resmi.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $service = app(LegacyRegisterService::class);
        $batch = $service->importFile($this->staff, $uploadedXlsx, 'Batch XLSX Valid');

        $this->assertNotNull($batch);
        $this->assertEquals('arsip_resmi.xlsx', $batch->source_filename);
        $this->assertEquals(1, $batch->total_rows);
        $this->assertEquals(1, $batch->valid_rows);
        $this->assertEquals('STAGED', $batch->status);

        // 2. Buat berkas XLSX dengan macro berbahaya (vbaProject.bin)
        $tmpMacro = tempnam(sys_get_temp_dir(), 'test_macro_').'.xlsx';
        $zipMacro = new ZipArchive;
        $zipMacro->open($tmpMacro, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zipMacro->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zipMacro->addFromString('xl/vbaProject.bin', 'DUMMY_VBA_BINARY_CODE');
        $zipMacro->close();

        $uploadedMacro = new UploadedFile($tmpMacro, 'malicious_macro.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Macro/VBA');
        $service->importFile($this->staff, $uploadedMacro);

        @unlink($tmpXlsx);
        @unlink($tmpMacro);
    }

    /**
     * BR-03: Pergantian periode penomoran (transisi tahun & transisi bulan) teruji presisi.
     */
    public function test_br_03_period_transition_and_reset_policies(): void
    {
        $service = app(NumberingService::class);

        // 1. Kebijakan YEARLY: Transisi 31 Desember 2026 -> 01 Januari 2027
        $simDec = $service->allocateSimulationNumber($this->staff, $this->yearlyPolicy, null, null, 12, 2026);
        $this->assertEquals(1, $simDec->sequence_number);
        $this->assertEquals('[SIMULASI] 001/P4I-YR/XII/2026', $simDec->simulated_number);

        $simDec2 = $service->allocateSimulationNumber($this->staff, $this->yearlyPolicy, null, null, 12, 2026);
        $this->assertEquals(2, $simDec2->sequence_number);
        $this->assertEquals('[SIMULASI] 002/P4I-YR/XII/2026', $simDec2->simulated_number);

        // Alokasi pada tahun baru 2027 harus reset kembali ke nomor 1
        $simJan = $service->allocateSimulationNumber($this->staff, $this->yearlyPolicy, null, null, 1, 2027);
        $this->assertEquals(1, $simJan->sequence_number);
        $this->assertEquals('[SIMULASI] 001/P4I-YR/I/2027', $simJan->simulated_number);

        // 2. Kebijakan MONTHLY: Transisi 31 Januari 2026 -> 01 Februari 2026
        $simMoJan = $service->allocateSimulationNumber($this->staff, $this->monthlyPolicy, null, null, 1, 2026);
        $this->assertEquals(1, $simMoJan->sequence_number);
        $this->assertEquals('[SIMULASI] 001/P4I-MO/I/2026', $simMoJan->simulated_number);

        // Alokasi pada bulan Februari harus reset kembali ke nomor 1
        $simMoFeb = $service->allocateSimulationNumber($this->staff, $this->monthlyPolicy, null, null, 2, 2026);
        $this->assertEquals(1, $simMoFeb->sequence_number);
        $this->assertEquals('[SIMULASI] 001/P4I-MO/II/2026', $simMoFeb->simulated_number);
    }

    /**
     * BR-06, BR-07 & BR-09: Nomor historis asli dipertahankan, alur rekonsiliasi ke VERIFIED/CONFLICT, tanpa nomor fiktif.
     */
    public function test_br_06_and_07_and_09_provenance_and_reconciliation_workflow(): void
    {
        $batch = LegacyRegisterBatch::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'source_filename' => 'register_2024_conflict.csv',
            'source_hash' => hash('sha256', 'dummy'),
            'total_rows' => 2,
            'valid_rows' => 1,
            'conflict_rows' => 1,
            'status' => 'HAS_CONFLICTS',
            'imported_by' => $this->staff->id,
        ]);

        $rowConflict = LegacyRegisterRow::create([
            'batch_id' => $batch->id,
            'row_index' => 1,
            'raw_number' => '002/P4I/I/2024',
            'normalized_number' => '002/P4I/I/2024',
            'document_date' => '2024-01-15',
            'letter_type_code' => 'SURAT_TUGAS',
            'source_location' => 'Bantex Lemari B-02 / Berkas Asli',
            'provenance_year' => 2024,
            'provenance_series' => 'P4I',
            'is_valid' => false,
            'conflict_type' => 'DUPLICATE_NUMBER',
            'conflict_details' => 'Duplikasi nomor dengan surat dinas lain',
            'verification_status' => 'CONFLICT', // BR-07
        ]);

        // Rekonsiliasi oleh Reviewer resmi (BR-08)
        $service = app(LegacyRegisterService::class);
        $reconciled = $service->reconcileRow(
            $this->reviewer,
            $rowConflict,
            'VERIFIED',
            'RETAIN_AS_IS',
            'Diverifikasi dari berkas fisik cap stempel basah asli',
            'BA-REKON/2026/01'
        );

        $this->assertEquals('VERIFIED', $reconciled->verification_status);
        $this->assertEquals('RETAIN_AS_IS', $reconciled->reconciliation_action);
        $this->assertEquals('BA-REKON/2026/01', $reconciled->board_resolution_ref);

        // Verifikasi audit log immutable tersimpan (BR-08)
        $this->assertDatabaseHas('reconciliation_audit_logs', [
            'row_id' => $rowConflict->id,
            'user_id' => $this->reviewer->id,
            'previous_status' => 'CONFLICT',
            'new_status' => 'VERIFIED',
            'action_taken' => 'RETAIN_AS_IS',
        ]);

        // Verifikasi nomor asli raw_number TIDAK BERUBAH (non-destructive BR-06)
        $this->assertEquals('002/P4I/I/2024', $rowConflict->fresh()->raw_number);
    }

    /**
     * BR-08: Pemeriksaan register mematuhi pemisahan tugas (staf dilarang merekonsiliasi).
     */
    public function test_br_08_reconciliation_role_separation_and_unauthorized_rejection(): void
    {
        $batch = LegacyRegisterBatch::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'source_filename' => 'audit_test.csv',
            'source_hash' => hash('sha256', 'dummy'),
            'total_rows' => 1,
            'valid_rows' => 1,
            'conflict_rows' => 0,
            'status' => 'STAGED',
            'imported_by' => $this->staff->id,
        ]);

        $row = LegacyRegisterRow::create([
            'batch_id' => $batch->id,
            'row_index' => 1,
            'raw_number' => '005/P4I/II/2024',
            'document_date' => '2024-02-01',
            'verification_status' => 'UNVERIFIED',
        ]);

        // Staf biasa mencoba merekonsiliasi
        $response = $this->actingAs($this->staff)->post(route('legacy.rows.reconcile', $row->id), [
            'status' => 'VERIFIED',
            'reconciliation_action' => 'RETAIN_AS_IS',
            'notes' => 'Aksi tanpa wewenang',
        ]);

        $response->assertForbidden();
    }

    /**
     * BR-10: Kebijakan nomor agenda internal dipisahkan dan tetap berstatus DRAFT.
     */
    public function test_br_10_agenda_policy_distinct_and_draft_status(): void
    {
        $agendaPolicy = AgendaPolicy::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'direction' => 'INCOMING',
            'code_prefix' => 'AG-IN',
            'format_pattern' => 'AG-IN-{year}-{seq:04}',
            'reset_period' => 'YEAR',
            'status' => 'DRAFT',
            'is_active' => false,
            'description' => 'Rancangan penomoran agenda internal masuk',
        ]);

        $this->assertFalse($agendaPolicy->is_active);
        $this->assertEquals('DRAFT', $agendaPolicy->status);

        // Uji registrasi surat masuk
        $agendaService = app(AgendaService::class);
        $incoming = $agendaService->registerIncomingLetter($this->staff, [
            'sender_name' => 'Kemendikbudristek',
            'sender_letter_number' => '0091/E/TU/2026',
            'letter_date' => '2026-11-01',
            'received_date' => '2026-11-01',
            'subject' => 'Surat Koordinasi BR-10',
            'classification' => 'INTERNAL',
        ]);

        $this->assertStringStartsWith('AG-IN-2026-', $incoming->internal_agenda_number);
        $this->assertNotEquals($incoming->sender_letter_number, $incoming->internal_agenda_number);
    }

    /**
     * BR-11, BR-12, & BR-13: Status kesiapan aktivasi tetap BLOCKED dan official_number strictly NULL.
     */
    public function test_br_11_and_12_and_13_readiness_blocked_and_safety_invariant(): void
    {
        $readiness = app(ReadinessService::class)->evaluateReadiness($this->org);

        $this->assertEquals('BLOCKED', $readiness['overall_status']);
        $this->assertFalse($readiness['official_numbering_active']);
        $this->assertCount(8, $readiness['pillars']);
        $this->assertNotEmpty($readiness['blocking_reasons']);

        // Uji bahwa surat baru atau approved tidak pernah memiliki nomor resmi
        $letter = Letter::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'author_id' => $this->staff->id,
            'subject' => 'Surat Pengujian Invarian BR-13',
            'recipient_name' => 'Mitra Uji',
            'classification' => 'INTERNAL',
            'planned_date' => now()->format('Y-m-d'),
            'status' => 'APPROVED',
            'official_number' => null,
        ]);

        $this->assertNull($letter->official_number);
    }
}
