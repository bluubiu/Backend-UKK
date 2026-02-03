<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Loan;
use App\Models\LoanDetail;
use App\Models\ReturnModel;
use App\Models\ReturnChecklist;
use App\Models\Fine;
use App\Models\ScoreLog;
use Carbon\Carbon;

class LoanSeeder extends Seeder
{
    public function run(): void
    {
        // Loan 1: Approved and returned (good condition, on time)
        $loan1 = Loan::create([
            'user_id' => 2, // Budi
            'loan_date' => Carbon::now()->subDays(10),
            'return_date' => Carbon::now()->subDays(3),
            'status' => 'approved',
            'approved_by' => 1,
            'approved_at' => Carbon::now()->subDays(10),
        ]);

        LoanDetail::create([
            'loan_id' => $loan1->id,
            'item_id' => 2, // Termometer
            'quantity' => 1,
        ]);

        $return1 = ReturnModel::create([
            'loan_id' => $loan1->id,
            'returned_at' => Carbon::now()->subDays(4),
            'checked_by' => 1,
            'final_condition' => 'baik',
            'notes' => 'Kondisi baik, tepat waktu',
        ]);

        ReturnChecklist::create([
            'return_id' => $return1->id,
            'completeness' => 5,
            'functionality' => 5,
            'cleanliness' => 5,
            'physical_damage' => 1,
            'on_time' => true,
        ]);

        ScoreLog::create([
            'user_id' => 2,
            'loan_id' => $loan1->id,
            'score_change' => 5,
            'reason' => 'Tepat waktu + kondisi baik',
        ]);

        // Loan 2: Approved and returned (late, damaged)
        $loan2 = Loan::create([
            'user_id' => 3, // Siti
            'loan_date' => Carbon::now()->subDays(15),
            'return_date' => Carbon::now()->subDays(10),
            'status' => 'approved',
            'approved_by' => 1,
            'approved_at' => Carbon::now()->subDays(15),
        ]);

        LoanDetail::create([
            'loan_id' => $loan2->id,
            'item_id' => 1, // Tensimeter
            'quantity' => 1,
        ]);

        $return2 = ReturnModel::create([
            'loan_id' => $loan2->id,
            'returned_at' => Carbon::now()->subDays(5), // 5 days late
            'checked_by' => 1,
            'final_condition' => 'rusak ringan',
            'notes' => 'Terlambat 5 hari, rusak ringan',
        ]);

        ReturnChecklist::create([
            'return_id' => $return2->id,
            'completeness' => 4,
            'functionality' => 3,
            'cleanliness' => 4,
            'physical_damage' => 3,
            'on_time' => false,
        ]);

        Fine::create([
            'return_id' => $return2->id,
            'late_days' => 5,
            'condition_fine' => 20000,
            'total_fine' => 45000, // 5*5000 + 20000
            'is_paid' => false,
        ]);

        ScoreLog::create([
            'user_id' => 3,
            'loan_id' => $loan2->id,
            'score_change' => -15,
            'reason' => 'Terlambat 5 hari (>3 hari), Rusak ringan',
        ]);

        // Loan 3: Pending (not yet approved)
        $loan3 = Loan::create([
            'user_id' => 2,
            'loan_date' => Carbon::now(),
            'return_date' => Carbon::now()->addDays(7),
            'status' => 'pending',
        ]);

        LoanDetail::create([
            'loan_id' => $loan3->id,
            'item_id' => 3, // Stetoskop
            'quantity' => 2,
        ]);

        // Loan 4: Rejected
        $loan4 = Loan::create([
            'user_id' => 4, // Ahmad (low score)
            'loan_date' => Carbon::now()->subDays(2),
            'return_date' => Carbon::now()->addDays(5),
            'status' => 'rejected',
            'approved_by' => 1,
            'approved_at' => Carbon::now()->subDays(2),
        ]);

        LoanDetail::create([
            'loan_id' => $loan4->id,
            'item_id' => 2,
            'quantity' => 1,
        ]);
    }
}
