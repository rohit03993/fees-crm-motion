<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'installment_id',
        'channel',
        'reminder_type',
        'scheduled_for',
        'sent_at',
        'status',
        'attempts',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'sent_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }
}


