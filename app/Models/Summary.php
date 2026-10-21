<?php

namespace App\Models;

class Summary extends TenantModel
{
    protected $attributes = ['status' => 'to_invoice'];

    protected function casts(): array
    {
        return ['month' => 'date', 'snapshot' => 'array', 'finalized_at' => 'datetime', 'invoice_date' => 'date', 'paid_on' => 'date'];
    }
}
