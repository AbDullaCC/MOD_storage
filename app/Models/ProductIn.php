<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductIn extends Model
{
    protected $guarded = [];

    protected $casts = [
        'added_at' => 'datetime',
    ];
    
    // Relationship: One batch can have many removals
    public function outs()
    {
        return $this->hasMany(Out::class);
    }

    // Relationship: One item can have many restock additions (batches)
    public function additions()
    {
        return $this->hasMany(Addition::class);
    }

    // Helper: Total quantity ever added (initial + restocks)
    public function getTotalInAttribute()
    {
        return $this->quantity + (int) $this->additions()->sum('quantity');
    }

    // Helper: Calculate how many are left right now
    public function getCurrentStockAttribute()
    {
        $totalOut = (int) $this->outs()->sum('quantity');
        $totalAdded = (int) $this->additions()->sum('quantity');
        return $this->quantity + $totalAdded - $totalOut;
    }
}
