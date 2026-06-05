<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Validates dynamic form template schemas.
 *
 * Expected roles: Admin or staff template managers indirectly through form template requests.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
final class FormTemplateSchemaValidator
{
    public const FIELD_TYPES = [
        'text',
        'textarea',
        'number',
        'date',
        'datetime',
        'time',
        'select',
        'multiselect',
        'radio',
        'checkbox',
        'boolean',
        'email',
        'phone',
        'file',
    ];

    /**
     * Normalize a form-template schema into the canonical storage shape.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function normalize(array $schema): array
    {
        return [
            ...$schema,
            'fields' => array_values($schema['fields'] ?? []),
        ];
    }

    /**
     * Return validation errors for a dynamic form-template schema without mutating it.
     *
     * Important rules: schema fields must be objects with unique identifier names, labels, supported field types, boolean required flags, and valid options for choice fields.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, string>
     */
    public static function errors(array $schema): array
    {
        $errors = [];
        $fields = $schema['fields'] ?? null;

        if (! is_array($fields) || $fields === []) {
            return ['schema.fields' => 'The form schema must include at least one field.'];
        }

        $names = [];

        foreach (array_values($fields) as $index => $field) {
            $path = "schema.fields.{$index}";

            if (! is_array($field)) {
                $errors[$path] = 'Each form field must be an object.';

                continue;
            }

            $name = Arr::get($field, 'name');
            $label = Arr::get($field, 'label');
            $type = Arr::get($field, 'type');

            if (! is_string($name) || ! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                $errors["{$path}.name"] = 'Each field name must start with a letter and contain only letters, numbers, and underscores.';
            } elseif (in_array($name, $names, true)) {
                $errors["{$path}.name"] = 'Field names must be unique within a form schema.';
            } else {
                $names[] = $name;
            }

            if (! is_string($label) || trim($label) === '') {
                $errors["{$path}.label"] = 'Each field must include a label.';
            }

            if (! is_string($type) || ! in_array($type, self::FIELD_TYPES, true)) {
                $errors["{$path}.type"] = 'Each field must use a supported field type.';
            }

            if (array_key_exists('required', $field) && ! is_bool($field['required'])) {
                $errors["{$path}.required"] = 'The required value must be true or false.';
            }

            if (in_array($type, ['select', 'multiselect', 'radio'], true)) {
                $options = $field['options'] ?? null;

                if (! is_array($options) || $options === []) {
                    $errors["{$path}.options"] = 'Choice fields must include at least one option.';
                } else {
                    foreach (array_values($options) as $optionIndex => $option) {
                        if (is_string($option) && trim($option) !== '') {
                            continue;
                        }

                        if (
                            is_array($option)
                            && is_string($option['label'] ?? null)
                            && trim($option['label']) !== ''
                            && is_string($option['value'] ?? null)
                            && trim($option['value']) !== ''
                        ) {
                            continue;
                        }

                        $errors["{$path}.options.{$optionIndex}"] = 'Each option must be a non-empty string or an object with label and value.';
                    }
                }
            }
        }

        return $errors;
    }
}
