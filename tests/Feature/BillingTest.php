<?php

namespace Tests\Feature;

use App\Models\Activation;
use App\Models\Approval;
use App\Models\TimeEntry;
use App\Services\Billing;
use Illuminate\Validation\ValidationException;

class BillingTest extends HubTestCase
{
    public function test_package_counts_only_recorded_billable_time(): void
    {
        $this->activate();
        $this->entry(120);
        $p = $this->preview();
        $this->assertSame(7200, $p['used_seconds']);
        $this->assertSame(10800, $p['remaining_seconds']);
        $this->assertSame(15000, $p['total_cents']);
    }

    public function test_unused_time_expires_by_default(): void
    {
        $this->activate('2026-09');
        $this->entry(60, '2026-09-10T09:00');
        app(Billing::class)->finalize($this->tenant->id, '2026-09');
        $this->activate();
        $this->assertSame(18000, $this->preview()['included_seconds']);
    }

    public function test_carryover_requires_finalized_previous_month_and_contract_opt_in(): void
    {
        $this->contract->update(['carryover' => true]);
        $this->activate('2026-09');
        $this->entry(60, '2026-09-10T09:00');
        app(Billing::class)->finalize($this->tenant->id, '2026-09');
        $a = $this->activate();
        $this->assertSame(240, $a->carried_minutes);
        $this->assertSame(32400, $this->preview()['included_seconds']);
    }

    public function test_price_changes_do_not_reprice_approved_months(): void
    {
        $this->activate();
        $this->entry(60);
        $this->contract->update(['fee_cents' => 99900, 'hourly_rate_cents' => 9900]);
        $this->assertSame(15000, $this->preview()['total_cents']);
        $this->assertSame(3000, $this->preview()['hourly_rate_cents']);
    }

    public function test_extras_are_not_billed_without_approval(): void
    {
        $this->activate();
        $this->entry(360);
        $p = $this->preview();
        $this->assertSame(3600, $p['pending_seconds']);
        $this->assertSame(0, $p['extra_cents']);
        $this->assertSame(15000, $p['total_cents']);
    }

    public function test_approval_time_and_amount_caps_are_enforced(): void
    {
        $this->activate();
        $this->entry(420);
        Approval::create(['tenant_id' => $this->tenant->id, 'month' => '2026-10-01', 'title' => 'Extra', 'description' => 'Approved', 'minutes' => 120, 'amount_cents' => 1500, 'status' => 'approved', 'decided_at' => now(), 'decided_by' => $this->client->id]);
        $p = $this->preview();
        $this->assertSame(1500, $p['extra_cents']);
        $this->assertSame(1800, $p['approved_extra_seconds']);
        $this->assertSame(5400, $p['pending_seconds']);
    }

    public function test_rejected_or_pending_approvals_do_not_count(): void
    {
        $this->activate();
        $this->entry(360);
        foreach (['pending', 'rejected'] as $s) {
            Approval::create(['tenant_id' => $this->tenant->id, 'month' => '2026-10-01', 'title' => 'Extra', 'description' => 'Test', 'minutes' => 60, 'amount_cents' => 3000, 'status' => $s]);
        }$this->assertSame(0, $this->preview()['extra_cents']);
    }

    public function test_nonbillable_does_not_consume_hours(): void
    {
        $this->activate();
        $this->work->update(['billable' => false]);
        $this->entry(60);
        $p = $this->preview();
        $this->assertSame(3600, $p['worked_seconds']);
        $this->assertSame(0, $p['used_seconds']);
        $this->assertSame(3600, $p['nonbillable_seconds']);
    }

    public function test_excluded_work_stays_visible_without_consuming_package(): void
    {
        $this->activate();
        $this->work->update(['uses_package' => false]);
        $this->entry(60);
        $p = $this->preview();
        $this->assertSame(0, $p['used_seconds']);
        $this->assertSame(3600, $p['pending_seconds']);
    }

    public function test_pay_as_you_go_uses_agreed_rate_without_mandatory_fee(): void
    {
        $this->contract->update(['model' => 'payg']);
        $this->activate('2026-10', ['included_minutes' => 0, 'fee_cents' => 0, 'hourly_rate_cents' => 4000]);
        $this->entry(90);
        $this->assertSame(6000, $this->preview()['total_cents']);
        $this->assertSame(0, $this->preview()['pending_seconds']);
    }

    public function test_fixed_fee_without_recorded_hours(): void
    {
        $this->contract->update(['model' => 'fixed']);
        $this->activate('2026-10', ['fee_cents' => 50000]);
        $this->assertSame(50000, $this->preview()['total_cents']);
    }

    public function test_work_without_activation_is_never_automatically_billed(): void
    {
        $this->entry(60);
        $this->assertSame(0, $this->preview()['total_cents']);
        $this->assertSame(3600, $this->preview()['pending_seconds']);
    }

