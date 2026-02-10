<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;    

use App\Traits\LogsActivity;

class AuthController extends Controller
{
    use LogsActivity;

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::with('role')->where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->logActivity('Suspicious Activity', "Gagal login untuk user: {$request->username}");
            return response()->json(['message' => 'Gagal login untuk user: ' . $request->username], 401);
        }

        if (!$user->is_active) {
            $this->logActivity('Suspicious Activity', "Akun tidak aktif: {$user->username}");
            return response()->json(['message' => 'Akun tidak aktif'], 403);
        }

        $token = $user->createToken('API Token')->plainTextToken;

        // Log the activity
        $this->logActivity('Login', "User {$user->username} logged in");

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed', // expects password_confirmation field
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                'regex:/[@$!%*#?&]/', // must contain a special character
            ],
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|max:20',
        ]);

        $user = User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role_id' => 3, // Default role: user (3), Petugas (2), Admin (1)
            'is_active' => true,
            'score' => 100, // Initial score MUST be 100
        ]);

        $token = $user->createToken('API Token')->plainTextToken;

        // Log the activity
        $this->logActivity('Register', "User {$user->username} registered", null, $user->toArray());

        return response()->json([
            'message' => 'Pendaftaran berhasil',
            'user' => $user->load('role'),
            'token' => $token,
        ], 201);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        // Log the activity
        $this->logActivity('Logout', "User {$user->username} logged out");

        return response()->json(['message' => 'Logout berhasil']);
    }
}
