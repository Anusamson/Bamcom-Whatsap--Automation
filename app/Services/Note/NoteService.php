<?php

namespace App\Services\Note;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Note;
use App\Models\User;
use App\Services\Activity\ActivityRecorderService;

/**
 * Service for CRM Internal Notes and Memos.
 */
class NoteService
{
    public function __construct(
        protected ActivityRecorderService $activityRecorder
    ) {}

    /**
     * Create a new note and auto-record CRM activity.
     *
     * @param  array<string, mixed>  $data
     */
    public function createNote(array $data, ?User $author = null): Note
    {
        $userId = $author?->id ?? auth()->id();

        // Resolve contact from lead if missing
        $contactId = $data['contact_id'] ?? null;
        if (! $contactId && ! empty($data['lead_id'])) {
            $contactId = Lead::where('id', $data['lead_id'])->value('contact_id');
        }

        $note = Note::create([
            'contact_id' => $contactId,
            'lead_id' => $data['lead_id'] ?? null,
            'user_id' => $userId,
            'content' => trim((string) $data['content']),
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
        ]);

        // Auto-record CRM activity
        $this->activityRecorder->recordNoteAdded($note, $author);

        return $note;
    }

    /**
     * Update an existing note.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateNote(Note $note, array $data): Note
    {
        $update = [];

        if (isset($data['content'])) {
            $update['content'] = trim((string) $data['content']);
        }

        if (array_key_exists('is_pinned', $data)) {
            $update['is_pinned'] = (bool) $data['is_pinned'];
        }

        $note->update($update);

        return $note;
    }

    /**
     * Toggle the pinned status of a note.
     */
    public function togglePin(Note $note): Note
    {
        $note->update([
            'is_pinned' => ! $note->is_pinned,
        ]);

        return $note;
    }

    /**
     * Delete / archive a note.
     */
    public function deleteNote(Note $note): bool
    {
        return (bool) $note->delete();
    }
}
