<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Intervention;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\Notifier;
use App\Support\Resources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function show(Ticket $ticket)
    {
        abort_unless(auth()->user()->allowed('tickets'), 403);
        $comments = TicketComment::where('ticket_id', $ticket->id)->when(! auth()->user()->isAdmin(), fn ($q) => $q->where('internal', false))->with('user')->orderBy('id')->get();
        $documents = Document::where('ticket_id', $ticket->id)->when(! auth()->user()->isAdmin(), fn ($q) => $q->where('client_visible', true))->get();
        $interventions = Intervention::where('ticket_id', $ticket->id)->withSum('entries', 'seconds')->get();

        return view('hub.ticket', compact('ticket', 'comments', 'documents', 'interventions'));
    }

    public function comment(Request $request, Ticket $ticket, Notifier $notifier)
    {
        abort_unless(auth()->user()->allowed('tickets'), 403);
        $data = $request->validate(['body' => 'required|string|max:10000', 'internal' => 'boolean']);
        DB::transaction(function () use ($ticket, $data, $request, $notifier) {
            $internal = auth()->user()->isAdmin() && $request->boolean('internal');
            TicketComment::create(['tenant_id' => $ticket->tenant_id, 'ticket_id' => $ticket->id, 'user_id' => auth()->id(), 'body' => $data['body'], 'internal' => $internal]);
            if (! $internal) {
                $notifier->ticket($ticket, 'Nuovo commento');
            }
        });

        return back()->with('notice', 'Commento aggiunto.');
    }

    public function status(Request $request, Ticket $ticket, Notifier $notifier)
    {
        abort_unless(auth()->user()->allowed('tickets'), 403);
        $data = $request->validate(['status' => 'required|in:new,review,in_progress,waiting,resolved,closed', 'reason' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($ticket, $data, $notifier) {
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if (! auth()->user()->isAdmin() && ! (in_array($ticket->status, ['resolved', 'closed']) && in_array($data['status'], ['new', 'closed']))) {
                abort(403);
            }
            if ($data['status'] === 'new' && blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'Descrivi il motivo della riapertura.']);
            }
            $ticket->update(['status' => $data['status']]);
            TicketComment::create(['tenant_id' => $ticket->tenant_id, 'ticket_id' => $ticket->id, 'user_id' => auth()->id(), 'body' => 'Stato: '.Resources::label($data['status']).'. '.($data['reason'] ?? ''), 'internal' => false]);
            $notifier->ticket($ticket, 'Stato aggiornato');
        });

        return back()->with('notice','Stato aggiornato.');
    }
}
