<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::firstOrCreate(
            ['username' => 'admin'],
            [
                'password' => bcrypt('password'),
                'full_name' => 'Administrator',
                'email' => 'admin@example.com',
                'phone' => '1234567890',
                'role_id' => 1,
                'is_active' => true,
                'score' => 100,
            ]
        );
    }
}
