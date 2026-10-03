<?php

namespace App\Support\Email;

/**
 * Immutable result of parsing a single job-related email. All fields except
 * `provider` and `status` are best-effort and may be null when the source email
 * did not contain them in a recognisable form.
 */
final class ParsedJobEmail
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $provider,
        public readonly ?string $from,
        public readonly ?string $subject,
        public readonly ?\DateTimeInterface $receivedAt,
        public readonly ?string $status,
        public readonly ?string $company,
        public readonly ?string $position,
        public readonly ?string $sourceUrl,
        public readonly string $snippet = '',
    ) {}

    /**
     * Whether the parser recognised a pipeline status worth applying.
     */
    public function hasStatus(): bool
    {
        return $this->status !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'provider' => $this->provider,
            'from' => $this->from,
            'subject' => $this->subject,
            'received_at' => $this->receivedAt?->format('c'),
            'status' => $this->status,
            'company' => $this->company,
            'position' => $this->position,
            'source_url' => $this->sourceUrl,
            'snippet' => $this->snippet,
        ];
    }
}
