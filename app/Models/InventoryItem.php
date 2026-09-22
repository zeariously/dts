<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'category',
        'item',
        'description',
        'location',
        'unit',
        'inventory_year',
        'fixed_value',
        'currently_available',
        'quarters',
        'quarter_stock',
        'ict_assets',
        'remarks',
    ];

    protected $casts = [
        'inventory_year' => 'integer',
        'fixed_value' => 'integer',
        'currently_available' => 'integer',
        'total_released' => 'integer',
        'tracked_released' => 'integer',
        'quarters' => 'array',
        'quarter_stock' => 'array',
        'ict_assets' => 'array',
    ];

    public function histories(): HasMany
    {
        return $this->hasMany(
            InventoryItemHistory::class,
            'inventory_item_id'
        )->latest();
    }
}
