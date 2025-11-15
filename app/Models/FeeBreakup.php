<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeBreakup extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'fee_type',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the student that owns this fee breakup
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

