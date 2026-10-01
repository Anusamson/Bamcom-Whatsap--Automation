<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\SendEmailRequest;
use App\Http\Requests\Email\SendTestEmailRequest;
use App\Models\EmailMessage;
use App\Services\Email\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmailApiController extends Controller
{
    public function __construct(
        protected EmailService $emailService
    ) {}

    /**
     * List outbound email messages.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailMessage::class);

        $messages = EmailMessage::query()
            ->with(['contact:id,uuid,first_name,last_name,email', 'user:id,name,email'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('contact_id'), fn ($q) => $q->where('contact_id', $request->integer('contact_id')))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json($messages);
    }

    /**
     * Retrieve email details with events history.
     */
    public function show(EmailMessage $email): JsonResponse
    {
        Gate::authorize('view', $email);

        $email->load(['events', 'contact', 'user', 'template', 'account:id,name,from_email,from_name,is_default']);

        return response()->json([
            'email' => $email,
        ]);
    }

    /**
     * Queue an outbound email.
     */
    public function store(SendEmailRequest $request): JsonResponse
    {
        Gate::authorize('send', EmailMessage::class);

        $message = $this->emailService->send(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Email queued for delivery successfully.',
            'email' => $message,
        ], HttpResponse::HTTP_ACCEPTED);
    }

    /**
     * Send test email via Amazon SES.
     */
    public function sendTest(SendTestEmailRequest $request): JsonResponse
    {
        Gate::authorize('send', EmailMessage::class);

        $message = $this->emailService->sendTestEmail(
            recipientEmail: $request->validated('to_email'),
            subject: $request->validated('subject'),
            bodyHtml: $request->validated('body_html'),
            sender: $request->user()
        );

        return response()->json([
            'message' => 'Test email queued successfully.',
            'email' => $message,
        ], HttpResponse::HTTP_ACCEPTED);
    }

    /**
     * Retrieve deliverability metrics and stats.
     */
    public function stats(): JsonResponse
    {
        Gate::authorize('viewAny', EmailMessage::class);

        return response()->json([
            'stats' => $this->emailService->getStats(),
        ]);
    }
}
