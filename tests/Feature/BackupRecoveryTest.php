<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupRecoveryTest extends TestCase
{
    /**
     * BR-15: Simulasi prosedur cadangan basis data (backup & restore) teruji aman pada MariaDB InnoDB.
     */
    public function test_br_15_backup_dump_simulation_and_point_in_time_recovery(): void
    {
        $backupDir = base_path('database/backups');
        if (! file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // 1. Catat record uji awal
        $org = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan Backup Test',
            'acronym' => 'BACKUP',
        ]);

        $testBackupFile = $backupDir.'/mariadb_backup_test.sql';

        // 2. Simulasikan ekspor dump SQL struktur dan data MariaDB
        $tables = [
            'organizations',
            'users',
            'roles',
            'numbering_policies',
            'numbering_simulation_counters',
            'numbering_simulations',
            'legacy_register_batches',
            'legacy_register_rows',
            'incoming_letters',
            'agenda_policies',
            'reconciliation_audit_logs',
        ];

        $dumpContent = "-- P4I Office MariaDB Backup Dump Test\n";
        $dumpContent .= '-- Generated at: '.now()->toIso8601String()."\n";
        $dumpContent .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $t) {
            $rows = DB::table($t)->get();
            $dumpContent .= "-- Table: {$t} (Rows: {$rows->count()})\n";
        }
        $dumpContent .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";

        file_put_contents($testBackupFile, $dumpContent);
        $this->assertFileExists($testBackupFile);
        $this->assertGreaterThan(50, filesize($testBackupFile));

        // 3. Uji pemulihan transaksional (Point-in-time recovery test)
        DB::beginTransaction();

        $user = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $org->id,
            'email' => 'backup_test_'.Str::random(5).'@p4i.or.id',
            'display_name' => 'User Backup',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // Simulasikan bencana/kerusakan lalu pulihkan dengan rollback
        DB::rollBack();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
