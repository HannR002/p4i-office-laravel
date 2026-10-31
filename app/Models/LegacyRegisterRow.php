<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyRegisterRow extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'batch_id',
        'row_index',
        'raw_number',
        'normalized_number',
        'document_date',
        'letter_type_code',
        'recipient',
        'subject',
        'source_location',
        'provenance_year',
        'provenance_series',
        'is_valid',
        'conflict_type',
        'conflict_details',
        'verification_status',
        'reconciliation_action',
        'reconciliation_notes',
        'board_resolution_ref',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'row_index' => 'integer',
        'is_valid' => 'boolean',
        'document_date' => 'date',
        'provenance_year' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LegacyRegisterBatch::class, 'batch_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ReconciliationAuditLog::class, 'row_id')->orderBy('created_at', 'desc');
    }
}
