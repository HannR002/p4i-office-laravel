<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'code',
        'label',
        'priority',
        'numbering_policy_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function numberingPolicy(): BelongsTo
    {
        return $this->belongsTo(NumberingPolicy::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(LetterTemplate::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }
}

