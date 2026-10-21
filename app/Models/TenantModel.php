<?php

namespace App\Models;

use App\Services\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

abstract class TenantModel extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $user = Auth::user();
            if (! $user) {
                $query->whereRaw('1 = 0');
            } elseif (! $user->isAdmin()) {
                $query->where($query->getModel()->qualifyColumn('tenant_id'), $user->tenant_id);
            }
        });
        static::creating(function ($model) {
            if (Auth::check() && ! Auth::user()->isAdmin()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
        static::created(fn ($m) => Audit::record('created', $m, null, $m->getAttributes()));
        static::updated(fn ($m) => Audit::record('updated', $m, $m->getRawOriginal(), $m->getAttributes()));
        static::deleted(fn ($m) => Audit::record('deleted', $m, $m->getAttributes(), null));
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
