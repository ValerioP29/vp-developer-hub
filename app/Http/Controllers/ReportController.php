<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Services\Billing;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function export(Request $request, Tenant $tenant, string $format, Billing $billing)
    {
        abort_unless(auth()->user()->allowed('billing'), 403);
        abort_unless(in_array($format, ['pdf', 'csv']), 404);
        $request->validate(['month' => 'required|date_format:Y-m']);
        $month = Billing::month($request->month);
        $end = $month->addMonth();
        $summary = $billing->preview($tenant->id, $request->month);
        $entries = TimeEntry::where('tenant_id', $tenant->id)->where('started_at', '<', $end->utc())->where('ended_at', '>', $month->utc())->with('intervention.application')->orderBy('started_at')->get();
        $rows = $entries->map(fn ($e) => ['date' => $e->started_at->setTimezone(config('hub.timezone'))->format('d/m/Y H:i'), 'title' => $e->intervention->title, 'application' => $e->intervention->application?->name ?? '—', 'seconds' => min($e->ended_at->timestamp, $end->timestamp) - max($e->started_at->timestamp, $month->timestamp), 'billable' => $e->intervention->billable ? 'Sì' : 'No', 'notes' => $e->intervention->public_notes]);
        $filename = 'VP-Hub-'.$tenant->slug.'-'.$month->format('Y-m');
        if ($format === 'pdf') {
            return Pdf::loadView('hub.report', compact('tenant', 'summary', 'rows'))->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])->download($filename.'.pdf');
        }

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Data', 'Attività', 'Applicativo', 'Minuti', 'Fatturabile', 'Note pubbliche'], ';', '"', '');
            foreach ($rows as $r) {
                $r['seconds'] = round($r['seconds'] / 60, 2);
                $values = array_map(function ($v) {
                    $v = (string) $v;

                    return preg_match('/^[\s]*[=+\-@\t\r]/u', $v) ? "'".$v : $v;
                }, array_values($r));
                fputcsv($stream, $values, ';', '"', '');
            }
            fclose($stream);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
