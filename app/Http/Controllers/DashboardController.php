<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Loan;
use App\Models\ReturnModel;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats()
    {
        // 1. Total Items
        $totalItems = Item::count();

        // 2. Active Loans (status 'approved')
        $activeLoans = Loan::where('status', 'approved')->count();

        // 3. Returned Today
        $returnedToday = ReturnModel::whereDate('returned_at', now())->count();

        // 4. Pending/Overdue Returns (approved and past return_date)
        $pendingReturns = Loan::where('status', 'approved')
            ->whereDate('return_date', '<', now())
            ->count();

        // 5. Total Borrowers (users with role 'peminjam')
        $totalUsers = User::whereHas('role', function($q) {
            $q->where('name', 'peminjam');
        })->count();
        
        // 6. Recent Loans (last 5)
        $recentLoans = Loan::with(['user', 'items'])
            ->latest()
            ->take(5)
            ->get();

        // 7. Loan Trends (Last 7 Days)
        $loanTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = Loan::whereDate('loan_date', $date)->count();
            $loanTrends[] = [
                'date' => now()->subDays($i)->format('D'), // Mon, Tue...
                'count' => $count
            ];
        }

        // 8. Top 5 Most Borrowed Items
        $topItems = Item::withCount('loans')
            ->orderBy('loans_count', 'desc')
            ->take(5)
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->name,
                    'loans_count' => $item->loans_count
                ];
            });

        // 9. Top 8 Performers (Users with best scores)
        $topPerformers = User::withCount('loans')
            ->whereHas('role', function($q) {
                $q->where('name', 'peminjam');
            })
            ->orderBy('score', 'desc')
            ->take(8)
            ->get();

        return response()->json([
            'total_items' => $totalItems,
            'active_loans' => $activeLoans,
            'returned_today' => $returnedToday,
            'pending_returns' => $pendingReturns,
            'total_users' => $totalUsers,
            'recent_loans' => $recentLoans,
            'loan_trends' => $loanTrends,
            'top_items' => $topItems,
            'top_performers' => $topPerformers
        ]);
    }

    public function userStats(Request $request)
    {
        $user = $request->user();

        // 1. Active Loans Data
        $activeLoansData = Loan::with('items')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->get();

        $activeLoansCount = $activeLoansData->count();
        $activeItemsCount = $activeLoansData->pluck('items')->flatten()->count();
        
        // Get names of actively borrowed items (limit to 3 for preview)
        $activeItemNames = $activeLoansData->pluck('items')->flatten()->pluck('name')->take(3)->values();

        // 2. Pending Returns (Overdue)
        $pendingReturns = Loan::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('return_date', '<', now())
            ->count();

        // 3. Total Fines (Unpaid)
        $totalFines = \App\Models\Fine::whereHas('returnModel.loan', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('is_paid', false)->sum('total_fine');

        // 4. Recent Activity (Loans)
        $recentActivity = Loan::with(['items', 'returnModel.fine'])
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        // 5. Next Return Date
        $nextReturnDate = Loan::where('user_id', $user->id)
            ->where('status', 'approved')
            ->orderBy('return_date', 'asc')
            ->pluck('return_date')
            ->first();

        // 6. Loan Trends (Last 7 Days) for User
        $loanTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = Loan::where('user_id', $user->id)
                ->whereDate('loan_date', $date)
                ->count();
            $loanTrends[] = [
                'date' => now()->subDays($i)->format('D'), // Mon, Tue...
                'count' => $count
            ];
        }

        // 7. Loan Status Distribution
        $loanStatusCounts = [
            'approved' => Loan::where('user_id', $user->id)->where('status', 'approved')->count(),
            'returned' => Loan::where('user_id', $user->id)->where('status', 'returned')->count(),
            'rejected' => Loan::where('user_id', $user->id)->where('status', 'rejected')->count(),
            'pending'  => Loan::where('user_id', $user->id)->where('status', 'pending')->count(),
        ];

        return response()->json([
            'user' => $user,
            'active_loans' => $activeLoansCount,
            'active_items_count' => $activeItemsCount,
            'active_item_names' => $activeItemNames,
            'pending_returns' => $pendingReturns,
            'total_fines' => $totalFines,
            'recent_activity' => $recentActivity,
            'next_return_date' => $nextReturnDate,
            'compliance_score' => $user->score,
            'loan_status_counts' => $loanStatusCounts,
            'loan_trends' => $loanTrends
        ]);
    }
}
