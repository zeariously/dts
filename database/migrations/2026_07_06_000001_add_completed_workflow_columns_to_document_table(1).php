<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addIsCompleted = ! Schema::hasColumn('document', 'is_completed');
        $addCompletedAt = ! Schema::hasColumn('document', 'completed_at');
        $addCompletedBy = ! Schema::hasColumn('document', 'completed_by');

        if (! $addIsCompleted && ! $addCompletedAt && ! $addCompletedBy) {
            return;
        }

        Schema::table('document', function (Blueprint $table) use (
            $addIsCompleted,
            $addCompletedAt,
            $addCompletedBy
        ) {
            if ($addIsCompleted) {
                $table->boolean('is_completed')->default(false);
            }

            if ($addCompletedAt) {
                $table->dateTime('completed_at')->nullable();
            }

            if ($addCompletedBy) {
                $table->unsignedBigInteger('completed_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        $columns = collect([
            'is_completed',
            'completed_at',
            'completed_by',
        ])->filter(fn (string $column) => Schema::hasColumn('document', $column))
          ->values()
          ->all();

        if (empty($columns)) {
            return;
        }

        Schema::table('document', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
