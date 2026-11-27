<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable, TwoFactorAuthenticatable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'nip', 'email', 'role', 'instansi_id', 'pembina_id', 'is_active'])
            ->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('pengguna');
    }

    protected $fillable = [
        'name', 'nip', 'email', 'password', 'role', 'instansi_id', 'pembina_id', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
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
            'two_factor_confirmed_at' => 'datetime',
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

    public function isPembina(): bool
    {
        return $this->role === Role::Pembina;
    }

    /** Pengguna pembina hanya boleh mengakses data instansi pembinanya sendiri; peran lain tidak dibatasi per pembina. */
    public function isScopedToPembina(): bool
    {
        return $this->isPembina();
    }

    public function canAccessPembina(?int $pembinaId): bool
    {
        return ! $this->isScopedToPembina() || ($pembinaId !== null && (int) $this->pembina_id === (int) $pembinaId);
    }

    public function pembina(): BelongsTo
    {
        return $this->belongsTo(InstansiPembina::class, 'pembina_id');
    }

    public function canAccessInstansi(?int $instansiId): bool
    {
        return ! $this->isScopedToInstansi() || ($instansiId !== null && (int) $this->instansi_id === (int) $instansiId);
    }
}
