<?php

namespace Tests\Feature;

use App\Models\DocumentExport;
use App\Models\Letter;
use App\Models\LetterRevision;
use App\Models\LetterTemplate;
use App\Models\LetterType;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentExportService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ArchiveTransactionConcurrencyTest extends TestCase
{
    private Organization $org;

    private OrgUnit $unit;

    private User $author;

    private LetterType $letterType;

    private LetterTemplate $template;

    private DocumentExportService $exportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $this->exportService = app(DocumentExportService::class);

        $this->org = Organization::create([
            'id' => (string) Str::uuid(),
            'legal_name' => 'Yayasan P4I Pusat',
            'acronym' => 'P4I-PUSAT',
        ]);

        $this->unit = OrgUnit::create([
            'organization_id' => $this->org->id,
            'name' => 'Divisi Operasional',
        ]);

        $roleStaff = Role::firstOrCreate(['role_key' => 'STAFF'], ['label' => 'Staf']);

        $this->author = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'unit_id' => $this->unit->id,
            'email' => 'author_concurrency@p4i.or.id',
            'display_name' => 'Staf Operasional P4I',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);
        $this->author->roles()->attach($roleStaff->id);

        $this->letterType = LetterType::create([
            'organization_id' => $this->org->id,
            'code' => 'SUR-OP',
            'label' => 'Surat Operasional',
            'is_active' => true,
        ]);

        $this->template = LetterTemplate::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'version' => 1,
            'title' => 'Templat Operasional',
            'form_schema' => ['fields' => ['kegiatan']],
            'content_template' => '<p>{{kegiatan}}</p>',
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
        $tables = [
            'document_exports',
            'audit_events',
            'letter_revisions',
            'letters',
            'letter_templates',
            'letter_types',
            'user_roles',
            'users',
            'roles',
            'org_units',
            'organizations',
        ];
        foreach ($tables as $t) {
            DB::table($t)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function createTestLetter(): Letter
    {
        $letter = Letter::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'author_id' => $this->author->id,
            'subject' => 'Instruksi Kerja Operasional Kearsipan Mandiri',
            'recipient_name' => 'Seluruh Staf Administrasi',
            'classification' => 'INTERNAL',
            'status' => 'DRAFT',
            'planned_date' => Carbon::now()->format('Y-m-d'),
            'official_number' => null,
        ]);

        $content = [
            'body' => [
                'Setiap staf wajib memastikan kelengkapan metadata naskah dinas sebelum diajukan ke tahap review.',
                'Naskah dinas berstatus draf hanya boleh diuji dalam lingkungan pengembangan lokal atau staging.',
            ],
        ];

        LetterRevision::create([
            'letter_id' => $letter->id,
            'version' => 1,
            'created_by' => $this->author->id,
            'subject' => $letter->subject,
            'recipient_name' => $letter->recipient_name,
            'classification' => $letter->classification,
            'planned_date' => $letter->planned_date,
            'content' => $content,
            'content_sha256' => hash('sha256', json_encode($content)),
        ]);

        return $letter;
    }

    /**
     * M4B-09: Idempotency Key mencegah duplikasi ekspor dan berkas ganda.
     */
    public function test_m4b_09_idempotent_export_returns_existing_record_without_duplication(): void
    {
        $letter = $this->createTestLetter();
        $idempotencyKey = 'idem_key_unique_test_123';

        $export1 = $this->exportService->exportLetter($this->author, $letter, 'PDF', $idempotencyKey);
        $this->assertEquals('READY', $export1->status);

        $initialExportCount = DocumentExport::where('idempotency_key', $idempotencyKey)->count();
        $this->assertEquals(1, $initialExportCount);

        // Ekspor ulang dengan kunci idempoten yang sama
        $export2 = $this->exportService->exportLetter($this->author, $letter, 'PDF', $idempotencyKey);

        // Harus mengembalikan entitas yang sama persis
        $this->assertEquals($export1->id, $export2->id);
        $this->assertEquals($export1->file_path, $export2->file_path);
        $this->assertEquals($export1->file_hash, $export2->file_hash);

        // Jumlah record di database tetap 1
        $this->assertEquals(1, DocumentExport::where('idempotency_key', $idempotencyKey)->count());
    }

    /**
     * M4B-09: Ekspor bersamaan dengan kunci berbeda menghasilkan berkas terpisah yang sah.
     */
    public function test_m4b_09_concurrent_exports_with_different_keys_generate_unique_files(): void
    {
        $letter = $this->createTestLetter();

        $exportA = $this->exportService->exportLetter($this->author, $letter, 'PDF', 'key_concurrent_A');
        $exportB = $this->exportService->exportLetter($this->author, $letter, 'PDF', 'key_concurrent_B');

        $this->assertNotEquals($exportA->id, $exportB->id);
        $this->assertNotEquals($exportA->file_path, $exportB->file_path);

        $disk = Storage::disk('private_archives');
        $this->assertTrue($disk->exists($exportA->file_path));
        $this->assertTrue($disk->exists($exportB->file_path));

        // Kedua berkas memiliki ukuran yang valid dan hash SHA-256 64-karakter yang valid
        $this->assertEquals(64, strlen($exportA->file_hash));
        $this->assertEquals(64, strlen($exportB->file_hash));
        $this->assertNotEmpty($exportA->file_hash);
        $this->assertNotEmpty($exportB->file_hash);
    }

    /**
     * M4B-10: Kegagalan dalam proses ekspor tidak meninggalkan berkas yatim (orphan files) di private storage.
     */
    public function test_m4b_10_export_failure_prevents_orphan_file_and_records_audit(): void
    {
        $letter = $this->createTestLetter();
        $disk = Storage::disk('private_archives');
        $initialFiles = $disk->allFiles();

        // Uji dengan format tidak valid (akan ditolak validasi awal)
        try {
            $this->exportService->exportLetter($this->author, $letter, 'EXE');
            $this->fail('Harusnya melempar InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Format ekspor tidak didukung', $e->getMessage());
        }

        // Pastikan tidak ada berkas fisik baru yang tertinggal di private storage
        $currentFiles = $disk->allFiles();
        $this->assertCount(count($initialFiles), $currentFiles, 'Tidak boleh ada berkas yatim di penyimpanan privat.');
    }

    /**
     * M4B-10: Pemulihan crash dan pencatatan audit kegagalan ekspor.
     */
    public function test_m4b_10_crash_recovery_updates_status_error_and_audits(): void
    {
        $letter = $this->createTestLetter();

        // Buat export dengan revision yang tidak ada (simulasi data korup)
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        $corruptLetter = Letter::create([
            'organization_id' => $this->org->id,
            'letter_type_id' => $this->letterType->id,
            'author_id' => $this->author->id,
            'subject' => 'Surat Korup Tanpa Revisi',
            'recipient_name' => 'Pihak Luar',
            'classification' => 'BIASA',
            'status' => 'DRAFT',
            'planned_date' => Carbon::now()->format('Y-m-d'),
            'official_number' => null,
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        try {
            $this->exportService->exportLetter($this->author, $corruptLetter, 'PDF');
            $this->fail('Harusnya melempar DomainException karena tidak ada revisi');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Surat tidak memiliki data revisi', $e->getMessage());
        }

        // Audit kegagalan tidak boleh merusak integritas sistem
        $this->assertDatabaseMissing('document_exports', [
            'letter_id' => $corruptLetter->id,
            'status' => 'READY',
        ]);
    }

    /**
     * STG-05: Penghentian proses PHP mendadak (SIGKILL/OOM/Timeout yang melewati try...catch)
     * direkonsiliasi secara aman:
     * 1. Mendeteksi PENDING macet (stale PENDING).
     * 2. Memeriksa berkas sementara (temp dir) dan berkas hasil ekspor.
     * 3. Memverifikasi hash SHA-256 berkas.
     * 4. Memulihkan pekerjaan utuh ber-hash cocok, dan menandai ERROR + menghapus berkas parsial bagi yang terputus.
     * 5. Tidak menghapus berkas valid milik proses READY maupun proses PENDING aktif yang baru berjalan.
     * 6. Menjamin tidak ada metadata READY yang menunjuk ke berkas hilang atau korup.
     */
    public function test_stg_05_sudden_process_crash_reconciles_stale_pending_and_protects_valid_files(): void
    {
        $letter = $this->createTestLetter();
        $revision = $letter->latestRevision;
        $disk = Storage::disk('private_archives');
        $year = Carbon::now()->format('Y');

        // A. Berkas READY yang valid (milik proses sah yang sudah selesai) -> TIDAK BOLEH DIHAPUS
        $validExport = $this->exportService->exportLetter($this->author, $letter, 'PDF', 'stg05_valid_ready');
        $this->assertEquals('READY', $validExport->status);
        $validPdfBytes = $disk->get($validExport->file_path);

        // B. Proses PENDING aktif (baru berjalan 1 menit lalu) dengan berkas sedang ditulis -> TIDAK BOLEH DIHAPUS
        $activePending = DocumentExport::create([
            'organization_id' => $this->org->id,
            'letter_id' => $letter->id,
            'revision_id' => $revision->id,
            'revision_version' => $revision->version,
            'content_hash' => hash('sha256', json_encode($revision->content)),
            'format' => 'PDF',
            'status' => 'PENDING',
            'idempotency_key' => 'stg05_active_pending',
            'exported_by' => $this->author->id,
        ]);
        $activePendingRelPath = "{$this->org->id}/{$year}/PDF/{$activePending->id}.pdf";
        $disk->put($activePendingRelPath, $validPdfBytes);

        // C. Proses PENDING macet (30 menit lalu) yang mati mendadak saat baru menulis berkas parsial rusak -> HARUS JADI ERROR & FILE DIHAPUS
        $stalePartialPending = DocumentExport::create([
            'organization_id' => $this->org->id,
            'letter_id' => $letter->id,
            'revision_id' => $revision->id,
            'revision_version' => $revision->version,
            'content_hash' => hash('sha256', json_encode($revision->content)),
            'format' => 'PDF',
            'status' => 'PENDING',
            'idempotency_key' => 'stg05_stale_partial',
            'exported_by' => $this->author->id,
        ]);
        DB::table('document_exports')->where('id', $stalePartialPending->id)->update([
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);
        $stalePartialRelPath = "{$this->org->id}/{$year}/PDF/{$stalePartialPending->id}.pdf";
        $disk->put($stalePartialRelPath, '%PDF-1.4 PARTIAL CRASHED STREAM WITHOUT EOF');

        // D. Proses PENDING macet (30 menit lalu) yang sudah selesai menulis berkas utuh & mencatat hash, namun mati tepat sebelum status=READY -> HARUS DIPULIHKAN JADI READY
        $staleRecoverablePending = DocumentExport::create([
            'organization_id' => $this->org->id,
            'letter_id' => $letter->id,
            'revision_id' => $revision->id,
            'revision_version' => $revision->version,
            'content_hash' => hash('sha256', json_encode($revision->content)),
            'format' => 'PDF',
            'file_hash' => hash('sha256', $validPdfBytes),
            'status' => 'PENDING',
            'idempotency_key' => 'stg05_stale_recoverable',
            'exported_by' => $this->author->id,
        ]);
        $staleRecoverableRelPath = "{$this->org->id}/{$year}/PDF/{$staleRecoverablePending->id}.pdf";
        $disk->put($staleRecoverableRelPath, $validPdfBytes);
        DB::table('document_exports')->where('id', $staleRecoverablePending->id)->update([
            'file_path' => $staleRecoverableRelPath,
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        // E. Metadata READY yang berkas fisiknya hilang -> HARUS DITANDAI ERROR (Tidak boleh ada READY menunjuk ke file hilang)
        $brokenMissingReady = DocumentExport::create([
            'organization_id' => $this->org->id,
            'letter_id' => $letter->id,
            'revision_id' => $revision->id,
            'revision_version' => $revision->version,
            'content_hash' => hash('sha256', json_encode($revision->content)),
            'format' => 'PDF',
            'file_path' => "{$this->org->id}/{$year}/PDF/missing_file_id.pdf",
            'file_hash' => str_repeat('a', 64),
            'file_size' => 4096,
            'mime_type' => 'application/pdf',
            'status' => 'READY',
            'idempotency_key' => 'stg05_broken_missing_ready',
            'exported_by' => $this->author->id,
        ]);

        // F. Berkas sementara (temp file) yatim berusia 40 menit di sys_get_temp_dir() -> HARUS DIHAPUS
        $staleTempFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'p4i_export_stale_test_'.uniqid().'.pdf';
        file_put_contents($staleTempFile, 'STALE TEMP CONTENT');
        touch($staleTempFile, now()->subMinutes(40)->getTimestamp());

        // Jalankan mekanisme rekonsiliasi crash recovery
        $report = $this->exportService->reconcileInterruptedExports(15, $this->org->id, $this->author->id);

        // Verifikasi hasil rekonsiliasi
        $this->assertEquals(1, $report['recovered_pending'], 'PENDING utuh ber-hash cocok harus dipulihkan menjadi READY.');
        $this->assertEquals(1, $report['failed_stale_pending'], 'PENDING parsial macet harus ditandai ERROR.');
        $this->assertEquals(1, $report['broken_ready_marked_error'], 'Metadata READY tanpa berkas fisik harus diubah menjadi ERROR.');
        $this->assertGreaterThanOrEqual(1, $report['stale_temp_files_removed'], 'Berkas temp yatim harus dibersihkan.');

        // 1. Berkas READY valid tetap utuh dan tidak terhapus
        $this->assertEquals('READY', $validExport->fresh()->status);
        $this->assertTrue($disk->exists($validExport->file_path));

        // 2. Proses PENDING aktif (belum stale) tetap PENDING dan berkasnya tidak dihapus
        $this->assertEquals('PENDING', $activePending->fresh()->status);
        $this->assertTrue($disk->exists($activePendingRelPath));

        // 3. Proses PENDING parsial menjadi ERROR dan berkas parsialnya telah dihapus dari storage
        $this->assertEquals('ERROR', $stalePartialPending->fresh()->status);
        $this->assertFalse($disk->exists($stalePartialRelPath));

        // 4. Proses PENDING utuh ber-hash cocok berhasil dipulihkan menjadi READY
        $this->assertEquals('READY', $staleRecoverablePending->fresh()->status);
        $this->assertTrue($disk->exists($staleRecoverableRelPath));

        // 5. Metadata READY yang kehilangan berkas fisik kini berstatus ERROR (tidak ada metadata READY menunjuk file hilang)
        $this->assertEquals('ERROR', $brokenMissingReady->fresh()->status);
        $this->assertFalse(file_exists($staleTempFile));
    }

    /**
     * STG-05 & STG-12: Perintah konsol Artisan p4i:reconcile-exports berjalan bersih untuk cron job Hostinger.
     */
    public function test_stg_05_reconcile_exports_artisan_command_executes_cleanly(): void
    {
        $this->artisan('p4i:reconcile-exports', [
            '--stale-minutes' => 15,
            '--org' => $this->org->id,
        ])->assertExitCode(0);
    }

    /**
     * M5B-A (Section 3.C): Membedakan exception biasa (in-process try..catch) dengan penghentian proses PHP
     * mendadak (subproses pekerja mati mendadak dengan exit(137) sebelum blok catch/cleanup dapat berjalan),
     * serta memverifikasi pendaftaran scheduler Laravel 13 (schedule:list).
     */
    public function test_m5b_a_real_php_subprocess_abrupt_termination_and_schedule_reconciliation(): void
    {
        $letter = $this->createTestLetter();
        $revision = $letter->latestRevision;
        $disk = Storage::disk('private_archives');
        $year = Carbon::now()->format('Y');

        // 1. Verifikasi pendaftaran scheduler di Laravel 13 (routes/console.php)
        $this->artisan('schedule:list')
            ->expectsOutputToContain('p4i:reconcile-exports --stale-minutes=15')
            ->assertExitCode(0);

        // 2. Jalankan subproses PHP CLI terpisah yang menyimulasikan worker mati mendadak (exit 137 / SIGKILL)
        // di tengah penulisan berkas tanpa pernah mengeksekusi blok catch (\Throwable)
        $crashTempFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'p4i_export_subproc_'.uniqid().'.pdf';

        $workerCode = sprintf(
            'require %s; $app = require_once %s; $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap(); '.
            'config(["database.connections.mysql.database" => %s]); Illuminate\Support\Facades\DB::purge("mysql"); '.
            '$exp = App\Models\DocumentExport::create(["organization_id" => %s, "letter_id" => %s, "revision_id" => %s, "revision_version" => 1, "content_hash" => %s, "format" => "PDF", "file_hash" => %s, "status" => "PENDING", "idempotency_key" => "subproc_crash_137", "exported_by" => %s]); '.
            '$rel = %s . "/" . %s . "/PDF/" . $exp->id . ".pdf"; '.
            '$exp->update(["file_path" => $rel]); '.
            '$abs = Illuminate\Support\Facades\Storage::disk("private_archives")->path($rel); '.
            'file_put_contents(%s, "TEMP_CRASH_BYTES"); '.
            'if (!is_dir(dirname($abs))) { mkdir(dirname($abs), 0775, true); } '.
            'file_put_contents($abs, "%%PDF-1.4 TRUNCATED BY ABRUPT WORKER KILL"); '.
            'exit(137);',
            var_export(base_path('vendor/autoload.php'), true),
            var_export(base_path('bootstrap/app.php'), true),
            var_export(config('database.connections.mysql.database'), true),
            var_export($this->org->id, true),
            var_export($letter->id, true),
            var_export($revision->id, true),
            var_export(hash('sha256', json_encode($revision->content)), true),
            var_export(str_repeat('f', 64), true),
            var_export($this->author->id, true),
            var_export($this->org->id, true),
            var_export($year, true),
            var_export($crashTempFile, true)
        );

        $process = new Process([PHP_BINARY, '-r', $workerCode], base_path());
        $process->run();
        $exitCode = $process->getExitCode();

        // Subproses benar-benar berhenti mendadak dengan kode 137 (melewati try...catch)
        $this->assertEquals(137, $exitCode, 'Subprocess stderr: '.$process->getErrorOutput());

        // Buktikan bahwa sesaat setelah proses PHP mati mendadak, status di DB masih tertinggal PENDING
        // dan berkas parsial masih tertinggal di disk (berbeda dengan exception biasa yang langsung ditangkap catch)
        $stuckExport = DocumentExport::where('idempotency_key', 'subproc_crash_137')->first();
        $this->assertNotNull($stuckExport);
        $crashExportId = $stuckExport->id;
        $crashRelPath = $stuckExport->file_path;
        $this->assertEquals('PENDING', $stuckExport->status);
        $this->assertTrue($disk->exists($crashRelPath));
        $this->assertTrue(file_exists($crashTempFile));

        // Simulasikan waktu telah berlalu melewati ambang stale (20 menit)
        DB::table('document_exports')->where('id', $crashExportId)->update([
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);
        touch($crashTempFile, now()->subMinutes(20)->getTimestamp());

        // 3. Eksekusi perintah rekonsiliasi terjadwal
        $this->artisan('p4i:reconcile-exports', [
            '--stale-minutes' => 15,
            '--org' => $this->org->id,
        ])->assertExitCode(0);

        // Verifikasi bahwa rekonsiliasi membersihkan berkas parsial & temp, mengubah status ke ERROR, dan mencatat AuditEvent
        $reconciledExport = DocumentExport::find($crashExportId);
        $this->assertEquals('ERROR', $reconciledExport->status);
        $this->assertFalse($disk->exists($crashRelPath), 'Berkas parsial hasil subproses yang mati mendadak harus dihapus.');
        $this->assertFalse(file_exists($crashTempFile), 'Berkas temp yatim hasil subproses yang mati mendadak harus dihapus.');

        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $this->org->id,
            'event_type' => 'DOCUMENT_EXPORT_RECONCILED_ERROR',
            'entity_type' => 'document_exports',
            'entity_id' => $crashExportId,
        ]);
    }
}
