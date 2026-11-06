<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Notifications\TicketReceived;
use App\Notifications\TicketSubmittedToTeam;
use App\Notifications\TicketUpdatedForReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
    }

    public function test_public_can_submit_incident_report_with_attachment(): void
    {
        config(['csirt.mail_notifications' => true, 'csirt.team_email' => 'tim@csirt.test', 'csirt.ticket_prefix' => 'TTIS']);

        $response = $this->post('/aduan', $this->payload([
            'attachments' => [UploadedFile::fake()->createWithContent('bukti.txt', 'log serangan')],
        ]));

        $ticket = Ticket::firstOrFail();
        $response->assertRedirect(route('tickets.submitted'));
        $this->assertMatchesRegularExpression('/^TTIS-\d{8}-[2-9A-HJ-NP-Z]{6}$/', $ticket->ticket_number);
        $this->assertSame(TicketStatus::New, $ticket->status);

        $attachment = $ticket->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringStartsWith("tickets/{$ticket->id}/", $attachment->path);
        $this->assertSame(hash('sha256', 'log serangan'), $attachment->sha256);

        Notification::assertSentTo(new AnonymousNotifiable, TicketReceived::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'pelapor@contoh.id');
        Notification::assertSentTo(new AnonymousNotifiable, TicketSubmittedToTeam::class);

        $this->get(route('tickets.submitted'))->assertSee($ticket->ticket_number);
    }

    public function test_no_email_is_sent_by_default(): void
    {
        config(['csirt.team_email' => 'tim@csirt.test']);

        $this->post('/aduan', $this->payload())->assertRedirect(route('tickets.submitted'));
        $ticket = Ticket::firstOrFail();

        $this->actingAs($this->user('admin'))->put("/dashboard/tickets/{$ticket->ticket_number}", [
            'status' => 'in_progress', 'note' => 'Sedang dianalisis', 'is_public' => '1',
        ])->assertRedirect();

        $this->post('/aduan/status/reply', ['ticket_number' => $ticket->ticket_number, 'email' => 'pelapor@contoh.id', 'note' => 'Ada log tambahan'])
            ->assertOk();

        Notification::assertNothingSent();
        $this->get(route('tickets.submitted'))->assertDontSee('dikirim ke email Anda');
    }

    public function test_reporter_can_reply_on_open_ticket_only(): void
    {
        $this->post('/aduan', $this->payload());
        $ticket = Ticket::firstOrFail();

        $this->post('/aduan/status/reply', ['ticket_number' => $ticket->ticket_number, 'email' => 'orang-lain@contoh.id', 'note' => 'Coba sisip'])
            ->assertRedirect(route('tickets.status.form'));
        $this->assertDatabaseMissing('ticket_updates', ['note' => '[Pelapor] Coba sisip']);

        $this->post('/aduan/status/reply', ['ticket_number' => $ticket->ticket_number, 'email' => 'pelapor@contoh.id', 'note' => 'Log tambahan terlampir'])
            ->assertOk()
            ->assertSee('Log tambahan terlampir');

        $ticket->forceFill(['status' => TicketStatus::Resolved])->save();
        $this->post('/aduan/status/reply', ['ticket_number' => $ticket->ticket_number, 'email' => 'pelapor@contoh.id', 'note' => 'Setelah ditutup'])
            ->assertRedirect(route('tickets.status.form'));
        $this->assertDatabaseMissing('ticket_updates', ['note' => '[Pelapor] Setelah ditutup']);
    }

    public function test_reporter_cannot_set_status_or_ticket_number(): void
    {
        $this->post('/aduan', $this->payload(['status' => 'resolved', 'ticket_number' => 'HACK-1']));

        $ticket = Ticket::firstOrFail();
        $this->assertSame(TicketStatus::New, $ticket->status);
        $this->assertNotSame('HACK-1', $ticket->ticket_number);
    }

    public function test_dangerous_attachment_types_are_rejected(): void
    {
        $this->post('/aduan', $this->payload([
            'attachments' => [UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')],
        ]))->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_validation_error_returns_to_report_form(): void
    {
        $this->get('/aduan');
        $this->get('/captcha/reload');

        $this->post('/aduan', $this->payload(['description' => 'pendek']))
            ->assertRedirect(route('tickets.create'))
            ->assertSessionHasErrors('description');
    }

    public function test_submission_is_rate_limited(): void
    {
        foreach (range(1, 3) as $i) {
            $this->post('/aduan', $this->payload());
        }

        $this->post('/aduan', $this->payload())->assertStatus(429);
    }

    public function test_status_check_requires_matching_email_and_shows_only_public_notes(): void
    {
        $this->post('/aduan', $this->payload());
        $ticket = Ticket::firstOrFail();
        $ticket->updates()->create(['note' => 'Catatan internal rahasia', 'is_public' => false]);
        $ticket->updates()->create(['note' => 'Sedang kami analisis', 'is_public' => true]);

        $this->from('/aduan/status')->post('/aduan/status', ['ticket_number' => $ticket->ticket_number, 'email' => 'orang-lain@contoh.id'])
            ->assertRedirect('/aduan/status')->assertSessionHas('error');

        $this->post('/aduan/status', ['ticket_number' => strtolower($ticket->ticket_number), 'email' => 'PELAPOR@contoh.id'])
            ->assertOk()
            ->assertSee('Sedang kami analisis')
            ->assertDontSee('Catatan internal rahasia')
            ->assertDontSee('Kronologi serangan');
    }

    public function test_admin_updates_ticket_and_timeline_is_recorded(): void
    {
        config(['csirt.mail_notifications' => true]);
        $this->post('/aduan', $this->payload());
        $ticket = Ticket::firstOrFail();
        $admin = $this->user('admin');

        $this->actingAs($admin)->get("/dashboard/tickets/{$ticket->ticket_number}")->assertOk()->assertSee('Kronologi serangan');

        $this->actingAs($admin)->put("/dashboard/tickets/{$ticket->ticket_number}", [
            'status' => 'resolved', 'assigned_to' => $admin->id, 'note' => 'Selesai ditangani', 'is_public' => '1',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertNotNull($ticket->closed_at);
        $this->assertDatabaseHas('ticket_updates', ['ticket_id' => $ticket->id, 'status' => 'resolved', 'note' => 'Selesai ditangani', 'is_public' => true]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'updated', 'auditable_type' => Ticket::class, 'auditable_id' => $ticket->id]);
        Notification::assertSentTo(new AnonymousNotifiable, TicketUpdatedForReporter::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'pelapor@contoh.id');
    }

    public function test_attachments_are_only_downloadable_by_admin(): void
    {
        $this->post('/aduan', $this->payload(['attachments' => [UploadedFile::fake()->createWithContent('bukti.txt', 'isi')]]));
        $ticket = Ticket::firstOrFail();
        $attachment = $ticket->attachments->first();
        $url = route('dashboard.tickets.attachments.download', [$ticket, $attachment]);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->user())->get($url)->assertForbidden();

        $response = $this->actingAs($this->user('admin'))->get($url)->assertOk();
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'attachment_downloaded']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'reporter_name' => 'Budi Pelapor',
            'reporter_email' => 'pelapor@contoh.id',
            'reporter_phone' => '+62 812-0000-0000',
            'reporter_organization' => 'Dinas Kominfo Contoh',
            'category' => 'defacement',
            'incident_at' => now()->subHour()->format('Y-m-d H:i'),
            'affected_asset' => 'https://contoh.go.id',
            'description' => 'Kronologi serangan: halaman utama situs berubah menjadi tampilan peretas.',
            'tlp' => 'AMBER',
            'agreement' => '1',
            'captcha' => 'abc',
        ];
    }
}
