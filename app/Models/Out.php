<?php

namespace App\Models;

use App\Models\Concerns\RecordsCreator;
use Illuminate\Database\Eloquent\Model;

class Out extends Model
{
    use RecordsCreator;

    protected $guarded = ['created_by', 'created_by_name', 'created_at'];

    protected $appends = ['recorded_by_label', 'recorded_at_display'];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function productIn()
    {
        return $this->belongsTo(ProductIn::class);
    }
}
