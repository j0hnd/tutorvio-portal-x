<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SwaggerDocumentationTest extends TestCase
{
    private string $docsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docsPath = storage_path('framework/testing/api-docs');

        File::deleteDirectory($this->docsPath);

        config([
            'l5-swagger.allowed_environments' => ['testing'],
            'l5-swagger.allow_production' => false,
            'l5-swagger.defaults.paths.docs' => $this->docsPath,
            'l5-swagger.defaults.generate_always' => false,
            'l5-swagger.documentations.default.paths.format_to_use_for_docs' => 'json',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->docsPath);

        parent::tearDown();
    }

    public function test_swagger_documentation_can_be_generated_and_served_in_allowed_environment(): void
    {
        $this->artisan('l5-swagger:generate')
            ->assertSuccessful();

        $docsFile = $this->docsPath.'/api-docs.json';

        $this->assertFileExists($docsFile);

        $generatedJson = json_decode((string) File::get($docsFile), true);

        $this->assertIsArray($generatedJson);
        $this->assertSame('3.0.0', $generatedJson['openapi'] ?? null);
        $this->assertArrayHasKey('paths', $generatedJson);

        $this->get('/docs')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('openapi', '3.0.0');

        $this->get('/api/documentation')
            ->assertOk();
    }
}
