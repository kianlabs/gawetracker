<?php

namespace App\Support\Email;

use Carbon\CarbonImmutable;

/**
 * Parses a raw email (RFC 822 text or a Gmail-style array of headers + body)
 * into a {@see ParsedJobEmail}. Pure and side-effect free so it can be unit
 * tested against captured fixtures.
 *
 * Status detection is ordered most-specific first: a rejection email whose body
 * mentions "interview" must classify as `rejected`, not `interview`.
 */
class JobEmailParser
{
    /** Sender domains that identify the source job board. */
    private const PROVIDER_DOMAINS = [
        'jobstreet' => ['jobstreet.com', 'jobstreet.co.id', 'seek.com', 'seek.com.au'],
        'glints' => ['glints.com'],
    ];

    /**
     * Ordered status rules. First match wins, so terminal/specific outcomes are
     * checked before generic ones.
     *
     * @var array<int, array{status: string, patterns: array<int, string>}>
     */
    private const STATUS_RULES = [
        [
            'status' => 'hired',
            'patterns' => [
                '/\b(congratulations|you(?:'."'".'| a)?re hired|you have been hired|welcome to the team|offer accepted|anda (?:telah )?diterima|selamat,? anda diterima)\b/i',
            ],
        ],
        [
            'status' => 'offer',
            'patterns' => [
                '/\b(job offer|offer letter|we(?:'."'".'| a)?d like to offer|pleased to offer|penawaran kerja|menawarkan posisi|surat penawaran)\b/i',
            ],
        ],
        [
            'status' => 'rejected',
            'patterns' => [
                '/\b(unfortunately|not (?:been )?(?:successful|selected|shortlisted)|unable to (?:progress|move forward)|decided not to (?:proceed|move)|regret to inform|application (?:was )?unsuccessful|position has been filled|tidak (?:lolos|berhasil|dapat melanjutkan|dipilih)|belum berhasil|kami memutuskan untuk tidak|tidak melanjutkan)\b/i',
            ],
        ],
        [
            'status' => 'interview',
            'patterns' => [
                '/\b(invite you to an? (?:job )?interview|interview (?:invitation|schedule|session)|schedule (?:an? )?(?:interview|wawancara)|invit(?:e|ation) (?:for|to) (?:an? )?interview|wawancara|interview (?:user|hr|teknis))\b/i',
            ],
        ],
        [
            'status' => 'screening',
            'patterns' => [
                '/\b(shortlisted|your application is being (?:reviewed|considered)|screening (?:process|stage)|masuk (?:tahap|proses) (?:seleksi|screening)|sedang (?:di)?(?:review|tinjau)|lolos (?:tahap )?screening)\b/i',
            ],
        ],
        [
            'status' => 'applied',
            'patterns' => [
                '/\b(application (?:received|submitted|was sent)|thank you for (?:your )?appl(?:ying|ication)|we(?:'."'".'| a)?ve received your application|you applied (?:for|to)|lamaran(?: anda)? (?:telah )?(?:diterima|terkirim)|terima kasih (?:telah|sudah) melamar|aplikasi anda (?:telah )?(?:dikirim|diterima))\b/i',
            ],
        ],
    ];

    /**
     * Subjects/snippets that indicate a digest rather than a real application.
     *
     * @var array<int, string>
     */
    private const DIGEST_PATTERNS = [
        '/\b(job alert|jobs? (?:you might like|for you|recommended)|new jobs? (?:matching|for you)|rekomendasi (?:lowongan|pekerjaan)|lowongan (?:baru )?untuk anda|weekly (?:job )?digest)\b/i',
    ];

    /**
     * Parse a raw RFC 822 message string.
     */
    public function parseRaw(string $raw): ParsedJobEmail
    {
        [$headers, $body] = $this->splitRaw($raw);

        return $this->parse(
            messageId: $headers['message-id'] ?? $this->syntheticId($raw),
            from: $headers['from'] ?? null,
            subject: $headers['subject'] ?? null,
            date: $headers['date'] ?? null,
            body: $body,
        );
    }

    /**
     * Parse from explicit fields (e.g. a Gmail API payload already decoded).
     */
    public function parse(
        ?string $messageId,
        ?string $from,
        ?string $subject,
        ?string $date,
        string $body,
    ): ParsedJobEmail {
        $text = $this->normalise(trim(($subject ?? '')."\n".$body));
        $provider = $this->detectProvider($from);
        $status = $this->isDigest($text) ? null : $this->classifyStatus($text);

        [$company, $position] = $this->extractCompanyAndPosition($subject, $body);

        return new ParsedJobEmail(
            messageId: $messageId ?: $this->syntheticId($subject.$body),
            provider: $provider,
            from: $from,
            subject: $subject,
            receivedAt: $this->parseDate($date),
            status: $status,
            company: $company,
            position: $position,
            sourceUrl: $this->extractUrl($body),
            snippet: mb_substr($text, 0, 300),
        );
    }

