<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function index(Request $request): View
    {
        $applications = JobApplication::with('offerDetail')
            ->where(function ($query) {
                $query->whereIn('status', ['offer', 'hired'])
                    ->orHas('offerDetail');
            })
            ->latest('updated_at')
            ->get();

        return view('offers.index', [
            'applications' => $applications,
        ]);
    }

    public function storeOrUpdate(Request $request, JobApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'base_salary' => ['nullable', 'numeric'],
            'salary_period' => ['nullable', 'string', 'max:255'],
            'thr' => ['nullable', 'string', 'max:255'],
            'bonus' => ['nullable', 'string', 'max:255'],
            'allowance' => ['nullable', 'string', 'max:255'],
            'health_insurance' => ['nullable', 'string', 'max:255'],
            'work_scheme' => ['nullable', 'string', 'max:255'],
            'deadline_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $application->offerDetail()->updateOrCreate(
            ['job_application_id' => $application->id],
            $validated
        );

        return back()->with('success', 'Detail penawaran kerja berhasil disimpan.');
    }
}
