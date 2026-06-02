<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PortalSettings\PortalSettingsService;
use Illuminate\Http\JsonResponse;

class PortalSettingController extends Controller
{
    public function __construct(private readonly PortalSettingsService $portalSettingsService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->portalSettingsService->all(publicOnly: true),
        ]);
    }
}
