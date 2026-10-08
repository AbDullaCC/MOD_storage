<?php

namespace App\Models;

use App\Models\Concerns\RecordsCreator;
use Illuminate\Database\Eloquent\Model;

class Addition extends Model
{
    use RecordsCreator;

    protected $guarded = ['created_by', 'created_by_name', 'created_at', 'cancelled_at', 'cancelled_by', 'cancelled_by_name', 'cancellation_reason'];

    protected $appends = ['recorded_by_label', 'recorded_at_display'];

    protected $casts = [
        'date' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function productIn()
    {
        return $this->belongsTo(ProductIn::class);
    }
}
