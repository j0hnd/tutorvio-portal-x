<?php

namespace App\Services\Billing;

class InvoicePricingService
{
    public function currency(?string $currency = null): string
    {
        $value = $currency
            ?? config('billing.default_currency')
            ?? config('billing.currencies.default')
            ?? config('billing.currency', 'USD');

        return strtoupper((string) $value);
    }

    /**
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
