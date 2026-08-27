<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('inventory_year')
                ->default(2027)
                ->after('category');

            $table->index(
                ['inventory_year', 'category'],
                'inventory_items_year_category_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(
                'inventory_items_year_category_index'
            );

            $table->dropColumn('inventory_year');
        });
    }
};
