<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\Timer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeTracking
{
    public function __construct(private Billing $billing) {}

    private function locked(Intervention $work, callable $callback): mixed
    {
        return DB::transaction(function () use ($work, $callback) {
            User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            Tenant::whereKey($work->tenant_id)->lockForUpdate()->firstOrFail();

            return $callback();
        }, 3);
    }

    private function check(Intervention $work, CarbonImmutable $start, CarbonImmutable $end, ?int $except = null): void
    {
        if ($end->lte($start) || $end->gt(CarbonImmutable::now()->addSecond()) || $end->diffInSeconds($start, true) > 86400) {
            throw ValidationException::withMessages(['time' => 'La durata deve essere positiva, non futura e non superiore a 24 ore per registrazione.']);
        }
        $this->billing->assertOpen($work->tenant_id, $start, $end);
        $overlap = TimeEntry::withoutGlobalScopes()->where('user_id', auth()->id())->where('started_at', '<', $end)->where('ended_at', '>', $start)->when($except, fn ($q) => $q->where('id', '!=', $except))->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['time' => 'Questo intervallo si sovrappone a una registrazione esistente.']);
        }
    }

    public function manual(Intervention $work, string $startedAt, int $minutes, ?TimeEntry $entry = null, ?int $version = null, ?string $reason = null): TimeEntry
    {
        return $this->locked($work, function () use ($work, $startedAt, $minutes, $entry, $version, $reason) {
            $local = CarbonImmutable::parse($startedAt, config('hub.timezone'));
            if ($local->format('Y-m-d\TH:i') !== $startedAt) {
                throw ValidationException::withMessages(['started_at' => 'Questo orario non esiste nel fuso Europe/Rome. Seleziona un orario valido.']);
            }
            $start = $local->utc()->setMicrosecond(0);
            $end = $start->addMinutes($minutes);
            if ($entry) {
                $entry->refresh();
                abort_unless($entry->user_id === auth()->id() && $entry->intervention_id === $work->id, 403);
                if ($entry->version !== $version) {
                    throw ValidationException::withMessages(['version' => 'La registrazione è stata modificata. Ricarica la pagina.']);
                }
                $this->billing->assertOpen($work->tenant_id, $entry->started_at, $entry->ended_at);
            }
            $this->check($work, $start, $end, $entry?->id);
            $timer = Timer::withoutGlobalScopes()->where('user_id', auth()->id())->whereNotNull('started_at')->first();
            if ($timer && $end->gt($timer->started_at)) {
                throw ValidationException::withMessages(['time' => 'Interrompi il timer attivo prima di registrare questo intervallo.']);
            }
            $values = ['tenant_id' => $work->tenant_id, 'intervention_id' => $work->id, 'user_id' => auth()->id(), 'started_at' => $start, 'ended_at' => $end, 'seconds' => $minutes * 60, 'source' => 'manual', 'correction_reason' => $reason];
            if ($entry) {
                $entry->update($values + ['version' => $entry->version + 1]);

                return $entry;
            }

            return TimeEntry::create($values);
        });
    }

    public function start(Intervention $work): Timer
    {
        return $this->locked($work, function () use ($work) {
            $timer = Timer::withoutGlobalScopes()->where('user_id', auth()->id())->first();
            if ($timer?->started_at) {
                throw ValidationException::withMessages(['timer' => 'Hai già un timer in corso. Interrompilo prima di avviarne un altro.']);
            }
            $now = CarbonImmutable::now()->setMicrosecond(0);
            $this->billing->assertOpen($work->tenant_id, $now, $now->addSecond());
            if (TimeEntry::withoutGlobalScopes()->where('user_id', auth()->id())->where('ended_at', '>', $now)->exists()) {
                throw ValidationException::withMessages(['timer' => 'È presente una registrazione che termina nel futuro.']);
            }
            $timer?->delete();

            return Timer::create(['tenant_id' => $work->tenant_id, 'intervention_id' => $work->id, 'user_id' => auth()->id(), 'started_at' => $now]);
        });
    }

    public function pause(Intervention $work): ?TimeEntry
    {
        return $this->locked($work, function () use ($work) {
            $timer = Timer::where('user_id', auth()->id())->where('intervention_id', $work->id)->firstOrFail();
            if (! $timer->started_at) {
                return null;
            }
            $start = $timer->started_at;
            $end = CarbonImmutable::now()->setMicrosecond(0);
            if ($end->lte($start)) {
                $timer->update(['started_at' => null]);

                return null;
            }
            // Split long-running timers into <=24h segments; all operations remain one transaction.
            $last = null;
            while ($start->lt($end)) {
                $segmentEnd = $start->addDay()->min($end);
                $this->check($work, $start, $segmentEnd);
                $last = TimeEntry::create(['tenant_id' => $work->tenant_id, 'intervention_id' => $work->id, 'user_id' => auth()->id(), 'started_at' => $start, 'ended_at' => $segmentEnd, 'seconds' => $segmentEnd->timestamp - $start->timestamp, 'source' => 'timer']);
                $start = $segmentEnd;
            }
            $timer->update(['started_at' => null]);

            return $last;
        });
    }
}
