<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'total_fee',
        'cash_allowance',
        'online_allowance',
        'credit_balance',
        'payment_mode',
        'installment_count',
        'installment_frequency_months',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_fee' => 'decimal:2',
            'cash_allowance' => 'decimal:2',
            'online_allowance' => 'decimal:2',
            'credit_balance' => 'decimal:2',
            'installment_count' => 'integer',
            'installment_frequency_months' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }
}

