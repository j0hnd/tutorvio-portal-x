<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\PortalSettings\PortalSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $settings = $request->has('settings')
            ? $request->validate([
                'settings' => ['required', 'array', 'min:1'],
            ])['settings']
            : $request->all();

        if ($settings === []) {
            throw ValidationException::withMessages([
                'settings' => 'At least one portal setting is required.',
            ]);
        }

        return response()->json([
            'data' => $this->portalSettingsService->update($settings, $request->user()),
        ]);
    }
}
