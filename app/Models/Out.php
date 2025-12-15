<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Out extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime',
    ];
    
    public function productIn()
    {
        return $this->belongsTo(ProductIn::class);
    }
}
