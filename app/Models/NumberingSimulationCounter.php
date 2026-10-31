<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberingSimulationCounter extends Model
{
    use HasFactory, HasUuids;

    public const CREATED_AT = null;

    protected $fillable = [
        'organization_id',
        'policy_id',
        'scope_key',
        'year_key',
        'month_key',
        'last_number',
    ];

    protected $casts = [
        'year_key' => 'integer',
        'month_key' => 'integer',
        'last_number' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(NumberingPolicy::class, 'policy_id');
    }
}
