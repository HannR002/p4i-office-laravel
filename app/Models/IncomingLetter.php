<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingLetter extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'sender_name',
        'sender_letter_number',
        'internal_agenda_number',
        'letter_date',
        'received_date',
        'subject',
        'classification',
        'recipient_unit_id',
        'recorded_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'letter_date' => 'date',
        'received_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function recipientUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'recipient_unit_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
