<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(security: [['sanctum' => []]])]
#[OA\Info(
    version: L5_SWAGGER_API_VERSION,
    description: L5_SWAGGER_API_DESCRIPTION,
    title: L5_SWAGGER_API_TITLE
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST.'/api/v1',
    description: 'Tutorvio Portal API v1'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    description: 'Use a Laravel Sanctum bearer token. Enter only the token value in Swagger UI.',
    bearerFormat: 'Token',
    scheme: 'bearer'
)]
#[OA\Get(
    path: '/health',
    operationId: 'healthCheck',
    summary: 'API health check',
    security: [],
    tags: ['System'],
    responses: [
        new OA\Response(response: 200, description: 'API is healthy'),
    ]
)]
class Documentation {}
