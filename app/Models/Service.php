<?php

namespace App\Models;

class Service extends TenantModel
{
    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
