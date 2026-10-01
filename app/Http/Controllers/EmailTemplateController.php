<?php

namespace App\Http\Controllers;

use App\Enums\EmailMessageType;
use App\Enums\EmailTemplateCategory;
use App\Enums\EmailTemplateStatus;
use App\Http\Requests\Email\StoreEmailTemplateRequest;
use App\Http\Requests\Email\UpdateEmailTemplateRequest;
use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\Inspection;
use App\Models\Property;
use App\Models\User;
use App\Services\Email\EmailService;
use App\Services\Email\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmailTemplateController extends Controller
{
    public function __construct(
        protected EmailTemplateService $templateService,
        protected EmailService $emailService
    ) {}

    /**
     * Display a listing of email templates.
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', EmailTemplate::class);

        $templates = EmailTemplate::query()
            ->with('creator:id,name,email')
            ->when($request->filled('category'), fn ($q) => $q->category($request->input('category')))
            ->when($request->filled('status'), fn ($q) => $q->status($request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.trim((string) $request->input('search')).'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $search)->orWhere('subject', 'like', $search));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $categories = array_map(fn (EmailTemplateCategory $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'badgeClass' => $c->badgeClass(),
            'requires_unsubscribe' => $c->requiresUnsubscribe(),
        ], EmailTemplateCategory::cases());

        $statuses = array_map(fn (EmailTemplateStatus $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'badgeClass' => $s->badgeClass(),
        ], EmailTemplateStatus::cases());

        if ($request->wantsJson()) {
            return response()->json([
                'templates' => $templates,
                'categories' => $categories,
                'statuses' => $statuses,
            ]);
        }

        return Inertia::render('Email/Templates/Index', [
            'templates' => $templates,
            'categories' => $categories,
            'statuses' => $statuses,
            'filters' => $request->only(['category', 'status', 'search']),
        ]);
    }

    /**
     * Show the template creation editor.
     */
    public function create(): Response
    {
        Gate::authorize('create', EmailTemplate::class);

        return Inertia::render('Email/Templates/Create', [
            'categories' => array_map(fn (EmailTemplateCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'requires_unsubscribe' => $c->requiresUnsubscribe(),
            ], EmailTemplateCategory::cases()),
            'statuses' => array_map(fn (EmailTemplateStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], EmailTemplateStatus::cases()),
            'sampleVariables' => $this->templateService->generateSampleVariables(),
        ]);
    }

    /**
     * Store a newly created email template.
     */
    public function store(StoreEmailTemplateRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', EmailTemplate::class);

        $template = EmailTemplate::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Email template created successfully.',
                'template' => $template,
            ], HttpResponse::HTTP_CREATED);
        }

        return redirect()->route('email-templates.index')->with('success', 'Email template created successfully.');
    }

    /**
     * Display the specified email template.
     */
    public function show(EmailTemplate $emailTemplate, Request $request): Response|JsonResponse
    {
        Gate::authorize('view', $emailTemplate);

        $emailTemplate->load('creator:id,name,email');

        if ($request->wantsJson()) {
            return response()->json([
                'template' => $emailTemplate,
            ]);
        }

        return Inertia::render('Email/Templates/Show', [
            'template' => $emailTemplate,
            'sampleVariables' => $this->templateService->generateSampleVariables(),
        ]);
    }

    /**
     * Show the template editing interface.
     */
    public function edit(EmailTemplate $emailTemplate): Response
    {
        Gate::authorize('update', $emailTemplate);

        return Inertia::render('Email/Templates/Edit', [
            'template' => $emailTemplate,
            'categories' => array_map(fn (EmailTemplateCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'requires_unsubscribe' => $c->requiresUnsubscribe(),
            ], EmailTemplateCategory::cases()),
            'statuses' => array_map(fn (EmailTemplateStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], EmailTemplateStatus::cases()),
            'sampleVariables' => $this->templateService->generateSampleVariables(),
        ]);
    }

    /**
     * Update the specified email template.
     */
    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $emailTemplate);

        $emailTemplate->update($request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Email template updated successfully.',
                'template' => $emailTemplate->fresh(),
            ]);
        }

        return redirect()->route('email-templates.index')->with('success', 'Email template updated successfully.');
    }

    /**
     * Remove the specified email template.
     */
    public function destroy(EmailTemplate $emailTemplate, Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $emailTemplate);

        $emailTemplate->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Email template deleted successfully.',
            ]);
        }

        return redirect()->route('email-templates.index')->with('success', 'Email template deleted successfully.');
    }

    /**
     * Preview rendered HTML and plain text with desktop / mobile simulation and merge variables.
     */
    public function preview(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        Gate::authorize('view', $emailTemplate);

        $contact = $request->filled('contact_id') ? Contact::find($request->integer('contact_id')) : null;
        $agent = $request->filled('agent_id') ? User::find($request->integer('agent_id')) : $request->user();
        $property = $request->filled('property_id') ? Property::find($request->integer('property_id')) : null;
        $inspection = $request->filled('inspection_id') ? Inspection::find($request->integer('inspection_id')) : null;

        $variables = $this->templateService->buildVariables(
            contact: $contact,
            agent: $agent,
            property: $property,
            inspection: $inspection,
            extra: $request->input('variables', $this->templateService->generateSampleVariables())
        );

        $wrapBrand = $request->boolean('wrap_brand', true);

        $rendered = $this->templateService->render(
            htmlTemplate: $emailTemplate->body_html,
            plainTemplate: $emailTemplate->body_plain,
            variables: $variables,
            preheader: $emailTemplate->preheader,
            wrapWithBrand: $wrapBrand
        );

        $subject = $this->templateService->renderSubject($emailTemplate->subject, $variables);

        return response()->json([
            'subject' => $subject,
            'preheader' => $emailTemplate->preheader,
            'html' => $rendered['html'],
            'plain' => $rendered['plain'],
            'variables_used' => $variables,
        ]);
    }

    /**
     * Send a live test email rendered from this template.
     */
    public function sendTest(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        Gate::authorize('view', $emailTemplate);

        $validated = $request->validate([
            'recipient_email' => ['required', 'email', 'max:255'],
            'wrap_brand' => ['nullable', 'boolean'],
        ]);

        $sampleVariables = $this->templateService->generateSampleVariables();
        $rendered = $this->templateService->render(
            htmlTemplate: $emailTemplate->body_html,
            plainTemplate: $emailTemplate->body_plain,
            variables: $sampleVariables,
            preheader: $emailTemplate->preheader,
            wrapWithBrand: $request->boolean('wrap_brand', true)
        );

        $subject = '[TEST PREVIEW] '.$this->templateService->renderSubject($emailTemplate->subject, $sampleVariables);

        $message = $this->emailService->send([
            'to_email' => $validated['recipient_email'],
            'subject' => $subject,
            'body_html' => $rendered['html'],
            'body_plain' => $rendered['plain'],
            'type' => EmailMessageType::Test,
            'email_template_id' => $emailTemplate->id,
            'metadata' => [
                'template_test' => true,
                'template_uuid' => $emailTemplate->uuid,
            ],
        ], $request->user());

        return response()->json([
            'message' => "Test email dispatched successfully to {$validated['recipient_email']}.",
            'email' => $message,
        ], HttpResponse::HTTP_ACCEPTED);
    }

    /**
     * Upload an image asset for email templates.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        Gate::authorize('create', EmailTemplate::class);

        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:5120'],
        ]);

        $file = $request->file('image');
        $path = $file->store('email-assets', 'public');

        return response()->json([
            'url' => asset('storage/'.$path),
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ], HttpResponse::HTTP_CREATED);
    }
}
