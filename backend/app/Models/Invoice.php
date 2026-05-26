<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_PAID = 'paid';

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_PAID,
        self::STATUS_UNPAID,
        self::STATUS_OVERDUE,
    ];

    protected $fillable = [
        'student_id',
        'subscription_id',
        'course_program_id',
        'invoice_number',
        'amount',
        'tax_amount',
        'total_amount',
        'currency',
        'issued_date',
        'due_date',
        'paid_date',
        'status',
        'payment_gateway',
        'gateway_customer_id',
        'gateway_invoice_id',
        'gateway_payment_intent_id',
        'gateway_checkout_session_id',
        'gateway_payment_method_id',
        'gateway_status',
        'payment_reference',
        'gateway_payload',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'issued_date' => 'date',
            'due_date' => 'date',
            'paid_date' => 'date',
            'gateway_payload' => 'array',
            'metadata' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function courseProgram(): BelongsTo
    {
        return $this->belongsTo(CourseProgram::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdueAsOf(?Carbon $asOf = null): bool
    {
        $today = ($asOf ?? Carbon::now(config('app.timezone')))->copy()->timezone(config('app.timezone'))->toDateString();

        return ! $this->isPaid()
            && $this->due_date !== null
            && $this->paid_date === null
            && $this->due_date->toDateString() < $today;
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeOverdueAsOf(Builder $query, ?Carbon $asOf = null): Builder
    {
        $today = ($asOf ?? Carbon::now(config('app.timezone')))->copy()->timezone(config('app.timezone'))->toDateString();

        return $query->where(function (Builder $query) use ($today): void {
            $query
                ->where('status', self::STATUS_OVERDUE)
                ->orWhere(function (Builder $query) use ($today): void {
                    $query
                        ->where('status', self::STATUS_UNPAID)
                        ->whereNull('paid_date')
                        ->whereDate('due_date', '<', $today);
                });
        });
    }
}
