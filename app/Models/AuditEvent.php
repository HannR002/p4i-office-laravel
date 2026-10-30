<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AuditEvent extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'actor_id',
        'event_type',
        'entity_type',
        'entity_id',
        'event_metadata',
        'occurred_at',
    ];

    protected $casts = [
        'event_metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Penegakan Immutabilitas: Audit events bersifat murni append-only
        static::updating(function () {
            throw new RuntimeException('Mutasi UPDATE dilarang pada entitas audit event yang bersifat append-only.');
        });

        static::deleting(function () {
            throw new RuntimeException('Mutasi DELETE dilarang pada entitas audit event yang bersifat append-only.');
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

