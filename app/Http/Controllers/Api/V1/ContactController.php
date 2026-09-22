<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Contact\CreateContactDTO;
use App\DTOs\Contact\UpdateContactDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\CreateContactRequest;
use App\Http\Requests\Contact\UpdateContactRequest;
use App\Models\Contact;
use App\Services\Contact\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContactController extends Controller
{
    public function __construct(
        protected ContactService $contactService
    ) {}

    /**
     * List contacts with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Contact::class);

        $filters = $request->only(['search', 'status', 'lead_source', 'assigned_user_id', 'per_page']);
        $paginator = $this->contactService->getPaginatedContacts($filters);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Store a newly created contact.
     */
    public function store(CreateContactRequest $request): JsonResponse
    {
        Gate::authorize('create', Contact::class);

        $dto = CreateContactDTO::fromArray($request->validated());
        $contact = $this->contactService->createContact($dto);

        return response()->json([
            'message' => 'Contact registered successfully.',
            'data' => $contact,
        ], 201);
    }

    /**
     * Display contact details.
     */
    public function show(Contact $contact): JsonResponse
    {
        Gate::authorize('view', $contact);

        $contact->load(['assignedUser.profile', 'assignedUser.team']);

        return response()->json([
            'data' => $contact,
        ]);
    }

    /**
     * Update the specified contact.
     */
    public function update(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        Gate::authorize('update', $contact);

        $dto = UpdateContactDTO::fromArray($request->validated());
        $updated = $this->contactService->updateContact($contact, $dto);

        return response()->json([
            'message' => 'Contact updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Remove the specified contact.
     */
    public function destroy(Contact $contact): JsonResponse
    {
        Gate::authorize('delete', $contact);

        $this->contactService->deleteContact($contact);

        return response()->json([
            'message' => 'Contact archived successfully.',
        ]);
    }
}
