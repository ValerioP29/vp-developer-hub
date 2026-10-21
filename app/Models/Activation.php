<?php

namespace App\Models;

class Activation extends TenantModel
{
    protected $attributes = ['carried_minutes' => 0, 'carried_remainder_seconds' => 0, 'carryover' => false];

    protected function casts(): array
    {
        return ['month' => 'date', 'approved_at' => 'datetime', 'carryover' => 'boolean'];
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
