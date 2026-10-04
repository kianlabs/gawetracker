<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\SavedSearch;
use App\Services\Discovery\GlintsSource;
use App\Services\Discovery\JobDiscoveryService;
use App\Services\Discovery\JobstreetSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The discovery UI: search Glints/Jobstreet from the browser, review what came
 * back, and promote a posting into a real JobApplication.
 *
 * The controller stays thin — all board-specific logic lives in the
 * JobDiscoveryService / JobSource implementations, so adding a third board
 * needs no change here.
 */
class JobDiscoveryController extends Controller
{
    /**
     * Boards offered in the UI. Kept as a map so the view can render labels
     * without hard-coding strings.
     *
     * @var array<string, string>
     */
    public const SOURCES = [
        'glints' => 'Glints',
        'jobstreet' => 'JobStreet',
    ];

    /**
     * List discovered postings with filtering, newest first.
     */
    public function index(Request $request): View
    {
        $query = JobPosting::query()->with('companyRecord');

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $source = (string) $request->input('source');
        if (array_key_exists($source, self::SOURCES)) {
            $query->where('source', $source);
        }

        $promoted = (string) $request->input('promoted');
        if ($promoted === 'yes') {
            $query->whereNotNull('job_application_id');
        } elseif ($promoted === 'no') {
            $query->whereNull('job_application_id');
        }

        $postings = $query->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('discovery.index', [
            'postings' => $postings,
            'sources' => self::SOURCES,
            'currentSearch' => $search,
            'currentSource' => $source,
            'currentPromoted' => $promoted,
            'totalPostings' => JobPosting::count(),
            'newPostings' => JobPosting::whereNull('job_application_id')->count(),
            'savedSearches' => SavedSearch::query()->orderBy('keyword')->get(),
        ]);
    }

    /**
     * Save a keyword so the scheduler re-runs it automatically.
     */
    public function storeSavedSearch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:60'],
        ], [
            'keyword.required' => 'Kata kunci pencarian wajib diisi.',
            'keyword.max' => 'Kata kunci maksimal 100 karakter.',
            'limit.integer' => 'Jumlah maksimal harus berupa angka.',
            'limit.min' => 'Jumlah minimal 1.',
            'limit.max' => 'Jumlah maksimal 60.',
        ]);

        SavedSearch::updateOrCreate(
            ['keyword' => trim($validated['keyword'])],
            ['limit' => (int) ($validated['limit'] ?? 30), 'is_active' => true],
        );

        return redirect()
            ->route('discovery.index')
            ->with('success', 'Pencarian disimpan. Sistem akan memperbaruinya secara berkala.');
    }

    /**
     * Stop (and remove) a saved search.
     */
    public function destroySavedSearch(SavedSearch $savedSearch): RedirectResponse
    {
        $savedSearch->delete();

        return redirect()
            ->route('discovery.index')
            ->with('success', 'Pencarian tersimpan dihapus.');
    }

    /**
     * Run a live search across every board and persist the results.
     */
    public function search(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:60'],
        ], [
            'keyword.required' => 'Kata kunci pencarian wajib diisi.',
            'keyword.max' => 'Kata kunci maksimal 100 karakter.',
            'limit.integer' => 'Jumlah maksimal harus berupa angka.',
            'limit.min' => 'Jumlah minimal 1.',
            'limit.max' => 'Jumlah maksimal 60.',
        ]);

        $keyword = trim($validated['keyword']);
        $limit = (int) ($validated['limit'] ?? 30);

        $service = new JobDiscoveryService([
            new GlintsSource,
            new JobstreetSource,
        ]);

        $result = $service->discover($keyword, $limit);

        $message = "Pencarian \"{$keyword}\" selesai: {$result['created']} lowongan baru, "
            ."{$result['updated']} diperbarui dari {$result['seen']} hasil.";

        $redirect = redirect()->route('discovery.index')->with('success', $message);

        if ($result['errors'] !== []) {
            $redirect->with('error', 'Sebagian sumber gagal: '.implode(' ', $result['errors']));
        }

        return $redirect;
    }

    /**
     * Promote a posting into a real application and link the two.
     */
    public function promote(JobPosting $posting): RedirectResponse
    {
        if ($posting->isPromoted()) {
            return redirect()
                ->route('applications.show', $posting->job_application_id)
                ->with('success', 'Lowongan ini sudah pernah dilamar.');
        }

        $company = Company::findOrCreateByName($posting->company);

        $application = JobApplication::create([
            'company' => $posting->company,
            'company_id' => $company?->id,
            'position' => $posting->title,
            'location' => $posting->location,
            'source' => $posting->source,
            'source_url' => $posting->source_url,
            'salary_note' => $posting->salary_note,
            'applied_at' => now()->toDateString(),
            'status' => 'wishlist',
            'last_status_change_at' => now(),
        ]);

        $application->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'wishlist',
            'note' => 'Lamaran dibuat dari lowongan '.$posting->source.'.',
        ]);

        $posting->forceFill(['job_application_id' => $application->id])->save();

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Lowongan dipindahkan ke daftar lamaran sebagai wishlist.');
    }

    /**
     * Dismiss a posting from the discovery list.
     */
    public function destroy(JobPosting $posting): RedirectResponse
    {
        $posting->delete();

        return redirect()
            ->route('discovery.index')
            ->with('success', 'Lowongan dihapus dari daftar temuan.');
    }
}
