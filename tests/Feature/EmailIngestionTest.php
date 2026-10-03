<?php

namespace Tests\Feature;

use App\Models\IngestedEmail;
use App\Models\JobApplication;
use App\Services\EmailIngestionService;
use App\Support\Email\JobEmailParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailIngestionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/emails/'.$name);
    }

    public function test_applied_email_creates_a_new_application(): void
    {
        $ledger = app(EmailIngestionService::class)->ingestRaw($this->fixture('jobstreet-applied.eml'));

        $this->assertNotNull($ledger);
        $this->assertSame('jobstreet', $ledger->provider);
        $this->assertSame('applied', $ledger->classified_status);

        $app = JobApplication::first();
        $this->assertNotNull($app);
        $this->assertSame('PT Teknologi Maju', $app->company);
        $this->assertSame('Backend Engineer', $app->position);
        $this->assertSame('applied', $app->status);
        $this->assertSame('JobStreet', $app->source);
        $this->assertSame($app->id, $ledger->job_application_id);
    }

    public function test_interview_email_advances_the_matching_application(): void
    {
        $service = app(EmailIngestionService::class);

        // First the confirmation creates the row.
        $service->ingestRaw($this->fixture('jobstreet-applied.eml'));
        $app = JobApplication::first();
        $this->assertSame('applied', $app->status);

        // Then the interview invite matches it by company + position.
        $service->ingestRaw($this->fixture('jobstreet-interview.eml'));

        $app->refresh();
        $this->assertSame('interview', $app->status);
        $this->assertNotNull($app->last_status_change_at);
        $this->assertDatabaseHas('status_histories', [
            'job_application_id' => $app->id,
            'from_status' => 'applied',
            'to_status' => 'interview',
        ]);

        // Only one application should exist — the second email matched, not created.
        $this->assertSame(1, JobApplication::count());
    }

    public function test_processing_the_same_email_twice_is_idempotent(): void
    {
        $service = app(EmailIngestionService::class);

        $first = $service->ingestRaw($this->fixture('jobstreet-applied.eml'));
        $second = $service->ingestRaw($this->fixture('jobstreet-applied.eml'));

        $this->assertNotNull($first);
        $this->assertNull($second, 'Duplicate Message-ID must be skipped.');
        $this->assertSame(1, IngestedEmail::count());
        $this->assertSame(1, JobApplication::count());
    }

    public function test_rejection_sets_terminal_status_and_is_not_overwritten(): void
    {
        $service = app(EmailIngestionService::class);

        JobApplication::create([
            'company' => 'Tokopedia',
            'position' => 'Senior Frontend Engineer',
            'status' => 'interview',
            'applied_at' => now()->subDays(10),
        ]);

        $service->ingestRaw($this->fixture('glints-rejected.eml'));

        $app = JobApplication::first();
        $this->assertSame('rejected', $app->status);

        // A later "applied"-style email must not resurrect a terminal application.
        $service->ingest(app(JobEmailParser::class)->parse(
            messageId: 'later-1',
            from: 'noreply@glints.com',
            subject: 'Your application to Tokopedia - Senior Frontend Engineer',
            date: now()->toIso8601String(),
            body: 'Thank you for your application. We have received it.',
        ));

        $this->assertSame('rejected', $app->fresh()->status);
    }

    public function test_stale_email_does_not_downgrade_an_advanced_application(): void
    {
        $service = app(EmailIngestionService::class);

        JobApplication::create([
            'company' => 'PT Teknologi Maju',
            'position' => 'Backend Engineer',
            'status' => 'offer',
            'applied_at' => now()->subDays(10),
        ]);

        // An older "applied" confirmation arriving late must be ignored.
        $service->ingestRaw($this->fixture('jobstreet-applied.eml'));

        $this->assertSame('offer', JobApplication::first()->status);
    }

    public function test_digest_email_is_ignored_entirely(): void
    {
        $ledger = app(EmailIngestionService::class)->ingestRaw($this->fixture('jobstreet-digest.eml'));

        $this->assertNull($ledger);
        $this->assertSame(0, JobApplication::count());
        $this->assertSame(0, IngestedEmail::count());
    }

    public function test_import_command_ingests_a_directory_of_emails(): void
    {
        $this->artisan('emails:import', ['path' => __DIR__.'/../Fixtures/emails'])
            ->assertExitCode(0);

        // applied + interview + rejected = 3 real emails; digest is skipped.
        $this->assertSame(3, IngestedEmail::count());
    }

    public function test_import_command_dry_run_writes_nothing(): void
    {
        $this->artisan('emails:import', [
            'path' => __DIR__.'/../Fixtures/emails',
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, IngestedEmail::count());
        $this->assertSame(0, JobApplication::count());
    }
}
