<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;

    protected $fillable = ['category_id', 'name', 'description', 'image', 'stock', 'available_stock', 'condition', 'is_active'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function loanDetails()
    {
        return $this->hasMany(LoanDetail::class);
    }

    public function loans()
    {
        return $this->belongsToMany(Loan::class, 'loan_details', 'item_id', 'loan_id');
    }
}
