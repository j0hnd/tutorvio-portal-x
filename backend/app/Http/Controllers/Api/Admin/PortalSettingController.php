<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\PortalSettings\PortalSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
        ]);

        return response()->json([
            'data' => $this->portalSettingsService->update($validated['settings'], $request->user()),
        ]);
    }
}
