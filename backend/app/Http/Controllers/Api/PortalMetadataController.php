<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PortalMetadataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalMetadataController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     */
    public function __construct(private readonly PortalMetadataService $portalMetadataService) {}

    /**
     * Handle the portal metadata endpoint.
     *
     * Authenticated users only. Access-control metadata is returned only to
     * admins or users allowed to view user management data.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->portalMetadataService->portalMetadata(
                includeAccessMetadata: $user !== null
                    && ($user->hasRole('admin') || $user->can('users.view'))
            ),
        ]);
    }
}
