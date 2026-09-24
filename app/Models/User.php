<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'nip', 'email', 'password', 'role', 'instansi_id', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $appends = ['role_label'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->role?->label() ?? '-';
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Operator instansi hanya boleh melihat & mengelola data instansinya sendiri. */
    public function isScopedToInstansi(): bool
    {
        return $this->role === Role::OperatorInstansi;
    }

    public function canAccessInstansi(?int $instansiId): bool
    {
        return ! $this->isScopedToInstansi() || ($instansiId !== null && (int) $this->instansi_id === (int) $instansiId);
    }
}
