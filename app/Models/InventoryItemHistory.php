<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItemHistory extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'old_available',
        'new_available',
        'old_remarks',
        'new_remarks',
        'updated_by',
        'updated_by_name',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(
            InventoryItem::class,
            'inventory_item_id'
        );
    }
}