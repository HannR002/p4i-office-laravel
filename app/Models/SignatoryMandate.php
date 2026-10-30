<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatoryMandate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'letter_type_id',
        'unit_id',
        'can_review',
        'can_approve',
        'can_sign',
        'valid_from',
        'valid_to',
        'status',
        'decree_number',
        'notes',
    ];

    protected $casts = [
        'can_review' => 'boolean',
        'can_approve' => 'boolean',
        'can_sign' => 'boolean',
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function letterType(): BelongsTo
    {
        return $this->belongsTo(LetterType::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'unit_id');
    }

    public function isValidNow(): bool
    {
        $today = now()->format('Y-m-d');

        return $this->status === 'ACTIVE'
            && $this->valid_from->format('Y-m-d') <= $today
            && $this->valid_to->format('Y-m-d') >= $today;
    }
}
