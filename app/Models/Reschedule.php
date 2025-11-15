<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reschedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'installment_id',
        'old_due_date',
        'new_due_date',
        'reason',
        'attempt_number',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'old_due_date' => 'date',
            'new_due_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the student that owns this reschedule
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the installment associated with this reschedule
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    /**
     * Get the user who requested this reschedule
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved this reschedule
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if reschedule is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}

