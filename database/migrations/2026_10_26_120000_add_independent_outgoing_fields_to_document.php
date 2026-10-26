<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document', function (Blueprint $table) {
            $table->unsignedBigInteger('outgoing_number')->nullable();
            $table->string('outgoing_status', 80)->nullable();
        });

        // Existing Outgoing documents receive 1,2,3... in historical IDdoc order.
        // Incoming document IDs and every existing relationship are untouched.
        $next = 0;
        DB::table('document')
            ->where('classification', 'True')
            ->orderBy('IDdoc')
            ->select('IDdoc')
            ->chunk(250, function ($rows) use (&$next) {
                foreach ($rows as $row) {
                    ++$next;
                    DB::table('document')->where('IDdoc', $row->IDdoc)->update([
                        'outgoing_number' => $next,
                        'outgoing_status' => 'Recorded',
                    ]);
                }
            });

        Schema::table('document', function (Blueprint $table) {
            $table->unique('outgoing_number', 'document_outgoing_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('document', function (Blueprint $table) {
            $table->dropUnique('document_outgoing_number_unique');
            $table->dropColumn(['outgoing_number', 'outgoing_status']);
        });
    }
};
