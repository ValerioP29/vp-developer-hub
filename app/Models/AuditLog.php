<?php

namespace App\Models;

class AuditLog extends TenantModel
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope('admin', fn ($q) => auth()->user()?->isAdmin() ? $q : $q->whereRaw('1=0'));
    }

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }
}
