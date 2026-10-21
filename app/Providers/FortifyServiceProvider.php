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
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.form', ['mode' => 'login']));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.form', ['mode' => 'forgot']));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.form', ['mode' => 'reset', 'request' => $request]));
        Fortify::confirmPasswordView(fn () => view('auth.form', ['mode' => 'confirm']));
        Fortify::twoFactorChallengeView(fn () => view('auth.form', ['mode' => 'challenge']));
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', mb_strtolower($request->email))->first();
            if (! $user || ! $user->active || ! Hash::check($request->password, $user->password)) {
                return null;
            }
            if (! $user->isAdmin() && Tenant::withoutGlobalScopes()->whereKey($user->tenant_id)->value('status') !== 'active') {
                return null;
            }

            return $user;
        });
        Fortify::resetUserPasswordsUsing(ResetPassword::class);
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(5)->by(mb_strtolower($r->input('email', '')).'|'.$r->ip()), Limit::perMinute(30)->by($r->ip())]);
        RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by($r->session()->get('login.id', $r->ip())));
        RateLimiter::for('hub', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?? $r->ip()));
    }
}
