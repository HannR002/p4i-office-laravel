<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Letter extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'unit_id',
        'letter_type_id',
        'template_id',
        'author_id',
        'subject',
        'recipient_name',
        'classification',
        'planned_date',
        'status',
        'current_revision_no',
        'official_number',
    ];

    protected $casts = [
        'planned_date' => 'date:Y-m-d',
        'current_revision_no' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'unit_id');
    }

    public function letterType(): BelongsTo
    {
        return $this->belongsTo(LetterType::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(LetterRevision::class)->orderBy('version', 'asc');
    }

    public function latestRevision(): HasOne
    {
        return $this->hasOne(LetterRevision::class)->ofMany('version', 'max');
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class)->orderBy('created_at', 'desc');
    }

    public function latestApprovalRequest(): HasOne
    {
        return $this->hasOne(ApprovalRequest::class)->latestOfMany();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LetterStatusHistory::class)->orderBy('created_at', 'asc');
    }
}
