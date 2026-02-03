<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreLog extends Model
{
    protected $fillable = [
        'user_id',
        'loan_id',
        'score_change',
        'reason'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
