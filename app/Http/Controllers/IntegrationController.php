<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    /**
     * Display the Bot & API Integrations dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $apiToken = $user->ensureApiToken();

        $botQuery = JobApplication::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('source', 'LIKE', '%bot%')
                    ->orWhere('source', 'LIKE', '%lempar%')
                    ->orWhere('notes', 'LIKE', '%bot%')
                    ->orWhere('notes', 'LIKE', '%lempar%');
            });

        $totalBot = (clone $botQuery)->count();
        $botThisWeek = (clone $botQuery)
            ->whereBetween('applied_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $recentBotApps = (clone $botQuery)
            ->with(['companyRecord'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        $webhookUrl = url('/api/bot/applications');

        // Check local Bot Engine status (port 3000)
        $botEngineOnline = false;
        $botConfig = null;
        try {
            $botResponse = Http::timeout(1)->get('http://localhost:3000/api/config');
            if ($botResponse->successful()) {
                $botEngineOnline = true;
                $botConfig = $botResponse->json('config') ?? [];
            }
        } catch (\Throwable) {
            $botEngineOnline = false;
        }

        return view('integrations.index', [
            'user' => $user,
            'apiToken' => $apiToken,
            'webhookUrl' => $webhookUrl,
            'totalBot' => $totalBot,
            'botThisWeek' => $botThisWeek,
            'recentBotApps' => $recentBotApps,
            'botEngineOnline' => $botEngineOnline,
            'botConfig' => $botConfig,
        ]);
    }

    /**
     * Update target criteria and portal limits on the bot engine.
     */
    public function updateBotConfig(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'searchKeywords' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'minSalary' => ['nullable'],
            'limitPerDay' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $payload['enableGlints'] = $request->boolean('enableGlints');
        $payload['enableJobstreet'] = $request->boolean('enableJobstreet');
        $payload['enableIndeed'] = $request->boolean('enableIndeed');
        $payload['enableLinkedin'] = $request->boolean('enableLinkedin');

        try {
            $res = Http::timeout(3)->post('http://localhost:3000/api/config', $payload);
            if ($res->successful()) {
                return redirect()
                    ->route('integrations.index')
                    ->with('success', 'Target dan kriteria LemparGawe Bot berhasil disimpan.');
            }
            return redirect()
                ->route('integrations.index')
                ->with('error', 'Gagal menyimpan konfigurasi ke Bot Engine: ' . ($res->json('error') ?? 'Unknown error'));
        } catch (\Throwable $e) {
            return redirect()
                ->route('integrations.index')
                ->with('error', 'Bot Engine tidak terhubung di http://localhost:3000: ' . $e->getMessage());
        }
    }

    /**
     * Regenerate user's API token.
     */
    public function regenerateToken(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->generateApiToken();

        return redirect()
            ->route('integrations.index')
            ->with('success', 'API Token baru berhasil dibuat. Pastikan memperbarui konfigurasi di bot Anda.');
    }
}
