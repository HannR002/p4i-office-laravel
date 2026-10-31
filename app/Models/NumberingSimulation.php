<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberingSimulation extends Model
{
    use HasFactory, HasUuids;

    public const CREATED_AT = 'allocated_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'policy_id',
        'letter_id',
        'simulated_number',
        'sequence_number',
        'year',
        'month',
        'idempotency_key',
        'allocated_by',
    ];

    protected $casts = [
        'sequence_number' => 'integer',
        'year' => 'integer',
        'month' => 'integer',
        'allocated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(NumberingPolicy::class, 'policy_id');
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function allocator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
