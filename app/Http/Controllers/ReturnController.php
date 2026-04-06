<?php

namespace App\Http\Controllers;

use App\Models\ReturnModel;
use App\Models\ReturnChecklist;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\Item;
use App\Models\User;
use App\Models\ScoreLog;
use App\Models\Notification;
use App\Http\Controllers\WaitingListController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

use App\Traits\LogsActivity;

class ReturnController extends Controller
{
    use LogsActivity;
    /**
     * POST /api/returns - Borrower submits return request
     */
    public function store(Request $request)
    {
        $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'notes' => 'nullable|string'
        ]);

        $loan = Loan::with('details')->findOrFail($request->loan_id);

        $user = Auth::user();
        if ($loan->user_id !== $user->id && !$user->hasRole(['admin', 'petugas'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (ReturnModel::where('loan_id', $loan->id)->exists()) {
            return response()->json(['message' => 'Peminjaman ini sudah dikembalikan'], 400);
        }

        $return = ReturnModel::create([
            'loan_id' => $request->loan_id,
            'returned_at' => now(),
            'checked_by' => null, 
            'final_condition' => null,
            'notes' => $request->notes
        ]);

        $this->logActivity('Return Items', "User {$user->username} mengajukan pengembalian untuk ID Pinjaman: {$return->loan_id}", null, $return->toArray());

        return response()->json($return->load('loan'), 201);
    }

    /**
     * GET /api/returns - List returns
     */
    public function index(Request $request)
    {
        $query = ReturnModel::with(['loan.user', 'loan.details.item', 'checker', 'checklist', 'fine']);

        if ($request->has('user_id')) {
            $query->whereHas('loan', function($q) use ($request) {
                $q->where('user_id', $request->user_id);
            });
        }

        return response()->json($query->latest()->get());
    }

    /**
     * PUT /api/returns/{id}/check - Officer fills checklist and processes return
     */
    public function check(Request $request, $id)
    {
        $request->validate([
            'completeness' => 'required|integer|min:1|max:5',
            'functionality' => 'required|integer|min:1|max:5',
            'cleanliness' => 'required|integer|min:1|max:5',
            'physical_damage' => 'required|integer|min:1|max:5',
        ]);

        $return = ReturnModel::with(['loan.details.item', 'loan.user'])->findOrFail($id);

        if ($return->checked_by) {
            return response()->json(['message' => 'Return sudah dicek'], 400);
        }

        try {
            DB::beginTransaction();

            $returnDate = Carbon::parse($return->returned_at);
            $dueDate = Carbon::parse($return->loan->return_date);
            $isOnTime = $returnDate->lte($dueDate);
            $lateDays = $isOnTime ? 0 : ceil($returnDate->floatDiffInDays($dueDate, false) * -1);

            $checklist = ReturnChecklist::create([
                'return_id' => $return->id,
                'completeness' => $request->completeness,
                'functionality' => $request->functionality,
                'cleanliness' => $request->cleanliness,
                'physical_damage' => $request->physical_damage,
                'on_time' => $isOnTime
            ]);

            $checklistScore = $checklist->calculateScore(); // Range 4-20
            $finalCondition = $this->determineFinalCondition($checklistScore, $request->physical_damage);

            $isPerfectCondition = ($checklistScore === 20);
            
            $conditionFine = 0;
            $lateFine = 0;

            if ($isOnTime && $isPerfectCondition) {
                $totalFine = 0;
            } else {
                $conditionFine = $this->calculateConditionFine($finalCondition);
                $lateFine = $lateDays * 15000; 
                $totalFine = $conditionFine + $lateFine;
            }

            if ($totalFine > 0) {
                Fine::create([
                    'return_id' => $return->id,
                    'late_days' => $lateDays,
                    'condition_fine' => $conditionFine,
                    'total_fine' => $totalFine,
                    'is_paid' => false
                ]);
            }

            $scoreChange = $this->calculateScoreChange($lateDays, $finalCondition, $isPerfectCondition);
            $user = $return->loan->user;
            
            $user->updateScore($scoreChange);

            ScoreLog::create([
                'user_id' => $user->id,
                'loan_id' => $return->loan->id,
                'score_change' => $scoreChange,
                'reason' => $this->getScoreReason($lateDays, $finalCondition, $isPerfectCondition)
            ]);

            foreach ($return->loan->details as $detail) {
                $item = Item::lockForUpdate()->find($detail->item_id);
                $item->increment('available_stock', $detail->quantity);

                WaitingListController::processWaitingList($item->id, $detail->quantity);
            }

            $oldReturn = $return->toArray();
            $return->update([
                'checked_by' => Auth::id(),
                'final_condition' => $finalCondition
            ]);
            
            $return->loan->update(['status' => 'returned']);

            if ($scoreChange != 0) {
                $type = $scoreChange > 0 ? 'score_increase' : 'score_decrease';
                $emoji = $scoreChange > 0 ? '📈' : '📉';
                $title = $scoreChange > 0 ? 'Score Bertambah!' : 'Score Berkurang';
                
                Notification::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'title' => $title,
                    'message' => "Score Anda {$type} {$scoreChange} poin. {$emoji}\nAlasan: " . $this->getScoreReason($lateDays, $finalCondition, $isPerfectCondition),
                    'data' => ['score_change' => $scoreChange, 'new_score' => $user->score, 'loan_id' => $return->loan->id]
                ]);
            }
            
            if ($totalFine > 0) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'fine_created',
                    'title' => 'Denda Baru',
                    'message' => 'Anda memiliki denda baru sebesar Rp ' . number_format($totalFine, 0, ',', '.') . ' untuk pengembalian peminjaman.',
                    'data' => ['fine_amount' => $totalFine, 'return_id' => $return->id]
                ]);
            }

            DB::commit();

            $this->logActivity('Verify Return', "Petugas memverifikasi pengembalian ID: {$return->id}. Final condition: {$finalCondition}", $oldReturn, $return->only(['checked_by', 'final_condition']));
            
            if ($totalFine > 0) {
                $this->logActivity('Fine Creation', "Denda otomatis dibuat untuk pengembalian ID: {$return->id}. Total: {$totalFine}");
            }

            $this->logActivity('Score Change', "User {$user->username} skor berubah sebesar {$scoreChange}. Skor baru: {$user->score}");

            return response()->json([
                'message' => 'Pengembalian berhasil diproses',
                'return' => $return->load(['checklist', 'fine']),
                'score_change' => $scoreChange,
                'user_new_score' => $user->score
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal memproses pengembalian', 'error' => $e->getMessage()], 500);
        }
    }


    private function determineFinalCondition($totalScore, $physicalDamage)
    {
        if ($physicalDamage <= 2) {
            return 'rusak berat';
        } elseif ($physicalDamage == 3) {
            return 'rusak ringan';
        }

        if ($totalScore >= 18) {
            return 'baik'; 
        } elseif ($totalScore >= 14) {
            return 'perlu disterilkan';
        } else {
            return 'rusak ringan'; 
        }
    }

    /**
     * Calculate fine based on condition
     */
    private function calculateConditionFine($condition)
    {
        $fineMap = [
            'baik' => 0,
            'perlu disterilkan' => 15000,
            'rusak ringan' => 30000,
            'rusak berat' => 75000
        ];

        return $fineMap[$condition] ?? 0;
    }

    /**
     * Calculate score change for user based on new requirements
     */
    private function calculateScoreChange($lateDays, $condition, $isPerfectCondition)
    {
        $score = 0;

        if ($lateDays == 0) {
            if ($isPerfectCondition) {
                $score += 5;
            } elseif ($condition === 'baik') {
                $score += 3;
            }
        } elseif ($lateDays >= 1 && $lateDays <= 3) {
            $score -= 10;
        } elseif ($lateDays > 3) {
            $score -= 20;
        }

        if ($condition === 'rusak ringan') {
            $score -= 20;
        } elseif ($condition === 'rusak berat') {
            $score -= 40;
        }

        return $score;
    }

    /**
     * Get human-readable reason for score change
     */
    private function getScoreReason($lateDays, $condition, $isPerfectCondition)
    {
        $reasons = [];

        if ($lateDays == 0) {
            if ($isPerfectCondition) {
                $reasons[] = 'Pengembalian Sempurna (+5)';
            } elseif ($condition === 'baik') {
                $reasons[] = 'Tepat waktu + kondisi baik (+3)';
            } else {
                $reasons[] = 'Tepat waktu';
            }
        } elseif ($lateDays >= 1 && $lateDays <= 3) {
            $reasons[] = "Terlambat {$lateDays} hari (-10)";
        } elseif ($lateDays > 3) {
            $reasons[] = "Terlambat {$lateDays} hari >3 (-20)";
        }

        if ($condition === 'rusak ringan') {
            $reasons[] = 'Rusak ringan (-20)';
        } elseif ($condition === 'rusak berat') {
            $reasons[] = 'Rusak berat (-40)';
        }

        return implode(', ', $reasons) ?: 'Pengembalian normal';
    }
}
