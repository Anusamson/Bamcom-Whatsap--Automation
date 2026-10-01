<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\StoreEmailSuppressionRequest;
use App\Models\EmailSuppression;
use App\Services\Email\EmailSuppressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmailSuppressionApiController extends Controller
{
    public function __construct(
        protected EmailSuppressionService $suppressionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailSuppression::class);

        $suppressions = EmailSuppression::query()
            ->with(['contact:id,uuid,first_name,last_name,email'])
            ->when($request->filled('reason'), fn ($q) => $q->where('reason', $request->input('reason')))
            ->when($request->filled('search'), fn ($q) => $q->where('email', 'like', '%'.trim((string) $request->input('search')).'%'))
            ->latest('suppressed_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($suppressions);
    }

    public function store(StoreEmailSuppressionRequest $request): JsonResponse
    {
        Gate::authorize('create', EmailSuppression::class);

        $suppression = $this->suppressionService->suppress(
            email: $request->validated('email'),
            reason: $request->validated('reason'),
            details: $request->validated('details'),
            contactId: $request->validated('contact_id'),
            userId: $request->user()->id
        );

        return response()->json([
            'message' => 'Suppression added successfully.',
            'suppression' => $suppression,
        ], HttpResponse::HTTP_CREATED);
    }

    public function destroy(EmailSuppression $emailSuppression, Request $request): JsonResponse
    {
        Gate::authorize('delete', $emailSuppression);

        $this->suppressionService->unsuppress($emailSuppression->email, $request->user()?->id);

        return response()->json([
            'message' => 'Suppression removed successfully.',
        ]);
    }
}
