<?php

namespace App\Http\Controllers;

use App\Http\Requests\Email\StoreEmailTemplateRequest;
use App\Http\Requests\Email\UpdateEmailTemplateRequest;
use App\Models\Contact;
use App\Models\EmailTemplate;
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
        protected EmailTemplateService $templateService
    ) {}

    /**
     * Display a listing of email templates.
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', EmailTemplate::class);

        $templates = EmailTemplate::query()
            ->with('creator:id,name,email')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.trim((string) $request->input('search')).'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $search)->orWhere('subject', 'like', $search));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($templates);
        }

        return Inertia::render('Email/Templates/Index', [
            'templates' => $templates,
            'filters' => $request->only(['category', 'search']),
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
     * Preview rendered HTML and plain text with sample or contact merge variables.
     */
    public function preview(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        Gate::authorize('view', $emailTemplate);

        $contact = null;
        if ($request->filled('contact_id')) {
            $contact = Contact::find($request->integer('contact_id'));
        }

        $variables = $this->templateService->buildVariablesForContact(
            $contact,
            $request->input('variables', [])
        );

        $rendered = $this->templateService->render(
            $emailTemplate->body_html,
            $emailTemplate->body_plain,
            $variables
        );

        $subject = $this->templateService->renderSubject($emailTemplate->subject, $variables);

        return response()->json([
            'subject' => $subject,
            'html' => $rendered['html'],
            'plain' => $rendered['plain'],
            'variables_used' => $variables,
        ]);
    }
}
