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
        Schema::create('document_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('letter_id')->constrained('letters')->cascadeOnDelete();
            $table->foreignUuid('revision_id')->constrained('letter_revisions')->cascadeOnDelete();
            $table->unsignedInteger('revision_version');
            $table->string('content_hash', 64);
            $table->string('format', 16); // 'PDF' | 'DOCX'
            $table->string('file_path', 255)->nullable();
            $table->string('file_hash', 64)->nullable(); // SHA-256 of file
            $table->unsignedBigInteger('file_size')->nullable(); // Bytes
            $table->string('mime_type', 128)->nullable();
            $table->string('status', 32)->default('PENDING'); // PENDING | READY | ERROR
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->foreignUuid('exported_by')->constrained('users')->restrictOnDelete();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamps();

            $table->index(['letter_id', 'revision_id']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['status', 'format']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_exports');
    }
};
