<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Rename old available column
        |--------------------------------------------------------------------------
        |
        | The old Inventory implementation used `available`.
        | We keep it only as legacy data.
        |
        */
        if (
            Schema::hasColumn('inventory_items', 'available')
            && !Schema::hasColumn('inventory_items', 'legacy_available')
        ) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->renameColumn(
                    'available',
                    'legacy_available'
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Fixed Value
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasColumn('inventory_items', 'fixed_value')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->integer('fixed_value')
                    ->nullable()
                    ->after('track_stock');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Currently Available in SPD
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasColumn('inventory_items', 'currently_available')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->integer('currently_available')
                    ->nullable()
                    ->after('fixed_value');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Total Released
        |--------------------------------------------------------------------------
        |
        | This is automatically generated ONLY when there is a usable
        | Fixed Value.
        |
        | Example:
        | Fixed = 30
        | Current = 11
        | Total Released = 19
        |
        */
        if (!Schema::hasColumn('inventory_items', 'total_released')) {
            DB::statement("
                ALTER TABLE inventory_items
                ADD COLUMN total_released INT
                GENERATED ALWAYS AS (
                    CASE
                        WHEN fixed_value IS NULL
                            OR currently_available IS NULL
                        THEN NULL

                        WHEN currently_available >= fixed_value
                        THEN 0

                        ELSE
                            CAST(fixed_value AS SIGNED)
                            - CAST(currently_available AS SIGNED)
                    END
                ) STORED
                AFTER currently_available
            ");
        } else {
            /*
             * Ensure existing total_released uses the final formula.
             */
            DB::statement("
                ALTER TABLE inventory_items
                MODIFY COLUMN total_released INT
                GENERATED ALWAYS AS (
                    CASE
                        WHEN fixed_value IS NULL
                            OR currently_available IS NULL
                        THEN NULL

                        WHEN currently_available >= fixed_value
                        THEN 0

                        ELSE
                            CAST(fixed_value AS SIGNED)
                            - CAST(currently_available AS SIGNED)
                    END
                ) STORED
            ");
        }

        /*
        |--------------------------------------------------------------------------
        | Tracked Released
        |--------------------------------------------------------------------------
        |
        | Used when there is NO usable Fixed Value.
        |
        | Example:
        | Current = 4
        | Released = 0
        |
        | Release 2:
        | Current = 2
        | tracked_released = 2
        |
        | Release another 1:
        | Current = 1
        | tracked_released = 3
        |
        */
        if (!Schema::hasColumn('inventory_items', 'tracked_released')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->unsignedInteger('tracked_released')
                    ->default(0)
                    ->after('total_released');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Legacy Available
        |--------------------------------------------------------------------------
        |
        | Add it only if neither the old `available` nor the renamed
        | `legacy_available` exists.
        |
        */
        if (
            !Schema::hasColumn('inventory_items', 'legacy_available')
            && !Schema::hasColumn('inventory_items', 'available')
        ) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->string('legacy_available')
                    ->nullable()
                    ->after('tracked_released');
            });
        }

      
        DB::statement("
            ALTER TABLE inventory_items
            MODIFY COLUMN fixed_value INT NULL
        ");

        DB::statement("
            ALTER TABLE inventory_items
            MODIFY COLUMN currently_available INT NULL
        ");
    }

    public function down(): void
    {
        
    }
};