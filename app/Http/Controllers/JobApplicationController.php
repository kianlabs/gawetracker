<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobApplicationRequest;
use App\Http\Requests\UpdateJobApplicationRequest;
use App\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobApplicationController extends Controller
{
    /**
     * Map of status values to human-readable labels and badge styles.
     *
     * @var array<string, array{label: string, class: string}>
     */
    public const STATUSES = [
        'wishlist' => ['label' => 'Wishlist', 'bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#cbd5e1'],
        'applied' => ['label' => 'Terkirim', 'bg' => '#eff6ff', 'text' => '#1d4ed8', 'border' => '#bfdbfe'],
        'screening' => ['label' => 'Screening', 'bg' => '#fefce8', 'text' => '#a16207', 'border' => '#fef08a'],
        'interview' => ['label' => 'Interview', 'bg' => '#f5f3ff', 'text' => '#6d28d9', 'border' => '#ddd6fe'],
        'offer' => ['label' => 'Offering', 'bg' => '#ecfdf5', 'text' => '#047857', 'border' => '#a7f3d0'],
        'hired' => ['label' => 'Diterima', 'bg' => '#15803d', 'text' => '#ffffff', 'border' => '#15803d'],
        'rejected' => ['label' => 'Ditolak', 'bg' => '#fef2f2', 'text' => '#b91c1c', 'border' => '#fecaca'],
    ];

    /**
     * Map of work type values to human-readable labels.
     *
     * @var array<string, string>
     */
    public const WORK_TYPES = [
        'remote' => 'Remote',
        'onsite' => 'Onsite',
        'hybrid' => 'Hybrid',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = $this->applyFilters(JobApplication::query(), $request);

        $search = trim((string) $request->input('search'));
        $status = $request->input('status');
        $workType = $request->input('work_type');
        $allowedSorts = ['applied_at', 'company', 'created_at', 'status'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'applied_at';
        $sortDir = strtolower((string) $request->input('sort_dir')) === 'asc' ? 'asc' : 'desc';

        $applications = $query->orderBy($sortBy, $sortDir)
            ->paginate(10)
            ->withQueryString();

        return view('applications.index', [
            'applications' => $applications,
            'statuses' => self::STATUSES,
            'workTypes' => self::WORK_TYPES,
            'currentSearch' => $search,
            'currentStatus' => $status,
            'currentWorkType' => $workType,
            'currentSortBy' => $sortBy,
            'currentSortDir' => $sortDir,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('applications.create', [
            'statuses' => self::STATUSES,
            'workTypes' => self::WORK_TYPES,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJobApplicationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['last_status_change_at'] = now();

        $application = JobApplication::create($validated);

        $application->statusHistories()->create([
            'from_status' => null,
            'to_status' => $application->status,
            'note' => 'Lamaran dibuat.',
            'created_at' => now(),
        ]);

        return redirect()
            ->route('applications.index')
            ->with('success', 'Lamaran pekerjaan berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(JobApplication $application): View
    {
        $application->load(['statusHistories' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        return view('applications.show', [
            'application' => $application,
            'statuses' => self::STATUSES,
            'workTypes' => self::WORK_TYPES,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JobApplication $application): View
    {
        return view('applications.edit', [
            'application' => $application,
            'statuses' => self::STATUSES,
            'workTypes' => self::WORK_TYPES,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJobApplicationRequest $request, JobApplication $application): RedirectResponse
    {
        $validated = $request->validated();
        $newStatus = $validated['status'];
        $oldStatus = $application->status;

        if ($oldStatus !== $newStatus) {
            $statusNote = trim((string) $request->input('status_change_note'));
            $application->last_status_change_at = now();

            $application->statusHistories()->create([
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'note' => $statusNote !== '' ? $statusNote : 'Status diperbarui dari '.(self::STATUSES[$oldStatus]['label'] ?? $oldStatus).' ke '.(self::STATUSES[$newStatus]['label'] ?? $newStatus).'.',
                'created_at' => now(),
            ]);
        }

        unset($validated['status_change_note']);
        $application->update($validated);

        return redirect()
            ->route('applications.index')
            ->with('success', 'Lamaran pekerjaan berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JobApplication $application): RedirectResponse
    {
        $company = $application->company;
        $position = $application->position;

        $application->delete();

        return redirect()
            ->route('applications.index')
            ->with('success', "Lamaran {$position} di {$company} berhasil dihapus.");
    }

    /**
     * Quickly change the status of an application from dashboard or list.
     */
    public function quickStatus(Request $request, JobApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(self::STATUSES))],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status yang dipilih tidak valid.',
        ]);

        $oldStatus = $application->status;
        $newStatus = $validated['status'];

        if ($oldStatus !== $newStatus) {
            $application->status = $newStatus;
            $application->last_status_change_at = now();
            $application->save();

            $note = trim((string) ($validated['note'] ?? ''));
            $application->statusHistories()->create([
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'note' => $note !== '' ? $note : 'Status diubah ke '.(self::STATUSES[$newStatus]['label'] ?? $newStatus).'.',
                'created_at' => now(),
            ]);
        }

        return back()->with('success', "Status lamaran {$application->company} berhasil diperbarui.");
    }

    /**
     * Add a note / interview log to the application history.
     */
    public function addHistoryNote(Request $request, JobApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
        ], [
            'note.required' => 'Catatan wajib diisi.',
        ]);

        $application->statusHistories()->create([
            'from_status' => $application->status,
            'to_status' => $application->status,
            'note' => $validated['note'],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Catatan berhasil ditambahkan ke riwayat.');
    }

    /**
     * Quickly store a new job application with minimal required fields.
     */
    public function quickStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url'],
            'work_type' => ['nullable', 'in:remote,onsite,hybrid'],
            'applied_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:wishlist,applied'],
        ], [
            'company.required' => 'Nama perusahaan wajib diisi.',
            'company.max' => 'Nama perusahaan maksimal 255 karakter.',
            'position.required' => 'Posisi pekerjaan wajib diisi.',
            'position.max' => 'Posisi pekerjaan maksimal 255 karakter.',
            'source_url.url' => 'Format tautan lowongan tidak valid.',
            'work_type.in' => 'Tipe kerja tidak valid.',
            'applied_at.date' => 'Format tanggal melamar tidak valid.',
            'status.in' => 'Status tidak valid.',
        ]);

        $appliedAt = ! empty($validated['applied_at']) ? $validated['applied_at'] : now()->toDateString();
        $status = ! empty($validated['status']) ? $validated['status'] : 'wishlist';

        $application = JobApplication::create([
            'company' => $validated['company'],
            'position' => $validated['position'],
            'source_url' => $validated['source_url'] ?? null,
            'work_type' => $validated['work_type'] ?? null,
            'applied_at' => $appliedAt,
            'status' => $status,
            'last_status_change_at' => now(),
        ]);

        $application->statusHistories()->create([
            'from_status' => null,
            'to_status' => $application->status,
            'note' => 'Lamaran dibuat via Quick Add.',
            'created_at' => now(),
        ]);

        return back()->with('success', 'Lamaran berhasil ditambahkan dalam waktu kilat!');
    }

    /**
     * Export applications to a streamed CSV file.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = $this->applyFilters(JobApplication::query(), $request);

        $fileName = 'lamaran_pekerjaan_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'ID',
            'Perusahaan',
            'Posisi',
            'Lokasi',
            'Tipe Kerja',
            'Sumber',
            'Tautan Lowongan',
            'Tanggal Melamar',
            'Status',
            'Catatan Gaji',
            'Nama Kontak',
            'Info Kontak',
            'Catatan Bebas',
            'Dibuat Pada',
        ];

        return response()->stream(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns);

            foreach ($query->orderBy('applied_at', 'desc')->orderBy('id', 'desc')->cursor() as $app) {
                fputcsv($handle, [
                    $app->id,
                    $app->company,
                    $app->position,
                    $app->location ?? '',
                    self::WORK_TYPES[$app->work_type] ?? ($app->work_type ?? ''),
                    $app->source ?? '',
                    $app->source_url ?? '',
                    $app->applied_at ? $app->applied_at->format('Y-m-d') : '',
                    self::STATUSES[$app->status]['label'] ?? ($app->status ?? ''),
                    $app->salary_note ?? '',
                    $app->contact_name ?? '',
                    $app->contact_info ?? '',
                    $app->notes ?? '',
                    $app->created_at ? $app->created_at->format('Y-m-d H:i:s') : '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Apply common filters (search, status, work_type) to a query.
     */
    private function applyFilters($query, Request $request)
    {
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('company', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if (array_key_exists($status, self::STATUSES)) {
                $query->where('status', $status);
            }
        }

        if ($workType = $request->input('work_type')) {
            if (array_key_exists($workType, self::WORK_TYPES)) {
                $query->where('work_type', $workType);
            }
        }

        return $query;
    }
}
