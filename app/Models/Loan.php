<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $fillable = [
        'user_id',
        'loan_date',
        'return_date',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'rejection_notes',
        'rejected_by',
        'rejected_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function details()
    {
        return $this->hasMany(LoanDetail::class);
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'loan_details', 'loan_id', 'item_id');
    }

    public function returnModel()
    {
        return $this->hasOne(ReturnModel::class, 'loan_id');
    }
}
