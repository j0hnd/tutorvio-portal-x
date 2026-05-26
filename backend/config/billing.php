<?php

return [
    'default_currency' => env('BILLING_DEFAULT_CURRENCY'),
    'currency' => env('BILLING_CURRENCY', 'USD'),

    'currencies' => [
        'default' => env('BILLING_DEFAULT_CURRENCY'),
        'supported' => [],
    ],

    'tax' => [
        'label' => env('BILLING_TAX_LABEL', 'VAT'),
        'rate' => (float) env('BILLING_TAX_RATE', env('BILLING_VAT_RATE', 0.12)),
        'vat_rate' => env('BILLING_VAT_RATE') !== null ? (float) env('BILLING_VAT_RATE') : null,
        'default' => [
            'country' => env('BILLING_TAX_COUNTRY'),
            'label' => env('BILLING_DEFAULT_TAX_LABEL'),
            'rate' => env('BILLING_DEFAULT_TAX_RATE') !== null ? (float) env('BILLING_DEFAULT_TAX_RATE') : null,
        ],
        'rules' => [],
    ],

    'invoice' => [
        'due_days' => (int) env('BILLING_INVOICE_DUE_DAYS', 14),
        'student_visibility_enabled' => filter_var(env('BILLING_INVOICE_STUDENT_VISIBILITY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'email' => [
            'automatic_enabled' => filter_var(env('BILLING_INVOICE_EMAIL_AUTOMATIC_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        ],
    ],
];
