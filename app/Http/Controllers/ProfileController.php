<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function show()
    {
        $user = Auth::user()->load(['role', 'scoreLogs' => function($q) {
            $q->latest()->take(10);
        }]);
        
        return response()->json($user);
    }

    /**
     * Update the authenticated user's profile information.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'nisn' => ['nullable', 'digits:10', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|digits:13',
            'username' => ['required', 'string', Rule::unique('users')->ignore($user->id)],
        ]);

        $data = $request->only(['full_name', 'email', 'nisn', 'phone', 'username']);

        $user->update($data);

        return response()->json([
            'message' => 'Profile berhasil diupdate',
            'user' => $user->load('role')
        ]);
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
            ],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Password saat ini yang Anda masukkan salah.'], 400);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Revoke all tokens except the current one if it exists
        if ($request->user()->currentAccessToken()) {
            $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();
        } else {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Password berhasil diperbarui.'
        ]);
    }

    /**
     * Update the authenticated user's profile photo.
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = Auth::user();

        if ($request->hasFile('photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $path = $request->file('photo')->store('profile-photos', 'public');
            
            $user->update([
                'profile_photo_path' => $path
            ]);

            return response()->json([
                'message' => 'Foto berhasil diupdate',
                'profile_photo_path' => $path,
                'url' => asset('storage/' . $path)
            ]);
        }

        return response()->json(['message' => 'Foto berhasil diupdate']);
    }
}
