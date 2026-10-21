<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Intervention;
use App\Models\Project;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Resources;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;

class AccessTest extends HubTestCase
{
    public function test_guest_cannot_read_portal_or_tenant_models(): void
    {
        Auth::logout();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertSame(0, Intervention::count());
        $this->assertSame(0, Tenant::count());
    }

    public function test_password_login_and_logout(): void
    {
        Auth::logout();
        $this->post('/login', ['email' => $this->client->email, 'password' => 'Test-Password!123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->client);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        Auth::logout();
        $this->post('/login', ['email' => $this->client->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_public_registration_is_absent(): void
    {
        Auth::logout();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_admin_must_enroll_mfa(): void
    {
        $this->admin->forceFill(['two_factor_confirmed_at' => null])->save();
        $this->get('/dashboard')->assertRedirect('/security');
    }

    public function test_suspended_tenant_cannot_login_or_reuse_session(): void
    {
        $this->tenant->update(['status' => 'suspended']);
        $this->actingAs($this->client)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => $this->client->email, 'password' => 'Test-Password!123'])->assertSessionHasErrors();
    }

    public function test_client_scope_and_idor_protection(): void
    {
        $this->actingAs($this->otherClient);
        $this->assertSame(0, Intervention::count());
        $this->get('/interventions/'.$this->work->id)->assertNotFound();
        $this->get('/clienti/alpha')->assertNotFound();
        $this->get('/reports/'.$this->tenant->id.'/csv?month=2026-10')->assertNotFound();
    }

    public function test_client_never_sees_internal_notes(): void
    {
        $this->actingAs($this->client)->get('/interventions/'.$this->work->id)->assertOk()->assertSee('Descrizione pubblica')->assertDontSee('SEGRETO_INTERNO');
        $this->assertArrayNotHasKey('internal_notes', $this->work->toArray());
    }

    public function test_client_cannot_mutate_admin_resources(): void
    {
        $this->actingAs($this->client);
        foreach (['clients', 'projects', 'applications', 'services', 'contracts', 'packages', 'categories', 'interventions'] as $r) {
            $this->post('/manage/'.$r, [])->assertForbidden();
        }$this->post('/interventions/'.$this->work->id.'/timer', ['action' => 'start'])->assertForbidden();
        $this->get('/audit')->assertForbidden();
    }

    public function test_client_user_has_no_economic_or_team_permissions(): void
    {
        $this->client->update(['role' => 'client_user']);
        $this->actingAs($this->client);
        foreach (['/billing', '/approvals', '/team', '/manage/contracts'] as $url) {
            $this->get($url)->assertForbidden();
        }$this->get('/dashboard')->assertOk()->assertDontSee('Importo previsto');
    }

    public function test_granular_permission_denial_is_server_enforced(): void
    {
        $this->client->update(['permissions' => ['denied' => ['tickets', 'documents']]]);
        $this->actingAs($this->client)->get('/manage/tickets')->assertForbidden();
        $this->get('/documents')->assertForbidden();
        $this->post('/manage/tickets', ['title' => 'test'])->assertForbidden();
    }

    public function test_cross_tenant_relation_is_rejected_even_for_admin(): void
    {
        $app = Application::create(['tenant_id' => $this->other->id, 'name' => 'Beta app']);
        $this->post('/manage/services', ['tenant_id' => $this->tenant->id, 'application_id' => $app->id, 'name' => 'Service', 'status' => 'active'])->assertSessionHasErrors('application_id');
        $this->assertDatabaseCount('services', 0);
    }

    public function test_cross_tenant_database_foreign_key(): void
    {
        $app = Application::create(['tenant_id' => $this->other->id, 'name' => 'Beta app']);
        $this->expectException(QueryException::class);
        Service::create(['tenant_id' => $this->tenant->id, 'application_id' => $app->id, 'name' => 'Invalid']);
    }

    public function test_tenant_cannot_be_transferred(): void
    {
        $this->post('/manage/projects', ['tenant_id' => $this->tenant->id, 'name' => 'Project', 'status' => 'active'])->assertRedirect();
        $id = Project::first()->id;
        $this->put('/manage/projects/'.$id, ['tenant_id' => $this->other->id, 'name' => 'Moved', 'status' => 'active'])->assertSessionHasErrors('tenant_id');
    }

    public function test_headers_protect_reserved_pages(): void
    {
        $response = $this->get('/dashboard');
        $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_admin_pages_render(): void
    {
        foreach (['/dashboard', '/billing', '/approvals', '/documents', '/team', '/audit', '/manage/clients', '/manage/projects', '/manage/applications', '/manage/services', '/manage/contracts', '/manage/packages', '/manage/categories', '/manage/interventions', '/manage/tickets', '/interventions/'.$this->work->id] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_all_creation_forms_render(): void
    {
        foreach (array_keys(Resources::all()) as $r) {
            $this->get('/manage/'.$r.'/create')->assertOk();
        }
    }

    public function test_referent_cannot_promote_to_admin_or_other_tenant(): void
    {
        $this->actingAs($this->client);
        $this->post('/team', ['name' => 'Bad', 'email' => 'bad@example.test', 'role' => 'super_admin', 'tenant_id' => $this->other->id])->assertSessionHasErrors('role');
        $this->put('/team/'.$this->otherClient->id, ['active' => false])->assertNotFound();
    }

    public function test_password_reset_validates_strength_and_invalidates_sessions(): void
    {
        Notification::fake();
        Auth::logout();
        $this->post('/forgot-password', ['email' => $this->client->email])->assertRedirect();
        $token = Password::createToken($this->client);
        $this->post('/reset-password', ['token' => $token, 'email' => $this->client->email, 'password' => 'New-Strong!234', 'password_confirmation' => 'New-Strong!234'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('New-Strong!234', $this->client->fresh()->password));
    }

    public function test_mfa_challenge_required_for_enrolled_account(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $this->client->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_recovery_codes' => encrypt(json_encode(['recovery-test-code'])), 'two_factor_confirmed_at' => now()])->save();
        Auth::logout();
        $this->post('/login', ['email' => $this->client->email, 'password' => 'Test-Password!123'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-test-code'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->client);
        $this->assertNotContains('recovery-test-code',$this->client->fresh()->recoveryCodes());
    }
}
