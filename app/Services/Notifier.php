<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\HubNotification;

class Notifier
{
    public function ticket(Ticket $ticket, string $event): void
    {
        if (! config('hub.notifications')) {
            return;
        }
        $tenant = Tenant::withoutGlobalScopes()->find($ticket->tenant_id);
        $users = User::where('active', true)->where(function ($q) use ($tenant) {
            $q->where('role', 'super_admin');
            if ($tenant->email_notifications) {
                $q->orWhere('tenant_id', $tenant->id);
            }
        })->get();
        foreach ($users as $user) {
            if ($user->allowed('tickets')) {
                $user->notify((new HubNotification($event, $ticket->id))->afterCommit());
            }
        }
    }
}
