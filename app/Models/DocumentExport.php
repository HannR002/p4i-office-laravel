<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExport extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'letter_id',
        'revision_id',
        'revision_version',
        'content_hash',
        'format',
        'file_path',
        'file_hash',
        'file_size',
        'mime_type',
        'status',
        'idempotency_key',
        'exported_by',
        'error_message',
        'download_count',
        'last_downloaded_at',
    ];

    protected $casts = [
        'revision_version' => 'integer',
        'file_size' => 'integer',
        'download_count' => 'integer',
        'last_downloaded_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(LetterRevision::class, 'revision_id');
    }

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    public function isReady(): bool
    {
        return $this->status === 'READY';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isError(): bool
    {
        return $this->status === 'ERROR';
    }

    public function getFileSizeFormattedAttribute(): string
    {
        return $this->getFormattedFileSizeAttribute();
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $this->file_size > 0 ? floor(log($this->file_size, 1024)) : 0;

        return number_format($this->file_size / pow(1024, $power), 1).' '.$units[$power];
    }
}
