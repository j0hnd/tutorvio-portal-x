<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PortalSettings\UpdatePortalSettingsRequest;
use App\Services\PortalSettings\PortalSettingsService;
use Illuminate\Http\JsonResponse;

class PortalSettingController extends Controller
{
    public function __construct(private readonly PortalSettingsService $portalSettingsService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->portalSettingsService->all(),
            'allowed_keys' => $this->portalSettingsService->allowedKeys(),
        ]);
    }

    public function update(UpdatePortalSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->portalSettingsService->update($request->settingsPayload(), $request->user()),
        ]);
    }
}
