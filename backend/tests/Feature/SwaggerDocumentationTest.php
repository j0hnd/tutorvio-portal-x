<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
            'l5-swagger.allowed_environments' => [app()->environment()],
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
        $this->assertImportantApiPathsAreDocumented($generatedJson);
        $this->assertDocumentedResponseExamplesMatchCurrentApiBehavior($generatedJson);
        $this->assertLessonJoinSchemaMatchesCurrentResponseFields($generatedJson);
        $this->assertLessonNoteRequestsUsePublicIdExamples($generatedJson);

        $this->get('/docs')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('openapi', '3.0.0');

        $this->get('/api/documentation')
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $generatedJson
     */
    private function assertImportantApiPathsAreDocumented(array $generatedJson): void
    {
        $paths = $generatedJson['paths'] ?? [];

        foreach ([
            '/health',
            '/dashboard',
            '/metadata',
            '/settings/public',
            '/lessons/{lesson}/join',
            '/lessons/{lesson}/lesson-notes',
            '/students/{student}/lesson-notes',
            '/lesson-notes',
            '/lesson-notes/pending',
            '/lesson-notes/{lessonNote}',
            '/academic-records',
            '/academic-records/{academicRecord}',
            '/academic-records/{academicRecord}/archive',
            '/conversations',
            '/conversations/unread-count',
            '/conversations/{conversation}',
            '/conversations/{conversation}/messages',
            '/conversations/{conversation}/read',
            '/conversations/{conversation}/messages/read',
            '/conversations/{conversation}/messages/{message}/attachments/{conversationAttachment}/download',
            '/conversations/{conversation}/messages/{message}/pin',
            '/conversations/{conversation}/typing/start',
            '/conversations/{conversation}/escalations',
            '/conversations/{conversation}/messages/{message}/escalations',
            '/message-templates',
            '/admin/message-templates',
            '/admin/chat-escalations',
            '/admin/chat-escalations/{conversationEscalation}/status',
        ] as $path) {
            $this->assertArrayHasKey($path, $paths, "Expected Swagger path [{$path}] to be documented.");
        }

        $this->assertArrayHasKey('get', $paths['/settings/public']);
        $publicSettingsServer = $paths['/settings/public']['get']['servers'][0]['url'] ?? null;

        $this->assertIsString($publicSettingsServer);
        $this->assertStringEndsWith('/api', $publicSettingsServer);
        $this->assertStringNotContainsString('/api/v1', $publicSettingsServer);

        $this->assertArrayHasKey('get', $paths['/lesson-notes']);
        $this->assertArrayHasKey('post', $paths['/lesson-notes']);
        $this->assertArrayHasKey('get', $paths['/lesson-notes/{lessonNote}']);
        $this->assertArrayHasKey('patch', $paths['/lesson-notes/{lessonNote}']);
        $this->assertArrayHasKey('get', $paths['/conversations']);
        $this->assertArrayHasKey('post', $paths['/conversations']);
        $this->assertArrayHasKey('post', $paths['/conversations/{conversation}/messages']);
        $this->assertArrayHasKey('delete', $paths['/conversations/{conversation}/messages/{message}/pin']);
        $this->assertArrayHasKey('post', $paths['/conversations/{conversation}/typing/stop']);
        $this->assertArrayHasKey('post', $paths['/admin/message-templates']);
        $this->assertArrayHasKey('patch', $paths['/admin/chat-escalations/{conversationEscalation}/status']);
    }

    /**
     * @param  array<string, mixed>  $generatedJson
     */
    private function assertDocumentedResponseExamplesMatchCurrentApiBehavior(array $generatedJson): void
    {
        $schemas = $generatedJson['components']['schemas'] ?? [];

        $this->assertSame(
            'Unauthenticated.',
            $schemas['UnauthorizedResponse']['properties']['message']['example'] ?? null
        );
        $this->assertSame(
            'Forbidden.',
            $schemas['ForbiddenResponse']['properties']['message']['example'] ?? null
        );
        $this->assertSame(
            'Server error.',
            $schemas['ServerErrorResponse']['properties']['message']['example'] ?? null
        );
        $this->assertSame(
            'Too many requests.',
            $schemas['TooManyRequestsResponse']['properties']['message']['example'] ?? null
        );

        $dashboardUserExample = $schemas['DashboardResponse']['properties']['data']['properties']['user']['example']['id'] ?? null;

        $this->assertIsString($dashboardUserExample);
        $this->assertStringStartsWith('usr_', $dashboardUserExample);
    }

    /**
     * @param  array<string, mixed>  $generatedJson
     */
    private function assertLessonJoinSchemaMatchesCurrentResponseFields(array $generatedJson): void
    {
        $dataSchema = $generatedJson['components']['schemas']['LessonJoinResponse']['properties']['data'] ?? [];
        $properties = $dataSchema['properties'] ?? [];

        foreach ([
            'lesson_id',
            'status',
            'can_join',
            'available_from',
            'available_until',
            'starts_at',
            'ends_at',
            'seconds_until_available',
            'meeting_provider',
            'meeting_link',
            'start_time',
            'end_time',
            'is_join_available',
            'join_starts_at',
            'join_ends_at',
            'reason',
            'message',
            'replacement_lesson',
        ] as $field) {
            $this->assertArrayHasKey($field, $properties, "Expected LessonJoinResponse.data.{$field} to be documented.");
        }

        $this->assertContains('start_time', $dataSchema['required'] ?? []);
        $this->assertContains('join_starts_at', $dataSchema['required'] ?? []);
        $this->assertSame('string', $properties['lesson_id']['type'] ?? null);
        $this->assertTrue(Str::isUlid($properties['lesson_id']['example'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $generatedJson
     */
    private function assertLessonNoteRequestsUsePublicIdExamples(array $generatedJson): void
    {
        $schemas = $generatedJson['components']['schemas'] ?? [];
        $requestProperties = $schemas['LessonNoteRequest']['properties'] ?? [];

        $this->assertSame('string', $requestProperties['lesson_id']['type'] ?? null);
        $this->assertTrue(Str::isUlid($requestProperties['lesson_id']['example'] ?? ''));
        $this->assertSame('string', $requestProperties['lesson_record_id']['type'] ?? null);
        $this->assertTrue(Str::isUlid($requestProperties['lesson_record_id']['example'] ?? ''));

        $noteProperties = $schemas['LessonNote']['properties'] ?? [];

        foreach (['id', 'lesson_id', 'student_id', 'teacher_id', 'author_id', 'lesson_record_id'] as $field) {
            $this->assertSame('string', $noteProperties[$field]['type'] ?? null, "Expected LessonNote.{$field} to be documented as a public ID string.");
        }

        $this->assertTrue(Str::isUlid(
            $generatedJson['paths']['/lesson-notes/{lessonNote}']['patch']['parameters'][0]['example'] ?? ''
        ));
    }
}
