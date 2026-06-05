<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PortalSettings\PortalSettingsService;
use Illuminate\Http\JsonResponse;

class PortalSettingController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  PortalSettingsService  $portalSettingsService
     */
    public function __construct(private readonly PortalSettingsService $portalSettingsService) {}

    /**
     * Display a filtered list of portal setting records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * This action does not require additional request parameters beyond the route context.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->portalSettingsService->all(publicOnly: true),
        ]);
    }
}
