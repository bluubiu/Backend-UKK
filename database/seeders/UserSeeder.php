<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create Officer (Petugas)
        User::create([
            'username' => 'petugas1',
            'password' => Hash::make('password'),
            'full_name' => 'Budi Petugas',
            'email' => 'petugas@example.com',
            'phone' => '081234567888',
            'role_id' => 2, // 2 = Petugas
            'is_active' => true,
            'score' => 100,
        ]);

        // Create regular users (borrowers)
        User::create([
            'username' => 'user1',
            'password' => Hash::make('password'),
            'full_name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'role_id' => 3, // 3 = User/Peminjam
            'is_active' => true,
            'score' => 100,
        ]);

        User::create([
            'username' => 'user2',
            'password' => Hash::make('password'),
            'full_name' => 'Siti Nurhaliza',
            'email' => 'siti@example.com',
            'phone' => '081234567891',
            'role_id' => 3,
            'is_active' => true,
            'score' => 85,
        ]);

        User::create([
            'username' => 'user3',
            'password' => Hash::make('password'),
            'full_name' => 'Ahmad Wijaya',
            'email' => 'ahmad@example.com',
            'phone' => '081234567892',
            'role_id' => 3,
            'is_active' => true,
            'score' => 45, // Low score for testing validation
        ]);
    }
}
