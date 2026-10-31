<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaPolicy extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'direction',
        'code_prefix',
        'format_pattern',
        'reset_period',
        'status',
        'is_active',
        'approved_decree_ref',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
