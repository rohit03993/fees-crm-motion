<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminStaffSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@feescrm.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
                'staff_id' => 'ADM-0001',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@feescrm.local'],
            [
                'name' => 'Tanisha (Staff)',
                'password' => Hash::make('Staff@123'),
                'role' => 'staff',
                'staff_id' => 'STF-0001',
                'is_active' => true,
            ]
        );
    }
}
