<?php

namespace Tests\Feature;

use App\Models\Activation;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Intervention;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Billing;
use App\Services\TimeTracking;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class HubTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $client;

    protected User $otherClient;

    protected Tenant $tenant;

    protected Tenant $other;

    protected Category $category;

    protected Contract $contract;

    protected Intervention $work;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-20 12:00:00');
        CarbonImmutable::setTestNow('2026-10-20 12:00:00');
        $this->admin = User::create(['name' => 'Admin Test', 'email' => 'admin@example.test', 'password' => 'Test-Password!123', 'role' => 'super_admin']);
        $this->admin->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->actingAs($this->admin);
        $this->tenant = Tenant::create(['name' => 'Alpha Demo', 'slug' => 'alpha', 'status' => 'active']);
        $this->other = Tenant::create(['name' => 'Beta Demo', 'slug' => 'beta', 'status' => 'active']);
        $this->client = User::create(['name' => 'Cliente Alpha', 'email' => 'alpha@example.test', 'password' => 'Test-Password!123', 'tenant_id' => $this->tenant->id, 'role' => 'client_admin']);
        $this->otherClient = User::create(['name' => 'Cliente Beta', 'email' => 'beta@example.test', 'password' => 'Test-Password!123', 'tenant_id' => $this->other->id, 'role' => 'client_admin']);
        $this->category = Category::create(['name' => 'Manutenzione', 'maintenance' => true]);
        $this->contract = Contract::create(['tenant_id' => $this->tenant->id, 'name' => 'Accordo', 'model' => 'package', 'starts_on' => '2026-01-01', 'fee_cents' => 15000, 'hourly_rate_cents' => 3000, 'included_minutes' => 300]);
        $this->work = Intervention::create(['tenant_id' => $this->tenant->id, 'category_id' => $this->category->id, 'performed_on' => '2026-10-10', 'title' => 'Intervento Alpha', 'description' => 'Descrizione pubblica', 'internal_notes' => 'SEGRETO_INTERNO', 'billable' => true, 'uses_package' => true]);
    }

    protected function activate(string $month = '2026-10', array $overrides = []): Activation
    {
        return app(Billing::class)->activate($this->contract, array_merge(['month' => $month, 'label' => 'Light personalizzato', 'included_minutes' => 300, 'fee_cents' => 15000, 'hourly_rate_cents' => 3000], $overrides));
    }

    protected function entry(int $minutes = 60, string $start = '2026-10-10T09:00', ?Intervention $work = null): TimeEntry
    {
        return app(TimeTracking::class)->manual($work ?? $this->work, $start, $minutes);
    }

    protected function preview(string $month = '2026-10'): array
    {
        return app(Billing::class)->preview($this->tenant->id, $month);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }
}
