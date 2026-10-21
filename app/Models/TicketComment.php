<?php

namespace App\Models;

class TicketComment extends TenantModel
{
    protected $attributes = ['internal' => false];

    protected function casts(): array
    {
        return ['internal' => 'boolean'];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
