<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Illuminate\Support\Str;

class InvoicePdfService
{
    /**
     * Render an invoice into a PDF document string.
     *
     * The invoice is loaded with its billing relationships and converted into a
     * simple PDF payload; no file is written by this method.
     */
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['student', 'subscription', 'courseProgram']);

        $lines = $this->contentLines($invoice);
        $stream = $this->pageStream($lines);

        return $this->pdfDocument($stream);
    }

    /**
     * Build the download filename for an invoice PDF.
     *
     * The invoice number is sanitized for filesystem-safe use, with the numeric
     * invoice ID as a fallback reference.
     */
    public function filename(Invoice $invoice): string
    {
        $reference = Str::of($invoice->invoice_number)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->value();

        return sprintf('invoice-%s.pdf', $reference ?: $invoice->id);
    }

    /**
     * @return array<int, array{type: string, text?: string, label?: string, value?: string}>
     */
    private function contentLines(Invoice $invoice): array
    {
        return [
            ['type' => 'title', 'text' => 'Tutorvio Invoice'],
            ['type' => 'subtitle', 'text' => 'Professional tutoring services'],
            ['type' => 'spacer'],
            ['type' => 'section', 'text' => 'Invoice Details'],
            ['type' => 'row', 'label' => 'Invoice number', 'value' => $invoice->invoice_number],
            ['type' => 'row', 'label' => 'Issued date', 'value' => $this->date($invoice->issued_date)],
            ['type' => 'row', 'label' => 'Due date', 'value' => $this->date($invoice->due_date)],
            ['type' => 'row', 'label' => 'Status', 'value' => Str::headline($invoice->status)],
            ['type' => 'row', 'label' => 'Payment date', 'value' => $invoice->paid_date ? $this->date($invoice->paid_date) : 'Not paid'],
            ['type' => 'spacer'],
            ['type' => 'section', 'text' => 'Student'],
            ['type' => 'row', 'label' => 'Name', 'value' => $invoice->student?->name ?? 'Unknown student'],
            ['type' => 'row', 'label' => 'Email', 'value' => $invoice->student?->email ?? 'N/A'],
            ['type' => 'spacer'],
            ['type' => 'section', 'text' => 'Package / Subscription'],
            ['type' => 'row', 'label' => 'Subscription', 'value' => $invoice->subscription?->plan_name ?? 'N/A'],
            ['type' => 'row', 'label' => 'Subscription status', 'value' => $invoice->subscription?->status ? Str::headline($invoice->subscription->status) : 'N/A'],
            ['type' => 'row', 'label' => 'Course package', 'value' => $invoice->courseProgram?->title ?? 'N/A'],
            ['type' => 'spacer'],
            ['type' => 'section', 'text' => 'Amounts'],
            ['type' => 'row', 'label' => 'Currency', 'value' => $invoice->currency],
            ['type' => 'row', 'label' => 'Subtotal', 'value' => $this->money($invoice->amount, $invoice->currency)],
            ['type' => 'row', 'label' => $this->taxLabel($invoice), 'value' => $this->money($invoice->tax_amount, $invoice->currency)],
            ['type' => 'total', 'label' => 'Total amount', 'value' => $this->money($invoice->total_amount, $invoice->currency)],
        ];
    }

    /**
     * @param  array<int, array{type: string, text?: string, label?: string, value?: string}>  $lines
     */
    private function pageStream(array $lines): string
    {
        $commands = [
            '0.96 0.97 0.98 rg',
            '0 730 612 62 re f',
            '0.10 0.18 0.25 rg',
            '42 718 528 1 re f',
            'BT',
        ];

        $y = 752;

        foreach ($lines as $line) {
            if ($line['type'] === 'spacer') {
                $y -= 14;

                continue;
            }

            if ($line['type'] === 'title') {
                $commands[] = '/F1 24 Tf';
                $commands[] = sprintf('1 0 0 1 42 %d Tm (%s) Tj', $y, $this->escape($line['text'] ?? ''));
                $y -= 22;

                continue;
            }

            if ($line['type'] === 'subtitle') {
                $commands[] = '/F1 10 Tf';
                $commands[] = sprintf('1 0 0 1 42 %d Tm (%s) Tj', $y, $this->escape($line['text'] ?? ''));
                $y -= 28;

                continue;
            }

            if ($line['type'] === 'section') {
                $commands[] = '/F2 12 Tf';
                $commands[] = sprintf('1 0 0 1 42 %d Tm (%s) Tj', $y, $this->escape(Str::upper($line['text'] ?? '')));
                $y -= 18;

                continue;
            }

            $label = $line['label'] ?? '';
            $value = $line['value'] ?? '';
            $font = $line['type'] === 'total' ? '/F2 11 Tf' : '/F1 10 Tf';

            foreach ($this->wrappedRows($label, $value) as $index => $row) {
                $commands[] = $font;
                $commands[] = sprintf('1 0 0 1 60 %d Tm (%s) Tj', $y, $this->escape($index === 0 ? $row['label'] : ''));
                $commands[] = sprintf('1 0 0 1 230 %d Tm (%s) Tj', $y, $this->escape($row['value']));
                $y -= 16;
            }

            if ($line['type'] === 'total') {
                $y -= 4;
            }
        }

        $commands[] = 'ET';

        return implode("\n", $commands)."\n";
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function wrappedRows(string $label, string $value): array
    {
        return collect(explode("\n", wordwrap($value, 54, "\n", true)))
            ->map(fn (string $line) => ['label' => $label, 'value' => $line])
            ->all();
    }

    private function pdfDocument(string $stream): string
    {
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n",
            "6 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n".$stream."endstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function date(mixed $value): string
    {
        return $value?->format('Y-m-d') ?? 'N/A';
    }

    private function money(mixed $amount, string $currency): string
    {
        return sprintf('%s %s', $currency, number_format((float) $amount, 2));
    }

    private function taxLabel(Invoice $invoice): string
    {
        return (string) data_get($invoice->metadata, 'tax_label', config('billing.tax.label', 'VAT'));
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->ascii($value));
    }

    private function ascii(string $value): string
    {
        return Str::ascii($value);
    }
}
