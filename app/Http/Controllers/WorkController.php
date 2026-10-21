<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use App\Models\TimeEntry;
use App\Models\Timer;
use App\Services\TimeTracking;
use Illuminate\Http\Request;

class WorkController extends Controller
{
    public function show(Intervention $intervention)
    {
        abort_unless(auth()->user()->allowed('portal'), 403);
        $intervention->load(['application', 'category', 'entries.user', 'tenant', 'ticket']);
        $timer = auth()->user()->isAdmin() ? Timer::where('user_id', auth()->id())->first() : null;

        return view('hub.intervention', compact('intervention', 'timer'));
    }

    public function time(Request $request, Intervention $intervention, TimeTracking $tracking)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $request->validate(['started_at' => 'required|date_format:Y-m-d\TH:i', 'minutes' => 'required|integer|min:1|max:1440', 'entry_id' => 'nullable|integer', 'version' => 'required_with:entry_id|integer', 'reason' => 'required_with:entry_id|nullable|string|max:2000']);
        $entry = empty($data['entry_id']) ? null : TimeEntry::findOrFail($data['entry_id']);
        $tracking->manual($intervention, $data['started_at'], (int) $data['minutes'], $entry, isset($data['version']) ? (int) $data['version'] : null, $data['reason'] ?? null);

        return back()->with('notice', 'Tempo registrato. I totali sono stati aggiornati.');
    }

    public function timer(Request $request, Intervention $intervention, TimeTracking $tracking)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $request->validate(['action' => 'required|in:start,pause']);
        $tracking->{$data['action']}($intervention);

        return back()->with('notice', $data['action'] === 'start' ? 'Timer avviato.' : 'Timer interrotto e tempo salvato.');
    }
}
