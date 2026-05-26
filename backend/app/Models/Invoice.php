<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
