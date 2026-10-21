<?php

namespace App\Models;

class Contract extends TenantModel
{
    protected $attributes = ['status' => 'active', 'carryover' => false, 'fee_cents' => 0, 'hourly_rate_cents' => 0, 'included_minutes' => 0];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'carryover' => 'boolean'];
    }
}
