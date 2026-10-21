<?php

namespace App\Http\Controllers;

use App\Models\Activation;
use App\Models\Adjustment;
use App\Models\Approval;
use App\Models\Contract;
use App\Models\Summary;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\Billing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    private function allowed(bool $admin = false): void
    {
        abort_unless(auth()->user()->allowed('billing') && (! $admin || auth()->user()->isAdmin()), 403);
    }

    public function index(Request $request, Billing $billing)
    {
        $this->allowed();
        $month = $request->input('month', now(config('hub.timezone'))->format('Y-m'));
        Billing::month($month);
        $tenants = Tenant::orderBy('name')->get();
        $selected = $request->integer('tenant_id') ?: $tenants->first()?->id;
        $tenant = $selected ? Tenant::findOrFail($selected) : null;
        $preview = $tenant ? $billing->preview($tenant->id, $month) : null;
        $activations = Activation::when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))->orderByDesc('month')->paginate(12, ['*'], 'activations_page');
        $summaries = Summary::when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))->orderByDesc('month')->paginate(12, ['*'], 'summaries_page');
        $contracts = $tenant ? Contract::where('tenant_id', $tenant->id)->where('status', 'active')->get() : collect();

        return view('hub.billing', compact('month', 'tenants', 'tenant', 'preview', 'activations', 'summaries', 'contracts'));
    }

    public function activate(Request $request, Billing $billing)
    {
        $this->allowed(true);
        $data = $request->validate(['contract_id' => 'required|integer', 'package_id' => 'nullable|exists:packages,id', 'month' => 'required|date_format:Y-m', 'label' => 'required|string|max:180', 'included_minutes' => 'required|integer|min:0|max:1000000', 'fee' => 'required|regex:/^\d+(\.\d{1,2})?$/|numeric|min:0|max:1000000', 'rate' => 'required|regex:/^\d+(\.\d{1,2})?$/|numeric|min:0|max:1000000', 'confirm' => 'accepted']);
        $contract = Contract::findOrFail($data['contract_id']);
        if ($contract->model === 'payg' && ($data['fee'] > 0 || $data['included_minutes'] > 0)) {
            throw ValidationException::withMessages(['fee' => 'A consuntivo: canone e minuti inclusi devono essere zero.']);
        }
        $data['fee_cents'] = (int) round($data['fee'] * 100);
        $data['hourly_rate_cents'] = (int) round($data['rate'] * 100);
        $billing->activate($contract, $data);

        return back()->with('notice', 'Attivazione approvata. Le condizioni sono ora storicizzate.');
    }

    public function finalize(Request $request, Billing $billing)
    {
        $this->allowed(true);
        $data = $request->validate(['tenant_id' => 'required|integer', 'month' => 'required|date_format:Y-m', 'confirm' => 'accepted']);
        $billing->finalize(Tenant::findOrFail($data['tenant_id'])->id, $data['month']);

        return back()->with('notice', 'Consuntivo consolidato e bloccato.');
    }

    public function invoice(Request $request, Summary $summary)
    {
        $this->allowed(true);
        $data = $request->validate(['status' => 'required|in:to_invoice,invoiced,paid', 'invoice_number' => 'required_unless:status,to_invoice|nullable|string|max:100', 'invoice_date' => 'required_unless:status,to_invoice|nullable|date|before_or_equal:today', 'paid_on' => 'required_if:status,paid|nullable|date|before_or_equal:today|after_or_equal:invoice_date']);
        if ($data['status'] !== 'paid') {
            $data['paid_on'] = null;
        }
        if ($data['status'] === 'to_invoice') {
            $data['invoice_number'] = null;
            $data['invoice_date'] = null;
        }
        DB::transaction(fn () => $summary->update($data));

        return back()->with('notice', 'Stato amministrativo aggiornato.');
    }

    public function adjustment(Request $request, Billing $billing)
    {
        $this->allowed(true);
        $data = $request->validate(['tenant_id' => 'required|integer', 'month' => 'required|date_format:Y-m', 'label' => 'required|string|max:180', 'kind' => 'required|in:pension,stamp,other', 'amount' => 'required|regex:/^\d+(\.\d{1,2})?$/|numeric|min:0.01|max:1000000', 'note' => 'required|string|max:2000']);
        DB::transaction(function () use ($data, $billing) {
            $tenant = Tenant::whereKey($data['tenant_id'])->lockForUpdate()->firstOrFail();
            $month = Billing::month($data['month']);
            $billing->assertOpen($tenant->id, $month, $month->addMonth());
            Adjustment::create(['tenant_id' => $tenant->id, 'month' => $month->toDateString(), 'label' => $data['label'], 'kind' => $data['kind'], 'amount_cents' => (int) round($data['amount'] * 100), 'note' => $data['note']]);
        });

        return back()->with('notice', 'Voce separata aggiunta. Nessuna aliquota è stata calcolata automaticamente.');
    }

    public function removeAdjustment(Adjustment $adjustment, Billing $billing)
    {
        $this->allowed(true);
        DB::transaction(function () use ($adjustment, $billing) {
            Tenant::whereKey($adjustment->tenant_id)->lockForUpdate()->firstOrFail();
            $month = Billing::month($adjustment->month->format('Y-m'));
            $billing->assertOpen($adjustment->tenant_id, $month, $month->addMonth());
            $adjustment->delete();
        });

        return back()->with('notice', 'Voce rimossa.');
    }

    public function approvals()
    {
        abort_unless(auth()->user()->allowed('approvals'), 403);
        $approvals = Approval::with('tenant')->latest()->paginate(20);

        return view('hub.approvals', compact('approvals'));
    }

    public function requestApproval(Request $request, Billing $billing)
    {
        $this->allowed(true);
        $data = $request->validate(['tenant_id' => 'required|integer', 'ticket_id' => 'nullable|integer', 'month' => 'required|date_format:Y-m', 'title' => 'required|string|max:180', 'description' => 'required|string|max:10000', 'minutes' => 'required|integer|min:1|max:100000', 'amount' => 'required|regex:/^\d+(\.\d{1,2})?$/|numeric|min:0.01|max:1000000']);
        $tenant = Tenant::findOrFail($data['tenant_id']);
        if (! empty($data['ticket_id'])) {
            abort_unless(Ticket::findOrFail($data['ticket_id'])->tenant_id === $tenant->id, 404);
        }
        DB::transaction(function () use ($data, $billing, $tenant) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $month = Billing::month($data['month']);
            $billing->assertOpen($tenant->id, $month, $month->addMonth());
            $values = collect($data)->except('amount')->all();
            $values['month'] = $month->toDateString();
            Approval::create($values + ['amount_cents' => (int) round($data['amount'] * 100)]);
        });

        return back()->with('notice', 'Richiesta di approvazione creata.');
    }

    public function decide(Request $request, Approval $approval, Billing $billing)
    {
        abort_unless(auth()->user()->allowed('approvals'), 403);
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'decision_note' => auth()->user()->isAdmin() ? 'required|string|max:2000' : 'nullable|string|max:2000', 'confirm' => 'accepted']);
        DB::transaction(function () use ($approval, $data, $billing) {
            Tenant::whereKey($approval->tenant_id)->lockForUpdate()->firstOrFail();
            $approval->refresh();
            if ($approval->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'La richiesta ha già ricevuto una decisione.']);
            }
            $month = Billing::month($approval->month->format('Y-m'));
            $billing->assertOpen($approval->tenant_id, $month, $month->addMonth());
            $approval->update(collect($data)->except('confirm')->all() + ['decided_at' => now(), 'decided_by' => auth()->id()]);
        });

        return back()->with('notice','Decisione registrata nel registro attività.');
    }
}
