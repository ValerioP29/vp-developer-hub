<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;

class AuthenticationTest extends HubTestCase
{
    public function test_bootstrap_emits_no_bytes_before_headers(): void
    {
        // A separate PHP process catches output hidden by PHPUnit's buffering.
        $process = new Process([PHP_BINARY, '-r', 'require "vendor/autoload.php"; require "bootstrap/app.php";'], base_path());
        $process->mustRun();
        $this->assertSame('', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    public function test_root_and_login_route_guests_and_authenticated_users(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/login')->assertRedirect('/dashboard');
        Auth::logout();
        $this->get('/')->assertRedirect('/login');
        $this->followingRedirects()->get('/')->assertOk()->assertSee('Email o username');
    }

    public function test_login_has_a_complete_html_form_and_csp(): void
    {
        Auth::logout();
        $response = $this->get('/login')->assertOk()->assertSee('<!doctype html>', false)
            ->assertSee('name="login"', false)->assertSee('name="password"', false)
            ->assertSee('name="_token"', false)->assertDontSee('name="remember"', false)
            ->assertDontSee('two-factor')->assertDontSee('Authenticator');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $this->assertSame(1, $dom->getElementsByTagName('form')->length);
        $this->assertSame(3, $dom->getElementsByTagName('input')->length);
        $this->assertStringContainsString("script-src 'self';", $response->headers->get('Content-Security-Policy'));
    }

    public function test_username_login_normalizes_case_and_whitespace_and_rotates_session(): void
    {
        $this->client->update(['username' => 'Alpha.User']);
        Auth::logout();
        $this->withSession(['marker' => 'preserved', 'url.intended' => '/security', 'login.id' => $this->admin->id]);
        $before = session()->getId();
        $this->post('/login', ['login' => '  ALPHA.User  ', 'password' => 'Test-Password!123'])
            ->assertRedirect('/dashboard')->assertSessionHas('marker', 'preserved')
            ->assertSessionMissing('login.id')->assertSessionMissing('url.intended');
        $this->assertAuthenticatedAs($this->client);
        $this->assertNotSame($before, session()->getId());
        $this->get('/dashboard')->assertOk();
    }

    public function test_email_login_is_case_insensitive_even_for_legacy_mixed_case_email(): void
    {
        $this->client->update(['email' => 'Alpha@Example.Test']);
        Auth::logout();
        $this->post('/login', ['login' => ' ALPHA@EXAMPLE.TEST ', 'password' => 'Test-Password!123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->client);
    }

    public function test_invalid_and_unavailable_accounts_have_the_same_login_error(): void
    {
        Auth::logout();
        foreach (['missing@example.test', $this->client->email, $this->client->username, $this->client->name] as $login) {
            $this->post('/login', ['login' => $login, 'password' => 'wrong'])
                ->assertSessionHasErrors(['login' => __('auth.failed')]);
            $this->assertGuest();
        }
        $this->client->update(['active' => false]);
        $this->post('/login', ['login' => $this->client->username, 'password' => 'Test-Password!123'])
            ->assertSessionHasErrors(['login' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_username_and_ip_login_limits_apply(): void
    {
        Auth::logout();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => $i % 2 ? ' ALPHA ' : 'alpha', 'password' => 'wrong'])->assertStatus(302);
        }
        $this->post('/login', ['login' => 'Alpha', 'password' => 'wrong'])->assertTooManyRequests();
        for ($i = 0; $i < 25; $i++) {
            $this->post('/login', ['login' => 'unknown-'.$i, 'password' => 'wrong'])->assertStatus(302);
        }
        $this->post('/login', ['login' => 'another', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_logout_invalidates_session_and_csrf_token(): void
    {
        $this->withSession(['private_marker' => 'remove']);
        $this->get('/dashboard');
        $id = session()->getId();
        $token = session()->token();
        $this->post('/logout')->assertRedirect('/')->assertSessionMissing('private_marker');
        $this->assertGuest();
        $this->assertNotSame($id, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_removed_endpoints_are_absent_for_guests_and_authenticated_users(): void
    {
        $endpoints = [
            ['get', '/security'], ['get', '/two-factor-challenge'], ['post', '/two-factor-challenge'],
            ['post', '/user/two-factor-authentication'], ['delete', '/user/two-factor-authentication'],
            ['post', '/user/confirmed-two-factor-authentication'], ['get', '/user/two-factor-qr-code'],
            ['get', '/user/two-factor-secret-key'], ['get', '/user/two-factor-recovery-codes'],
            ['post', '/user/two-factor-recovery-codes'],
        ];
        foreach ([true, false] as $authenticated) {
            if (! $authenticated) {
                Auth::logout();
            }
            foreach ($endpoints as [$method, $uri]) {
                $this->{$method}($uri)->assertNotFound();
            }
        }
    }

    public function test_protected_routes_still_require_authentication(): void
    {
        Auth::logout();
        foreach (['/dashboard', '/team', '/documents', '/billing', '/audit', '/manage/clients'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_configured_proxy_produces_https_redirects_assets_and_secure_cookies(): void
    {
        Auth::logout();
        TrustProxies::at(['10.0.2.2']);
        config(['session.secure' => true]);
        try {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.2'])
                ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'hub.example.test', 'X-Forwarded-Port' => '443']);
            $response = $this->get('/')->assertRedirect('https://hub.example.test/login')
                ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
            $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
            $this->assertTrue($cookie->isSecure());
            $this->assertTrue($cookie->isHttpOnly());
            $this->assertSame('lax', $cookie->getSameSite());
            $this->post('/login', ['login' => $this->client->email, 'password' => 'Test-Password!123'])
                ->assertRedirect('https://hub.example.test/dashboard');
        } finally {
            TrustProxies::flushState();
        }
    }

    public function test_untrusted_client_cannot_spoof_proxy_headers(): void
    {
        Auth::logout();
        TrustProxies::at(['10.0.2.2']);
        try {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.25'])
                ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example'])
                ->get('/');
            $this->assertStringNotContainsString('attacker.example', $response->headers->get('Location'));
            $response->assertHeaderMissing('Strict-Transport-Security');
        } finally {
            TrustProxies::flushState();
        }
    }

    public function test_username_unique_index_is_case_insensitive(): void
    {
        $this->client->update(['username' => 'unique.name']);
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $this->otherClient->id)->update(['username' => 'UNIQUE.NAME']);
    }

    public function test_malformed_login_is_rejected_without_a_server_error(): void
    {
        Auth::logout();
        $this->postJson('/login', ['login' => ['invalid'], 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('login');
        $this->assertGuest();
    }

    public function test_team_username_validation_preserves_tenant_authorization(): void
    {
        Notification::fake();
        $this->actingAs($this->client);
        $data = ['name' => 'Nuovo referente', 'email' => 'new@example.test', 'role' => 'client_user', 'tenant_id' => $this->other->id, 'username' => ' New.User '];
        $this->post('/team', $data)->assertSessionHasNoErrors();
        $created = User::where('email', $data['email'])->firstOrFail();
        $this->assertSame('new.user', $created->username);
        $this->assertSame($this->tenant->id, $created->tenant_id);
        $this->assertSame('client_user', $created->role);
        $this->post('/team', array_replace($data, ['email' => 'another@example.test', 'username' => 'NEW.USER']))->assertSessionHasErrors('username');
        $this->post('/team', array_replace($data, ['username' => ['invalid']]))->assertSessionHasErrors('username');
        $this->client->update(['role' => 'client_user']);
        $this->post('/team', $data)->assertForbidden();
    }

    public function test_suspended_tenant_cannot_login_with_username(): void
    {
        $this->tenant->update(['status' => 'suspended']);
        Auth::logout();
        $this->post('/login', ['login' => $this->client->username, 'password' => 'Test-Password!123'])
            ->assertSessionHasErrors(['login' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_assigning_username_preserves_existing_credentials_and_permissions(): void
    {
        $before = $this->client->only(['name', 'email', 'password', 'role', 'tenant_id', 'active', 'permissions']);
        $this->artisan('hub:set-username', ['email' => $this->client->email, 'username' => ' Alpha.User '])->assertSuccessful();
        $this->assertSame('alpha.user', $this->client->fresh()->username);
        $this->assertSame($before, $this->client->fresh()->only(array_keys($before)));
        $this->artisan('hub:set-username', ['email' => $this->otherClient->email, 'username' => 'ALPHA.USER'])->assertFailed();
        $this->artisan('hub:set-username', ['email' => $this->otherClient->email, 'username' => 'invalid@example.test'])->assertFailed();
    }

    public function test_username_migration_preserves_existing_rows_and_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_11_02_000100_add_username_to_users.php');
        $before = DB::table('users')->orderBy('id')->get()->map(fn ($user) => collect($user)->except('username')->all())->all();
        $migration->down();
        $migration->up();
        $after = DB::table('users')->orderBy('id')->get();
        $this->assertSame($before, $after->map(fn ($user) => collect($user)->except('username')->all())->all());
        foreach ($after as $user) {
            $this->assertSame('user-'.$user->id, $user->username);
        }
    }
}
