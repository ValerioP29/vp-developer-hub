<?php

namespace App\Providers;

use App\Actions\ResetPassword;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponse::class, \App\Http\Responses\LoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.form', ['mode' => 'login']));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.form', ['mode' => 'forgot']));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.form', ['mode' => 'reset', 'request' => $request]));
        Fortify::confirmPasswordView(fn () => view('auth.form', ['mode' => 'confirm']));
        Fortify::authenticateUsing(function (Request $request) {
            $identifier = mb_strtolower(trim($request->string('login')->toString()));
            $column = str_contains($identifier, '@') ? 'email' : 'username';
            $user = User::whereRaw('LOWER('.$column.') = ?', [$identifier])->first();
            if (! $user || ! $user->active || ! Hash::check($request->password, $user->password)) {
                return null;
            }
            if (! $user->isAdmin() && Tenant::withoutGlobalScopes()->whereKey($user->tenant_id)->value('status') !== 'active') {
                return null;
            }

            return $user;
        });
        Fortify::resetUserPasswordsUsing(ResetPassword::class);
        RateLimiter::for('login', function (Request $request) {
            // Throttling runs before Fortify validates the request payload.
            $identifier = $request->input('login');
            $identifier = is_string($identifier) ? mb_strtolower(trim($identifier)) : '';

            return [Limit::perMinute(5)->by($identifier.'|'.$request->ip()), Limit::perMinute(30)->by($request->ip())];
        });
        RateLimiter::for('hub', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?? $r->ip()));
    }
}
