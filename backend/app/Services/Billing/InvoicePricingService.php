<?php

namespace App\Services\Billing;

class InvoicePricingService
{
    /**
     * Resolve the billing currency code for invoice pricing.
     *
     * A provided currency overrides configured defaults, and the returned code
     * is normalized to uppercase.
     */
    public function currency(?string $currency = null): string
    {
        $value = $currency
            ?? config('billing.default_currency')
            ?? config('billing.currencies.default')
            ?? config('billing.currency', 'USD');

        return strtoupper((string) $value);
    }

    /**
     * Resolve the configured tax profile for an optional country code.
     *
     * Country-specific billing config is preferred when present; otherwise the
     * service falls back to the default tax label, country, and rate.
     *
     * @return array{country: string|null, label: string, rate: float}
     */
    public function taxProfile(?string $country = null): array
    {
        $country = $country !== null ? strtoupper($country) : null;
        $rule = $country ? config("billing.tax.rules.{$country}") : null;

        if (is_array($rule)) {
            return [
                'country' => $country,
                'label' => (string) ($rule['label'] ?? $this->defaultTaxLabel()),
                'rate' => $this->rate($rule['rate'] ?? $this->defaultTaxRate()),
            ];
        }

        return [
            'country' => $country ?? config('billing.tax.default.country'),
            'label' => $this->defaultTaxLabel(),
            'rate' => $this->defaultTaxRate(),
        ];
    }

    /**
     * Calculate the tax amount for a subtotal and tax rate.
     *
     * The result is rounded to two decimal places for invoice storage.
     */
    public function taxAmount(float $subtotal, float $rate): float
    {
        return round($subtotal * $rate, 2);
    }

    private function defaultTaxLabel(): string
    {
        return (string) (
            config('billing.tax.default.label')
            ?? config('billing.tax.label', 'VAT')
        );
    }

    private function defaultTaxRate(): float
    {
        return $this->rate(
            config('billing.tax.default.rate')
            ?? config('billing.tax.vat_rate')
            ?? config('billing.tax.rate', 0)
        );
    }

    private function rate(mixed $rate): float
    {
        return round((float) $rate, 4);
    }
}
