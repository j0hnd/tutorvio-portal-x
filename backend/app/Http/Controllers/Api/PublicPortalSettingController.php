<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PortalSettings\PublicPortalSettingsResource;
use App\Services\PortalSettings\PortalSettingsService;

class PublicPortalSettingController extends Controller
{
    public function __construct(private readonly PortalSettingsService $portalSettingsService) {}

    public function __invoke(): PublicPortalSettingsResource
    {
        return new PublicPortalSettingsResource(
            $this->portalSettingsService->all(publicOnly: true)
        );
    }
}
