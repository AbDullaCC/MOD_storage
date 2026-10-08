<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class InventoryAudit extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['before_values' => 'array', 'after_values' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory audit entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Inventory audit entries cannot be deleted.'));
    }
}
