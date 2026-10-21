<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Intervention;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\Timer;
use App\Services\Billing;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, Billing $billing)
    {
        abort_unless(auth()->user()->allowed('portal'), 403);
        $month = $request->input('month', now(config('hub.timezone'))->format('Y-m'));
        $date = Billing::month($month);
        $tenants = Tenant::orderBy('name')->get();
        $totals = $billing->previewMany($tenants->modelKeys(), $month);
        $metrics = ['clients' => $tenants->where('status', 'active')->count(), 'contracts' => Contract::where('status', 'active')->where('starts_on', '<=', $date->endOfMonth()->toDateString())->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $date->toDateString()))->count(), 'seconds' => array_sum(array_column($totals, 'worked_seconds')), 'remaining' => array_sum(array_column($totals, 'remaining_seconds')), 'pending' => Ticket::whereNotIn('status', ['resolved', 'closed'])->count(), 'open' => Intervention::where('status', '!=', 'completed')->count(), 'amount' => array_sum(array_column($totals, 'total_cents'))];
        $recent = Intervention::with(['tenant', 'application', 'category'])->withSum('entries', 'seconds')->orderByDesc('performed_on')->limit(6)->get();
        $tickets = Ticket::with('tenant')->whereNotIn('status', ['resolved', 'closed'])->latest()->limit(5)->get();
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $date->subMonths($i)->format('Y-m');
            $monthly = $m === $month ? $totals : $billing->previewMany($tenants->modelKeys(), $m);
            $seconds = array_sum(array_column($monthly, 'worked_seconds'));
            $trend[] = ['label' => Billing::month($m)->translatedFormat('M'), 'seconds' => $seconds];
        }
        $applications = Application::where('status', 'active')->get();
        $services = Service::where('status', 'active')->get();
        $logo = auth()->user()->isAdmin() ? null : Document::where('category', 'logo')->where('client_visible', true)->latest()->first();
        $timer = auth()->user()->isAdmin() ? Timer::where('user_id', auth()->id())->whereNotNull('started_at')->with('intervention')->first() : null;

        return view('hub.dashboard', compact('month', 'tenants', 'totals', 'metrics', 'recent', 'tickets', 'trend', 'applications', 'services', 'logo', 'timer'));
    }

    public function audit()
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $logs = AuditLog::latest('id')->paginate(30);

        return view('hub.audit', compact('logs'));
    }
}
