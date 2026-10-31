<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'letter_id',
        'revision_id',
        'revision_version',
        'content_sha256',
        'submitted_by',
        'submitted_at',
        'status',
        'claimed_by',
        'claimed_at',
        'submission_notes',
        'workflow_version',
        'idempotency_key',
    ];

    protected $casts = [
        'revision_version' => 'integer',
        'workflow_version' => 'integer',
        'submitted_at' => 'datetime',
        'claimed_at' => 'datetime',
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
        return $this->belongsTo(LetterRevision::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'approval_request_id')->orderBy('step_no');
    }
}
