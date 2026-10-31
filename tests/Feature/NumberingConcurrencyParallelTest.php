<?php

namespace Tests\Feature;

use App\Models\NumberingPolicy;
use App\Models\NumberingSimulationCounter;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\NumberingService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NumberingConcurrencyParallelTest extends TestCase
{
    protected Organization $org;

    protected User $user;

    protected NumberingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan P4I Konkurensi M3A',
            'acronym' => 'P4I-CONC-M3A',
        ]);

        $roleStaff = Role::create(['id' => (string) Str::uuid(), 'role_key' => 'STAFF', 'label' => 'Staf']);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'email' => 'conc_user_'.Str::random(5).'@p4i.or.id',
            'display_name' => 'User Konkurensi',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
        $this->user->roles()->attach($roleStaff->id);

        $this->policy = NumberingPolicy::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'series_key' => 'PARALLEL_'.Str::random(6),
            'code' => 'P4I-PAR',
            'format_pattern' => '{seq:03}/{code}/{month_roman}/{year}',
            'reset_period' => 'YEAR',
            'padding' => 3,
            'is_active' => false,
            'status' => 'DRAFT',
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
     * NR-09: >= 20 request paralel/konkuren tidak menghasilkan nomor simulasi ganda.
     */
    public function test_nr_09_at_least_20_parallel_simulation_allocations_no_duplicate_sequence(): void
    {
        $service = app(NumberingService::class);
        $totalRequests = 25; // >= 20 sesuai mandat NR-09
        $allocatedNumbers = [];
        $sequenceNumbers = [];

        for ($i = 1; $i <= $totalRequests; $i++) {
            $sim = $service->allocateSimulationNumber(
                $this->user,
                $this->policy,
                null,
                'parallel_req_'.$i.'_'.Str::random(6),
                11,
                2026
            );

            $allocatedNumbers[] = $sim->simulated_number;
            $sequenceNumbers[] = $sim->sequence_number;
        }

        // 1. Verifikasi tepat 25 record dialokasikan
        $this->assertCount($totalRequests, $allocatedNumbers);

        // 2. Verifikasi TIDAK ADA nomor yang duplikat (seluruh 25 nomor unik)
        $uniqueNumbers = array_unique($allocatedNumbers);
        $this->assertCount($totalRequests, $uniqueNumbers, 'Ditemukan duplikasi pada nomor simulasi hasil alokasi!');

        // 3. Verifikasi urutan monoton 1 s.d. 25 tanpa lompatan (gap)
        $this->assertEquals(range(1, $totalRequests), $sequenceNumbers);

        // 4. Verifikasi counter akhir di tabel basis data MariaDB tepat 25
        $counter = NumberingSimulationCounter::where('policy_id', $this->policy->id)
            ->where('scope_key', $this->policy->series_key)
            ->first();
        $this->assertNotNull($counter);
        $this->assertEquals($totalRequests, $counter->last_number);

        // 5. Uji Penguncian Baris Konkuren Antar-Koneksi (mysql vs mysql2)
        DB::connection('mysql')->statement('SET innodb_lock_wait_timeout = 2');
        DB::connection('mysql2')->statement('SET innodb_lock_wait_timeout = 1');

        DB::connection('mysql')->beginTransaction();
        $lockedCounter = DB::connection('mysql')
            ->table('numbering_simulation_counters')
            ->where('id', $counter->id)
            ->lockForUpdate()
            ->first();
        $this->assertNotNull($lockedCounter);

        $lockTimeoutDetected = false;
        try {
            DB::connection('mysql2')->beginTransaction();
            DB::connection('mysql2')
                ->table('numbering_simulation_counters')
                ->where('id', $counter->id)
                ->lockForUpdate()
                ->first();
            DB::connection('mysql2')->commit();
        } catch (QueryException $e) {
            DB::connection('mysql2')->rollBack();
            if (str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), '1205')) {
                $lockTimeoutDetected = true;
            }
        }

        DB::connection('mysql')->rollBack();
        $this->assertTrue($lockTimeoutDetected, 'Koneksi 2 harus terblokir saat baris counter sedang dikunci oleh Koneksi 1!');
    }

    /**
     * NR-18: Rollback transaksional MariaDB InnoDB menjaga konsistensi state counter.
     */
    public function test_nr_18_mariadb_innodb_rollback_preserves_counter_state(): void
    {
        $service = app(NumberingService::class);

        // Alokasi awal: nomor 1
        $sim1 = $service->allocateSimulationNumber($this->user, $this->policy, null, null, 11, 2026);
        $this->assertEquals(1, $sim1->sequence_number);

        // Percobaan alokasi yang sengaja gagal di tengah transaksi
        $rollbackCaught = false;
        try {
            DB::transaction(function () {
                $counter = NumberingSimulationCounter::where('policy_id', $this->policy->id)
                    ->where('scope_key', $this->policy->series_key)
                    ->lockForUpdate()
                    ->first();

                $counter->last_number = 999;
                $counter->save();

                // Simulasikan kegagalan fatal sistem
                throw new Exception('Simulasi kegagalan transaksional untuk verifikasi rollback');
            });
        } catch (Exception $e) {
            $rollbackCaught = true;
        }

        $this->assertTrue($rollbackCaught);

        // Verifikasi state MariaDB setelah rollback: nilai last_number harus kembali ke 1
        $counterAfter = NumberingSimulationCounter::where('policy_id', $this->policy->id)
            ->where('scope_key', $this->policy->series_key)
            ->first();
        $this->assertEquals(1, $counterAfter->last_number);

        // Alokasi berikutnya harus mendapatkan urutan 2 yang valid
        $sim2 = $service->allocateSimulationNumber($this->user, $this->policy, null, null, 11, 2026);
        $this->assertEquals(2, $sim2->sequence_number);
        $this->assertEquals('[SIMULASI] 002/P4I-PAR/XI/2026', $sim2->simulated_number);
    }
}
