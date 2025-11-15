<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_fee_id',
        'installment_number',
        'due_date',
        'amount',
        'original_amount',
        'paid_amount',
        'status',
        'grace_end_date',
        'last_reminder_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'grace_end_date' => 'date',
            'last_reminder_at' => 'datetime',
        ];
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(StudentFee::class, 'student_fee_id');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(InstallmentReminder::class);
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(Reschedule::class);
    }
}

