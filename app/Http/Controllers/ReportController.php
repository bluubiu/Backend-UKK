<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\ReturnModel;
use App\Models\Fine;
use App\Models\User;
use App\Models\Item;
use Illuminate\Http\Request;

use App\Traits\LogsActivity;

class ReportController extends Controller
{
    use LogsActivity;
    /**
     * GET /api/reports/loans - Laporan peminjaman
     */
    public function loans(Request $request)
    {
        $query = Loan::with(['user', 'details.item', 'approver']);

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('loan_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('loan_date', '<=', $request->end_date);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $loans = $query->latest()->get();

        // Log the activity
        $this->logActivity('Report Generation', 'Laporan peminjaman dihasilkan', null, [
            'filters' => $request->all(),
            'total_records' => $loans->count()
        ]);

        return response()->json([
            'total' => $loans->count(),
            'data' => $loans,
            'summary' => [
                'pending' => $loans->where('status', 'pending')->count(),
                'approved' => $loans->where('status', 'approved')->count(),
                'rejected' => $loans->where('status', 'rejected')->count(),
            ]
        ]);
    }

    /**
     * GET /api/reports/returns - Laporan pengembalian
     */
    public function returns(Request $request)
    {
        $query = ReturnModel::with(['loan.user', 'loan.details.item', 'checker', 'checklist', 'fine']);

        if ($request->has('start_date')) {
            $query->where('returned_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('returned_at', '<=', $request->end_date);
        }

        $returns = $query->latest()->get();

        // Log the activity
        $this->logActivity('Report Generation', 'Laporan pengembalian dihasilkan', null, [
            'filters' => $request->all(),
            'total_records' => $returns->count()
        ]);

        return response()->json([
            'total' => $returns->count(),
            'data' => $returns,
            'summary' => [
                'baik' => $returns->where('final_condition', 'baik')->count(),
                'perlu_disterilkan' => $returns->where('final_condition', 'perlu disterilkan')->count(),
                'rusak_ringan' => $returns->where('final_condition', 'rusak ringan')->count(),
                'rusak_berat' => $returns->where('final_condition', 'rusak berat')->count(),
            ]
        ]);
    }

    /**
     * GET /api/reports/fines - Laporan denda
     */
    public function fines(Request $request)
    {
        $query = Fine::with(['returnModel.loan.user', 'returnModel.loan.details.item']);

        if ($request->has('is_paid')) {
            $query->where('is_paid', $request->is_paid);
        }

        $fines = $query->latest()->get();

        // Log the activity
        $this->logActivity('Report Generation', 'Laporan denda dihasilkan', null, [
            'filters' => $request->all(),
            'total_records' => $fines->count()
        ]);

        return response()->json([
            'total' => $fines->count(),
            'data' => $fines,
            'summary' => [
                'total_fines' => $fines->sum('total_fine'),
                'paid' => $fines->where('is_paid', true)->sum('total_fine'),
                'unpaid' => $fines->where('is_paid', false)->sum('total_fine'),
                'count_paid' => $fines->where('is_paid', true)->count(),
                'count_unpaid' => $fines->where('is_paid', false)->count(),
            ]
        ]);
    }

    /**
     * GET /api/reports/scores - Daftar skor peminjam
     */
    public function scores(Request $request)
    {
        $query = User::with(['role', 'scoreLogs'])->where('role_id', '!=', 1); // Exclude admin

        // Sort by score
        $orderBy = $request->get('order_by', 'desc'); // desc = highest first
        $query->orderBy('score', $orderBy);

        $users = $query->get();

        // Log the activity
        $this->logActivity('Report Generation', 'Laporan skor peminjam dihasilkan', null, [
            'filters' => $request->all(),
            'total_records' => $users->count()
        ]);

        return response()->json([
            'total' => $users->count(),
            'data' => $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'score' => $user->score,
                    'is_active' => $user->is_active,
                    'total_score_changes' => $user->scoreLogs->count(),
                ];
            }),
            'summary' => [
                'average_score' => round($users->avg('score'), 2),
                'highest_score' => $users->max('score'),
                'lowest_score' => $users->min('score'),
                'below_50' => $users->where('score', '<', 50)->count(),
            ]
        ]);
    }

    /**
     * GET /api/reports/items-condition - Kondisi alat
     */
    public function itemsCondition(Request $request)
    {
        $query = Item::with(['category']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('condition')) {
            $query->where('condition', $request->condition);
        }

        $items = $query->get();

        // Log the activity
        $this->logActivity('Report Generation', 'Laporan kondisi alat dihasilkan', null, [
            'filters' => $request->all(),
            'total_records' => $items->count()
        ]);

        return response()->json([
            'total' => $items->count(),
            'data' => $items,
            'summary' => [
                'baik' => $items->where('condition', 'baik')->count(),
                'rusak_ringan' => $items->where('condition', 'rusak ringan')->count(),
                'rusak_berat' => $items->where('condition', 'rusak berat')->count(),
                'total_stock' => $items->sum('stock'),
                'available_stock' => $items->sum('available_stock'),
                'borrowed_stock' => $items->sum('stock') - $items->sum('available_stock'),
            ]
        ]);
    }
}
