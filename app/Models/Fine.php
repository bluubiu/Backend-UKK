<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fine extends Model
{
    protected $fillable = [
        'return_id',
        'late_days',
        'condition_fine',
        'total_fine',
        'is_paid',
        'payment_confirmed_by_user',
        'user_payment_date',
        'user_notes',
        'proof_of_payment',
        'verified_by',
        'paid_at'
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'condition_fine' => 'decimal:2',
        'total_fine' => 'decimal:2'
    ];

    public function returnModel()
    {
        return $this->belongsTo(ReturnModel::class, 'return_id');
    }
}
