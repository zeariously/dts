<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dts_admin_announcements')) {
            return;
        }

        Schema::create('dts_admin_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('message');
            $table->string('target_type', 20)->default('all');
            $table->string('target_value', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at'], 'dts_announcements_active_index');
            $table->index(['target_type', 'target_value'], 'dts_announcements_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dts_admin_announcements');
    }
};
