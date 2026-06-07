<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('username')->nullable();
            $table->string('rights')->nullable();

            $table->string('action')->nullable();
            $table->string('module')->nullable();
            $table->text('description')->nullable();

            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->json('properties')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};