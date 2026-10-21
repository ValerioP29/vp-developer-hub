<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    private function authorizeDocument(Document $document): void
    {
        abort_unless(auth()->user()->allowed('documents'), 403);
        abort_unless(auth()->user()->isAdmin() || $document->client_visible, 404);
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->allowed('documents'), 403);
        $request->validate(['category' => 'nullable|string|max:80', 'tenant_id' => 'nullable|integer']);
        $documents = Document::with('tenant')->when(! auth()->user()->isAdmin(), fn ($q) => $q->where('client_visible', true))->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->tenant_id))->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))->latest()->paginate(15)->withQueryString();

        return view('hub.documents', compact('documents'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->allowed('documents'), 403);
        $data = $request->validate(['tenant_id' => 'required_if:unused,never|nullable|integer', 'ticket_id' => 'nullable|integer', 'contract_id' => 'nullable|integer', 'title' => 'required|string|max:180', 'category' => 'required|in:contract,technical,quote,report,service,attachment,logo', 'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,txt,csv', 'max:'.config('hub.upload_max_kb')], 'previous_id' => 'nullable|integer', 'client_visible' => 'boolean']);
        $tenantId = auth()->user()->isAdmin() ? (int) ($data['tenant_id'] ?? 0) : auth()->user()->tenant_id;
        Tenant::findOrFail($tenantId);
        if (! auth()->user()->isAdmin()) {
            abort_unless(! empty($data['ticket_id']) && empty($data['previous_id']) && empty($data['contract_id']), 403);
            $data['category'] = 'attachment';
        }
        foreach (['ticket_id' => Ticket::class, 'contract_id' => Contract::class] as $key => $class) {
            if (! empty($data[$key])) {
                $related = $class::findOrFail($data[$key]);
                abort_unless($related->tenant_id === $tenantId, 404);
            }
        }
        $file = $request->file('file');
        if ($data['category'] === 'logo' && ! in_array($file->getMimeType(), ['image/png', 'image/jpeg'])) {
            throw ValidationException::withMessages(['file' => 'Il logo deve essere PNG o JPEG.']);
        }
        $path = $file->store('tenants/'.$tenantId, 'local');
        try {
            DB::transaction(function () use ($data, $tenantId, $path, $file, $request) {
                Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
                $previous = empty($data['previous_id']) ? null : Document::findOrFail($data['previous_id']);
                if ($previous) {
                    $this->authorizeDocument($previous);
                    abort_unless($previous->tenant_id === $tenantId, 404);
                }
                $series = $previous?->series ?? (string) Str::uuid();
                $version = $previous ? Document::where('series', $series)->max('version') + 1 : 1;
                Document::create(['tenant_id' => $tenantId, 'ticket_id' => $previous?->ticket_id ?? ($data['ticket_id'] ?? null), 'contract_id' => $previous?->contract_id ?? ($data['contract_id'] ?? null),
                    'series' => $series, 'version' => $version, 'title' => $data['title'], 'category' => $data['category'], 'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                    'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'client_visible' => auth()->user()->isAdmin() ? $request->boolean('client_visible') : true, 'uploaded_by' => auth()->id()]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('notice', 'Documento caricato in archivio privato.');
    }

    public function download(Document $document)
    {
        $this->authorizeDocument($document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        Audit::record('downloaded', $document);

        return Storage::disk('local')->download($document->path, $document->original_name, ['Content-Type' => 'application/octet-stream']);
    }

    public function logo(Document $document)
    {
        $this->authorizeDocument($document);
        abort_unless($document->category === 'logo' && in_array($document->mime, ['image/png', 'image/jpeg']), 404);

        return response()->file(Storage::disk('local')->path($document->path),['Content-Type' => $document->mime]);
    }
}
