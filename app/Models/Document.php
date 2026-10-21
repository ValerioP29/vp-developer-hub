<?php

namespace App\Models;

class Document extends TenantModel
{
    protected $attributes = ['version' => 1, 'client_visible' => true];

    protected function casts(): array
    {
        return ['client_visible' => 'boolean'];
    }

    protected $hidden = ['path'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
