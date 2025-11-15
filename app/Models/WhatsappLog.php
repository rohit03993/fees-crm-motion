<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'installment_id',
        'misc_charge_id',
        'message_type',
        'message_content',
        'phone_number',
        'status',
        'response_data',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'response_data' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get the student associated with this WhatsApp log
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function miscCharge(): BelongsTo
    {
        return $this->belongsTo(MiscCharge::class);
    }
}

