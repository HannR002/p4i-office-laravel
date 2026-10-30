<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberingPolicy extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'series_key',
        'code',
        'format_pattern',
        'reset_period',
        'padding',
        'is_active',
        'legacy_reconciled',
        'approved_policy_ref',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'legacy_reconciled' => 'boolean',
        'padding' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function letterTypes(): HasMany
    {
        return $this->hasMany(LetterType::class);
    }
}
