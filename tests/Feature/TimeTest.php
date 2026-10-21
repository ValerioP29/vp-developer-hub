<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Intervention;
use App\Models\TimeEntry;
use App\Services\Billing;
use App\Services\TimeTracking;
use Illuminate\Validation\ValidationException;

class TimeTest extends HubTestCase
{
    public function test_overlap_is_rejected(): void
    {
        $this->entry(60);
        $this->expectException(ValidationException::class);
        $this->entry(60, '2026-10-10T09:30');
    }

    public function test_adjacent_entries_are_valid(): void
    {
        $this->entry(60);
        $this->entry(60, '2026-10-10T10:00');
        $this->assertSame(7200, (int) TimeEntry::sum('seconds'));
    }

    public function test_future_time_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->entry(60, '2026-10-21T10:00');
    }

    public function test_corrections_replace_duration_and_audit_old_value(): void
    {
        $entry = $this->entry();
        app(TimeTracking::class)->manual($this->work, '2026-10-10T09:00', 90, $entry, 1, 'Durata corretta');
        $this->assertSame(5400, (int) TimeEntry::sum('seconds'));
        $this->assertSame(2, $entry->fresh()->version);
        $log = AuditLog::where('subject_type', 'TimeEntry')->where('event', 'updated')->first();
        $this->assertSame(3600, $log->before['seconds']);
        $this->assertSame(5400, $log->after['seconds']);
    }

    public function test_stale_correction_is_rejected(): void
    {
        $entry = $this->entry();
        app(TimeTracking::class)->manual($this->work, '2026-10-10T09:00', 90, $entry, 1, 'Correction');
        $this->expectException(ValidationException::class);
        app(TimeTracking::class)->manual($this->work, '2026-10-10T09:00', 120, $entry, 1, 'Stale');
    }

    public function test_timer_pause_resume_and_retry_do_not_duplicate_time(): void
    {
        $s = app(TimeTracking::class);
        $s->start($this->work);
        $this->travel(10)->minutes();
        $s->pause($this->work);
        $s->pause($this->work);
        $this->travel(5)->minutes();
        $s->start($this->work);
        $this->travel(20)->minutes();
        $s->pause($this->work);
        $this->assertSame(1800, (int) TimeEntry::sum('seconds'));
        $this->assertSame(2, TimeEntry::count());
    }

    public function test_only_one_timer_per_operator(): void
    {
        app(TimeTracking::class)->start($this->work);
        $this->expectException(ValidationException::class);
        app(TimeTracking::class)->start($this->work);
    }

    public function test_running_timer_blocks_manual_overlap(): void
    {
        app(TimeTracking::class)->start($this->work);
        $this->travel(20)->minutes();
        $this->expectException(ValidationException::class);
        $this->entry(10, '2026-10-20T14:05');
    }

    public function test_intervention_billing_flags_cannot_change_after_finalization(): void
    {
        $this->activate('2026-09');
        $this->entry(60, '2026-09-10T09:00');
        app(Billing::class)->finalize($this->tenant->id, '2026-09');
        $this->put('/manage/interventions/'.$this->work->id, ['tenant_id' => $this->tenant->id, 'category_id' => $this->category->id, 'performed_on' => '2026-09-10', 'title' => 'Changed', 'status' => 'completed', 'billable' => false, 'uses_package' => true])->assertSessionHasErrors('time');
        $this->assertTrue($this->work->fresh()->billable);
    }

    public function test_new_development_is_always_excluded_from_maintenance(): void
    {
        $cat = Category::create(['name' => 'Nuovo sviluppo', 'maintenance' => false]);
        $this->post('/manage/interventions', ['tenant_id' => $this->tenant->id, 'category_id' => $cat->id, 'performed_on' => '2026-10-10', 'title' => 'New work', 'status' => 'planned', 'billable' => 1, 'uses_package' => 1])->assertSessionHasNoErrors();
        $this->assertFalse(Intervention::latest('id')->first()->uses_package);
    }
}
