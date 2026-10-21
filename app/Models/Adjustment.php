<?php

namespace App\Models;

class Adjustment extends TenantModel
{
    protected function casts(): array
    {
        return ['month' => 'date'];
    }
}
