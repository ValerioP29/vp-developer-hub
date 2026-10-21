<?php

namespace App\Services;

use App\Models\Activation;
use App\Models\Adjustment;
use App\Models\Approval;
use App\Models\Contract;
use App\Models\Summary;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\Timer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Billing
{
    public static function month(string $value): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])(?:-01)?$/', $value)) {
            throw ValidationException::withMessages(['month' => 'Seleziona un mese valido.']);
        }

        return CarbonImmutable::parse(substr($value, 0, 7).'-01', config('hub.timezone'))->startOfDay();
    }

    public function assertOpen(int $tenantId, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $first = $start->setTimezone(config('hub.timezone'))->startOfMonth()->toDateString();
        $last = $end->subSecond()->setTimezone(config('hub.timezone'))->startOfMonth()->toDateString();
        if (Summary::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereDate('month', '>=', $first)->whereDate('month', '<=', $last)->exists()) {
            throw ValidationException::withMessages(['time' => 'Il mese è consolidato: le registrazioni e le condizioni storiche sono bloccate.']);
        }
    }

    public function activate(Contract $contract, array $data): Activation
    {
        return DB::transaction(function () use ($contract, $data) {
            Tenant::whereKey($contract->tenant_id)->lockForUpdate()->firstOrFail();
            $month = self::month($data['month']);
            $this->assertOpen($contract->tenant_id, $month, $month->addMonth());
            if ($contract->status !== 'active' || $contract->starts_on->toDateString() > $month->toDateString() || ($contract->ends_on && $contract->ends_on->toDateString() < $month->endOfMonth()->toDateString())) {
                throw ValidationException::withMessages(['contract_id' => 'Il contratto deve essere attivo per tutto il mese selezionato.']);
            }
            if (Activation::where('tenant_id', $contract->tenant_id)->whereDate('month', $month)->exists()) {
                throw ValidationException::withMessages(['month' => 'Esiste già un’attivazione per questo mese.']);
            }
            $carried = 0;
            $carriedRemainder = 0;
            if ($contract->carryover) {
                $previous = Summary::where('tenant_id', $contract->tenant_id)->whereDate('month', $month->subMonth())->first();
                if ($previous && ($previous->snapshot['carryover'] ?? false)) {
                    $carried = intdiv($previous->snapshot['remaining_seconds'], 60);
                    $carriedRemainder = $previous->snapshot['remaining_seconds'] % 60;
                }
            }

            return Activation::create([
                'tenant_id' => $contract->tenant_id, 'contract_id' => $contract->id, 'package_id' => $data['package_id'] ?? null,
                'month' => $month->toDateString(), 'label' => $data['label'], 'model' => $contract->model,
                'included_minutes' => $data['included_minutes'], 'carried_minutes' => $carried, 'carried_remainder_seconds' => $carriedRemainder, 'fee_cents' => $data['fee_cents'],
                'hourly_rate_cents' => $data['hourly_rate_cents'], 'carryover' => $contract->carryover, 'terms' => $contract->extra_terms,
                'approved_at' => now(), 'approved_by' => auth()->id(),
            ]);
        });
    }

    public function preview(int $tenantId, string $value): array
    {
        return $this->previewMany([$tenantId], $value)[$tenantId];
    }

    /** Batch the dashboard queries: query count is independent of the number of tenants. */
    public function previewMany(array $tenantIds, string $value): array
    {
        if (! $tenantIds) {
            return [];
        }
        $month = self::month($value);
        $end = $month->addMonth();
        $saved = Summary::whereIn('tenant_id', $tenantIds)->whereDate('month', $month)->get()->keyBy('tenant_id');
        $openIds = array_values(array_diff($tenantIds, $saved->keys()->all()));
        $result = $saved->map(fn ($summary) => $summary->snapshot + ['finalized' => true])->all();
        if (! $openIds) {
            return $result;
        }
        $activations = Activation::whereIn('tenant_id', $openIds)->whereDate('month', $month)->whereNotNull('approved_at')->get()->keyBy('tenant_id');
        $entries = TimeEntry::whereIn('tenant_id', $openIds)->where('started_at', '<', $end->utc())->where('ended_at', '>', $month->utc())->with('intervention')->get()->groupBy('tenant_id');
        $approvals = Approval::whereIn('tenant_id', $openIds)->whereDate('month', $month)->where('status', 'approved')->get()->groupBy('tenant_id');
        $adjustments = Adjustment::whereIn('tenant_id', $openIds)->whereDate('month', $month)->get()->groupBy('tenant_id');
        foreach ($openIds as $id) {
            $items = ($adjustments[$id] ?? collect())->map->only(['label', 'kind', 'amount_cents', 'note'])->all();
            $result[$id] = $this->calculate($month, $activations[$id] ?? null, $entries[$id] ?? collect(), $approvals[$id] ?? collect(), $items);
        }

        return $result;
    }

    private function calculate(CarbonImmutable $month, ?Activation $activation, Collection $entries, Collection $approvals, array $adjustments): array
    {
        $end = $month->addMonth();
        $worked = $includedWork = $separateWork = $nonbillable = 0;
        foreach ($entries as $entry) {
            $seconds = max(0, min($entry->ended_at->timestamp, $end->timestamp) - max($entry->started_at->timestamp, $month->timestamp));
            $worked += $seconds;
            if (! $entry->intervention->billable) {
                $nonbillable += $seconds;
            } elseif ($entry->intervention->uses_package) {
                $includedWork += $seconds;
            } else {
                $separateWork += $seconds;
            }
        }
        $included = $activation ? ($activation->included_minutes + $activation->carried_minutes) * 60 + $activation->carried_remainder_seconds : 0;
        $payg = $activation?->model === 'payg';
        $used = $payg ? 0 : min($included, $includedWork);
        $extra = ($payg ? 0 : max(0, $includedWork - $included)) + $separateWork;
        if (! $activation) {
            $extra = $includedWork + $separateWork;
        }
        $approvedSeconds = min($extra, $approvals->sum('minutes') * 60);
        $rate = $activation?->hourly_rate_cents ?? 0;
        $cap = (int) $approvals->sum('amount_cents');
        // A monetary cap also limits the authorized time at the historical hourly rate.
        if ($rate > 0) {
            $approvedSeconds = min($approvedSeconds, intdiv($cap * 3600, $rate));
        } else {
            $approvedSeconds = 0;
        }
        $extraCents = min($cap, (int) round($approvedSeconds * $rate / 3600, 0, PHP_ROUND_HALF_UP));
        $baseCents = $activation ? (int) $activation->fee_cents : 0;
        $adjustmentCents = array_sum(array_column($adjustments, 'amount_cents'));
        $usageCents = $payg ? (int) round($includedWork * $rate / 3600, 0, PHP_ROUND_HALF_UP) : 0;

        return ['month' => $month->format('Y-m'), 'label' => $activation?->label ?? 'Nessun accordo mensile attivo', 'model' => $activation?->model,
            'worked_seconds' => $worked, 'nonbillable_seconds' => $nonbillable, 'included_seconds' => $included, 'used_seconds' => $used,
            'remaining_seconds' => max(0, $included - $used), 'extra_seconds' => $extra, 'approved_extra_seconds' => $approvedSeconds,
            'pending_seconds' => $extra - $approvedSeconds, 'base_cents' => $baseCents, 'usage_cents' => $usageCents, 'extra_cents' => $extraCents,
            'total_cents' => $baseCents + $usageCents + $extraCents, 'adjustments' => $adjustments, 'adjustment_cents' => $adjustmentCents, 'amount_due_cents' => $baseCents + $usageCents + $extraCents + $adjustmentCents, 'hourly_rate_cents' => $rate, 'carryover' => (bool) $activation?->carryover];
    }

    public function finalize(int $tenantId, string $value): Summary
    {
        return DB::transaction(function () use ($tenantId, $value) {
            Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
            $month = self::month($value);
            if ($month->addMonth()->isFuture()) {
                throw ValidationException::withMessages(['month' => 'Il mese non è ancora terminato.']);
            }
            if (Summary::where('tenant_id', $tenantId)->whereDate('month', $month)->exists()) {
                throw ValidationException::withMessages(['month' => 'Il mese è già consolidato.']);
            }
            if (Timer::where('tenant_id', $tenantId)->whereNotNull('started_at')->where('started_at', '<', $month->addMonth()->utc())->exists()) {
                throw ValidationException::withMessages(['month' => 'Interrompi il timer prima di consolidare.']);
            }
            $snapshot = $this->preview($tenantId, $value);
            if ($snapshot['pending_seconds'] > 0) {
                throw ValidationException::withMessages(['month' => 'Sono presenti ore senza autorizzazione. Completa le approvazioni o classifica le attività come non fatturabili.']);
            }

            return Summary::create(['tenant_id' => $tenantId, 'month' => $month->toDateString(), 'snapshot' => $snapshot, 'total_cents' => $snapshot['total_cents'], 'finalized_at' => now()]);
        });
    }
}