    /**
     * Detect the job board from the sender address, falling back to body links.
     */
    public function detectProvider(?string $from): string
    {
        $haystack = strtolower($from ?? '');

        foreach (self::PROVIDER_DOMAINS as $provider => $domains) {
            foreach ($domains as $domain) {
                if (str_contains($haystack, $domain)) {
                    return $provider;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Classify the pipeline status, or null when nothing matches.
     */
    public function classifyStatus(string $text): ?string
    {
        foreach (self::STATUS_RULES as $rule) {
            foreach ($rule['patterns'] as $pattern) {
                if (preg_match($pattern, $text)) {
                    return $rule['status'];
                }
            }
        }

        return null;
    }

    /**
     * Whether the text looks like a job-alert digest rather than an application.
     */
    public function isDigest(string $text): bool
    {
        foreach (self::DIGEST_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Best-effort extraction of the hiring company and the position title.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function extractCompanyAndPosition(?string $subject, string $body): array
    {
        $company = null;
        $position = null;

        // "Your application for Backend Engineer at PT Teknologi" / "... di PT X"
        // "Your application to Tokopedia - Senior Frontend Engineer"
        $subject ??= '';
        if (preg_match('/\b(?:application|lamaran)\s+(?:for|untuk|to|ke)\s+(?:the\s+)?(?:position\s+(?:of\s+)?)?(.+?)\s+(?:at|di|@)\s+(.+?)(?:[.\-–|]|$)/i', $subject, $m)) {
            // "application for <position> at <company>"
            $position = $this->clean($m[1]);
            $company = $this->clean($m[2]);
        } elseif (preg_match('/\b(?:application|lamaran)\s+(?:to|ke)\s+(.+?)(?:\s*[-–|]\s*(.+?))?[.\-–|]?$/i', $subject, $m)) {
            // "application to <company> - <position>"
            $company = $this->clean($m[1]);
            $position = isset($m[2]) ? $this->clean($m[2]) : null;
        }

        // "position of X" without a company, in subject or body
        if ($position === null && preg_match('/\bposition of\s+(.+?)(?:[.\-–|]|$)/i', $subject."\n".$body, $m)) {
            $position = $this->clean($m[1]);
        }

        // "at Company" / "di Company" (company only)
        if ($company === null && preg_match('/\b(?:at|di)\s+((?:PT|CV|UD|Pte\.?|Ltd\.?|Inc\.?|Corp\.?|Tbk\.?)[^.\n,]{1,60})/i', $body, $m)) {
            $company = $this->clean($m[1]);
        }

        return [$company, $position];
    }

    /**
     * Split a raw message into lower-cased header map + decoded body.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function splitRaw(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        [$headerBlock, $body] = array_pad(explode("\n\n", $raw, 2), 2, '');

        $headers = [];
        foreach (explode("\n", $headerBlock) as $line) {
            if (preg_match('/^([A-Za-z\-]+):\s*(.*)$/', $line, $m)) {
                $headers[strtolower($m[1])] = trim($m[2]);
            }
        }

        return [$headers, $this->decodeBody($body)];
    }

    /**
     * Decode a possibly quoted-printable / base64 body.
     */
    private function decodeBody(string $body): string
    {
        if (preg_match('/^[A-Za-z0-9+\/=\s]+$/', $body) && ! str_contains($body, ' ')) {
            $decoded = base64_decode($body, true);
            if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
                return $decoded;
            }
        }

        return quoted_printable_decode($body);
    }

    /**
     * Strip HTML tags, collapse whitespace and normalise smart punctuation.
     */
    private function normalise(string $text): string
    {
        $text = preg_replace('/<[^>]+>/', ' ', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\u{2018}", "\u{2019}"], "'", $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function clean(string $value): ?string
    {
        $value = trim($value, " \t\n\r\0\x0B.\-–—|,;:");

        return $value === '' ? null : mb_substr($value, 0, 120);
    }

    private function parseDate(?string $date): ?\DateTimeInterface
    {
        if (! $date) {
            return null;
        }

        try {
            return CarbonImmutable::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractUrl(string $body): ?string
    {
        if (preg_match('#https?://[^\s"\'<>)]+jobstreet[^\s"\'<>)]*#i', $body, $m)) {
            return $m[0];
        }
        if (preg_match('#https?://[^\s"\'<>)]+glints[^\s"\'<>)]*#i', $body, $m)) {
            return $m[0];
        }

        return null;
    }

    private function syntheticId(string $seed): string
    {
        return 'synthetic-'.hash('sha256', $seed);
    }
}
