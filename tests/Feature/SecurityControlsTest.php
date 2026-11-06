<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\AuditLog;
use App\Models\Ticket;
use App\Notifications\TicketReceived;
use App\Notifications\TicketUpdatedForReporter;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/**
 * Regresi untuk temuan analisis whitebox & kontrol standar keamanan
 * (lihat docs/KEPATUHAN-KEAMANAN.md).
 */
class SecurityControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Daftar host tepercaya disimpan Symfony sebagai state global (static) dan
        // akan bocor ke test lain dalam proses yang sama bila tidak dibersihkan.
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    public function test_unknown_host_header_is_rejected_but_legitimate_hosts_work(): void
    {
        config(['app.url' => 'https://csirt.instansi.go.id', 'csirt.trusted_hosts' => ['10.10.1.5']]);
        // Validasi host nonaktif di env "testing"/"local"; nyalakan seperti di produksi.
        $this->app['env'] = 'production';

        $this->get('http://evil.example/aduan')->assertStatus(400);
        $this->get('http://csirt.instansi.go.id.evil.example/aduan')->assertStatus(400);

        $this->get('https://csirt.instansi.go.id/aduan')->assertOk();
        // Alias loopback diterima (bukan 400) lalu dialihkan ke host kanonik.
        $this->get('http://localhost/aduan')->assertRedirect('https://csirt.instansi.go.id/aduan');
        $this->get('http://127.0.0.1:8080/aduan')->assertRedirect('https://csirt.instansi.go.id/aduan');
        $this->get('http://[::1]:8080/aduan')->assertRedirect('https://csirt.instansi.go.id/aduan');
        // Host tambahan yang didaftarkan eksplisit diterima apa adanya.
        $this->get('http://10.10.1.5/aduan')->assertOk();
    }

    public function test_loopback_aliases_redirect_to_the_canonical_host_so_sessions_are_not_split(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        // Cookie sesi terikat ke host: login di localhost lalu membuka 127.0.0.1 tampak seperti
        // sesi hilang. Semua alias dialihkan ke host APP_URL, path & query dipertahankan.
        $this->get('http://127.0.0.1:8080/posts?search=a')->assertRedirect('http://localhost:8080/posts?search=a');
        $this->get('http://localhost:8080/dashboard')->assertRedirect('http://localhost:8080/csirt-login');

        // Health check tidak dialihkan. POST juga tidak (mengulang POST tidak aman), jadi
        // responsnya diproses di host permintaan: tidak ada pengalihan ke host kanonik.
        $this->get('http://127.0.0.1:8080/up')->assertOk();
        $response = $this->post('http://127.0.0.1:8080/logout');
        $this->assertStringNotContainsString('localhost:8080/posts', (string) $response->headers->get('Location'));
        $this->assertStringStartsWith('http://127.0.0.1:8080/', (string) $response->headers->get('Location'));

        // Dapat dimatikan.
        config(['csirt.canonical_redirect' => false]);
        $this->get('http://127.0.0.1:8080/aduan')->assertOk();
    }

    public function test_reporter_input_never_reaches_outgoing_email(): void
    {
        $ticket = $this->ticket(['reporter_name' => '[Klik di sini](https://phishing.example) <b>x</b>']);

        foreach ([new TicketReceived($ticket), new TicketUpdatedForReporter($ticket, true)] as $notification) {
            $mail = $notification->toMail(new \stdClass());
            $text = $mail->greeting . ' ' . implode(' ', $mail->introLines) . ' ' . implode(' ', $mail->outroLines);

            $this->assertStringNotContainsString('phishing.example', $text);
            $this->assertStringNotContainsString('Klik di sini', $text);
        }
    }

    public function test_maps_embed_only_accepts_google_host(): void
    {
        $this->assertNotNull(HtmlSanitizer::mapsUrl('https://www.google.com/maps/embed?pb=1'));
        $this->assertNull(HtmlSanitizer::mapsUrl('https://google.com.evil.example/maps/embed?pb=1'));
        $this->assertNull(HtmlSanitizer::mapsUrl('https://www.google.evil/maps/embed?pb=1'));
        $this->assertNull(HtmlSanitizer::mapsUrl('http://www.google.com/maps/embed?pb=1'));
    }

    public function test_two_factor_challenge_is_cancelled_after_repeated_wrong_codes(): void
    {
        $user = $this->user();
        $this->post('/login', ['email' => $user->email, 'password' => 'Password!123', 'captcha' => 'x']);

        foreach (range(1, 4) as $i) {
            $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHas('error');
        }

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertRedirect(route('login'));
        $this->assertNull(session('login'));
        $this->assertDatabaseHas('audit_logs', ['event' => '2fa_locked', 'user_id' => $user->id]);
    }

    public function test_two_factor_rate_limit_is_per_account_across_ips(): void
    {
        config(['csirt.login_whitelist' => ['10.0.0.0/8']]);
        $user = $this->user();

        // Penyerang berganti IP di setiap percobaan (semuanya lolos whitelist);
        // batas tetap berlaku per akun, bukan per IP.
        foreach (range(1, 6) as $i) {
            $this->flushSession();
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.' . $i]);
            $this->post('/login', ['email' => $user->email, 'password' => 'Password!123', 'captcha' => 'x']);
            $status = $this->post('/two-factor-challenge', ['code' => '000000'])->getStatusCode();
        }

        $this->assertSame(429, $status, 'Percobaan ke-6 untuk akun yang sama harus dibatasi walau IP berbeda');
    }

    public function test_session_expires_after_absolute_lifetime(): void
    {
        config(['csirt.session_absolute_minutes' => 60]);
        $user = $this->user('admin');

        $this->actingAs($user)->withSession(['auth.started_at' => now()->timestamp])->get('/dashboard')->assertOk();

        $this->travel(61)->minutes();
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'session_expired', 'user_id' => $user->id]);
    }

    public function test_access_denied_is_audited(): void
    {
        $writer = $this->user();

        $this->actingAs($writer)->get('/dashboard/users')->assertForbidden();

        $log = AuditLog::where('event', 'access_denied')->firstOrFail();
        $this->assertSame($writer->id, $log->user_id);
        $this->assertStringContainsString('dashboard/users', $log->description);
        $this->assertSame(1, AuditLog::where('event', 'access_denied')->count(), 'Satu penolakan = satu catatan');
    }

    public function test_reporter_personal_data_is_not_copied_to_audit_log(): void
    {
        $ticket = $this->ticket(['reporter_name' => 'Budi Rahasia', 'reporter_email' => 'budi@rahasia.id', 'description' => 'Kronologi rahasia insiden']);

        $json = json_encode(AuditLog::where('auditable_type', Ticket::class)->where('auditable_id', $ticket->id)->get()->toArray());

        $this->assertStringNotContainsString('Budi Rahasia', $json);
        $this->assertStringNotContainsString('budi@rahasia.id', $json);
        $this->assertStringNotContainsString('Kronologi rahasia', $json);
        $this->assertStringContainsString('[data pribadi]', $json);
    }

    public function test_security_events_are_mirrored_to_security_log_channel(): void
    {
        $messages = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$messages) {
            $messages[] = $e->message;
        });

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/csirt-login')->assertNotFound();

        // withServerVariables bersifat menetap untuk request berikutnya: kembalikan ke IP yang diizinkan.
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        $user = $this->user();
        $this->post('/login', ['email' => $user->email, 'password' => 'salah', 'captcha' => 'x']);

        $this->assertContains('login_ip_blocked', $messages);
        $this->assertContains('login_failed', $messages);
    }

    public function test_ticket_can_only_be_assigned_to_active_staff(): void
    {
        $admin = $this->user('admin');
        $writer = $this->user();
        $ticket = $this->ticket();

        $this->actingAs($admin)->put("/dashboard/tickets/{$ticket->ticket_number}", ['status' => 'verified', 'assigned_to' => $writer->id])
            ->assertSessionHasErrors('assigned_to');

        $this->actingAs($admin)->put("/dashboard/tickets/{$ticket->ticket_number}", ['status' => 'verified', 'assigned_to' => $admin->id])
            ->assertSessionHasNoErrors();
    }

    public function test_password_policy_follows_asvs_and_bcrypt_limit(): void
    {
        $valid = fn (string $p) => Validator::make(['p' => $p], ['p' => Password::defaults()])->passes();

        $this->assertFalse($valid('Pendek!12'), 'kurang dari 12 karakter');
        $this->assertTrue($valid('Cukup!Panjang12'));
        $this->assertFalse($valid('Aa1!' . str_repeat('x', 69)), 'lebih dari 72 byte terpotong oleh bcrypt');
    }

    public function test_prune_removes_old_audit_logs_and_anonymizes_closed_tickets(): void
    {
        Storage::fake('local');
        config(['csirt.retention.audit_log_days' => 30, 'csirt.retention.closed_ticket_days' => 90]);

        $old = AuditLog::create(['event' => 'lama']);
        AuditLog::query()->whereKey($old->id)->update(['created_at' => now()->subDays(31)]);
        $recent = AuditLog::create(['event' => 'baru']);

        $closed = $this->ticket(['reporter_name' => 'Pelapor Lama', 'reporter_email' => 'lama@contoh.id']);
        $path = UploadedFile::fake()->create('bukti.txt', 1)->store('tickets/' . $closed->id, 'local');
        $closed->attachments()->create(['original_name' => 'bukti.txt', 'path' => $path, 'size' => 1, 'sha256' => str_repeat('a', 64)]);
        $closed->updates()->create(['note' => 'catatan berisi data pribadi', 'is_public' => true]);
        $closed->forceFill(['status' => TicketStatus::Resolved, 'closed_at' => now()->subDays(91)])->save();
        $open = $this->ticket(['reporter_name' => 'Pelapor Aktif']);

        $this->artisan('csirt:prune', ['--dry-run' => true])->assertSuccessful();
        $this->assertModelExists($old);

        $this->artisan('csirt:prune')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);

        $closed->refresh();
        $this->assertNotNull($closed->anonymized_at);
        $this->assertSame('[dihapus]', $closed->reporter_name);
        $this->assertSame(0, $closed->attachments()->count());
        Storage::disk('local')->assertMissing($path);
        $this->assertStringNotContainsString('data pribadi', (string) $closed->updates()->value('note'));
        $this->assertSame('Pelapor Aktif', $open->fresh()->reporter_name);
        $this->assertDatabaseHas('audit_logs', ['event' => 'data_pruned']);
    }

    public function test_security_headers_present(): void
    {
        $this->get('/')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
    }

    private function ticket(array $attributes = []): Ticket
    {
        return Ticket::create($attributes + [
            'reporter_name' => 'Pelapor', 'reporter_email' => 'pelapor@contoh.id', 'category' => 'phishing',
            'description' => 'Deskripsi insiden yang cukup panjang.', 'tlp' => 'AMBER',
        ]);
    }
}
