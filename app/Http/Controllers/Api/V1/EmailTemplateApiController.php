<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EmailMessageType;
use App\Enums\EmailTemplateCategory;
use App\Enums\EmailTemplateStatus;
use App\Http\Controllers\Controller;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmailTemplateApiController extends Controller
{
    public function __construct(
        protected EmailTemplateService $templateService,
        protected EmailService $emailService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailTemplate::class);

        $templates = EmailTemplate::query()
            ->when($request->filled('category'), fn ($q) => $q->category($request->input('category')))
            ->when($request->filled('status'), fn ($q) => $q->status($request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.trim((string) $request->input('search')).'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $search)->orWhere('subject', 'like', $search));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'templates' => $templates,
            'categories' => array_column(EmailTemplateCategory::cases(), 'value'),
            'statuses' => array_column(EmailTemplateStatus::cases(), 'value'),
        ]);
    }

    public function store(StoreEmailTemplateRequest $request): JsonResponse
    {
        Gate::authorize('create', EmailTemplate::class);

        $template = EmailTemplate::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Template created successfully.',
            'template' => $template,
        ], HttpResponse::HTTP_CREATED);
    }

    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        Gate::authorize('view', $emailTemplate);

        return response()->json([
            'template' => $emailTemplate,
            'sample_variables' => $this->templateService->generateSampleVariables(),
        ]);
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): JsonResponse
    {
        Gate::authorize('update', $emailTemplate);

        $emailTemplate->update($request->validated());

        return response()->json([
            'message' => 'Template updated successfully.',
            'template' => $emailTemplate->fresh(),
        ]);
    }

    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        Gate::authorize('delete', $emailTemplate);

        $emailTemplate->delete();

        return response()->json([
            'message' => 'Template deleted successfully.',
        ]);
    }

    /**
     * Live preview rendering endpoint.
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

        $rendered = $this->templateService->render(
            htmlTemplate: $emailTemplate->body_html,
            plainTemplate: $emailTemplate->body_plain,
            variables: $variables,
            preheader: $emailTemplate->preheader,
            wrapWithBrand: $request->boolean('wrap_brand', true)
        );

        return response()->json([
            'subject' => $this->templateService->renderSubject($emailTemplate->subject, $variables),
            'preheader' => $emailTemplate->preheader,
            'html' => $rendered['html'],
            'plain' => $rendered['plain'],
            'variables_used' => $variables,
        ]);
    }

    /**
     * Dispatch test email from template.
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
}
