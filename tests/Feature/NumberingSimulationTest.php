<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterType;
use App\Models\NumberingPolicy;
use App\Models\NumberingSimulationCounter;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class NumberingSimulationTest extends TestCase
{
    protected Organization $org;

    protected User $admin;

    protected User $staff;

    protected LetterType $letterType;

    protected NumberingPolicy $policy;

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

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'admin_test_'.Str::random(5).'@p4i.or.id',
            'display_name' => 'Admin Pengujian',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $this->admin->roles()->attach($adminRole->id);

        $this->staff = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'staff_test_'.Str::random(5).'@p4i.or.id',
            'display_name' => 'Staf Pengujian',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $this->staff->roles()->attach($staffRole->id);

        $this->policy = NumberingPolicy::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'series_key' => 'TEST_SERIES_'.Str::random(5),
            'code' => 'P4I-TEST',
            'format_pattern' => '{seq:03}/{code}/{month_roman}/{year}',
            'reset_period' => 'YEAR',
            'padding' => 3,
            'is_active' => false,
            'status' => 'DRAFT',
        ]);

        $this->letterType = LetterType::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'code' => 'SURAT_TEST',
            'label' => 'Surat Pengujian',
            'numbering_policy_id' => $this->policy->id,
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
     * NR-01: Admin dapat membuat rancangan numbering policy tanpa mengaktifkannya.
     */
    public function test_nr_01_admin_can_create_draft_policy_without_activating(): void
    {
        $response = $this->actingAs($this->admin)->post(route('numbering.policies.store'), [
            'series_key' => 'SK_TEST_'.Str::random(4),
            'code' => 'P4I-SK',
            'format_pattern' => '{seq:04}/{code}/{month_roman}/{year}',
            'reset_period' => 'YEAR',
            'padding' => 4,
            'description' => 'Draf SK Pengurus',
        ]);

        $response->assertRedirect(route('numbering.policies.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('numbering_policies', [
            'organization_id' => $this->org->id,
            'code' => 'P4I-SK',
            'is_active' => false,
            'status' => 'DRAFT',
            'padding' => 4,
        ]);
    }

    /**
     * NR-02: Tersedia preview string nomor dari data simulasi dan aturan yang dipilih, bertanda [SIMULASI].
     */
    public function test_nr_02_simulation_preview_always_labeled_simulasi_and_isolated(): void
    {
        $service = app(NumberingService::class);
        $preview = $service->previewSimulation($this->policy, 1, 11, 2026);

        $this->assertStringStartsWith('[SIMULASI]', $preview);
        $this->assertEquals('[SIMULASI] 001/P4I-TEST/XI/2026', $preview);

        // Uji lewat HTTP endpoint
        $response = $this->actingAs($this->staff)->postJson(route('numbering.preview'), [
            'policy_id' => $this->policy->id,
            'seq' => 5,
            'month' => 4,
            'year' => 2026,
        ]);

        $response->assertOk();
        $response->assertJson([
            'is_simulation' => true,
            'preview' => '[SIMULASI] 005/P4I-TEST/IV/2026',
        ]);
    }

    /**
     * NR-03: Format derived dynamically from format_pattern, not hardcoded.
     */
    public function test_nr_03_format_derived_dynamically_from_pattern(): void
    {
        $customPolicy = NumberingPolicy::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'series_key' => 'CUSTOM_'.Str::random(4),
            'code' => 'TGS',
            'format_pattern' => 'P4I/{year}/{month}/{code}-{seq:05}',
            'reset_period' => 'MONTH',
            'padding' => 5,
            'is_active' => false,
        ]);

        $service = app(NumberingService::class);
        $output = $service->previewSimulation($customPolicy, 42, 8, 2026);

        $this->assertEquals('[SIMULASI] P4I/2026/08/TGS-00042', $output);
    }

    /**
     * NR-04: Roman month conversion (I..XII) and invalid month rejection.
     */
    public function test_nr_04_roman_month_conversion_and_validation(): void
    {
        $service = app(NumberingService::class);

        $expected = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        foreach ($expected as $m => $roman) {
            $this->assertEquals($roman, $service->toRomanMonth($m));
        }

        $this->expectException(InvalidArgumentException::class);
        $service->toRomanMonth(13);
    }

    /**
     * NR-10: Idempotent allocation using idempotency_key.
     */
    public function test_nr_10_idempotent_allocation_does_not_double_increment(): void
    {
        $service = app(NumberingService::class);
        $key = 'idemp_key_'.Str::random(10);

        $sim1 = $service->allocateSimulationNumber($this->staff, $this->policy, null, $key, 11, 2026);
        $this->assertEquals(1, $sim1->sequence_number);
        $this->assertEquals('[SIMULASI] 001/P4I-TEST/XI/2026', $sim1->simulated_number);

        // Panggilan kedua dengan kunci yang sama
        $sim2 = $service->allocateSimulationNumber($this->staff, $this->policy, null, $key, 11, 2026);
        $this->assertEquals($sim1->id, $sim2->id);
        $this->assertEquals(1, $sim2->sequence_number);

        // Verifikasi counter di basis data tetap 1 (tidak naik ganda)
        $counter = NumberingSimulationCounter::where('policy_id', $this->policy->id)
            ->where('scope_key', $this->policy->series_key)
            ->first();
        $this->assertEquals(1, $counter->last_number);
    }

    /**
     * NR-15: official_number remains strictly NULL after simulation allocation.
     */
    public function test_nr_15_official_number_remains_strictly_null(): void
    {
        $letter = Letter::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'author_id' => $this->staff->id,
            'subject' => 'Surat Uji Invarian',
            'recipient_name' => 'Mitra Riset',
            'classification' => 'INTERNAL',
            'planned_date' => now()->format('Y-m-d'),
            'status' => 'APPROVED',
            'official_number' => null,
        ]);

        $service = app(NumberingService::class);
        $sim = $service->allocateSimulationNumber($this->staff, $this->policy, $letter);

        $letter->refresh();
        $this->assertNull($letter->official_number);
        $this->assertNotNull($sim->simulated_number);
        $this->assertStringStartsWith('[SIMULASI]', $sim->simulated_number);
    }
}
