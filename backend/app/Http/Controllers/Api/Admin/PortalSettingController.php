<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PortalSettings\UpdatePortalSettingsRequest;
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
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * This action does not require additional request parameters beyond the route context.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->portalSettingsService->all(),
            'allowed_keys' => $this->portalSettingsService->allowedKeys(),
        ]);
    }

    /**
     * Update the selected portal setting record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The UpdatePortalSettingsRequest handles authorization and validation before the controller action runs.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  UpdatePortalSettingsRequest  $request
     * @return JsonResponse
     */
    public function update(UpdatePortalSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->portalSettingsService->update($request->settingsPayload(), $request->user()),
        ]);
    }
}
