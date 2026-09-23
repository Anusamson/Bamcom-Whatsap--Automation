<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\Note\NoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NoteController extends Controller
{
    public function __construct(
        protected NoteService $noteService
    ) {}

    /**
     * Store a newly created note for a contact or lead.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Note::class);

        $validated = $request->validate([
            'contact_id' => ['required', 'exists:contacts,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'content' => ['required', 'string', 'max:5000'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $this->noteService->createNote($validated, $request->user());

        return back()->with('success', 'Note recorded successfully.');
    }

    /**
     * Update an existing note.
     */
    public function update(Request $request, Note $note): RedirectResponse
    {
        Gate::authorize('update', $note);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $this->noteService->updateNote($note, $validated);

        return back()->with('success', 'Note updated successfully.');
    }

    /**
     * Toggle the pinned status of a note.
     */
    public function togglePin(Note $note): RedirectResponse
    {
        Gate::authorize('update', $note);

        $this->noteService->togglePin($note);

        $message = $note->is_pinned ? 'Note pinned to top of timeline.' : 'Note unpinned.';

        return back()->with('success', $message);
    }

    /**
     * Delete / archive a note.
     */
    public function destroy(Note $note): RedirectResponse
    {
        Gate::authorize('delete', $note);

        $this->noteService->deleteNote($note);

        return back()->with('success', 'Note deleted successfully.');
    }
}
