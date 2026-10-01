<?php

namespace App\Http\Controllers;

use App\Enums\EmailSuppressionReason;
use App\Http\Requests\Email\StoreEmailSuppressionRequest;
use App\Models\EmailSuppression;
use App\Services\Email\EmailSuppressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmailSuppressionController extends Controller
{
    public function __construct(
        protected EmailSuppressionService $suppressionService
    ) {}

    /**
     * Display a listing of suppressed email addresses.
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', EmailSuppression::class);

        $suppressions = EmailSuppression::query()
            ->with(['contact:id,uuid,first_name,last_name,email', 'user:id,name,email'])
            ->when($request->filled('reason'), fn ($q) => $q->where('reason', $request->input('reason')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.trim((string) $request->input('search')).'%';
                $q->where('email', 'like', $search);
            })
            ->latest('suppressed_at')
            ->paginate(20)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($suppressions);
        }

        return Inertia::render('Email/Suppressions/Index', [
            'suppressions' => $suppressions,
            'filters' => $request->only(['reason', 'search']),
            'reasons' => array_map(fn ($r) => ['value' => $r->value, 'label' => $r->label()], EmailSuppressionReason::cases()),
        ]);
    }

    /**
     * Add an email address to the suppression ledger.
     */
    public function store(StoreEmailSuppressionRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', EmailSuppression::class);

        $suppression = $this->suppressionService->suppress(
            email: $request->validated('email'),
            reason: $request->validated('reason'),
            details: $request->validated('details'),
            contactId: $request->validated('contact_id'),
            userId: $request->user()->id
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Email address added to suppression list successfully.',
                'suppression' => $suppression,
            ], HttpResponse::HTTP_CREATED);
        }

        return back()->with('success', 'Email added to suppression list.');
    }

    /**
     * Remove an email from the suppression ledger.
     */
    public function destroy(EmailSuppression $emailSuppression, Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $emailSuppression);

        $this->suppressionService->unsuppress($emailSuppression->email, $request->user()?->id);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Email address removed from suppression list.',
            ]);
        }

        return back()->with('success', 'Email address removed from suppression list.');
    }
}
