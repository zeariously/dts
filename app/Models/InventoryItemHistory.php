<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItemHistory extends Model
{
    protected $table = 'inventory_item_histories';

    protected $fillable = [
        'inventory_item_id',
        'action',
        'old_data',
        'new_data',
        'old_available',
        'new_available',
        'old_currently_available',
        'new_currently_available',
        'legacy_old_available',
        'legacy_new_available',
        'old_remarks',
        'new_remarks',
        'updated_by',
        'updated_by_name',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'old_available' => 'integer',
        'new_available' => 'integer',
        'old_currently_available' => 'integer',
        'new_currently_available' => 'integer',
        'legacy_old_available' => 'integer',
        'legacy_new_available' => 'integer',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(
            InventoryItem::class,
            'inventory_item_id'
        );
    }
}
