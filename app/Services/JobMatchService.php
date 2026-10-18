<?php

namespace App\Services;

class JobMatchService
{
    /**
     * Default candidate skills profile for GaweTracker.
     *
     * @var list<string>
     */
    public const DEFAULT_CANDIDATE_SKILLS = [
        'Fullstack Web Developer',
        'Laravel',
        'React',
        'Next.js',
        'MySQL',
        'TypeScript',
        'API',
        'Docker',
    ];

    /**
     * Comprehensive catalog of ATS engineering keywords and regex patterns.
     * Maps canonical keyword to regex pattern.
     *
     * @var array<string, string>
     */
    protected const KEYWORD_PATTERNS = [
        // Candidate Core Profile
        'Fullstack Web Developer' => '/\b(full[-\s]?stack(\s+web)?(\s+developer|\s+engineer)?|web\s+developer)\b/i',
        'Laravel' => '/\blaravel\b/i',
        'React' => '/\breact(\.js|js)?\b(?!\s*native)/i',
        'Next.js' => '/\bnext(\.js|js)?\b/i',
        'MySQL' => '/\bmysql\b/i',
        'TypeScript' => '/\b(typescript|ts)\b/i',
        'API' => '/\b(rest\s*api|restful(\s*api)?|api\s*design|web\s*api|apis|rest\b|backend\s*api)\b/i',
        'Docker' => '/\b(docker|containerization|containers|docker-compose)\b/i',

        // Common Tech Stack & Frameworks (Gap & Match Potential)
        'PHP' => '/\bphp(\s*[78]\.\d)?\b/i',
        'JavaScript' => '/\b(javascript|js|es6)\b/i',
        'Tailwind CSS' => '/\btailwind(\s*css)?\b/i',
        'Git' => '/\b(git|github|gitlab)\b/i',
        'HTML/CSS' => '/\b(html5?|css3?)\b/i',
        'PostgreSQL' => '/\b(postgresql|postgres)\b/i',
        'Redis' => '/\bredis\b/i',
        'AWS' => '/\b(aws|amazon\s*web\s*services|ec2|s3|lambda|ecs)\b/i',
        'Kubernetes' => '/\b(kubernetes|k8s)\b/i',
        'GraphQL' => '/\bgraphql\b/i',
        'Vue.js' => '/\bvue(\.js|js)?\b/i',
        'Node.js' => '/\bnode(\.js|js)?\b/i',
        'NestJS' => '/\bnest(\.js|js)?\b/i',
        'Python' => '/\bpython\b/i',
        'Go' => '/\b(golang|go\s+language)\b/i',
        'Java' => '/\bjava\b(?!\s*script)/i',
        'Spring Boot' => '/\bspring\s*boot\b/i',
        'C#' => '/\bc#|\bcsharp\b/i',
        'Microservices' => '/\bmicroservices?\b/i',
        'Kafka' => '/\b(apache\s*kafka|kafka)\b/i',
        'RabbitMQ' => '/\brabbitmq\b/i',
        'Elasticsearch' => '/\belasticsearch\b/i',
        'MongoDB' => '/\bmongo(db)?\b/i',
        'CI/CD' => '/\b(ci[\/\-]cd|continuous\s+integration|github\s*actions)\b/i',
        'TDD' => '/\b(tdd|test[\s\-]driven|unit\s+test(ing)?|phpunit|jest)\b/i',
        'Linux' => '/\blinux\b/i',
        'Nginx' => '/\bnginx\b/i',
        'GCP' => '/\b(gcp|google\s*cloud)\b/i',
        'Azure' => '/\bazure\b/i',
    ];

