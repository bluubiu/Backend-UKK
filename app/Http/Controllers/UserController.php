<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

use App\Traits\LogsActivity;

class UserController extends Controller
{
    use LogsActivity;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('role')->get();
        return response()->json($users);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|unique:users',
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
            'full_name' => 'required',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'username' => $request->username,
            'password' => bcrypt($request->password),
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role_id' => $request->role_id,
            'is_active' => true,
            'score' => 0,
        ]);

        // Log the activity
        $this->logActivity('Buat User', "Admin membuat user: {$user->username}", null, $user->toArray());

        return response()->json($user->load('role'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('role')->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'username' => 'sometimes|unique:users,username,' . $id,
            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed', // expects password_confirmation field
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                'regex:/[@$!%*#?&]/', // must contain a special character
            ],
            'full_name' => 'sometimes',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'nullable',
            'role_id' => 'sometimes|exists:roles,id',
            'is_active' => 'sometimes|boolean',
            'score' => 'sometimes|integer',
        ]);

        $data = $request->only(['username', 'full_name', 'email', 'phone', 'role_id', 'is_active', 'score']);

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $oldValues = $user->getOriginal();
        $user->update($data);

        // Log the activity
        $this->logActivity('Update User', "Admin mengupdate user: {$user->username}", $oldValues, $user->getChanges());

        return response()->json($user->load('role'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $oldValues = $user->toArray();
        $user->delete(); // Soft delete

        // Log the activity
        $this->logActivity('Delete User', "Admin menghapus user: {$user->username}", $oldValues);

        return response()->json(['message' => 'User berhasil dihapus']);
    }

    /**
     * Reset user password to default format
     */
    public function resetPasswordToDefault(string $id)
    {
        $user = User::findOrFail($id);
        
        // Generate default password: [username]123#
        $defaultPassword = $user->username . '123#';
        
        // Update password
        $user->password = bcrypt($defaultPassword);
        $user->save();

        // Log the activity
        $this->logActivity('Reset Password', "Admin mereset password user: {$user->username} ke default", null, ['user_id' => $user->id]);

        return response()->json([
            'message' => 'Password berhasil direset ke default',
            'default_password' => $defaultPassword
        ]);
    }
}
