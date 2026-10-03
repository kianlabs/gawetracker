<?php

namespace Tests\Unit;

use App\Support\Email\JobEmailParser;
use PHPUnit\Framework\TestCase;

class JobEmailParserTest extends TestCase
{
    private JobEmailParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new JobEmailParser;
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/emails/'.$name);
    }

    public function test_detects_jobstreet_and_glints_providers(): void
    {
        $this->assertSame('jobstreet', $this->parser->parseRaw($this->fixture('jobstreet-applied.eml'))->provider);
        $this->assertSame('glints', $this->parser->parseRaw($this->fixture('glints-rejected.eml'))->provider);
    }

    public function test_classifies_applied_confirmation(): void
    {
        $parsed = $this->parser->parseRaw($this->fixture('jobstreet-applied.eml'));

        $this->assertSame('applied', $parsed->status);
        $this->assertSame('PT Teknologi Maju', $parsed->company);
        $this->assertSame('Backend Engineer', $parsed->position);
        $this->assertSame('<jobstreet-applied-001@id.jobstreet.com>', $parsed->messageId);
    }

    public function test_classifies_interview_invitation(): void
    {
        $parsed = $this->parser->parseRaw($this->fixture('jobstreet-interview.eml'));

        $this->assertSame('interview', $parsed->status);
    }

    public function test_rejection_wins_over_interview_mention(): void
    {
        // The rejection body mentions "apply for future openings"; a naive parser
        // could misread it. Ensure the terminal status is chosen.
        $parsed = $this->parser->parseRaw($this->fixture('glints-rejected.eml'));

        $this->assertSame('rejected', $parsed->status);
        $this->assertSame('Tokopedia', $parsed->company);
    }

    public function test_digest_emails_are_not_classified_as_applications(): void
    {
        $parsed = $this->parser->parseRaw($this->fixture('jobstreet-digest.eml'));

        $this->assertNull($parsed->status, 'Job-alert digests must not create applications.');
    }

    public function test_unknown_sender_is_ignored(): void
    {
        $parsed = $this->parser->parse(
            messageId: 'x',
            from: 'hr@somecompany.com',
            subject: 'Application received',
            date: null,
            body: 'We have received your application.',
        );

        $this->assertSame('unknown', $parsed->provider);
    }

    public function test_classify_status_priority_is_terminal_first(): void
    {
        $this->assertSame('hired', $this->parser->classifyStatus('Congratulations, you have been hired!'));
        $this->assertSame('offer', $this->parser->classifyStatus('We are pleased to offer you the role.'));
        $this->assertSame('rejected', $this->parser->classifyStatus('We regret to inform you that we will not proceed.'));
        $this->assertSame('interview', $this->parser->classifyStatus('We would like to invite you to an interview.'));
        $this->assertSame('screening', $this->parser->classifyStatus('Your profile has been shortlisted.'));
        $this->assertSame('applied', $this->parser->classifyStatus('Thank you for your application.'));
        $this->assertNull($this->parser->classifyStatus('Hello, just checking in.'));
    }

    public function test_indonesian_status_phrases_are_recognised(): void
    {
        $this->assertSame('applied', $this->parser->classifyStatus('Terima kasih telah melamar posisi ini.'));
        $this->assertSame('interview', $this->parser->classifyStatus('Kami mengundang Anda untuk wawancara.'));
        $this->assertSame('rejected', $this->parser->classifyStatus('Kami memutuskan untuk tidak melanjutkan proses Anda.'));
    }

    public function test_message_without_message_id_gets_stable_synthetic_id(): void
    {
        $a = $this->parser->parse(null, 'noreply@glints.com', 'Application received', null, 'Thank you for your application.');
        $b = $this->parser->parse(null, 'noreply@glints.com', 'Application received', null, 'Thank you for your application.');

        $this->assertStringStartsWith('synthetic-', $a->messageId);
        $this->assertSame($a->messageId, $b->messageId, 'Same input must produce the same synthetic id (idempotency).');
    }
}
