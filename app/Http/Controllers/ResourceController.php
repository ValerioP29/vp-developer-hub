<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tenant;
use App\Services\Audit;
use App\Services\Billing;
use App\Services\Notifier;
use App\Support\Resources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResourceController extends Controller
{
    private function definition(string $resource, bool $write = false): array
    {
        $def = Resources::get($resource);
        $user = auth()->user();
        abort_unless($user->allowed($def['ability'] ?? 'portal'), 403);
        if (($def['admin'] ?? false) || ($write && $resource !== 'tickets')) {
            abort_unless($user->isAdmin(), 403);
        }

        return $def;
    }

    public function index(Request $request, string $resource)
    {
        $def = $this->definition($resource);
        $q = $def['model']::query();
        $request->validate(['search' => 'nullable|string|max:180', 'tenant_id' => 'nullable|integer', 'application_id' => 'nullable|integer', 'category_id' => 'nullable|integer', 'month' => 'nullable|date_format:Y-m', 'status' => 'nullable|string|max:30']);
        $searchColumn = in_array($resource, ['tickets', 'interventions']) ? 'title' : 'name';
        if ($request->filled('search')) {
            $q->whereLike($searchColumn, '%'.$request->search.'%');
        }
        if (isset($def['fields']['tenant_id']) && $request->filled('tenant_id')) {
            $q->where('tenant_id', $request->tenant_id);
        }
        foreach (['application_id', 'category_id', 'status'] as $filter) {
            if (isset($def['fields'][$filter]) && $request->filled($filter)) {
                $q->where($filter, $request->$filter);
            }
        }
        if ($resource === 'interventions') {
            if ($request->filled('month')) {
                $month = Billing::month($request->month);
                $q->whereBetween('performed_on', [$month->toDateString(), $month->endOfMonth()->toDateString()]);
            }
            $q->withSum('entries', 'seconds')->with(['application', 'category', 'tenant']);
        }
        if ($resource === 'tickets') {
            $q->with(['application', 'tenant']);
        }
        $records = $q->orderByDesc('id')->paginate(15)->withQueryString();

        return view('hub.resource-index', compact('def', 'resource', 'records'));
    }

    public function show(string $resource, int $id)
    {
        $def = $this->definition($resource);
        $record = $def['model']::findOrFail($id);

        return view('hub.resource-show', compact('def', 'resource', 'record'));
    }

    public function create(string $resource)
    {
        $def = $this->definition($resource, true);

        return view('hub.resource-form', ['def' => $def, 'resource' => $resource, 'record' => new $def['model'], 'options' => $this->options($def)]);
    }

    public function edit(string $resource, int $id)
    {
        $def = $this->definition($resource, true);
        abort_unless(auth()->user()->isAdmin(), 403);
        $record = $def['model']::findOrFail($id);

        return view('hub.resource-form', compact('def', 'resource', 'record') + ['options' => $this->options($def)]);
    }

    private function options(array $def): array
    {
        $result = [];
        foreach ($def['fields'] as $key => $f) {
            if ($f['type'] === 'tenant') {
                $result[$key] = Tenant::orderBy('name')->get();
            } elseif ($f['type'] === 'relation') {
                $model = Resources::get($f['options'][0])['model'];
                $result[$key] = $model::orderBy('id')->get();
            }
        }

        return $result;
    }

    public function store(Request $request, string $resource)
    {
        return $this->save($request, $resource);
    }

    public function update(Request $request, string $resource, int $id)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return $this->save($request, $resource, $id);
    }

    private function save(Request $request, string $resource, ?int $id = null)
    {
        $def = $this->definition($resource, true);
        $record = $id ? $def['model']::findOrFail($id) : new $def['model'];
        $rules = [];
        foreach ($def['fields'] as $key => $f) {
            if (! auth()->user()->isAdmin() && in_array($key, ['tenant_id', 'status', 'priority'])) {
                continue;
            }
            $rules[$key] = explode('|', $f['rules']);
        }
        if ($resource === 'clients') {
            $rules['slug'][] = Rule::unique('tenants', 'slug')->ignore($id);
        }
        if ($resource === 'categories') {
            $rules['name'][] = Rule::unique('categories', 'name')->ignore($id);
        }
        $data = $request->validate($rules);
        foreach ($def['fields'] as $key => $f) {
            if (! array_key_exists($key, $rules)) {
                continue;
            }
            if ($f['type'] === 'checkbox') {
                $data[$key] = $request->boolean($key);
            }
            if ($f['type'] === 'money') {
                if (! preg_match('/^\d+(?:\.\d{1,2})?$/', (string) $data[$key])) {
                    throw ValidationException::withMessages([$key => 'Usa un importo positivo con al massimo due decimali.']);
                }
                $data[$key] = (int) round((float) $data[$key] * 100);
            }
        }
        if (isset($def['fields']['tenant_id'])) {
            $tenantId = auth()->user()->isAdmin() ? (int) $data['tenant_id'] : auth()->user()->tenant_id;
            Tenant::findOrFail($tenantId);
            $data['tenant_id'] = $tenantId;
            if ($id && $record->tenant_id !== $tenantId) {
                throw ValidationException::withMessages(['tenant_id' => 'Non è possibile trasferire una risorsa a un’altra azienda.']);
            }
            foreach ($def['fields'] as $key => $field) {
                if ($field['type'] === 'relation' && ! empty($data[$key])) {
                    $relation = Resources::get($field['options'][0])['model'];
                    $related = $relation::findOrFail($data[$key]);
                    if (isset($related->tenant_id) && $related->tenant_id !== $tenantId) {
                        throw ValidationException::withMessages([$key => 'La risorsa deve appartenere alla stessa azienda.']);
                    }
                }
            }
        }
        if ($resource === 'interventions' && ! Category::findOrFail($data['category_id'])->maintenance) {
            $data['uses_package'] = false;
        }
        if ($resource === 'contracts' && $data['model'] === 'payg' && ($data['fee_cents'] || $data['included_minutes'])) {
            throw ValidationException::withMessages(['model' => 'Il modello a consuntivo non prevede canone né ore incluse.']);
        }
        if ($resource === 'tickets' && ! $id) {
            $data['created_by'] = auth()->id();
            if (! auth()->user()->isAdmin()) {
                $data += ['status' => 'new', 'priority' => 'normal'];
            }
        }
        DB::transaction(function () use ($record, $data, $resource, $id) {
            if (isset($data['tenant_id'])) {
                Tenant::whereKey($data['tenant_id'])->lockForUpdate()->firstOrFail();
            }
            if ($resource === 'interventions' && $id) {
                $billing = app(Billing::class);
                foreach ($record->entries as $entry) {
                    $billing->assertOpen($record->tenant_id, $entry->started_at, $entry->ended_at);
                }
            }
            $record->fill($data)->save();
            if (in_array($resource, ['packages', 'categories'])) {
                Audit::record($id ? 'updated' : 'created', $record, null, $record->getAttributes());
            }
            if ($resource === 'tickets') {
                app(Notifier::class)->ticket($record, $id ? 'Richiesta aggiornata' : 'Nuova richiesta');
            }
        });

        return redirect(in_array($resource,['interventions', 'tickets']) ? "/$resource/{$record->id}" : "/manage/$resource")->with('notice','Dati salvati.');
    }
}
