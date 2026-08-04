<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('document', 'document_group_id')) {
            Schema::table('document', function (Blueprint $table): void {
                /*
                 * For multiple assignment only.
                 * All A/B/C entries share this generated base DTS number.
                 * Existing and future single documents keep this as NULL.
                 */
                $table->bigInteger('document_group_id')
                    ->nullable()
                    ->after('IDdoc');

                $table->index(
                    'document_group_id',
                    'document_document_group_id_index'
                );
            });
        }

        if (! Schema::hasColumn('document', 'assignment_suffix')) {
            Schema::table('document', function (Blueprint $table): void {
                /* A, B, C ... Z, AA, AB for multiple assignments only. */
                $table->string('assignment_suffix', 10)
                    ->nullable()
                    ->after('document_group_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('document', 'assignment_suffix')) {
            Schema::table('document', function (Blueprint $table): void {
                $table->dropColumn('assignment_suffix');
            });
        }

        if (Schema::hasColumn('document', 'document_group_id')) {
            Schema::table('document', function (Blueprint $table): void {
                $table->dropIndex('document_document_group_id_index');
                $table->dropColumn('document_group_id');
            });
        }
    }
};
