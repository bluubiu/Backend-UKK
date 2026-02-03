<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Loan;
use App\Models\LoanDetail;
use App\Models\User;
use App\Models\Item;
use App\Models\Role;
use Carbon\Carbon;

class TestLoanSeeder extends Seeder
{
    public function run(): void
    {
        // Get peminjam role
        $peminjamRole = Role::where('name', 'peminjam')->first();
        
        if (!$peminjamRole) {
            $this->command->error('Peminjam role not found!');
            return;
        }

        // Get or create test borrowers
        $borrower1 = User::firstOrCreate(
            ['email' => 'peminjam1@test.com'],
            [
                'username' => 'peminjam_1',
                'full_name' => 'Ahmad Fauzi',
                'password' => bcrypt('password123'),
                'role_id' => $peminjamRole->id,
                'score' => 85,
                'phone' => '081111111111',
            ]
        );

        $borrower2 = User::firstOrCreate(
            ['email' => 'peminjam2@test.com'],
            [
                'username' => 'peminjam_2',
                'full_name' => 'Siti Nurhaliza',
                'password' => bcrypt('password123'),
                'role_id' => $peminjamRole->id,
                'score' => 65,
                'phone' => '082222222222',
            ]
        );

        $borrower3 = User::firstOrCreate(
            ['email' => 'peminjam3@test.com'],
            [
                'username' => 'peminjam_3',
                'full_name' => 'Budi Santoso',
                'password' => bcrypt('password123'),
                'role_id' => $peminjamRole->id,
                'score' => 45,
                'phone' => '083333333333',
            ]
        );

        // Get available items
        $items = Item::where('available_stock', '>', 0)->get();
        
        if ($items->count() === 0) {
            $this->command->error('No items available! Please check items stock.');
            return;
        }

        // Create PENDING loans
        $this->createPendingLoan($borrower1, $items->first(), 2, Carbon::now(), Carbon::now()->addDays(3));
        $this->createPendingLoan($borrower2, $items->skip(1)->first(), 1, Carbon::now(), Carbon::now()->addDays(5));
        $this->createPendingLoan($borrower3, $items->first(), 1, Carbon::now(), Carbon::now()->addDays(2));

        // Create APPROVED loans (for returns)
        $approvedLoan1 = $this->createApprovedLoan($borrower1, $items->first(), 1, Carbon::now()->subDays(5), Carbon::now()->addDays(2));
        $approvedLoan2 = $this->createApprovedLoan($borrower2, $items->skip(1)->first(), 1, Carbon::now()->subDays(10), Carbon::now()->subDays(2));

        // Create RETURN records
        \App\Models\ReturnModel::create([
            'loan_id' => $approvedLoan1->id,
            'returned_at' => Carbon::now(),
            'checked_by' => null,
            'final_condition' => null,
            'notes' => 'Test return - on time'
        ]);

        \App\Models\ReturnModel::create([
            'loan_id' => $approvedLoan2->id,
            'returned_at' => Carbon::now(),
            'checked_by' => null,
            'final_condition' => null,
            'notes' => 'Test return - late 2 days'
        ]);

        $this->command->info('✅ Test data created!');
        $this->command->info('📋 3 PENDING loans for approval');
        $this->command->info('📦 2 returns for inspection (1 on-time, 1 late)');
    }

    private function createPendingLoan($user, $item, $quantity, $loanDate, $returnDate)
    {
        $loan = Loan::create([
            'user_id' => $user->id,
            'loan_date' => $loanDate,
            'return_date' => $returnDate,
            'status' => 'pending',
        ]);

        LoanDetail::create([
            'loan_id' => $loan->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
        ]);

        return $loan;
    }

    private function createApprovedLoan($user, $item, $quantity, $loanDate, $returnDate)
    {
        $loan = Loan::create([
            'user_id' => $user->id,
            'loan_date' => $loanDate,
            'return_date' => $returnDate,
            'status' => 'approved',
            'approved_by' => 1,
            'approved_at' => Carbon::now()->subDays(3),
        ]);

        LoanDetail::create([
            'loan_id' => $loan->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
        ]);

        $item->decrement('available_stock', $quantity);

        return $loan;
    }
}
