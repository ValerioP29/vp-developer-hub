<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TimeEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TicketDocumentTest extends HubTestCase
{
    private function ticket(): Ticket
    {
        return Ticket::create(['tenant_id' => $this->tenant->id, 'created_by' => $this->client->id, 'title' => 'Richiesta test', 'description' => 'Problema dimostrativo']);
    }

    public function test_ticket_creation_does_not_consume_hours_and_ignores_spoofed_tenant(): void
    {
        $this->actingAs($this->client)->post('/manage/tickets', ['tenant_id' => $this->other->id, 'title' => 'Help', 'description' => 'Issue', 'status' => 'closed', 'priority' => 'urgent'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tickets', ['tenant_id' => $this->tenant->id, 'status' => 'new', 'priority' => 'normal']);
        $this->assertSame(0, TimeEntry::count());
    }

    public function test_ticket_comments_and_internal_visibility(): void
    {
        $ticket = $this->ticket();
        $this->post('/tickets/'.$ticket->id.'/comments', ['body' => 'INTERNAL_SECRET', 'internal' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->client)->get('/tickets/'.$ticket->id)->assertOk()->assertDontSee('INTERNAL_SECRET');
        $this->post('/tickets/'.$ticket->id.'/comments', ['body' => 'Public reply', 'internal' => 1]);
        $this->assertFalse(TicketComment::latest('id')->first()->internal);
    }

    public function test_client_can_confirm_resolution_or_request_reopen(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['status' => 'resolved']);
        $this->actingAs($this->client)->post('/tickets/'.$ticket->id.'/status', ['status' => 'closed'])->assertSessionHasNoErrors();
        $this->post('/tickets/'.$ticket->id.'/status', ['status' => 'new'])->assertSessionHasErrors('reason');
        $this->post('/tickets/'.$ticket->id.'/status', ['status' => 'new', 'reason' => 'Il problema si ripresenta'])->assertSessionHasNoErrors();
        $this->assertSame('new', $ticket->fresh()->status);
    }

    public function test_client_cannot_arbitrarily_change_ticket_state(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->client)->post('/tickets/'.$ticket->id.'/status', ['status' => 'resolved'])->assertForbidden();
    }

    public function test_private_upload_download_and_version_history(): void
    {
        Storage::fake('local');
        $data = ['tenant_id' => $this->tenant->id, 'title' => 'Contratto', 'category' => 'contract', 'client_visible' => 1, 'file' => UploadedFile::fake()->createWithContent('agreement.txt', 'Test agreement')];
        $this->post('/documents', $data)->assertSessionHasNoErrors();
        $doc = Document::first();
        Storage::disk('local')->assertExists($doc->path);
        $this->get('/documents/'.$doc->id.'/download')->assertDownload('agreement.txt');
        $this->post('/documents', array_merge($data, ['previous_id' => $doc->id, 'file' => UploadedFile::fake()->createWithContent('agreement-v2.txt', 'Version two')]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('documents', ['series' => $doc->series, 'version' => 2]);
        $this->assertDatabaseCount('documents', 2);
        $this->actingAs($this->otherClient)->get('/documents/'.$doc->id.'/download')->assertNotFound();
    }

    public function test_hidden_document_cannot_be_downloaded_by_client(): void
    {
        Storage::fake('local');
        $this->post('/documents', ['tenant_id' => $this->tenant->id, 'title' => 'Private', 'category' => 'technical', 'file' => UploadedFile::fake()->createWithContent('private.txt', 'Secret')])->assertSessionHasNoErrors();
        $doc = Document::first();
        $this->actingAs($this->client)->get('/documents/'.$doc->id.'/download')->assertNotFound();
    }

    public function test_executable_upload_is_rejected(): void
    {
        Storage::fake('local');
        $this->post('/documents', ['tenant_id' => $this->tenant->id, 'title' => 'Attack', 'category' => 'technical', 'file' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo "bad";')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_client_can_only_attach_to_own_ticket(): void
    {
        Storage::fake('local');
        $ticket = $this->ticket();
        $this->actingAs($this->client)->post('/documents', ['title' => 'Screenshot', 'category' => 'attachment', 'ticket_id' => $ticket->id, 'file' => UploadedFile::fake()->image('screen.png')])->assertSessionHasNoErrors();
        $this->post('/documents', ['title' => 'Doc', 'category' => 'contract', 'file' => UploadedFile::fake()->createWithContent('file.txt', 'text')])->assertForbidden();
        $this->actingAs($this->otherClient)->post('/documents', ['title' => 'Doc', 'category' => 'attachment', 'ticket_id' => $ticket->id, 'file' => UploadedFile::fake()->createWithContent('file.txt', 'text')])->assertNotFound();
    }

    public function test_csv_export_neutralizes_formula_and_excludes_internal_notes(): void
    {
        $this->activate();
        $this->work->update(['title' => '=HYPERLINK("bad")']);
        $this->entry();
        $response = $this->get('/reports/'.$this->tenant->id.'/csv?month=2026-10')->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString('SEGRETO_INTERNO', $content);
    }

    public function test_pdf_is_real_pdf_and_authorized(): void
    {
        $this->activate();
        $this->entry();
        $this->actingAs($this->client);
        $response = $this->get('/reports/'.$this->tenant->id.'/pdf?month=2026-10')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_ticket_other_tenant_idor_is_denied(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->otherClient)->get('/tickets/'.$ticket->id)->assertNotFound();
        $this->post('/tickets/'.$ticket->id.'/comments',['body' => 'Attack'])->assertNotFound();
    }
}
