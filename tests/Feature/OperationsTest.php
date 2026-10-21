<?php

namespace Tests\Feature;

use App\Models\Adjustment;
use App\Models\Project;
use App\Models\Tenant;
use App\Notifications\HubNotification;
use App\Services\Billing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OperationsTest extends HubTestCase
{
    public function test_contract_starting_on_first_of_month_can_activate(): void
    {
        $this->contract->update(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']);
        $this->activate();
        $this->assertSame(15000, $this->preview()['total_cents']);
    }

    public function test_manual_fiscal_elements_are_separate_and_not_automatic(): void
    {
        $this->activate();
        $this->assertSame(0, $this->preview()['adjustment_cents']);
        $this->post('/billing/adjustments', ['tenant_id' => $this->tenant->id, 'month' => '2026-10', 'label' => 'Bollo concordato', 'kind' => 'stamp', 'amount' => '2.00', 'note' => 'Importo manuale verificato'])->assertSessionHasNoErrors();
        $p = $this->preview();
        $this->assertSame(15000, $p['total_cents']);
        $this->assertSame(200, $p['adjustment_cents']);
        $this->assertSame(15200, $p['amount_due_cents']);
        $id = Adjustment::first()->id;
        $this->actingAs($this->client)->delete('/billing/adjustments/'.$id)->assertForbidden();
        $this->actingAs($this->admin)->delete('/billing/adjustments/'.$id)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('adjustments', 0);
    }

    public function test_adjustments_and_decisions_cannot_change_after_close(): void
    {
        $this->activate('2026-09');
        app(Billing::class)->finalize($this->tenant->id, '2026-09');
        $this->post('/billing/adjustments', ['tenant_id' => $this->tenant->id, 'month' => '2026-09', 'label' => 'Bollo', 'kind' => 'stamp', 'amount' => '2.00', 'note' => 'Test'])->assertSessionHasErrors('time');
    }

    public function test_nonexistent_dst_time_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->entry(60, '2026-03-29T02:30');
    }

    public function test_elapsed_duration_across_dst_is_preserved(): void
    {
        $entry = $this->entry(120, '2026-03-29T01:30');
        $this->assertSame(7200, $entry->seconds);
        $this->assertSame('04:30', $entry->ended_at->setTimezone('Europe/Rome')->format('H:i'));
    }

    public function test_crud_client_project_application_and_archival(): void
    {
        $this->post('/manage/clients', ['name' => 'Gamma', 'slug' => 'gamma', 'status' => 'active', 'email' => 'gamma@example.test'])->assertSessionHasNoErrors();
        $t = Tenant::where('slug', 'gamma')->firstOrFail();
        $this->post('/manage/projects', ['tenant_id' => $t->id, 'name' => 'Progetto Gamma', 'status' => 'active'])->assertSessionHasNoErrors();
        $project = Project::firstOrFail();
        $this->post('/manage/applications', ['tenant_id' => $t->id, 'project_id' => $project->id, 'name' => 'App Gamma', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->put('/manage/clients/'.$t->id, ['name' => 'Gamma', 'slug' => 'gamma', 'status' => 'archived'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tenants', ['id' => $t->id, 'status' => 'archived']);
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_search_and_filters_render_correct_results(): void
    {
        $this->get('/manage/interventions?month=2026-10&search=Alpha&category_id='.$this->category->id)->assertOk()->assertSee('Intervento Alpha');
        $this->get('/manage/interventions?month=2026-09')->assertOk()->assertDontSee('Intervento Alpha');
    }

    public function test_contract_details_include_scope_for_client_admin(): void
    {
        $this->contract->update(['included_activities' => 'Attività concordate', 'excluded_activities' => 'Sviluppo escluso']);
        $this->actingAs($this->client)->get('/manage/contracts/'.$this->contract->id)->assertOk()->assertSee('Attività concordate')->assertSee('Sviluppo escluso');
    }

    public function test_user_input_is_html_escaped(): void
    {
        $this->work->update(['description' => '<script>alert("bad")</script>']);
        $this->actingAs($this->client)->get('/interventions/'.$this->work->id)->assertOk()->assertDontSee('<script>alert("bad")</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_ticket_notifications_are_configurable_and_queued(): void
    {
        Notification::fake();
        config(['hub.notifications' => true]);
        $this->tenant->update(['email_notifications' => true]);
        $this->post('/manage/tickets', ['tenant_id' => $this->tenant->id, 'title' => 'Test mail', 'description' => 'Demo', 'status' => 'new', 'priority' => 'normal'])->assertSessionHasNoErrors();
        Notification::assertSentTo($this->client, HubNotification::class);
        Notification::assertNotSentTo($this->otherClient, HubNotification::class);
        $this->assertInstanceOf(ShouldQueue::class, new HubNotification('Update', 1));
    }

    public function test_login_rate_limit(): void
    {
        Auth::logout();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'bad@example.test', 'password' => 'wrong']);
        }$response = $this->post('/login', ['email' => 'bad@example.test', 'password' => 'wrong']);
        $response->assertStatus(429);
    }

    public function test_demo_refuses_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('hub:demo')->assertFailed();
    }
}
