<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('hub:prune {--apply}', function () {
    $sessions = DB::table('sessions')->where('last_activity', '<', now()->subDays(config('hub.session_retention_days'))->timestamp);
    $audits = DB::table('audit_logs')->where('created_at', '<', now()->subDays(config('hub.audit_retention_days')));
    $this->info('Sessioni scadute: '.$sessions->count().'; eventi audit oltre retention: '.$audits->count());
    if ($this->option('apply')) {
        $sessions->delete();
        $audits->delete();
        $this->info('Retention applicata secondo configurazione.');
    }
})->purpose('Anteprima retention; --apply elimina soltanto record oltre le soglie configurate.');
Schedule::command('auth:clear-resets')->daily()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily()->withoutOverlapping();
Schedule::command('hub:prune')->dailyAt('03:15')->withoutOverlapping();
