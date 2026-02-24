<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password-notification', [\App\Http\Controllers\ForgotPasswordController::class, 'store']);
Route::get('/items/available', [\App\Http\Controllers\ItemController::class, 'available']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('users', UserController::class)->middleware('role:admin');
    Route::post('/users/{id}/reset-password-default', [UserController::class, 'resetPasswordToDefault'])->middleware('role:admin');
    Route::get('/roles', function() {
        return response()->json(\App\Models\Role::all());
    })->middleware('role:admin');
    
    // Category Routes (Split for permissions)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('role:admin');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('role:admin');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('role:admin');
    
    // Items Routes
    Route::apiResource('items', \App\Http\Controllers\ItemController::class)->middleware('role:admin');

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead']);

    // Loan Routes
    Route::post('/loans', [\App\Http\Controllers\LoanController::class, 'store']); // Anyone logged in
    Route::get('/loans', [\App\Http\Controllers\LoanController::class, 'index']); // Filter logic inside controller
    Route::get('/loans/{id}', [\App\Http\Controllers\LoanController::class, 'show']);
    Route::get('/loans/{id}/receipt', [\App\Http\Controllers\LoanController::class, 'receipt']); // Generate receipt
    
    // Officer only (Operational)
    Route::put('/loans/{id}/approve', [\App\Http\Controllers\LoanController::class, 'approve'])->middleware('role:petugas');
    Route::put('/loans/{id}/reject', [\App\Http\Controllers\LoanController::class, 'reject'])->middleware('role:petugas');

    // Waiting List Routes
    Route::post('/waiting-lists', [\App\Http\Controllers\WaitingListController::class, 'store']);
    Route::get('/waiting-lists', [\App\Http\Controllers\WaitingListController::class, 'index']);
    Route::delete('/waiting-lists/{id}', [\App\Http\Controllers\WaitingListController::class, 'destroy']);

    // Return Routes
    Route::post('/returns', [\App\Http\Controllers\ReturnController::class, 'store']); // Borrower submits return
    Route::get('/returns', [\App\Http\Controllers\ReturnController::class, 'index']);
    Route::put('/returns/{id}/check', [\App\Http\Controllers\ReturnController::class, 'check'])->middleware('role:admin,petugas'); // Officer checklist

    // Fine Management Routes
    Route::get('/fines', [\App\Http\Controllers\FineController::class, 'index']); // Borrower sees theirs, Officer sees all
    Route::put('/fines/{id}/confirm-payment', [\App\Http\Controllers\FineController::class, 'confirmPayment']); // Borrower confirms
    Route::put('/fines/{id}/verify-payment', [\App\Http\Controllers\FineController::class, 'verifyPayment'])->middleware('role:admin,petugas'); // Officer verifies

    // Report Routes (Admin/Petugas)
    Route::prefix('reports')->middleware('role:admin,petugas')->group(function() {
        Route::get('/loans', [\App\Http\Controllers\ReportController::class, 'loans']);
        Route::get('/returns', [\App\Http\Controllers\ReportController::class, 'returns']);
        Route::get('/fines', [\App\Http\Controllers\ReportController::class, 'fines']);
        Route::get('/scores', [\App\Http\Controllers\ReportController::class, 'scores']);
        Route::get('/items-condition', [\App\Http\Controllers\ReportController::class, 'itemsCondition']);
    });

    // Dashboard Stats
    Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats'])->middleware('role:admin,petugas');
    Route::get('/user/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'userStats']);

    // Activity Log Route
    Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index'])->middleware('role:admin');

    // Profile Routes
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update']);
    Route::post('/profile/photo', [\App\Http\Controllers\ProfileController::class, 'updatePhoto']);
});