<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasColumn(
                'inventory_item_histories',
                'action'
            )
        ) {
            Schema::table(
                'inventory_item_histories',
                function (Blueprint $table) {
                    $table->string(
                        'action',
                        30
                    )
                        ->nullable()
                        ->after('inventory_item_id');
                }
            );
        }

        if (
            !Schema::hasColumn(
                'inventory_item_histories',
                'old_data'
            )
        ) {
            Schema::table(
                'inventory_item_histories',
                function (Blueprint $table) {
                    $table->json('old_data')
                        ->nullable()
                        ->after('action');
                }
            );
        }

        if (
            !Schema::hasColumn(
                'inventory_item_histories',
                'new_data'
            )
        ) {
            Schema::table(
                'inventory_item_histories',
                function (Blueprint $table) {
                    $table->json('new_data')
                        ->nullable()
                        ->after('old_data');
                }
            );
        }
    }

    public function down(): void
    {
        $columns = [];

        foreach (
            [
                'action',
                'old_data',
                'new_data',
            ]
            as $column
        ) {
            if (
                Schema::hasColumn(
                    'inventory_item_histories',
                    $column
                )
            ) {
                $columns[] = $column;
            }
        }

        if (!empty($columns)) {
            Schema::table(
                'inventory_item_histories',
                function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                }
            );
        }
    }
};
