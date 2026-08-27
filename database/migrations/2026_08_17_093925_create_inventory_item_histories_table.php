<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_item_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->string('old_available')->nullable();
            $table->string('new_available')->nullable();

            $table->text('old_remarks')->nullable();
            $table->text('new_remarks')->nullable();

            // User who made the update
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('updated_by_name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_histories');
    }
};