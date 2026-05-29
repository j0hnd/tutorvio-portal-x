<?php

namespace App\Support;

use Throwable;

class LogSanitizer
{
    public const REDACTED_VALUE = '[REDACTED]';

    private const SENSITIVE_KEY_PARTS = [
        'authorization',
        'password',
        'passcode',
        'token',
        'secret',
        'private_key',
        'api_key',
        'session',
        'remember',
        'cookie',
        'signature',
        'signed_document',
        'signed_url',
        'private_url',
        'private_file',
        'internal_note',
        'internal_remark',
        'teacher_note',
        'private_note',
        'student_note',
        'payment_metadata',
        'payment_method',
        'payment_intent',
        'checkout_session',
        'card_number',
        'credit_card',
        'card',
        'cvv',
        'cvc',
    ];

    /**
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeArray(array $data, bool $dropSensitiveKeys = false): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (! is_string($key) || ! self::isValidMetadataKey($key)) {
                continue;
            }

            if (self::containsSensitiveKeyPart($key)) {
                if (! $dropSensitiveKeys) {
                    $sanitized[trim($key)] = self::REDACTED_VALUE;
                }

                continue;
            }

            $sanitized[trim($key)] = self::sanitizeValue($value, $dropSensitiveKeys);
        }

        return $sanitized;
    }

    public static function sanitizeValue(mixed $value, bool $dropSensitiveKeys = false): mixed
    {
        if (is_array($value)) {
            return self::sanitizeArray($value, $dropSensitiveKeys);
        }

        if ($value instanceof Throwable) {
            return [
                'type' => $value::class,
                'message' => self::sanitizeString($value->getMessage()),
                'code' => $value->getCode(),
            ];
        }

        if (is_string($value)) {
            return self::sanitizeString($value);
        }

        if (is_object($value)) {
            return $value::class;
        }

        return $value;
    }

    public static function sanitizeString(string $value): string
    {
        $sanitized = trim($value);

        if ($sanitized === '') {
            return $value;
        }

        if (self::isSensitiveStringValue($sanitized)) {
            return self::REDACTED_VALUE;
        }

        $sanitized = preg_replace(
            '/\bBearer\s+[A-Za-z0-9\-._~+\/]+=*/i',
            'Bearer '.self::REDACTED_VALUE,
            $sanitized
        ) ?? $sanitized;

        $sanitized = preg_replace(
            '/\b(password|token|api[_ -]?key|session[_ -]?id|remember[_ -]?token|authorization)\b\s*(?:[:=]\s*)?[A-Za-z0-9\-._~+\/]+=*/i',
            '$1 '.self::REDACTED_VALUE,
            $sanitized
        ) ?? $sanitized;

        $sanitized = preg_replace_callback(
            '#https?://[^\s<>"\']+#i',
            fn (array $matches): string => self::sanitizeUrl($matches[0]),
            $sanitized
        ) ?? $sanitized;

        $sanitized = preg_replace(
            '/\b(?:\d[ -]*?){13,19}\b/',
            self::REDACTED_VALUE,
            $sanitized
        ) ?? $sanitized;

        return $sanitized;
    }

    public static function containsSensitiveKeyPart(string $key): bool
    {
        $normalizedKey = strtolower(str_replace(['-', ' '], '_', $key));

        foreach (self::SENSITIVE_KEY_PARTS as $sensitivePart) {
            if (str_contains($normalizedKey, $sensitivePart)) {
                return true;
            }
        }

        return false;
    }

    public static function isSensitiveStringValue(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^Bearer\s+[A-Za-z0-9\-._~+\/]+=*$/i', $trimmed) === 1) {
            return true;
        }

        if (preg_match('/^eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$/', $trimmed) === 1) {
            return true;
        }

        if (preg_match('/\b(?:\d[ -]*?){13,19}\b/', $trimmed) === 1) {
            return true;
        }

        return self::urlContainsSensitiveData($trimmed);
    }

    public static function isValidMetadataKey(string $key): bool
    {
        $trimmed = trim($key);

        if ($trimmed === '') {
            return false;
        }

        return preg_match('/^[A-Za-z0-9._:-]+$/', $trimmed) === 1;
    }

    private static function sanitizeUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        $path = strtolower((string) ($parts['path'] ?? ''));

        if (str_contains($path, '/private/') || str_contains($path, '/protected/') || str_contains($path, '/storage/private/')) {
            return self::REDACTED_VALUE;
        }

        if (! isset($parts['query']) || ! self::queryContainsSensitiveData($parts['query'])) {
            return $url;
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';

        return $scheme.$host.$port.$path.'?'.self::REDACTED_VALUE;
    }

    private static function urlContainsSensitiveData(string $value): bool
    {
        $parts = parse_url($value);

        if ($parts === false) {
            return false;
        }

        $path = strtolower((string) ($parts['path'] ?? ''));

        return str_contains($path, '/private/')
            || str_contains($path, '/protected/')
            || str_contains($path, '/storage/private/')
            || (isset($parts['query']) && self::queryContainsSensitiveData($parts['query']));
    }

    private static function queryContainsSensitiveData(string $query): bool
    {
        parse_str($query, $params);

        foreach (array_keys($params) as $key) {
            if (self::containsSensitiveKeyPart((string) $key) || in_array(strtolower((string) $key), ['expires', 'x-amz-signature'], true)) {
                return true;
            }
        }

        return false;
    }
}
