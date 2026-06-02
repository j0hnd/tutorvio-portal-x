<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Support\LogSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class AuditLog extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'actor_user_id',
        'action_type',
        'module',
        'target_entity_type',
        'target_entity_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected function metadata(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value) => ($sanitized = $this->sanitizeMetadata($value)) === null
                ? null
                : json_encode($sanitized, JSON_THROW_ON_ERROR),
        );
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    private function sanitizeMetadata(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('Audit log metadata must be an array or null.');
        }

        $this->assertSafeMetadataKeys($value);

        return $value;
    }

    /**
     * @param  array<mixed>  $metadata
     */
    private function assertSafeMetadataKeys(array $metadata, string $path = 'metadata'): void
    {
        foreach ($metadata as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (LogSanitizer::containsSensitiveKeyPart($normalizedKey)) {
                throw new InvalidArgumentException(
                    sprintf('Audit log metadata key "%s.%s" is not allowed.', $path, $key)
                );
            }

            if (is_array($value)) {
                $this->assertSafeMetadataKeys($value, sprintf('%s.%s', $path, $key));
            }
        }
    }
}
