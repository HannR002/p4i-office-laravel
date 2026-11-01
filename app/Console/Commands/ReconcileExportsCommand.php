<?php

namespace App\Console\Commands;

use App\Services\DocumentExportService;
use Illuminate\Console\Command;

class ReconcileExportsCommand extends Command
{
    /**
     * Nama dan signature perintah konsol untuk rekonsiliasi arsip ekspor yang terputus mendadak (STG-05, STG-12).
     *
     * @var string
     */
    protected $signature = 'p4i:reconcile-exports {--stale-minutes=15 : Ambang batas menit untuk status PENDING yang dianggap macet} {--org= : Filter UUID organisasi tertentu}';

    /**
     * Deskripsi perintah konsol.
     *
     * @var string
     */
    protected $description = 'Rekonsiliasi ekspor dokumen yang terputus mendadak (crash recovery), pembersihan berkas yatim, dan verifikasi metadata READY.';

    public function handle(DocumentExportService $exportService): int
    {
        $staleMinutes = max(1, (int) $this->option('stale-minutes'));
        $organizationId = $this->option('org') ?: null;

        $report = $exportService->reconcileInterruptedExports($staleMinutes, $organizationId);

        $this->info('Rekonsiliasi Ekspor Dokumen P4I Office Selesai:');
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
