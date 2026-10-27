<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dts_outgoing_manual_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('IDdoc')->primary();
            $table->string('display_document_no', 80)->unique();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('dts_outgoing_manual_codes');
    }
};
