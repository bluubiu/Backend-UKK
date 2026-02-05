<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|exists:users,username',
        ]);

        $user = User::where('username', $request->username)->first();

        // Create notification for all admins
        $admins = User::whereHas('role', function($q) {
            $q->where('name', 'admin');
        })->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'Permintaan Reset Password',
                'message' => "User @{$user->username} ({$user->full_name}) lupa password dan meminta reset.",
                'type' => 'alert', // or 'info'
                'is_read' => false,
            ]);
        }

        return response()->json(['message' => 'Notifikasi telah dikirim ke Administrator.']);
    }
}
