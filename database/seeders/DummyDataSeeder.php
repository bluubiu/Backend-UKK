<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanDetail;
use App\Models\ReturnModel;
use App\Models\ReturnChecklist;
use App\Models\ScoreLog;
use App\Models\Fine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Categories
        $categories = [
            ['name' => 'Alat Kesehatan Umum', 'description' => 'Peralatan medis dasar'],
            ['name' => 'Elektronik Medis', 'description' => 'Peralatan medis yang menggunakan listrik/baterai'],
            ['name' => 'Laboratorium', 'description' => 'Peralatan untuk pengujian lab'],
            ['name' => 'Peraga Pendidikan', 'description' => 'Model anatomi dan alat peraga'],
            ['name' => 'Furnitur Medis', 'description' => 'Kursi roda, bed pasien, dll'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat['name']], $cat);
        }

        $allCategories = Category::all();

        // 2. Create Items
        $itemNames = [
            'Tensimeter Digital', 'Termometer Infrared', 'Stetoskop Littmann', 'Oksimeter', 'Nebulizer',
            'Mikroskop Binokuler', 'Tabung Oksigen 1m3', 'Kursi Roda Standar', 'Timbangan Digital', 'Penlight Medical',
            'Model Kerangka Manusia', 'Model Jantung Manusia', 'Ambulatory Blood Pressure Monitor', 'Glukometer Kit',
            'Bed Pasien Manual', 'Tiang Infus', 'Sterilisator Kering', 'Lampu Operasi Portable', 'Suction Pump', 'ECG 3 Channel'
        ];

        foreach ($itemNames as $index => $name) {
            Item::create([
                'category_id' => $allCategories->random()->id,
                'name' => $name,
                'description' => "Deskripsi untuk $name. Alat ini digunakan untuk keperluan medis di UKS.",
                'stock' => rand(5, 15),
                'available_stock' => rand(2, 5),
                'condition' => ['baik', 'baik', 'baik', 'rusak ringan'][rand(0, 3)],
                'is_active' => true,
            ]);
        }

        $allItems = Item::all();

        // 3. Create Users (Peminjam Only, Admins/Petugas already exist)
        $usersData = [
            ['username' => 'andi', 'full_name' => 'Andi Wijaya'],
            ['username' => 'siska', 'full_name' => 'Siska Pratama'],
            ['username' => 'rudi', 'full_name' => 'Rudi Hermawan'],
            ['username' => 'maya', 'full_name' => 'Maya Sari'],
            ['username' => 'deni', 'full_name' => 'Deni Ramadhan'],
            ['username' => 'lina', 'full_name' => 'Lina Marlina'],
            ['username' => 'eko', 'full_name' => 'Eko Prasetyo'],
            ['username' => 'nina', 'full_name' => 'Nina Amelia'],
            ['username' => 'indra', 'full_name' => 'Indra Gunawan'],
            ['username' => 'yuna', 'full_name' => 'Yuna Safitri'],
        ];

        foreach ($usersData as $u) {
            User::firstOrCreate(
                ['username' => $u['username']],
                [
                    'password' => Hash::make('password'),
                    'full_name' => $u['full_name'],
                    'email' => $u['username'] . '@example.com',
                    'phone' => '08' . rand(1000000000, 9999999999),
                    'role_id' => 3, // Peminjam
                    'is_active' => true,
                    'score' => rand(60, 100),
                ]
            );
        }

        $allBorrowers = User::whereHas('role', function($q) {
            $q->where('name', 'peminjam');
        })->get();
        $officer = User::whereHas('role', function($q) {
            $q->where('name', 'petugas');
        })->first();

        // 4. Create Loans
        // Generate loans over the last 30 days
        for ($i = 0; $i < 50; $i++) {
            $user = $allBorrowers->random();
            $daysAgo = rand(0, 30);
            $loanDate = Carbon::now()->subDays($daysAgo);
            $returnDate = (clone $loanDate)->addDays(rand(3, 7));
            
            // Random Status
            $statusChance = rand(0, 100);
            if ($statusChance < 40) $status = 'returned';
            elseif ($statusChance < 70) $status = 'approved';
            elseif ($statusChance < 90) $status = 'pending';
            else $status = 'rejected';

            $loan = Loan::create([
                'user_id' => $user->id,
                'loan_date' => $loanDate,
                'return_date' => $returnDate,
                'status' => $status,
                'approved_by' => $status !== 'pending' ? $officer->id : null,
                'approved_at' => $status !== 'pending' ? (clone $loanDate)->addHours(rand(1, 24)) : null,
            ]);

            // Add 1-2 items per loan
            $itemsForLoan = $allItems->random(rand(1, 2));
            foreach ($itemsForLoan as $item) {
                LoanDetail::create([
                    'loan_id' => $loan->id,
                    'item_id' => $item->id,
                    'quantity' => rand(1, 2),
                ]);
            }

            // If returned, create return record
            if ($status === 'returned') {
                $returnedAt = (clone $returnDate)->addDays(rand(-2, 5)); // Some early, some late
                $isLate = $returnedAt > $returnDate;
                
                $finalCondition = ['baik', 'baik', 'baik', 'rusak ringan', 'rusak berat'][rand(0, 4)];
                
                $returnRecord = ReturnModel::create([
                    'loan_id' => $loan->id,
                    'returned_at' => $returnedAt,
                    'checked_by' => $officer->id,
                    'final_condition' => $finalCondition,
                    'notes' => 'Generated dummy data return.',
                ]);

                ReturnChecklist::create([
                    'return_id' => $returnRecord->id,
                    'completeness' => 5,
                    'functionality' => $finalCondition === 'baik' ? 5 : 3,
                    'cleanliness' => 5,
                    'physical_damage' => $finalCondition === 'baik' ? 1 : 4,
                    'on_time' => !$isLate,
                ]);

                // Calculate fine if late or damaged
                if ($isLate || $finalCondition !== 'baik') {
                    $lateDays = $isLate ? $returnedAt->diffInDays($returnDate) : 0;
                    $lateFine = $lateDays * 5000;
                    $conditionFine = $finalCondition === 'baik' ? 0 : ($finalCondition === 'rusak ringan' ? 25000 : 100000);
                    
                    Fine::create([
                        'return_id' => $returnRecord->id,
                        'late_days' => $lateDays,
                        'condition_fine' => $conditionFine,
                        'total_fine' => $lateFine + $conditionFine,
                        'is_paid' => rand(0, 1),
                    ]);
                }

                // Change score
                $scoreChange = 0;
                if ($isLate) $scoreChange -= 10;
                if ($finalCondition === 'baik') $scoreChange += 5;
                elseif ($finalCondition === 'rusak ringan') $scoreChange -= 20;
                else $scoreChange -= 50;

                ScoreLog::create([
                    'user_id' => $user->id,
                    'loan_id' => $loan->id,
                    'score_change' => $scoreChange,
                    'reason' => 'System generated dummy score update.',
                ]);

                $user->increment('score', $scoreChange);
            }
        }
    }
}