    /**
     * Analyze a job description and position against candidate skills.
     *
     * @param  list<string>|null  $candidateSkills
     * @return array{
     *     match_percentage: int,
     *     matched_keywords: list<string>,
     *     missing_keywords: list<string>,
     *     recommendation: string,
     *     analyzed_at: string
     * }
     */
    public function analyze(string $description, ?string $position = null, ?array $candidateSkills = null): array
    {
        $candidateSkills = $candidateSkills ?? self::DEFAULT_CANDIDATE_SKILLS;
        $combinedText = trim(($position ? $position.' ' : '').$description);

        // Normalize candidate skills map (case-insensitive for fast lookup)
        $candidateSkillsMap = [];
        foreach ($candidateSkills as $skill) {
            $candidateSkillsMap[strtolower(trim($skill))] = $skill;
        }

        // Special handling for synonyms / aliases in candidate skills
        if (isset($candidateSkillsMap['laravel']) && ! isset($candidateSkillsMap['php'])) {
            $candidateSkillsMap['php'] = 'PHP';
        }
        if ((isset($candidateSkillsMap['react']) || isset($candidateSkillsMap['next.js'])) && ! isset($candidateSkillsMap['javascript'])) {
            $candidateSkillsMap['javascript'] = 'JavaScript';
        }

        $matchedKeywords = [];
        $missingKeywords = [];

        // Scan text against the ATS keyword catalog
        foreach (self::KEYWORD_PATTERNS as $keyword => $pattern) {
            if (preg_match($pattern, $combinedText)) {
                $lowerKey = strtolower($keyword);
                if (isset($candidateSkillsMap[$lowerKey]) || $this->matchesCandidateSkill($keyword, $candidateSkillsMap)) {
                    $matchedKeywords[] = $keyword;
                } else {
                    $missingKeywords[] = $keyword;
                }
            }
        }

        // Position-based heuristic if very few tech keywords were explicitly found in text
        if (count($matchedKeywords) === 0 && count($missingKeywords) === 0 && $position) {
            $posLower = strtolower($position);
            if (str_contains($posLower, 'developer') || str_contains($posLower, 'engineer') || str_contains($posLower, 'programmer')) {
                if (str_contains($posLower, 'full') || str_contains($posLower, 'web')) {
                    $matchedKeywords[] = 'Fullstack Web Developer';
                }
                if (str_contains($posLower, 'backend') || str_contains($posLower, 'php') || str_contains($posLower, 'laravel')) {
                    $matchedKeywords[] = 'Laravel';
                    $matchedKeywords[] = 'API';
                }
                if (str_contains($posLower, 'frontend') || str_contains($posLower, 'react')) {
                    $matchedKeywords[] = 'React';
                }
            }
        }

        // Fallback: If still empty (e.g. very vague job description), infer default match
        if (empty($matchedKeywords) && empty($missingKeywords)) {
            $matchedKeywords = ['Fullstack Web Developer'];
        }

        $matchedKeywords = array_values(array_unique($matchedKeywords));
        $missingKeywords = array_values(array_unique($missingKeywords));

        $matchPercentage = $this->calculatePercentage($matchedKeywords, $missingKeywords, $combinedText);
        $recommendation = $this->generateRecommendation($matchPercentage, $matchedKeywords, $missingKeywords);

        return [
            'match_percentage' => $matchPercentage,
            'matched_keywords' => $matchedKeywords,
            'missing_keywords' => $missingKeywords,
            'recommendation' => $recommendation,
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if a keyword is covered by candidate skills through fuzzy / synonym rules.
     *
     * @param  array<string, string>  $candidateSkillsMap
     */
    protected function matchesCandidateSkill(string $keyword, array $candidateSkillsMap): bool
    {
        $k = strtolower($keyword);

        if ($k === 'api' || $k === 'rest api') {
            return isset($candidateSkillsMap['api']) || isset($candidateSkillsMap['rest api']);
        }

        if ($k === 'fullstack web developer' || $k === 'fullstack') {
            return isset($candidateSkillsMap['fullstack web developer'])
                || isset($candidateSkillsMap['full stack'])
                || isset($candidateSkillsMap['fullstack']);
        }

        if ($k === 'html/css' || $k === 'tailwind css') {
            return isset($candidateSkillsMap['react']) || isset($candidateSkillsMap['next.js']);
        }

        return false;
    }

    /**
     * Calculate a realistic, calibrated ATS match percentage (70% - 95% for typical matches).
     *
     * @param  list<string>  $matched
     * @param  list<string>  $missing
     */
    protected function calculatePercentage(array $matched, array $missing, string $text): int
    {
        $mCount = count($matched);
        $uCount = count($missing);
        $total = $mCount + $uCount;

        if ($total === 0) {
            return 75;
        }

        $ratio = $mCount / $total;

        // Core strength bonus: does the text request Laravel, React/Next.js, or API?
        $coreHits = 0;
        foreach (['Laravel', 'React', 'Next.js', 'MySQL', 'TypeScript', 'API', 'Docker'] as $core) {
            if (in_array($core, $matched, true)) {
                $coreHits++;
            }
        }

        // Calibrate score onto typical ATS curve
        if ($uCount === 0) {
            // Perfect match on detected keywords
            $score = 90 + min(5, $coreHits);
        } elseif ($ratio >= 0.75) {
            // Strong match: 82% - 94%
            $score = 82 + (int) round(($ratio - 0.75) * 48);
        } elseif ($ratio >= 0.50) {
            // Moderate match: 70% - 81%
            $score = 70 + (int) round(($ratio - 0.50) * 44);
        } elseif ($ratio >= 0.30) {
            // Partial match: 52% - 69%
            $score = 52 + (int) round(($ratio - 0.30) * 85);
        } else {
            // Low match: 25% - 50%
            $score = max(25, (int) round($ratio * 100 + 15));
        }

        // Apply slight bonus for strong core stack coverage
        if ($coreHits >= 3 && $score < 95) {
            $score = min(95, $score + 3);
        }

        return (int) min(98, max(20, $score));
    }

    /**
     * Generate actionable interview and CV optimization recommendations.
     *
     * @param  list<string>  $matched
     * @param  list<string>  $missing
     */
    protected function generateRecommendation(int $matchPercentage, array $matched, array $missing): string
    {
        $matchedTop = array_slice($matched, 0, 4);
        $missingTop = array_slice($missing, 0, 4);

        $matchedStr = ! empty($matchedTop) ? implode(', ', $matchedTop) : 'keahlian fullstack Anda';
        $missingStr = ! empty($missingTop) ? implode(', ', $missingTop) : '';

        if ($matchPercentage >= 80) {
            $rec = "Kecocokan profil Anda sangat tinggi ({$matchPercentage}%). Soroti pengalaman nyata menggunakan {$matchedStr} pada ringkasan profil CV dan portofolio Anda.";
            if (! empty($missingStr)) {
                $rec .= " Untuk memaksimalkan ATS parser, cantumkan pemahaman konsep atau familiaritas Anda dengan {$missingStr}.";
            }
            $rec .= ' Pada interview teknikal, tekankan pengalaman arsitektur fullstack terintegrasi dan clean code.';

            return $rec;
        }

        if ($matchPercentage >= 65) {
            $rec = "Kecocokan profil Anda cukup kuat ({$matchPercentage}%). Anda memiliki pondasi solid di {$matchedStr}.";
            if (! empty($missingStr)) {
                $rec .= " Terdapat gap kata kunci ATS pada: {$missingStr}. Tambahkan proyek sampingan atau studi mandiri terkait topik tersebut di CV.";
            }
            $rec .= " Siapkan argumen interview bahwa kapabilitas {$matchedStr} Anda memungkinkan adaptasi cepat ke stack baru.";

            return $rec;
        }

        $rec = "Tingkat kecocokan profil saat ini {$matchPercentage}%. Terdapat perbedaan stack teknologi utama, terutama pada {$missingStr}.";
        $rec .= " Rekomendasi: Tonjolkan kemampuan adaptasi teknis, fundamental rekayasa perangkat lunak, dan bagaimana keahlian di {$matchedStr} dapat ditransfer ke sistem mereka.";

        return $rec;
    }
}
