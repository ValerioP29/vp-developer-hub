<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HubAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user?->active || (! $user->isAdmin() && $user->tenant?->status !== 'active')) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors(['email' => 'Accesso non disponibile. Contatta l’amministratore.']);
        }
        if ($user->isAdmin() && ! $user->two_factor_confirmed_at) {
            return redirect('/security')->with('notice', 'Configura l’autenticazione a due fattori per accedere al centro operativo.');
        }

        return $next($request);
    }
}
