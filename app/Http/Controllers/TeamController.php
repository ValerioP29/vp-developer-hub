<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    private function allowed(): void
    {
        abort_unless(auth()->user()->allowed('team'), 403);
    }

    public function index()
    {
        $this->allowed();
        $users = User::when(! auth()->user()->isAdmin(), fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id))->orderBy('name')->paginate(20);

        return view('hub.team', compact('users'));
    }

    public function store(Request $request)
    {
        $this->allowed();
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:180|unique:users', 'tenant_id' => 'nullable|integer', 'role' => ['required', Rule::in(auth()->user()->isAdmin() ? ['client_admin', 'client_user'] : ['client_user'])]]);
        $tenantId = auth()->user()->isAdmin() ? (int) ($data['tenant_id'] ?? 0) : auth()->user()->tenant_id;
        Tenant::findOrFail($tenantId);
        $data['tenant_id'] = $tenantId;
        $data['email'] = mb_strtolower($data['email']);
        $data['password'] = Str::password(32);
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            Audit::record('user_created', $user, null, $user->toArray());

            return $user;
        });
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('notice', 'Referente creato. Inviata la procedura per impostare la password tramite il servizio email configurato.');
    }

    public function update(Request $request, int $id)
    {
        $this->allowed();
        $user = User::whereKey($id)->when(! auth()->user()->isAdmin(), fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id)->where('role', 'client_user'))->firstOrFail();
        abort_if($user->id === auth()->id() || $user->isAdmin(), 403);
        $data = $request->validate(['active' => 'required|boolean', 'denied' => 'nullable|array', 'denied.*' => 'in:billing,approvals,team,tickets,documents']);
        DB::transaction(function () use ($user, $data) {
            $before = $user->toArray();
            $user->update(['active' => $data['active'], 'permissions' => ['denied' => $data['denied'] ?? []]]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::record('user_permissions', $user, $before, $user->toArray());
        });

        return back()->with('notice', 'Accessi aggiornati.');
    }

    public function resend(int $id)
    {
        $this->allowed();
        $user = User::whereKey($id)->when(! auth()->user()->isAdmin(), fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id)->where('role', 'client_user'))->firstOrFail();
        abort_if($user->isAdmin(), 403);
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('notice','Procedura di recupero password inviata.');
    }
}
