<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PortalSettings\PublicPortalSettingsResource;
use App\Services\PortalSettings\PortalSettingsService;

class PublicPortalSettingController extends Controller
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
     * Handle the public portal setting endpoint.
     *
     * Public route, throttled by api-public middleware.
     * This action does not require additional request parameters beyond the route context.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @return PublicPortalSettingsResource
     */
    public function __invoke(): PublicPortalSettingsResource
    {
        return new PublicPortalSettingsResource(
            $this->portalSettingsService->all(publicOnly: true)
        );
    }
}
