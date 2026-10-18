<?php

namespace Tests\Unit;

use App\Services\JobMatchService;
use PHPUnit\Framework\TestCase;

class JobMatchServiceTest extends TestCase
{
    private JobMatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new JobMatchService;
    }

    public function test_it_identifies_matching_and_missing_keywords_accurately(): void
    {
        $description = <<<'TEXT'
        We are seeking a Fullstack Web Developer to build scalable web applications.
        Requirements:
        - Deep expertise in Laravel and PHP
        - Strong frontend development using React and Next.js with TypeScript
        - Experience designing RESTful API endpoints and schema design in MySQL
        - Knowledge of Docker containerization
        - Nice to have: experience with AWS and Redis
        TEXT;

        $result = $this->service->analyze($description, 'Fullstack Web Developer');

        $this->assertIsInt($result['match_percentage']);
        $this->assertGreaterThanOrEqual(75, $result['match_percentage']);
        $this->assertLessThanOrEqual(98, $result['match_percentage']);

        // Check matched keywords
        $this->assertContains('Fullstack Web Developer', $result['matched_keywords']);
        $this->assertContains('Laravel', $result['matched_keywords']);
        $this->assertContains('React', $result['matched_keywords']);
        $this->assertContains('Next.js', $result['matched_keywords']);
        $this->assertContains('TypeScript', $result['matched_keywords']);
        $this->assertContains('API', $result['matched_keywords']);
        $this->assertContains('MySQL', $result['matched_keywords']);
        $this->assertContains('Docker', $result['matched_keywords']);

        // Check missing keywords
        $this->assertContains('AWS', $result['missing_keywords']);
        $this->assertContains('Redis', $result['missing_keywords']);

        // Check recommendation structure
        $this->assertNotEmpty($result['recommendation']);
        $this->assertStringContainsString('Laravel', $result['recommendation']);
    }

    public function test_it_handles_lower_matching_job_descriptions(): void
    {
        $description = <<<'TEXT'
        Looking for a Senior Backend Specialist with deep expertise in Python, Django, PostgreSQL, Kubernetes, and Kafka.
        Must have experience with GCP and Microservices.
        TEXT;

        $result = $this->service->analyze($description, 'Backend Python Engineer');

        // Low match since candidate is Laravel / React fullstack
        $this->assertLessThan(65, $result['match_percentage']);
        $this->assertContains('Python', $result['missing_keywords']);
        $this->assertContains('PostgreSQL', $result['missing_keywords']);
        $this->assertContains('Kubernetes', $result['missing_keywords']);
        $this->assertContains('Kafka', $result['missing_keywords']);
    }

    public function test_it_handles_empty_or_brief_descriptions_gracefully(): void
    {
        $result = $this->service->analyze('', 'Senior Fullstack Developer');

        $this->assertIsInt($result['match_percentage']);
        $this->assertGreaterThanOrEqual(70, $result['match_percentage']);
        $this->assertNotEmpty($result['matched_keywords']);
        $this->assertNotEmpty($result['recommendation']);
    }

    public function test_it_allows_custom_candidate_skills_override(): void
    {
        $description = 'Looking for Python and Django developer with PostgreSQL';
        $customSkills = ['Python', 'PostgreSQL'];

        $result = $this->service->analyze($description, 'Python Developer', $customSkills);

        $this->assertContains('Python', $result['matched_keywords']);
        $this->assertContains('PostgreSQL', $result['matched_keywords']);
        $this->assertGreaterThanOrEqual(80, $result['match_percentage']);
    }
}
