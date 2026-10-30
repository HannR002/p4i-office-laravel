<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'approval_request_id',
        'letter_id',
        'revision_id',
        'step_no',
        'reviewer_id',
        'mandate_id',
        'decision',
        'decision_content_sha256',
        'comments',
        'decided_at',
    ];

    protected $casts = [
        'step_no' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(LetterRevision::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(SignatoryMandate::class, 'mandate_id');
    }
}

