<?php

namespace App\Models;

use App\Models\Concerns\RecordsCreator;
use Illuminate\Database\Eloquent\Model;

class ProductIn extends Model
{
    use RecordsCreator;

    protected $guarded = ['created_by', 'created_by_name', 'created_at', 'archived_at', 'cancelled_at', 'cancelled_by', 'cancelled_by_name', 'cancellation_reason', 'active_serial_number'];

    protected $appends = ['recorded_by_label', 'recorded_at_display', 'can_correct'];

    protected $casts = [
        'added_at' => 'datetime',
        'archived_at' => 'datetime',
        'cancelled_at' => 'datetime',
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
        if ($this->cancelled_at) {
            return 0;
        }

        return $this->quantity + (int) $this->additions()->whereNull('cancelled_at')->sum('quantity');
    }

    // Helper: Calculate how many are left right now
    public function getCurrentStockAttribute()
    {
        if ($this->cancelled_at) {
            return 0;
        }
        $totalOut = (int) $this->outs()->whereNull('cancelled_at')->sum('quantity');
        $totalAdded = (int) $this->additions()->whereNull('cancelled_at')->sum('quantity');

        return $this->quantity + $totalAdded - $totalOut;
    }

    public function getCanReplaceAttribute(): bool
    {
        $hasOuts = $this->relationLoaded('outs') ? $this->outs->isNotEmpty() : $this->outs()->exists();
        $hasAdditions = $this->relationLoaded('additions') ? $this->additions->isNotEmpty() : $this->additions()->exists();

        return $this->can_correct && ! $this->archived_at && ! $this->cancelled_at && ! $hasOuts && ! $hasAdditions;
    }
}
