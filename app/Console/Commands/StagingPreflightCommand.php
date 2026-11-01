<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StagingPreflightCommand extends Command
{
    /**
     * Signature perintah pemeriksa kompatibilitas lingkungan pra-staging Hostinger (STG-08, STG-09, STG-10, STG-15).
     *
     * @var string
     */
    protected $signature = 'p4i:staging-preflight {--json : Cetak hasil pemeriksaan dalam format JSON}';

    /**
     * Deskripsi perintah konsol.
     *
     * @var string
     */
    protected $description = 'Menjalankan pemeriksaan kesiapan teknis pra-staging (PHP, MariaDB InnoDB, Triggers, Private Storage, dan Safety Guards).';

    public function handle(): int
    {
        $requiredExtensions = ['pdo_mysql', 'mbstring', 'xml', 'zip', 'gd', 'openssl', 'fileinfo'];
        $extensionsReport = [];
        $allExtensionsLoaded = true;

        foreach ($requiredExtensions as $ext) {
            $loaded = extension_loaded($ext);
            $extensionsReport[$ext] = $loaded;
            if (! $loaded) {
                $allExtensionsLoaded = false;
            }
        }

        // Database & InnoDB check
        $dbVersion = null;
        $innoDbSupported = false;
        $triggersPresent = [];
        $expectedTriggers = [
            'trg_letter_revisions_no_update',
            'trg_letter_revisions_no_delete',
            'trg_audit_events_no_update',
            'trg_audit_events_no_delete',
        ];

        try {
            $versionRow = DB::selectOne('SELECT VERSION() AS ver');
            $dbVersion = $versionRow->ver ?? 'UNKNOWN';

            $engines = DB::select('SHOW ENGINES');
            foreach ($engines as $eng) {
                if (strtoupper($eng->Engine ?? '') === 'INNODB' && in_array(strtoupper($eng->Support ?? ''), ['YES', 'DEFAULT'], true)) {
                    $innoDbSupported = true;
                }
            }

            $dbName = DB::connection()->getDatabaseName();
            $triggerRows = DB::select('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?', [$dbName]);
            $foundTriggerNames = array_map(fn ($r) => $r->TRIGGER_NAME, $triggerRows);

            foreach ($expectedTriggers as $trg) {
                $triggersPresent[$trg] = in_array($trg, $foundTriggerNames, true);
            }
        } catch (\Throwable $e) {
            $dbVersion = 'ERROR: '.$e->getMessage();
        }

        // Private Storage Isolation Check
        $privateRoot = realpath(Storage::disk('private_archives')->path('')) ?: Storage::disk('private_archives')->path('');
        $publicRoot = realpath(public_path()) ?: public_path();
        $privateOutsidePublic = ! str_starts_with(
            strtolower(str_replace('\\', '/', $privateRoot)),
            strtolower(str_replace('\\', '/', $publicRoot))
        );

        // Safety Guards Check
        $officialNumberingActive = (bool) config('features.official_numbering', false) || (bool) config('p4i.official_numbering_active', false);
        $nonNullOfficialNumbers = 0;
        try {
            $nonNullOfficialNumbers = DB::table('letters')->whereNotNull('official_number')->count();
        } catch (\Throwable) {
            $nonNullOfficialNumbers = -1;
        }

        $report = [
            'php_version' => PHP_VERSION,
            'php_compatible' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'extensions' => $extensionsReport,
            'all_extensions_loaded' => $allExtensionsLoaded,
            'db_version' => $dbVersion,
            'innodb_supported' => $innoDbSupported,
            'immutability_triggers' => $triggersPresent,
            'all_triggers_present' => count(array_filter($triggersPresent)) === count($expectedTriggers),
            'private_storage_outside_public' => $privateOutsidePublic,
            'official_numbering_active' => $officialNumberingActive,
            'non_null_official_numbers' => $nonNullOfficialNumbers,
            'safety_invariants_intact' => (! $officialNumberingActive && $nonNullOfficialNumbers === 0),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'staging_deployment_status' => 'LOCAL_PREPARATION_COMPLETE_HOSTINGER_STAGING_BLOCKED',
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('=== P4I OFFICE STAGING PREFLIGHT CHECK ===');
            $this->line('PHP Version                : '.$report['php_version'].' ('.($report['php_compatible'] ? 'OK' : 'FAIL').')');
            $this->line('Required Extensions        : '.($report['all_extensions_loaded'] ? 'ALL LOADED' : 'MISSING EXTENSIONS'));
            $this->line('Database Version           : '.$report['db_version']);
            $this->line('InnoDB Engine              : '.($report['innodb_supported'] ? 'SUPPORTED' : 'NOT SUPPORTED'));
            $this->line('Immutability Triggers (4)  : '.($report['all_triggers_present'] ? 'ACTIVE' : 'INCOMPLETE'));
            $this->line('Private Storage Isolated   : '.($report['private_storage_outside_public'] ? 'YES' : 'NO'));
            $this->line('Safety Invariants Intact   : '.($report['safety_invariants_intact'] ? 'YES (OFF & NULL)' : 'VIOLATION'));
            $this->line('Staging Status             : '.$report['staging_deployment_status']);
        }

        return ($report['php_compatible'] && $report['all_extensions_loaded'] && $report['innodb_supported'] && $report['private_storage_outside_public'] && $report['safety_invariants_intact'])
            ? self::SUCCESS
            : self::FAILURE;
    }
}
