<?php

namespace App\Http\Controllers;

use App\Models\WaitingList;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanDetail;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Traits\LogsActivity;

class WaitingListController extends Controller
{
    use LogsActivity;
    /**
     * daftar antrian
     */
    public function store(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $item = Item::find($request->item_id);

        // Business rule: Jika stok alat habis, peminjam bisa masuk waiting list
        if ($item->available_stock >= $request->quantity) {
            return response()->json([
                'message' => 'Item still available. Please create a loan request instead.'
            ], 400);
        }

        // Logic for estimating availability date
        // Get current total quantity already in waiting list for this item
        $currentWaitingQuantity = WaitingList::where('item_id', $request->item_id)
            ->where('status', 'waiting')
            ->sum('quantity');
        
        // Find return dates of active loans for this item (asc)
        $returnDates = LoanDetail::where('item_id', $item->id)
            ->whereHas('loan', function($q) {
                $q->whereIn('status', ['approved', 'borrowed']);
            })
            ->join('loans', 'loan_details.loan_id', '=', 'loans.id')
            ->orderBy('loans.return_date', 'asc')
            ->pluck('loans.return_date')
            ->toArray();
            
        // We need (currentWait + requestedQuantity) items to be returned
        $targetQuantity = $currentWaitingQuantity + $request->quantity;
        
        $estimatedDate = null;
        if (!empty($returnDates)) {
            // Find the point where cumulative returns reach targetQuantity
            // For simplicity, we assume each loan is 1 quantity (or we iterate properly)
            // Let's refine: get all active loan details with their quantities
            $activeDetails = LoanDetail::where('item_id', $item->id)
                ->whereHas('loan', function($q) {
                    $q->whereIn('status', ['approved', 'borrowed']);
                })
                ->join('loans', 'loan_details.loan_id', '=', 'loans.id')
                ->select('loan_details.quantity', 'loans.return_date')
                ->orderBy('loans.return_date', 'asc')
                ->get();
                
            $count = 0;
            foreach ($activeDetails as $detail) {
                $count += $detail->quantity;
                if ($count >= $targetQuantity) {
                    $estimatedDate = $detail->return_date;
                    break;
                }
            }
            
            // If still not reached, use the last return date + some buffer or just the last date
            if (!$estimatedDate && !empty($returnDates)) {
                $estimatedDate = end($returnDates);
            }
        }

        $waitingList = WaitingList::create([
            'user_id' => Auth::id(),
            'item_id' => $request->item_id,
            'quantity' => $request->quantity,
            'requested_at' => now(),
            'estimated_available_at' => $estimatedDate,
            'status' => 'waiting'
        ]);

        // Log the activity
        $this->logActivity('Waiting List Entry', "User menambahkan antrian untuk item: {$item->name}. Est: {$estimatedDate}", null, $waitingList->toArray());

        return response()->json($waitingList, 201);
    }

    /**
     * list antrian (berdasarkan item)
     */
    public function index(Request $request)
    {
        $query = WaitingList::with(['user', 'item']);

        // If not admin or petugas, only show own waiting list entries
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'petugas'])) {
            $query->where('user_id', $user->id);
        }

        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // FIFO ordering
        $query->orderBy('requested_at', 'asc');

        return response()->json($query->get());
    }

    /**
     * batalkan antrian
     */
    public function destroy($id)
    {
        $waitingList = WaitingList::findOrFail($id);

        // Only the user who created it can cancel, or admin
        $userRole = Auth::user()->role->name ?? null;
        
        if ($waitingList->user_id !== Auth::id() && $userRole !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $oldValues = $waitingList->toArray();
        $waitingList->delete();

        // Log the activity
        $this->logActivity('Waiting List Cancellation', "User membatalkan antrian untuk item ID: {$waitingList->item_id}", $oldValues);

        return response()->json(['message' => 'Antrian berhasil dibatalkan']);
    }

    /**
     * Process waiting list when item is returned (called internally)
     * This would be called from ReturnController after stock is replenished
     */
    public static function processWaitingList($itemId, $quantityReturned)
    {
        // Get waiting list entries for this item, ordered by FIFO
        $waitingEntries = WaitingList::where('item_id', $itemId)
            ->where('status', 'waiting')
            ->orderBy('requested_at', 'asc')
            ->get();

        $availableStock = Item::find($itemId)->available_stock;

        foreach ($waitingEntries as $entry) {
            $item = Item::find($itemId);
            if ($item->available_stock >= $entry->quantity) {
                try {
                    DB::beginTransaction();

                    // 1. Create a new Loan automatically
                    $loan = Loan::create([
                        'user_id' => $entry->user_id,
                        'loan_date' => now(),
                        'return_date' => now()->addDays(7), // Default 7 days
                        'status' => 'pending', // Waiting for admin approval as requested
                        'approved_by' => null,
                        'approved_at' => null,
                    ]);

                    // 2. Create Loan Detail
                    LoanDetail::create([
                        'loan_id' => $loan->id,
                        'item_id' => $itemId,
                        'quantity' => $entry->quantity
                    ]);

                    // 3. Update Waiting List entry
                    $entry->update([
                        'status' => 'converted',
                        'loan_id' => $loan->id
                    ]);

                    // 4. Decrement stock immediately to reserve it
                    $item->decrement('available_stock', $entry->quantity);

                    DB::commit();

                    // 5. Create Activity Log
                    ActivityLog::create([
                        'action' => 'Waiting List Processing',
                        'description' => "Entri antrian untuk User ID {$entry->user_id} berhasil diubah menjadi peminjaman ID {$loan->id} untuk Item ID {$itemId}",
                        'user_id' => $entry->user_id,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent()
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    \Illuminate\Support\Facades\Log::error("Gagal mengubah antrian menjadi peminjaman: " . $e->getMessage());
                    continue; 
                }
            }
        }
    }
}
