<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use App\Models\ReturnModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ScoreLog;
use App\Models\Notification;

use App\Traits\LogsActivity;

class FineController extends Controller
{
    use LogsActivity;
    /**
     * GET /api/fines - List my fines (for borrower) or all fines (for officer)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Fine::with([
            'returnModel.loan.user', 
            'returnModel.loan.details.item', 
            'returnModel.checklist', 
            'returnModel.fine'
        ]);

        // Borrowers can only see their own fines
        if (!$user->hasRole(['admin', 'petugas'])) {
            $query->whereHas('returnModel.loan', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($request->has('user_id')) {
            // Admin/Petugas filtering by user
            $query->whereHas('returnModel.loan', function($q) use ($request) {
                $q->where('user_id', $request->user_id);
            });
        }
        
        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'paid') {
                $query->where('is_paid', true);
            } elseif ($request->status === 'unpaid') {
                $query->where('is_paid', false);
            } elseif ($request->status === 'pending_verification') {
                $query->where('is_paid', false)
                      ->where('payment_confirmed_by_user', true);
            }
        }

        return response()->json($query->latest()->get());
    }

    /**
     * PUT /api/fines/{id}/confirm-payment - Borrower confirms they paid
     */
    public function confirmPayment(Request $request, $id)
    {
        $fine = Fine::with('returnModel.loan')->findOrFail($id);
        
        // Authorization
        if ($fine->returnModel->loan->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($fine->is_paid) {
            return response()->json(['message' => 'Denda sudah dibayar.'], 400);
        }

        $request->validate([
            'notes' => 'nullable|string',
            'proof_of_payment' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        $proofPath = null;
        if ($request->hasFile('proof_of_payment')) {
            $file = $request->file('proof_of_payment');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('proof_of_payments', $filename, 'public');
            $proofPath = $path;
        }

        $oldValues = $fine->toArray();
        $updateData = [
            'payment_confirmed_by_user' => true,
            'user_payment_date' => now(),
            'user_notes' => $request->notes,
        ];

        if ($proofPath) {
            $updateData['proof_of_payment'] = $proofPath;
        }

        $fine->update($updateData);

        // Log the activity
        $this->logActivity('Fine Payment', "Konfirmasi pembayaran denda berhasil disubmit. Menunggu verifikasi. ID: {$fine->id}", $oldValues, $fine->only(['payment_confirmed_by_user', 'user_payment_date', 'user_notes', 'proof_of_payment']));

        return response()->json([
            'message' => 'Konfirmasi pembayaran denda berhasil disubmit. Menunggu verifikasi.',
            'fine' => $fine
        ]);
    }

    /**
     * PUT /api/fines/{id}/verify-payment - Officer verifies payment
     */
    public function verifyPayment(Request $request, $id)
    {
        $fine = Fine::with('returnModel.loan.user')->findOrFail($id);

        if ($fine->is_paid) {
            return response()->json(['message' => 'Denda sudah dibayar.'], 400);
        }

        $request->validate([
            'action' => 'required|in:accept,reject',
            'notes' => 'nullable|string' // Rejection reason if any
        ]);

        $oldValues = $fine->toArray();
        if ($request->action === 'accept') {
            $fine->update([
                'is_paid' => true,
                'paid_at' => now(),
                'verified_by' => Auth::id()
            ]);

            // Add +10 Score Bonus
            $user = $fine->returnModel->loan->user;
            $scoreChange = 10;
            $newScore = $user->updateScore($scoreChange);

            // Log Score Change
            ScoreLog::create([
                'user_id' => $user->id,
                'loan_id' => $fine->returnModel->loan->id,
                'score_change' => $scoreChange,
                'reason' => 'Pembayaran denda lunas (+10)'
            ]);

            // Notification
            Notification::create([
                'user_id' => $user->id,
                'type' => 'score_increase',
                'title' => 'Score Bertambah!',
                'message' => "Score Anda bertambah {$scoreChange} poin karena pelunasan denda.",
                'data' => [
                    'score_change' => $scoreChange, 
                    'new_score' => $newScore,
                    'fine_id' => $fine->id
                ]
            ]);
            
            // Log the activity
            $this->logActivity('Fine Verification', "Petugas menerima pembayaran denda ID: {$fine->id}", $oldValues, $fine->only(['is_paid', 'paid_at', 'verified_by']));

            return response()->json(['message' => 'Denda berhasil diverifikasi.', 'fine' => $fine]);
        } else {
            // Reject confirmation (reset specific fields so user can try again)
            $fine->update([
                'payment_confirmed_by_user' => false,
                'user_payment_date' => null,
                'user_notes' => null // Or consider keeping history? For now reset. // Or send notes back?
            ]);
            
            // Log the activity
            $this->logActivity('Fine Verification', "Petugas menolak pembayaran denda ID: {$fine->id}", $oldValues, $fine->only(['payment_confirmed_by_user', 'user_payment_date', 'user_notes']));

            // In a real app we might want to notify the user why it was rejected
            return response()->json(['message' => 'Verifikasi denda ditolak.', 'fine' => $fine]);
        }
    }
}
