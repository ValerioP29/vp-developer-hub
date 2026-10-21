<?php

namespace App\Models;

use App\Services\Audit;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $attributes = ['status' => 'active', 'email_notifications' => false];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['email_notifications' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($q) {
            if (! auth()->check()) {
                $q->whereRaw('1=0');
            } elseif (! auth()->user()->isAdmin()) {
                $q->where('id', auth()->user()->tenant_id);
            }
        });
        static::created(fn ($m) => Audit::record('created', $m, null, $m->getAttributes()));
        static::updated(fn ($m) => Audit::record('updated', $m, $m->getRawOriginal(), $m->getAttributes()));
    }
}
