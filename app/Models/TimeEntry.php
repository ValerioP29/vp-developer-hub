<?php

namespace App\Models;

class TimeEntry extends TenantModel
{
    protected $attributes = ['version' => 1, 'source' => 'manual'];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
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
