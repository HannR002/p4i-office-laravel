<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'letter_type_id',
        'version',
        'title',
        'description',
        'form_schema',
        'content_template',
        'is_active',
    ];

    protected $casts = [
        'form_schema' => 'array',
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function letterType(): BelongsTo
    {
        return $this->belongsTo(LetterType::class);
    }
}

