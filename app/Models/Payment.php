<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'installment_id',
        'misc_charge_id',
        'penalty_id',
        'payment_mode',
        'bank_id',
        'voucher_number',
        'employee_name',
        'amount_received',
        'base_amount',
        'gst_amount',
        'gst_percentage',
        'transaction_id',
        'deposited_to',
        'payment_date',
        'remarks',
        'status',
        'recorded_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_received' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'gst_percentage' => 'decimal:2',
            'payment_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the student that owns this payment
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the installment associated with this payment
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    /**
     * Get the user who recorded this payment
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the user who approved this payment
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the bank associated with this payment
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function miscCharge(): BelongsTo
    {
        return $this->belongsTo(MiscCharge::class);
    }

    public function penalty(): BelongsTo
    {
        return $this->belongsTo(Penalty::class);
    }

    /**
     * Check if payment is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}

