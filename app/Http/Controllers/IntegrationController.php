<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                    ->orWhere('source', 'LIKE', '%lempar jaring%')
                    ->orWhere('notes', 'LIKE', '%bot%');
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

        return view('integrations.index', [
            'user' => $user,
            'apiToken' => $apiToken,
            'webhookUrl' => $webhookUrl,
            'totalBot' => $totalBot,
            'botThisWeek' => $botThisWeek,
            'recentBotApps' => $recentBotApps,
        ]);
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
