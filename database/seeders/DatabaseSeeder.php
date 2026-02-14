<?php

namespace Database\Seeders;

use App\Models\UserModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        UserModel::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'admin',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'state' => 'Test State',
            'zip_code' => 'Test Zip Code',
            'country' => 'Test Country',
            'phone_number' => 'Test Phone',
        ]);
    }
}
