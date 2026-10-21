<?php

namespace App\Models;

class Approval extends TenantModel
{
    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['month' => 'date', 'decided_at' => 'datetime'];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
