<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    protected $attributes = ['active' => true, 'role' => 'client_user'];

    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'tenant_id', 'role', 'active', 'permissions'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->username ??= 'user-'.strtolower((string) Str::ulid());
        });
    }

    protected function username(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : strtolower(trim($value)));
    }

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'active' => 'boolean', 'permissions' => 'array'];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function allowed(string $ability): bool
    {
        if (! $this->active) {
            return false;
        }
        if ($this->isAdmin()) {
            return true;
        }
        $base = ['portal', 'tickets', 'documents'];
        if ($this->role === 'client_admin') {
            $base = array_merge($base, ['billing', 'approvals', 'team']);
        }

        return in_array($ability, $base, true) && ! in_array($ability, $this->permissions['denied'] ?? [], true);
    }
}
