<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    /**
     * Log an activity for the currently authenticated user or system.
     *
     * @param string $action
     * @param string $description
     * @param array|null $oldValues
     * @param array|null $newValues
     * @return void
     */
    public function logActivity($action, $description, $oldValues = null, $newValues = null)
    {
        // Don't log passwords or other sensitive fields
        $sensitiveFields = ['password', 'token', 'access_token', 'remember_token'];
        
        if ($oldValues) {
            $oldValues = array_diff_key($oldValues, array_flip($sensitiveFields));
        }
        if ($newValues) {
            $newValues = array_diff_key($newValues, array_flip($sensitiveFields));
        }

        ActivityLog::create([
            'user_id' => Auth::id(), // This will be null if no user is authenticated
            'action' => $action,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
