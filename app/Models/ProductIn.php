<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductIn extends Model
{
    protected $guarded = [];

    // Relationship: One batch can have many removals
    public function outs()
    {
        return $this->hasMany(Out::class);
    }

    // Helper: Calculate how many are left right now
    public function getCurrentStockAttribute()
    {
        $totalOut = $this->outs()->sum('quantity');
        return $this->quantity - $totalOut;
    }
}
