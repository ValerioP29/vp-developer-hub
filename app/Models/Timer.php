<?php

namespace App\Models;

class Timer extends TenantModel
{
    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime'];
    }

    public function intervention()
    {
        return $this->belongsTo(Intervention::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
