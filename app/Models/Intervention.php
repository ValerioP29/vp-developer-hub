<?php

namespace App\Models;

class Intervention extends TenantModel
{
    protected $attributes = ['status' => 'planned', 'billable' => true, 'uses_package' => true];

    protected function casts(): array
    {
        return ['performed_on' => 'date', 'billable' => 'boolean', 'uses_package' => 'boolean'];
    }

    protected $hidden = ['internal_notes'];

    public function entries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
