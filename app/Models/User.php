<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'organization_id',
        'unit_id',
        'email',
        'display_name',
        'password',
        'status',
        'mfa_enabled',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'mfa_enabled' => 'boolean',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'unit_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot('granted_by', 'granted_at');
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class, 'author_id');
    }

    public function mandates(): HasMany
    {
        return $this->hasMany(SignatoryMandate::class, 'user_id');
    }

    public function isSystemAdmin(): bool
    {
        return $this->roles->contains('role_key', 'SYSTEM_ADMIN');
    }

    public function hasRole(string $roleKey): bool
    {
        return $this->roles->contains('role_key', $roleKey);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
