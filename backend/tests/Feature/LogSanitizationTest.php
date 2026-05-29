<?php

namespace Tests\Feature;

use App\Logging\SanitizeLogContext;
use App\Support\LogSanitizer;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class LogSanitizationTest extends TestCase
{
    public function test_sanitizer_redacts_sensitive_log_context_and_preserves_safe_metadata(): void
    {
        $sanitized = LogSanitizer::sanitizeArray([
            'actor_id' => 12,
            'action_type' => 'admin.updated',
            'target_type' => 'student',
            'target_id' => 34,
            'request_id' => 'req-123',
            'password' => 'secret-password',
            'access_token' => 'secret-token',
            'authorization' => 'Bearer full-token-value',
            'session_id' => 'session-value',
            'private_file_url' => 'https://portal.test/storage/private/report.pdf?signature=secret',
            'payment_metadata' => ['gateway_payment_method_id' => 'pm_secret'],
            'nested' => [
                'safe_value' => 'kept',
                'remember_token' => 'remember-secret',
            ],
        ]);

        $this->assertSame(12, $sanitized['actor_id']);
        $this->assertSame('admin.updated', $sanitized['action_type']);
        $this->assertSame('student', $sanitized['target_type']);
        $this->assertSame(34, $sanitized['target_id']);
        $this->assertSame('req-123', $sanitized['request_id']);
        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['access_token']);
        $this->assertSame('[REDACTED]', $sanitized['authorization']);
        $this->assertSame('[REDACTED]', $sanitized['session_id']);
        $this->assertSame('[REDACTED]', $sanitized['private_file_url']);
        $this->assertSame('[REDACTED]', $sanitized['payment_metadata']);
        $this->assertSame('kept', $sanitized['nested']['safe_value']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['remember_token']);
    }

    public function test_configured_log_tap_sanitizes_messages_context_and_exceptions_before_writing(): void
    {
        $path = storage_path('logs/log-sanitization-test.log');

        if (file_exists($path)) {
            unlink($path);
        }

        config([
            'logging.channels.log_sanitization_test' => [
                'driver' => 'single',
                'path' => $path,
                'level' => 'debug',
                'tap' => [SanitizeLogContext::class],
            ],
        ]);

        Log::channel('log_sanitization_test')->warning(
            'Failed with Bearer message-token-value and https://portal.test/files?id=1&signature=url-secret',
            [
                'actor_id' => 99,
                'password' => 'plain-password',
                'exception' => new RuntimeException('Reset token reset-secret leaked'),
            ]
        );

        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString('actor_id', $contents);
        $this->assertStringContainsString('99', $contents);
        $this->assertStringContainsString('[REDACTED]', $contents);
        $this->assertStringNotContainsString('message-token-value', $contents);
        $this->assertStringNotContainsString('url-secret', $contents);
        $this->assertStringNotContainsString('plain-password', $contents);
        $this->assertStringNotContainsString('reset-secret', $contents);

        Log::forgetChannel('log_sanitization_test');
        unlink($path);
    }
}
