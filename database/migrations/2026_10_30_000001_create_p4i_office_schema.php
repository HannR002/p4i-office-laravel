<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Organisasi
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('legal_name', 255);
            $table->string('acronym', 32);
            $table->text('address_placeholder')->nullable();
            $table->timestamps();
        });

        // 2. Unit Kerja Organisasi
        Schema::create('org_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('org_units')->restrictOnDelete();
            $table->string('name', 255);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        // 3. Pengguna Sistem (Users)
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained('org_units')->restrictOnDelete();
            $table->string('email', 255)->unique();
            $table->string('display_name', 255);
            $table->string('password');
            $table->string('status', 32)->default('ACTIVE');
            $table->boolean('mfa_enabled')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // 4. Peran Sistem (Roles)
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('role_key', 64)->unique();
            $table->string('label', 255);
            $table->timestamps();
        });

        // 5. Penugasan Peran Pengguna (User Roles)
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignUuid('granted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('granted_at')->useCurrent();

            $table->primary(['user_id', 'role_id']);
        });

        // 6. Kebijakan Penomoran (Safety: default is_active = false)
        Schema::create('numbering_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('series_key', 64);
            $table->string('code', 40);
            $table->string('format_pattern', 255)->default('{seq:03}/{code}/{month_roman}/{year}');
            $table->string('reset_period', 16)->default('YEAR');
            $table->smallInteger('padding')->default(3);
            $table->boolean('is_active')->default(false);
            $table->boolean('legacy_reconciled')->default(false);
            $table->string('approved_policy_ref', 255)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'series_key']);
        });

        // 7. Jenis Surat (Letter Types)
        Schema::create('letter_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('label', 255);
            $table->string('priority', 10)->default('P0');
            $table->foreignUuid('numbering_policy_id')->nullable()->constrained('numbering_policies')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        // 8. Templat Surat (Letter Templates)
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('letter_type_id')->constrained('letter_types')->restrictOnDelete();
            $table->integer('version');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->json('form_schema');
            $table->longText('content_template');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['letter_type_id', 'version']);
        });

        // 9. Surat Dinas (Letters - Safety: official_number remains NULL)
        Schema::create('letters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained('org_units')->restrictOnDelete();
            $table->foreignUuid('letter_type_id')->constrained('letter_types')->restrictOnDelete();
            $table->foreignUuid('template_id')->nullable()->constrained('letter_templates')->restrictOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->string('subject', 255);
            $table->string('recipient_name', 255);
            $table->string('classification', 32)->default('INTERNAL');
            $table->date('planned_date');
            $table->string('status', 32)->default('DRAFT');
            $table->integer('current_revision_no')->default(1);
            $table->string('official_number', 128)->nullable(); // SAFETY: NULL di Sprint M0
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['author_id', 'status']);
            $table->index('classification');
        });

        // 10. Riwayat Revisi Surat (Immutable Revisions)
        Schema::create('letter_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('letter_id')->constrained('letters')->restrictOnDelete();
            $table->integer('version');
            $table->foreignUuid('template_id')->nullable()->constrained('letter_templates')->restrictOnDelete();
            $table->string('subject', 255);
            $table->string('recipient_name', 255);
            $table->string('classification', 32);
            $table->date('planned_date');
            $table->json('content');
            $table->char('content_sha256', 64);
            $table->text('change_summary')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['letter_id', 'version']);
            $table->index('created_by');
        });

        // 11. Mandat Pejabat (Signatory & Reviewer Mandates)
        Schema::create('signatory_mandates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('letter_type_id')->nullable()->constrained('letter_types')->restrictOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained('org_units')->restrictOnDelete();
            $table->boolean('can_review')->default(true);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_sign')->default(false);
            $table->date('valid_from');
            $table->date('valid_to');
            $table->string('status', 32)->default('ACTIVE');
            $table->string('decree_number', 255);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['organization_id', 'valid_from', 'valid_to']);
        });

        // 12. Permohonan Persetujuan (Approval Requests)
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('letter_id')->constrained('letters')->restrictOnDelete();
            $table->foreignUuid('revision_id')->constrained('letter_revisions')->restrictOnDelete();
            $table->integer('revision_version');
            $table->char('content_sha256', 64);
            $table->foreignUuid('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->string('status', 32)->default('PENDING');
            $table->text('submission_notes')->nullable();
            $table->integer('workflow_version')->default(1);
            $table->timestamps();

            $table->index(['letter_id', 'status']);
            $table->index(['organization_id', 'status']);
        });

        // 13. Keputusan Telaah Reviewer (Approval Steps)
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('approval_request_id')->constrained('approval_requests')->restrictOnDelete();
            $table->foreignUuid('letter_id')->constrained('letters')->restrictOnDelete();
            $table->foreignUuid('revision_id')->constrained('letter_revisions')->restrictOnDelete();
            $table->integer('step_no')->default(1);
            $table->foreignUuid('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('mandate_id')->constrained('signatory_mandates')->restrictOnDelete();
            $table->string('decision', 32);
            $table->char('decision_content_sha256', 64);
            $table->text('comments')->nullable();
            $table->timestamp('decided_at')->useCurrent();

            $table->index('approval_request_id');
            $table->index(['letter_id', 'decided_at']);
        });

        // 14. Riwayat Status Surat (Letter Status History)
        Schema::create('letter_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('letter_id')->constrained('letters')->restrictOnDelete();
            $table->foreignUuid('revision_id')->nullable()->constrained('letter_revisions')->restrictOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['letter_id', 'created_at']);
        });

        // 15. Jejak Audit Append-Only (Audit Events)
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 128);
            $table->string('entity_type', 64);
            $table->uuid('entity_id')->nullable();
            $table->json('event_metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['organization_id', 'occurred_at']);
        });

        // 16. Engine-Level Immutability Triggers (MariaDB/MySQL)
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("
                CREATE TRIGGER trg_letter_revisions_no_update
                BEFORE UPDATE ON letter_revisions
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Letter revisions are strictly immutable and cannot be updated.';
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_letter_revisions_no_delete
                BEFORE DELETE ON letter_revisions
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Letter revisions are strictly immutable and cannot be deleted.';
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_audit_events_no_update
                BEFORE UPDATE ON audit_events
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Audit events are strictly append-only and cannot be updated.';
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_audit_events_no_delete
                BEFORE DELETE ON audit_events
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Audit events are strictly append-only and cannot be deleted.';
                END;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_events_no_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_events_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_letter_revisions_no_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_letter_revisions_no_update');
        }

        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('letter_status_history');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('signatory_mandates');
        Schema::dropIfExists('letter_revisions');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_templates');
        Schema::dropIfExists('letter_types');
        Schema::dropIfExists('numbering_policies');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('org_units');
        Schema::dropIfExists('organizations');
    }
};
