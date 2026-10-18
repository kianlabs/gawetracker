<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BotApplicationController extends Controller
{
    /**
     * Check API status and authentication health.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        $botAppCount = JobApplication::query()
            ->where('user_id', $user?->id)
            ->where(function ($q) {
                $q->where('source', 'LIKE', '%bot%')
                    ->orWhere('source', 'LIKE', '%lempar jaring%')
                    ->orWhere('notes', 'LIKE', '%bot%');
            })
            ->count();

        return response()->json([
            'success' => true,
            'status' => 'online',
            'app' => 'GaweTracker Bot Ingestion API',
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'stats' => [
                'total_bot_applications' => $botAppCount,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Ingest job applications submitted by external bots or extensions.
     * Supports both a single job object or a batch list of jobs.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Extract items array whether input is a direct list, {jobs: [...]}, {applications: [...]}, or a single object
        $rawItems = $request->input('jobs')
            ?? $request->input('applications')
            ?? ($request->isJson() && is_array($request->json()->all()) && array_is_list($request->json()->all())
                ? $request->json()->all()
                : [$request->all()]);

        if (empty($rawItems)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EMPTY_PAYLOAD',
                    'message' => 'Data lowongan tidak boleh kosong.',
                ],
            ], 422);
        }

        $botName = trim((string) ($request->input('bot_name') ?? $request->header('X-Bot-Name') ?? 'Bot Lempar Jaring Loker'));
        $results = [];
        $insertedCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($rawItems as $index => $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                $normalized = $this->normalizeJobPayload($raw, $botName);

                $validator = Validator::make($normalized, [
                    'company' => ['required', 'string', 'max:255'],
                    'position' => ['required', 'string', 'max:255'],
                    'location' => ['nullable', 'string', 'max:255'],
                    'work_type' => ['nullable', 'in:remote,onsite,hybrid'],
                    'source' => ['nullable', 'string', 'max:255'],
                    'source_url' => ['nullable', 'string', 'max:2048'],
                    'salary_note' => ['nullable', 'string', 'max:255'],
                    'applied_at' => ['nullable', 'date'],
                    'status' => ['nullable', 'in:'.implode(',', JobApplication::STATUSES)],
                    'notes' => ['nullable', 'string'],
                    'job_description' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    $results[] = [
                        'index' => $index,
                        'company' => $raw['company'] ?? ($raw['company_name'] ?? null),
                        'position' => $raw['position'] ?? ($raw['title'] ?? null),
                        'status' => 'failed',
                        'errors' => $validator->errors()->all(),
                    ];
                    $skippedCount++;

                    continue;
                }

                $validated = $validator->validated();

                // Check for deduplication within last 30 days for this user
                $duplicateQuery = JobApplication::query()
                    ->where('user_id', $user?->id)
                    ->where('company', $validated['company'])
                    ->where('position', $validated['position']);

                if (! empty($validated['source_url'])) {
                    $duplicateQuery->orWhere(function ($q) use ($user, $validated) {
                        $q->where('user_id', $user?->id)
                            ->where('source_url', $validated['source_url']);
                    });
                }

                $existing = $duplicateQuery->first();

                if ($existing && ! ($request->boolean('allow_duplicates') || ($raw['allow_duplicates'] ?? false))) {
                    $results[] = [
                        'index' => $index,
                        'id' => $existing->id,
                        'company' => $existing->company,
                        'position' => $existing->position,
                        'status' => 'duplicate',
                        'message' => "Sudah pernah tercatat pada {$existing->applied_at?->format('d M Y')}.",
                    ];
                    $skippedCount++;

                    continue;
                }

                // Resolve company canonical record
                $company = Company::findOrCreateByName($validated['company']);

                $appliedAt = ! empty($validated['applied_at'])
                    ? Carbon::parse($validated['applied_at'])->toDateString()
                    : now()->toDateString();

                $status = $validated['status'] ?? 'applied';

                $application = JobApplication::create([
                    'user_id' => $user?->id,
                    'company' => $validated['company'],
                    'company_id' => $company?->id,
                    'position' => $validated['position'],
                    'location' => $validated['location'] ?? null,
                    'work_type' => $validated['work_type'] ?? null,
                    'source' => $validated['source'] ?? $botName,
                    'source_url' => $validated['source_url'] ?? null,
                    'salary_note' => $validated['salary_note'] ?? null,
                    'applied_at' => $appliedAt,
                    'status' => $status,
                    'last_status_change_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                    'job_description' => $validated['job_description'] ?? null,
                ]);

                // Create initial status history entry
                $sourceLabel = $validated['source'] ?? $botName;
                $application->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => $status,
                    'note' => "Di-submit otomatis via {$sourceLabel}.",
                    'created_at' => now(),
                ]);

                $results[] = [
                    'index' => $index,
                    'id' => $application->id,
                    'company' => $application->company,
                    'position' => $application->position,
                    'status' => 'created',
                    'pipeline_status' => $application->status,
                    'applied_at' => $application->applied_at?->format('Y-m-d'),
                ];
                $insertedCount++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INGESTION_ERROR',
                    'message' => 'Gagal memproses data lamaran: '.$e->getMessage(),
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil memproses {$insertedCount} lamaran".($skippedCount > 0 ? " ({$skippedCount} dilewati/duplikat)" : '').'.',
            'inserted_count' => $insertedCount,
            'skipped_count' => $skippedCount,
            'data' => $results,
        ], 201);
    }

    /**
     * Map flexible keys from different bot implementations into GaweTracker fields.
     * Compatible with Bot-Lempar-Jaring-Loker, Indeed, Glints, Jobstreet, & Chrome Extensions.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeJobPayload(array $raw, string $botName): array
    {
        // 1. Position / Title
        $position = $raw['position'] ?? $raw['title'] ?? $raw['job_title'] ?? $raw['role'] ?? null;

        // 2. Company
        $company = $raw['company'] ?? $raw['company_name'] ?? $raw['employer'] ?? null;

        // 3. Location
        $location = $raw['location'] ?? $raw['city'] ?? null;

        // 4. Work Type
        $workType = strtolower(trim((string) ($raw['work_type'] ?? $raw['type'] ?? '')));
        if (! in_array($workType, ['remote', 'onsite', 'hybrid'], true)) {
            if (str_contains($workType, 'remote') || str_contains(strtolower((string) $location), 'remote')) {
                $workType = 'remote';
            } elseif (str_contains($workType, 'hybrid') || str_contains(strtolower((string) $location), 'hybrid')) {
                $workType = 'hybrid';
            } elseif (str_contains($workType, 'onsite') || str_contains($workType, 'on-site')) {
                $workType = 'onsite';
            } else {
                $workType = null;
            }
        }

        // 5. Source / Platform
        $platform = $raw['platform'] ?? $raw['source'] ?? null;
        if (! empty($platform)) {
            // E.g. "Glints", "JobStreet", "LinkedIn", "Indeed" -> "Bot Lempar Jaring (Glints)"
            if (! str_contains(strtolower((string) $platform), 'bot')) {
                $source = "{$botName} ({$platform})";
            } else {
                $source = (string) $platform;
            }
        } else {
            $source = $botName;
        }

        // 6. Source URL
        $sourceUrl = $raw['source_url'] ?? $raw['url'] ?? $raw['link'] ?? null;

        // 7. Salary
        $salary = $raw['salary_note'] ?? $raw['salary'] ?? $raw['compensation'] ?? null;

        // 8. Applied Date
        $appliedAt = $raw['applied_at'] ?? $raw['appliedAt'] ?? $raw['date'] ?? null;

        // 9. Status
        $status = strtolower(trim((string) ($raw['status'] ?? 'applied')));
        if (! in_array($status, JobApplication::STATUSES, true)) {
            $status = 'applied';
        }

        // 10. Notes / Questionnaire AI answers
        $notes = $raw['notes'] ?? $raw['note'] ?? null;
        if (empty($notes) && ! empty($raw['questionnaire_answers'])) {
            $notes = is_array($raw['questionnaire_answers'])
                ? json_encode($raw['questionnaire_answers'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : (string) $raw['questionnaire_answers'];
        }

        return [
            'company' => is_string($company) ? trim($company) : null,
            'position' => is_string($position) ? trim($position) : null,
            'location' => is_string($location) ? trim($location) : null,
            'work_type' => $workType,
            'source' => $source,
            'source_url' => is_string($sourceUrl) ? trim($sourceUrl) : null,
            'salary_note' => is_string($salary) ? trim($salary) : null,
            'applied_at' => $appliedAt,
            'status' => $status,
            'notes' => is_string($notes) ? trim($notes) : null,
            'job_description' => $raw['job_description'] ?? $raw['description'] ?? null,
        ];
    }
}
