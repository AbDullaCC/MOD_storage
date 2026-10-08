<?php

namespace App\Models;

use App\Models\Concerns\RecordsCreator;
use Illuminate\Database\Eloquent\Model;

class ProductIn extends Model
{
    use RecordsCreator;

    protected $guarded = ['created_by', 'created_by_name', 'created_at'];

    protected $appends = ['recorded_by_label', 'recorded_at_display'];

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
