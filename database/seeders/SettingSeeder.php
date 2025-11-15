<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::updateOrCreate(
            ['key' => 'penalty.grace_days'],
            [
                'value' => config('fees.penalty.grace_days'),
                'type' => 'number',
                'description' => 'Grace period in days before penalties apply',
                'updated_at' => now(),
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'penalty.rate_percent_per_day'],
            [
                'value' => config('fees.penalty.rate_percent_per_day'),
                'type' => 'number',
                'description' => 'Penalty percent applied per overdue day',
                'updated_at' => now(),
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'reminder.cadence_days'],
            [
                'value' => config('fees.penalty.reminder_frequency_days'),
                'type' => 'number',
                'description' => 'Days between overdue installment reminders',
                'updated_at' => now(),
            ]
        );

        // Guardian Relations (JSON array of relation options)
        Setting::updateOrCreate(
            ['key' => 'student.guardian_relations'],
            [
                'value' => json_encode([
                    'Father',
                    'Mother',
                    'Grandfather',
                    'Grandmother',
                    'Cousin Sister',
                    'Cousin Brother',
                    'Uncle',
                    'Aunt',
                    'Brother',
                    'Sister',
                    'Guardian',
                    'Other'
                ]),
                'type' => 'json',
                'description' => 'Available guardian relation options for students',
                'updated_at' => now(),
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'penalty.gst_percentage'],
            [
                'value' => config('fees.gst_percentage', 18.0),
                'type' => 'number',
                'description' => 'GST percentage applied on online allowance overage',
                'updated_at' => now(),
            ]
        );
    }
}


