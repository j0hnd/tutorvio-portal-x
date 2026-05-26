<?php

return [
    'currency' => env('BILLING_CURRENCY', 'USD'),

    'tax' => [
        'label' => env('BILLING_TAX_LABEL', 'VAT'),
        'rate' => (float) env('BILLING_TAX_RATE', 0.12),
    ],

    'invoice' => [
        'due_days' => (int) env('BILLING_INVOICE_DUE_DAYS', 14),
    ],
];
