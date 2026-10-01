<?php

namespace App\Http\Controllers;

use App\Enums\EmailMessageStatus;
use App\Enums\EmailMessageType;
use App\Http\Requests\Email\SendEmailRequest;
use App\Http\Requests\Email\SendTestEmailRequest;
use App\Models\EmailMessage;
use App\Services\Email\EmailService;
use App\Services\Email\EmailSuppressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmailController extends Controller
{
    public function __construct(
        protected EmailService $emailService,
        protected EmailSuppressionService $suppressionService
    ) {}

    /**
     * Display a listing of outbound email messages with audit filters.
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', EmailMessage::class);

        $query = EmailMessage::query()
            ->with(['contact:id,uuid,first_name,last_name,email', 'user:id,name,email', 'account:id,name,from_email'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('contact_id'), fn ($q) => $q->where('contact_id', $request->integer('contact_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.trim((string) $request->input('search')).'%';
                $q->where(function ($sub) use ($search) {
                    $sub->where('to_email', 'like', $search)
                        ->orWhere('to_name', 'like', $search)
                        ->orWhere('subject', 'like', $search)
                        ->orWhere('provider_message_id', 'like', $search);
                });
            })
            ->latest('id');

        $messages = $query->paginate(20)->withQueryString();
        $stats = $this->emailService->getStats();

        if ($request->wantsJson()) {
            return response()->json([
                'messages' => $messages,
                'stats' => $stats,
                'statuses' => EmailMessageStatus::cases(),
                'types' => EmailMessageType::cases(),
            ]);
        }

        return Inertia::render('Email/Index', [
            'messages' => $messages,
            'stats' => $stats,
            'filters' => $request->only(['status', 'type', 'search', 'contact_id']),
            'statuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], EmailMessageStatus::cases()),
            'types' => array_map(fn ($t) => ['value' => $t->value, 'label' => $t->label()], EmailMessageType::cases()),
        ]);
    }

    /**
     * Display the specified email message and its full event delivery ledger.
     */
    public function show(EmailMessage $email, Request $request): Response|JsonResponse
    {
        Gate::authorize('view', $email);

        $email->load(['events', 'contact', 'user', 'account', 'template']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $email,
            ]);
        }

        return Inertia::render('Email/Show', [
            'emailMessage' => $email,
        ]);
    }

    /**
     * Queue an outbound email message for delivery via Amazon SES.
     */
    public function store(SendEmailRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('send', EmailMessage::class);

        $emailMessage = $this->emailService->send(
            $request->validated(),
            $request->user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Email queued for asynchronous delivery successfully.',
                'email' => $emailMessage,
            ], HttpResponse::HTTP_ACCEPTED);
        }

        return redirect()->route('emails.index')->with('success', 'Email queued for delivery.');
    }

    /**
     * Dispatch an Amazon SES test email.
     */
    public function sendTest(SendTestEmailRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('send', EmailMessage::class);

        $emailMessage = $this->emailService->sendTestEmail(
            recipientEmail: $request->validated('to_email'),
            subject: $request->validated('subject'),
            bodyHtml: $request->validated('body_html'),
            sender: $request->user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'SES test email dispatched to queue successfully.',
                'email' => $emailMessage,
            ], HttpResponse::HTTP_ACCEPTED);
        }

        return back()->with('success', "Test email queued for {$request->validated('to_email')}.");
    }

    /**
     * Retrieve aggregated email deliverability and reputation statistics.
     */
    public function stats(): JsonResponse
    {
        Gate::authorize('viewAny', EmailMessage::class);

        return response()->json([
            'stats' => $this->emailService->getStats(),
        ]);
    }

    /**
     * Public unsubscribe display page.
     */
    public function unsubscribe(Request $request): Response|JsonResponse
    {
        $email = $request->query('email', '');

        if ($request->wantsJson()) {
            return response()->json([
                'email' => $email,
                'is_suppressed' => $this->suppressionService->isSuppressed((string) $email),
            ]);
        }

        return Inertia::render('Email/Unsubscribe', [
            'email' => $email,
            'isSuppressed' => $this->suppressionService->isSuppressed((string) $email),
        ]);
    }

    /**
     * Process public unsubscribe request.
     */
    public function processUnsubscribe(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->suppressionService->recordUnsubscribe(
            email: $validated['email'],
            reason: $validated['reason'] ?? 'User opted out via unsubscribe portal'
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'You have been successfully unsubscribed from marketing emails.',
            ]);
        }

        return back()->with('success', 'You have been successfully unsubscribed.');
    }
}
