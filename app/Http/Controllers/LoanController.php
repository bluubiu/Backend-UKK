<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanDetail;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Traits\LogsActivity;

class LoanController extends Controller
{
    use LogsActivity;
    /**
     * list peminjaman (filter by status, user)
     */
    public function index(Request $request)
    {
        $query = Loan::with(['user', 'details.item', 'returnModel.fine', 'returnModel.checklist']);
        
        // If not admin or petugas, only show own loans
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'petugas'])) {
            $query->where('user_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * detail peminjaman
     */
    public function show($id)
    {
        $loan = Loan::with(['user', 'details.item', 'approver'])->findOrFail($id);
        return response()->json($loan);
    }

    /**
     * ajukan peminjaman (peminjam)
     */
    public function store(Request $request)
    {
        $request->validate([
            'loan_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:loan_date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // Check user score compliance
        $user = Auth::user();
        if ($user->score < 50) {
            return response()->json([
                'message' => 'Skor kepatuhan Anda terlalu rendah untuk meminjam. Skor minimal: 50, skor Anda: ' . $user->score
            ], 403);
        }

        // Check for unpaid fines
        // Note: Using a direct query on Fine model via relationship
        $hasUnpaidFines = \App\Models\Fine::whereHas('returnModel.loan', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('is_paid', false)->exists();

        if ($hasUnpaidFines) {
            return response()->json([
                'message' => 'Anda memiliki denda yang belum dibayar. Harap lunasi denda terlebih dahulu sebelum melakukan peminjaman baru.'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $loan = Loan::create([
                'user_id' => Auth::id(), // Must be logged in
                'loan_date' => $request->loan_date,
                'return_date' => $request->return_date,
                'status' => 'pending',
            ]);

            foreach ($request->items as $itemData) {
                // Optional: Check if stock implies enough NOW? 
                // Requirement says "Cek tersedia SEBELUM APPROVE". 
                // So at simplified level, we allow request even if stock low, but maybe warn?
                // Standard logic: Just save request.

                LoanDetail::create([
                    'loan_id' => $loan->id,
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                ]);
            }

            DB::commit();

            // Log the activity
            $this->logActivity('Ajukan Peminjaman', "Peminjam {$user->username} mengajukan peminjaman", null, $loan->load('details')->toArray());

            return response()->json($loan->load('details'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat peminjaman', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * setujui peminjaman (petugas)
     */
    public function approve(Request $request, $id)
    {
        $loan = Loan::with('details')->findOrFail($id);

        if ($loan->status !== 'pending') {
            return response()->json(['message' => 'Peminjaman tidak dalam status pending'], 400);
        }

        try {
            DB::beginTransaction();

            // Cek stok tersedia sebelum approve
            foreach ($loan->details as $detail) {
                $item = Item::lockForUpdate()->find($detail->item_id); // Lock row to prevent race condition

                if (!$item) {
                     throw new \Exception("Item ID {$detail->item_id} tidak ditemukan");
                }

                if ($item->available_stock < $detail->quantity) {
                    throw new \Exception("Stok tidak mencukupi untuk item: {$item->name}. Tersedia: {$item->available_stock}, Diminta: {$detail->quantity}");
                }

                // Saat approve: kurangi available_stock
                $item->decrement('available_stock', $detail->quantity);
            }

            // Ubah status jadi approved, simpan approved_by dan approved_at
            $oldValues = $loan->toArray();
            $loan->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            // Log the activity
            $this->logActivity('Setujui Peminjaman', "Petugas menyetujui peminjaman ID: {$loan->id}", $oldValues, $loan->getChanges());

            return response()->json(['message' => 'Peminjaman disetujui', 'loan' => $loan]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * tolak peminjaman (petugas)
     */
    public function reject(Request $request, $id)
    {
        $loan = Loan::findOrFail($id);

        if ($loan->status !== 'pending') {
            return response()->json(['message' => 'Peminjaman tidak dalam status pending'], 400);
        }

        $request->validate([
            'rejection_reason' => 'required|string',
            'rejection_notes' => 'nullable|string'
        ]);

        // Saat reject: tidak ubah stok (karena belum status approved/diambil)
        // Hanya ubah status dan simpan detail penolakan
        $oldValues = $loan->toArray();
        $loan->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'rejection_notes' => $request->rejection_notes,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
        ]);

        // Log the activity
        $this->logActivity('Tolak Peminjaman', "Officer rejected loan ID: {$loan->id}. Reason: {$request->rejection_reason}", $oldValues, $loan->getChanges());

        return response()->json(['message' => 'Peminjaman ditolak', 'loan' => $loan]);
    }

    /**
     * GET /api/loans/{id}/receipt - Generate loan receipt
     */
    public function receipt($id)
    {
        $loan = Loan::with(['user', 'details.item.category', 'approver'])->findOrFail($id);

        // Format receipt data for easy frontend rendering
        $receipt = [
            'receipt_number' => 'LOAN-' . str_pad($loan->id, 6, '0', STR_PAD_LEFT),
            'borrower' => [
                'name' => $loan->user->full_name,
                'email' => $loan->user->email,
                'phone' => $loan->user->phone,
            ],
            'items' => $loan->details->map(function($detail) {
                return [
                    'name' => $detail->item->name,
                    'category' => $detail->item->category->name ?? '-',
                    'quantity' => $detail->quantity,
                    'condition' => $detail->item->condition,
                ];
            }),
            'dates' => [
                'loan_date' => $loan->loan_date,
                'return_date' => $loan->return_date,
                'approved_at' => $loan->approved_at,
            ],
            'officer' => [
                'name' => $loan->approver->full_name ?? '-',
                'approved_at' => $loan->approved_at,
            ],
            'status' => $loan->status,
            'generated_at' => now()->toDateTimeString(),
        ];

        return response()->json($receipt);
    }
}
