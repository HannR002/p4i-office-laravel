<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'is_valid',
        'conflict_type',
        'conflict_details',
        'verification_status',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'row_index' => 'integer',
        'is_valid' => 'boolean',
        'document_date' => 'date',
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
}
