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
        // 1. Tambah kolom pada approval_requests untuk idempotensi dan pencatatan claim review
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('workflow_version');
            $table->foreignUuid('claimed_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('claimed_at')->nullable()->after('claimed_by');
        });

        // 2. Tambah kolom pada approval_steps untuk idempotensi dan workflow version binding
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->integer('workflow_version')->default(1)->after('step_no');
            $table->string('idempotency_key', 64)->nullable()->unique()->after('comments');
        });

        // 3. Tambah kolom pada signatory_mandates untuk penarikan mandat dan flag simulasi
        Schema::table('signatory_mandates', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('status');
            $table->boolean('is_simulation')->default(false)->after('decree_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signatory_mandates', function (Blueprint $table) {
            $table->dropColumn(['revoked_at', 'is_simulation']);
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn(['workflow_version', 'idempotency_key']);
        });

        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropForeign(['claimed_by']);
            $table->dropColumn(['idempotency_key', 'claimed_by', 'claimed_at']);
        });
    }
};
