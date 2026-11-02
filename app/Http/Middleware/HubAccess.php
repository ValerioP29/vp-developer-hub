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

            return redirect('/login')->withErrors(['login' => __('auth.failed')]);
        }

        return $next($request);
    }
}
