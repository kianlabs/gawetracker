<?php

namespace App\Http\Controllers;

use App\Models\InterviewChecklist;
use App\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InterviewChecklistController extends Controller
{
    public function store(Request $request, JobApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $application->interviewChecklists()->create([
            'title' => $validated['title'],
            'is_completed' => false,
            'completed_at' => null,
        ]);

        return back()->with('success', 'Item checklist berhasil ditambahkan.');
    }

    public function toggle(InterviewChecklist $checklist): RedirectResponse
    {
        // Single-user app: route model binding provides 404 for non-existent records.
        // Multi-user TODO: verify $checklist->jobApplication->user_id === Auth::id()
        
        $checklist->is_completed = ! $checklist->is_completed;
        $checklist->completed_at = $checklist->is_completed ? now() : null;
        $checklist->save();

        return back()->with('success', 'Status checklist berhasil diperbarui.');
    }

    public function destroy(InterviewChecklist $checklist): RedirectResponse
    {
        // Single-user app: route model binding provides 404 for non-existent records.
        // Multi-user TODO: verify $checklist->jobApplication->user_id === Auth::id()
        
        $checklist->delete();

        return back()->with('success', 'Item checklist berhasil dihapus.');
    }
}
