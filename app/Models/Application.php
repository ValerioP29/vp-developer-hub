<?php

namespace App\Models;

class Application extends TenantModel
{
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
