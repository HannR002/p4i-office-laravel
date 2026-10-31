<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambah metadata provenance dan tindakan rekonsiliasi pada legacy_register_rows (BR-06, BR-07, BR-08)
        Schema::table('legacy_register_rows', function (Blueprint $table) {
            $table->string('source_location', 255)->nullable()->after('subject');
            $table->integer('provenance_year')->nullable()->after('source_location');
            $table->string('provenance_series', 64)->nullable()->after('provenance_year');
            $table->string('reconciliation_action', 32)->nullable()->after('verification_status');
            $table->text('reconciliation_notes')->nullable()->after('reconciliation_action');
            $table->string('board_resolution_ref', 255)->nullable()->after('reconciliation_notes');
        });

        // 2. Tabel Kebijakan Buku Agenda (BR-10 - Status Tetap DRAFT non-resmi)
        Schema::create('agenda_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('direction', 16)->default('INCOMING'); // INCOMING / OUTGOING
            $table->string('code_prefix', 32)->default('AG-IN');
            $table->string('format_pattern', 128)->default('AG-IN-{year}-{seq:04}');
            $table->string('reset_period', 16)->default('YEAR');
            $table->string('status', 32)->default('DRAFT'); // DRAFT / RATIFIED
            $table->boolean('is_active')->default(false); // SAFETY: Non-aktif
            $table->string('approved_decree_ref', 255)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'direction', 'code_prefix'], 'ap_org_dir_prefix_unique');
        });

        // 3. Tabel Riwayat Audit Rekonsiliasi Arsip Historis (BR-08)
        Schema::create('reconciliation_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('row_id')->constrained('legacy_register_rows')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_status', 32);
            $table->string('new_status', 32);
            $table->string('action_taken', 64);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['row_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_audit_logs');
        Schema::dropIfExists('agenda_policies');

        Schema::table('legacy_register_rows', function (Blueprint $table) {
            $table->dropColumn([
                'source_location',
                'provenance_year',
                'provenance_series',
                'reconciliation_action',
                'reconciliation_notes',
                'board_resolution_ref',
            ]);
        });
    }
};
