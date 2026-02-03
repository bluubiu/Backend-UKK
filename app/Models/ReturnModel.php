<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnModel extends Model
{
    protected $table = 'returns';
    
    protected $fillable = [
        'loan_id',
        'returned_at',
        'checked_by',
        'final_condition',
        'notes'
    ];

    protected $casts = [
        'returned_at' => 'datetime'
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function checklist()
    {
        return $this->hasOne(ReturnChecklist::class, 'return_id');
    }

    public function fine()
    {
        return $this->hasOne(Fine::class, 'return_id');
    }
}
