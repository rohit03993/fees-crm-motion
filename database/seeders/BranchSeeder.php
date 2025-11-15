<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['name' => 'Main Campus', 'code' => 'MAIN'],
            ['name' => 'City Center', 'code' => 'CITY'],
            ['name' => 'South Zone', 'code' => 'SOUTH'],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(
                ['code' => $branch['code']],
                $branch
            );
        }
    }
}

