<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'legal_name',
        'acronym',
        'address_placeholder',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(OrgUnit::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function letterTypes(): HasMany
    {
        return $this->hasMany(LetterType::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }
}
