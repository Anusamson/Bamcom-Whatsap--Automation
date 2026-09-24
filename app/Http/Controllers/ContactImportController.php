<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\Contact\ContactImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactImportController extends Controller
{
    public function __construct(
        protected ContactImportService $importService
    ) {}

    /**
     * Download the standard CSV contact import template.
     */
    public function downloadTemplate(): StreamedResponse
    {
        Gate::authorize('create', Contact::class);

        $csvContent = $this->importService->generateSampleCsvTemplate();
        $filename = 'contacts_import_template_'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($csvContent): void {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Preview contact records from an uploaded CSV file or Google Sheet URL.
     */
    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('create', Contact::class);

        $validated = $request->validate([
            'source_type' => ['required', 'string', 'in:csv,google_sheet'],
            'file' => ['required_if:source_type,csv', 'nullable', 'file', 'mimes:csv,txt', 'max:10240'],
            'sheet_url' => ['required_if:source_type,google_sheet', 'nullable', 'url', 'max:1000'],
        ]);

        if ($validated['source_type'] === 'csv') {
            $result = $this->importService->previewFromCsv($request->file('file'));
        } else {
            $result = $this->importService->previewFromGoogleSheet($validated['sheet_url']);
        }

        return response()->json($result);
    }

    /**
     * Execute contact import from an uploaded CSV file.
     */
    public function importCsv(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', Contact::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'duplicate_handling' => ['nullable', 'string', 'in:skip,update'],
            'default_lead_source' => ['nullable', 'string'],
            'default_status' => ['nullable', 'string'],
            'default_assigned_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $options = [
            'duplicate_handling' => $validated['duplicate_handling'] ?? 'skip',
            'default_lead_source' => $validated['default_lead_source'] ?? null,
            'default_status' => $validated['default_status'] ?? null,
            'default_assigned_user_id' => $validated['default_assigned_user_id'] ?? null,
        ];

        $report = $this->importService->importFromCsv(
            file: $request->file('file'),
            options: $options,
            importer: $request->user()
        );

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        $message = "Import complete: {$report['imported_count']} new contacts created, {$report['updated_count']} updated, {$report['skipped_count']} skipped.";

        return back()->with('success', $message)->with('import_report', $report);
    }

    /**
     * Execute contact import from a Google Sheet URL.
     */
    public function importGoogleSheet(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', Contact::class);

        $validated = $request->validate([
            'sheet_url' => ['required', 'url', 'max:1000'],
            'duplicate_handling' => ['nullable', 'string', 'in:skip,update'],
            'default_lead_source' => ['nullable', 'string'],
            'default_status' => ['nullable', 'string'],
            'default_assigned_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $options = [
            'duplicate_handling' => $validated['duplicate_handling'] ?? 'skip',
            'default_lead_source' => $validated['default_lead_source'] ?? null,
            'default_status' => $validated['default_status'] ?? null,
            'default_assigned_user_id' => $validated['default_assigned_user_id'] ?? null,
        ];

        $report = $this->importService->importFromGoogleSheet(
            sheetUrl: $validated['sheet_url'],
            options: $options,
            importer: $request->user()
        );

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        $message = "Google Sheet import complete: {$report['imported_count']} new contacts created, {$report['updated_count']} updated, {$report['skipped_count']} skipped.";

        return back()->with('success', $message)->with('import_report', $report);
    }
}
