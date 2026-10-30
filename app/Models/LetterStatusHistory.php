<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterStatusHistory extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;
    protected $table = 'letter_status_history';

    protected $fillable = [
        'letter_id',
        'revision_id',
        'from_status',
        'to_status',
        'actor_id',
        'reason',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(LetterRevision::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

