<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\StoreEmailTemplateRequest;
use App\Http\Requests\Email\UpdateEmailTemplateRequest;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmailTemplateApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailTemplate::class);

        $templates = EmailTemplate::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($templates);
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
}
