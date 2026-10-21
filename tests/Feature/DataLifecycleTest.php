<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Tenant;
use App\Services\Billing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DataLifecycleTest extends HubTestCase
{
    public function test_batch_summaries_preserve_results_and_avoid_per_tenant_queries(): void
    {
        $this->activate();
        $this->entry(60);
        $expected = $this->preview();
        for ($i = 0; $i < 20; $i++) {
            Tenant::create(['name' => 'Scale '.$i, 'slug' => 'scale-'.$i]);
        }
        $ids = Tenant::pluck('id')->all();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $result = app(Billing::class)->previewMany($ids, '2026-10');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertEquals($expected, $result[$this->tenant->id]);
        $this->assertCount(22, $result);
        $this->assertLessThanOrEqual(7, $queries);
        $this->actingAs($this->otherClient);
        $scoped = app(Billing::class)->previewMany([$this->tenant->id], '2026-10');
        $this->assertSame(0, $scoped[$this->tenant->id]['worked_seconds']);
        $this->assertSame(0, $scoped[$this->tenant->id]['total_cents']);
    }

    public function test_export_is_complete_private_and_has_no_authentication_secrets(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('tenants/'.$this->tenant->id.'/demo.txt', 'Fictional content');
        Document::create(['tenant_id' => $this->tenant->id, 'series' => (string) Str::uuid(), 'title' => 'Demo', 'category' => 'technical', 'original_name' => 'demo.txt', 'path' => 'tenants/'.$this->tenant->id.'/demo.txt', 'mime' => 'text/plain', 'size' => 17, 'uploaded_by' => $this->admin->id]);
        $path = storage_path('app/export-test-'.Str::uuid().'.zip');
        try {
            $this->artisan('hub:tenant-data', ['tenant' => $this->tenant->id, '--export' => $path])->assertSuccessful();
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $data = $zip->getFromName('data.json');
            $this->assertStringContainsString('Alpha Demo', $data);
            $this->assertStringNotContainsString('two_factor_secret', $data);
            $this->assertStringNotContainsString('password', $data);
            $this->assertSame(2, $zip->numFiles);
            $zip->close();
            $this->assertSame(0600, fileperms($path) & 0777);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_purge_requires_archival_exact_confirmation_and_keeps_other_tenants(): void
    {
        $this->artisan('hub:tenant-data', ['tenant' => $this->tenant->id, '--purge' => true, '--confirm' => 'DELETE:alpha'])->assertFailed();
        $this->tenant->update(['status' => 'archived']);
        $this->artisan('hub:tenant-data', ['tenant' => $this->tenant->id, '--purge' => true, '--confirm' => 'WRONG'])->assertFailed();
        $this->assertDatabaseHas('interventions', ['id' => $this->work->id]);
        $this->artisan('hub:tenant-data', ['tenant' => $this->tenant->id, '--purge' => true, '--confirm' => 'DELETE:alpha'])->assertSuccessful();
        $this->assertDatabaseMissing('tenants', ['id' => $this->tenant->id]);
        $this->assertDatabaseHas('tenants', ['id' => $this->other->id]);
        $this->assertDatabaseHas('users',['id' => $this->otherClient->id]);
    }
}
