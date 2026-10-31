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
        // 1. Tambah metadata pada numbering_policies untuk status DRAFT dan scope
        Schema::table('numbering_policies', function (Blueprint $table) {
            $table->string('status', 32)->default('DRAFT')->after('is_active');
            $table->string('scope_type', 32)->default('SERIES')->after('status');
            $table->text('description')->nullable()->after('scope_type');
        });

        // 2. Tabel Counter Simulasi Penomoran (Concurrency-Safe Counter di DB Test / Terisolasi)
        Schema::create('numbering_simulation_counters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('policy_id')->constrained('numbering_policies')->restrictOnDelete();
            $table->string('scope_key', 64);
            $table->integer('year_key');
            $table->integer('month_key')->default(0);
            $table->integer('last_number')->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(
                ['policy_id', 'scope_key', 'year_key', 'month_key'],
                'nsc_policy_scope_year_month_unique'
            );
        });

        // 3. Tabel Riwayat Alokasi Simulasi (Safety: Tidak pernah menjadi official_number)
        Schema::create('numbering_simulations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('policy_id')->constrained('numbering_policies')->restrictOnDelete();
            $table->foreignUuid('letter_id')->nullable()->constrained('letters')->nullOnDelete();
            $table->string('simulated_number', 128);
            $table->integer('sequence_number');
            $table->integer('year');
            $table->integer('month')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->foreignUuid('allocated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('allocated_at')->useCurrent();

            $table->index(['organization_id', 'policy_id']);
            $table->index('simulated_number');
        });

        // 4. Tabel Staging Batch Impor Register Historis
        Schema::create('legacy_register_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('source_filename', 255);
            $table->char('source_hash', 64);
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('conflict_rows')->default(0);
            $table->string('status', 32)->default('STAGED');
            $table->foreignUuid('imported_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        // 5. Tabel Detail Baris Staging Register Historis
        Schema::create('legacy_register_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('legacy_register_batches')->cascadeOnDelete();
            $table->integer('row_index');
            $table->string('raw_number', 255);
            $table->string('normalized_number', 255)->nullable();
            $table->date('document_date')->nullable();
            $table->string('letter_type_code', 64)->nullable();
            $table->string('recipient', 255)->nullable();
            $table->text('subject')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->string('conflict_type', 64)->nullable();
            $table->text('conflict_details')->nullable();
            $table->string('verification_status', 32)->default('UNVERIFIED');
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'is_valid']);
            $table->index('raw_number');
        });

        // 6. Tabel Buku Agenda Surat Masuk (Pemisahan Tegas Nomor Asli vs Nomor Agenda P4I)
        Schema::create('incoming_letters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('sender_name', 255);
            $table->string('sender_letter_number', 128); // Nomor asli surat luar instansi
            $table->string('internal_agenda_number', 128)->unique(); // Nomor agenda internal P4I
            $table->date('letter_date');
            $table->date('received_date');
            $table->text('subject');
            $table->string('classification', 32)->default('INTERNAL');
            $table->foreignUuid('recipient_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 32)->default('REGISTERED');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'received_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_letters');
        Schema::dropIfExists('legacy_register_rows');
        Schema::dropIfExists('legacy_register_batches');
        Schema::dropIfExists('numbering_simulations');
        Schema::dropIfExists('numbering_simulation_counters');

        Schema::table('numbering_policies', function (Blueprint $table) {
            $table->dropColumn(['status', 'scope_type', 'description']);
        });
    }
};
