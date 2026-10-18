<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Services\JobMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JobMatchController extends Controller
{
    public function __construct(
        protected JobMatchService $jobMatchService
    ) {}

    /**
     * Analyze match score and ATS keyword gaps for a specific job application.
     */
    public function matchApplication(Request $request, JobApplication $application): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'job_description' => ['nullable', 'string', 'max:10000'],
        ], [
            'job_description.max' => 'Deskripsi lowongan maksimal 10.000 karakter.',
        ]);

        $inputDesc = trim((string) ($validated['job_description'] ?? ''));

        if ($inputDesc !== '') {
            $application->job_description = $inputDesc;
        }

        $textToAnalyze = $inputDesc !== ''
            ? $inputDesc
            : ($application->job_description ?: ($application->notes ?: $application->position));

        $result = $this->jobMatchService->analyze($textToAnalyze, $application->position);

        $application->match_score = $result['match_percentage'];
        $application->match_analysis = $result;
        $application->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'match_score' => $result['match_percentage'],
                'match_percentage' => $result['match_percentage'],
                'matched_keywords' => $result['matched_keywords'],
                'missing_keywords' => $result['missing_keywords'],
                'recommendation' => $result['recommendation'],
                'analyzed_at' => $result['analyzed_at'],
                'message' => 'Analisis Match Score & ATS Keyword berhasil dilakukan.',
            ]);
        }

        return back()
            ->with('success', "Analisis Match Score ATS berhasil: {$result['match_percentage']}% kesesuaian.")
            ->with('match_result', $result);
    }

    /**
     * Standalone / general match endpoint comparing arbitrary job text with candidate profile.
     */
    public function matchGeneral(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_description' => ['required', 'string', 'max:10000'],
            'position' => ['nullable', 'string', 'max:255'],
        ], [
            'job_description.required' => 'Deskripsi lowongan wajib diisi.',
            'job_description.max' => 'Deskripsi lowongan maksimal 10.000 karakter.',
            'position.max' => 'Posisi maksimal 255 karakter.',
        ]);

        $result = $this->jobMatchService->analyze(
            $validated['job_description'],
            $validated['position'] ?? null
        );

        return response()->json([
            'success' => true,
            'match_score' => $result['match_percentage'],
            'match_percentage' => $result['match_percentage'],
            'matched_keywords' => $result['matched_keywords'],
            'missing_keywords' => $result['missing_keywords'],
            'recommendation' => $result['recommendation'],
            'analyzed_at' => $result['analyzed_at'],
        ]);
    }
}
