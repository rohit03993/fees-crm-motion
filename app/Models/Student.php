<?php

namespace App\Models;

use App\Models\Discount;
use App\Models\InstallmentReminder;
use App\Models\Penalty;
use App\Models\Reschedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_uid',
        'name',
        'student_photo',
        'father_name',
        'guardian_1_name',
        'guardian_1_whatsapp',
        'guardian_1_relation',
        'guardian_1_photo',
        'guardian_2_name',
        'guardian_2_whatsapp',
        'guardian_2_relation',
        'guardian_2_photo',
        'course_id',
        'branch_id',
        'admission_date',
        'program_start_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'admission_date' => 'date',
            'program_start_date' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fee(): HasOne
    {
        return $this->hasOne(StudentFee::class);
    }

    public function installments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Installment::class,
            StudentFee::class,
            'student_id',
            'student_fee_id'
        );
    }

    public function miscCharges(): HasMany
    {
        return $this->hasMany(MiscCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