    public function test_duplicate_activation_is_rejected(): void
    {
        $this->activate();
        $this->expectException(ValidationException::class);
        $this->activate();
    }

    public function test_expired_contract_cannot_activate(): void
    {
        $this->contract->update(['ends_on' => '2026-09-30']);
        $this->expectException(ValidationException::class);
        $this->activate();
    }

    public function test_month_boundary_in_local_timezone_is_split_correctly(): void
    {
        $this->activate('2026-09');
        $this->activate();
        $this->entry(120, '2026-09-30T23:30');
        $this->assertSame(1800, $this->preview('2026-09')['worked_seconds']);
        $this->assertSame(5400, $this->preview()['worked_seconds']);
    }

    public function test_finalization_locks_historical_time_and_conditions(): void
    {
        $this->activate('2026-09');
        $this->entry(60, '2026-09-10T09:00');
        $s = app(Billing::class)->finalize($this->tenant->id, '2026-09');
        $this->assertSame('to_invoice', $s->status);
        $this->assertTrue($this->preview('2026-09')['finalized']);
        $this->expectException(ValidationException::class);
        $this->entry(60, '2026-09-11T09:00');
    }

    public function test_current_month_cannot_be_finalized(): void
    {
        $this->activate();
        $this->expectException(ValidationException::class);
        app(Billing::class)->finalize($this->tenant->id, '2026-10');
    }

    public function test_pending_extra_blocks_finalization(): void
    {
        $this->activate('2026-09');
        $this->entry(360, '2026-09-10T09:00');
        $this->expectException(ValidationException::class);
        app(Billing::class)->finalize($this->tenant->id, '2026-09');
    }

    public function test_issuing_invoice_does_not_mark_paid(): void
    {
        $this->activate('2026-09');
        $s = app(Billing::class)->finalize($this->tenant->id, '2026-09');
        $this->put('/billing/'.$s->id.'/invoice', ['status' => 'invoiced', 'invoice_number' => '2026-01', 'invoice_date' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->assertSame('invoiced', $s->fresh()->status);
        $this->assertNull($s->fresh()->paid_on);
        $this->put('/billing/'.$s->id.'/invoice', ['status' => 'paid', 'invoice_number' => '2026-01', 'invoice_date' => '2026-10-01'])->assertSessionHasErrors('paid_on');
    }

    public function test_client_admin_can_approve_but_not_twice(): void
    {
        $a = Approval::create(['tenant_id' => $this->tenant->id, 'month' => '2026-10-01', 'title' => 'Extra', 'description' => 'Test', 'minutes' => 60, 'amount_cents' => 3000]);
        $this->actingAs($this->client)->post('/approvals/'.$a->id.'/decide', ['status' => 'approved', 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame('approved', $a->fresh()->status);
        $this->post('/approvals/'.$a->id.'/decide', ['status' => 'rejected', 'confirm' => 1])->assertSessionHasErrors('status');
    }

    public function test_client_cannot_approve_another_tenant(): void
    {
        $a = Approval::create(['tenant_id' => $this->other->id, 'month' => '2026-10-01', 'title' => 'Extra', 'description' => 'Test', 'minutes' => 60, 'amount_cents' => 3000]);
        $this->actingAs($this->client)->post('/approvals/'.$a->id.'/decide', ['status' => 'approved', 'confirm' => 1])->assertNotFound();
    }

    public function test_activation_endpoint_stores_money_in_cents(): void
    {
        $this->post('/billing/activate', ['contract_id' => $this->contract->id, 'month' => '2026-10', 'label' => 'Custom', 'included_minutes' => 300, 'fee' => '150.50', 'rate' => '30.25', 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame(15050, Activation::first()->fee_cents);
    }

    public function test_approval_endpoint_accepts_month_and_preserves_amount(): void
    {
        $this->post('/approvals', ['tenant_id' => $this->tenant->id, 'month' => '2026-10', 'title' => 'Extra', 'description' => 'Description', 'minutes' => 30, 'amount' => '15.50'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-01', Approval::first()->month->toDateString());
        $this->assertSame(1550, Approval::first()->amount_cents);
    }

    public function test_carryover_preserves_timer_seconds_without_rounding_loss(): void
    {
        $this->contract->update(['carryover' => true]);
        $this->activate('2026-09');
        TimeEntry::create(['tenant_id' => $this->tenant->id, 'intervention_id' => $this->work->id, 'user_id' => $this->admin->id, 'started_at' => '2026-09-10 08:00:00', 'ended_at' => '2026-09-10 08:00:31', 'seconds' => 31, 'source' => 'timer']);
        app(Billing::class)->finalize($this->tenant->id, '2026-09');
        $activation = $this->activate();
        $this->assertSame(29, $activation->carried_remainder_seconds);
        $this->assertSame(35969, $this->preview()['included_seconds']);
    }
}
