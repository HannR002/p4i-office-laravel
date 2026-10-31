<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyRegisterBatch extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'source_filename',
        'source_hash',
        'total_rows',
        'valid_rows',
        'conflict_rows',
        'status',
        'imported_by',
        'notes',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'valid_rows' => 'integer',
        'conflict_rows' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(LegacyRegisterRow::class, 'batch_id')->orderBy('row_index');
    }
}
