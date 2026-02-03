<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnChecklist extends Model
{
    protected $fillable = [
        'return_id',
        'completeness',
        'functionality',
        'cleanliness',
        'physical_damage',
        'on_time'
    ];

    protected $casts = [
        'on_time' => 'boolean'
    ];

    public function returnModel()
    {
        return $this->belongsTo(ReturnModel::class, 'return_id');
    }

    public function calculateScore()
    {
        // Max score: 20 (5+5+5+5)
        // All fields are now consistent: 1 (bad) to 5 (good)
        return $this->completeness + 
               $this->functionality + 
               $this->cleanliness + 
               $this->physical_damage;
    }
}
