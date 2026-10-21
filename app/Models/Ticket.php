<?php

namespace App\Models;

class Ticket extends TenantModel
{
    protected $attributes = ['status' => 'new', 'priority' => 'normal'];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
