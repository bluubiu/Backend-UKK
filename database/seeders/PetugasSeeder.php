<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class PetugasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get petugas role
        $petugasRole = Role::where('name', 'petugas')->first();
        
        if (!$petugasRole) {
            $this->command->error('Petugas role not found! Please run RoleSeeder first.');
            return;
        }

        // Create petugas user
        $petugas = User::firstOrCreate(
            ['email' => 'petugas@mediuks.com'],
            [
                'username' => 'petugas_uks',
                'full_name' => 'Petugas UKS',
                'password' => Hash::make('password123'),
                'role_id' => $petugasRole->id,
                'score' => 100, // Default score
                'phone' => '081234567890',
            ]
        );

        $this->command->info('Petugas user created successfully!');
        $this->command->info('Email: petugas@mediuks.com');
        $this->command->info('Password: password123');
    }
}
