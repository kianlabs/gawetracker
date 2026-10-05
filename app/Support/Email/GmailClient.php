<?php

namespace App\Support\Email;

use Illuminate\Support\Facades\Http;

/**
 * Minimal read-only Gmail API client.
 *
 * Fetches job-related messages and returns them as [messageId, from, subject,
 * date, body] tuples. Requires an OAuth2 access token with the
 * `https://www.googleapis.com/auth/gmail.readonly` scope.
 *
 * The query intentionally narrows to the two job boards so we never page through
 * an entire mailbox.
 */
class GmailClient
{
    private const BASE = 'https://gmail.googleapis.com/gmail/v1/users/me';

    public function __construct(
        private readonly string $accessToken,
        private readonly string $query = 'from:(jobstreet.com OR glints.com) newer_than:90d',
    ) {}

    /**
     * Yield decoded messages matching the query.
     *
     * @return \Generator<array{id: string, from: ?string, subject: ?string, date: ?string, body: string}>
     */
    public function messages(int $max = 100): \Generator
    {
        $pageToken = null;
        $fetched = 0;

        do {
            $params = ['q' => $this->query, 'maxResults' => min(50, $max - $fetched)];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $list = $this->get('/messages', $params);
            if ($list === null) {
                return;
            }

            foreach ($list['messages'] ?? [] as $ref) {
                $msg = $this->get('/messages/'.$ref['id'], ['format' => 'full']);
                if ($msg === null) {
                    continue;
                }

                yield $this->decode($msg);

                if (++$fetched >= $max) {
                    return;
                }
            }

            $pageToken = $list['nextPageToken'] ?? null;
        } while ($pageToken !== null);
    }

    /**
     * @param  array<string, mixed>  $gmailMessage
     * @return array{id: string, from: ?string, subject: ?string, date: ?string, body: string}
     */
    private function decode(array $gmailMessage): array
    {
        $headers = collect($gmailMessage['payload']['headers'] ?? [])
            ->keyBy(fn ($h) => strtolower($h['name'] ?? ''));

        return [
            'id' => $gmailMessage['id'] ?? '',
            'from' => $headers->get('from')['value'] ?? null,
            'subject' => $headers->get('subject')['value'] ?? null,
            'date' => $headers->get('date')['value'] ?? null,
            'body' => $this->extractBody($gmailMessage['payload'] ?? []),
        ];
    }

    /**
     * Walk the MIME tree and return the first text/plain (or text/html) part.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractBody(array $payload): string
    {
        $mime = $payload['mimeType'] ?? '';

        if (in_array($mime, ['text/plain', 'text/html'], true)) {
            $data = $payload['body']['data'] ?? '';
            if ($data !== '') {
                return $this->base64UrlDecode($data);
            }
        }

        foreach ($payload['parts'] ?? [] as $part) {
            $body = $this->extractBody($part);
            if ($body !== '') {
                return $body;
            }
        }

        return '';
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $query): ?array
    {
        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->timeout(30)
            ->get(self::BASE.$path, $query);

        return $response->successful() ? $response->json() : null;
    }
}
