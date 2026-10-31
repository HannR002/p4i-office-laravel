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
        // 1. Tambah penanda sampel pada legacy_register_batches (Stage 3 & SC-07)
        Schema::table('legacy_register_batches', function (Blueprint $table) {
            $table->boolean('is_sample')->default(false)->after('status');
        });

        // 2. Tambah kolom pengusul rekonsiliasi (Segregation of Duties - Stage 3 & SC-08)
        Schema::table('legacy_register_rows', function (Blueprint $table) {
            $table->foreignUuid('proposed_by')->nullable()->after('reconciliation_action')->constrained('users')->nullOnDelete();
            $table->timestamp('proposed_at')->nullable()->after('proposed_by');
            $table->string('proposed_action', 64)->nullable()->after('proposed_at');
            $table->text('proposed_notes')->nullable()->after('proposed_action');
        });

        // 3. Penguatan log audit rekonsiliasi (append-only audit trail)
        Schema::table('reconciliation_audit_logs', function (Blueprint $table) {
            $table->foreignUuid('proposer_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignUuid('verifier_id')->nullable()->after('proposer_id')->constrained('users')->nullOnDelete();
            $table->string('board_resolution_ref', 255)->nullable()->after('action_taken');
            $table->text('rationale')->nullable()->after('board_resolution_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['proposer_id']);
            $table->dropForeign(['verifier_id']);
            $table->dropColumn(['proposer_id', 'verifier_id', 'board_resolution_ref', 'rationale']);
        });

        Schema::table('legacy_register_rows', function (Blueprint $table) {
            $table->dropForeign(['proposed_by']);
            $table->dropColumn(['proposed_by', 'proposed_at', 'proposed_action', 'proposed_notes']);
        });

        Schema::table('legacy_register_batches', function (Blueprint $table) {
            $table->dropColumn('is_sample');
        });
    }
};
