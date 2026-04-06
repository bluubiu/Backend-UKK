<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with('user.role')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }

   
    public function clear()
    {
        ActivityLog::truncate();
        return response()->json(['message' => 'Semua log aktivitas berhasil dihapus']);
    }
}
