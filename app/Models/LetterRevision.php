<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class LetterRevision extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'letter_id',
        'version',
        'template_id',
        'subject',
        'recipient_name',
        'classification',
        'planned_date',
        'content',
        'content_sha256',
        'change_summary',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'content' => 'array',
        'planned_date' => 'date:Y-m-d',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Penegakan Immutabilitas: Dilarang mengubah atau menghapus revisi naskah
        static::updating(function () {
            throw new RuntimeException('Mutasi UPDATE dilarang pada entitas revisi surat yang bersifat immutabel.');
        });

        static::deleting(function () {
            throw new RuntimeException('Mutasi DELETE dilarang pada entitas revisi surat yang bersifat immutabel.');
        });
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
