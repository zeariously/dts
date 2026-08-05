<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive, backward-compatible support for one document with
     * multiple independent personnel assignments.
     *
     * Existing/legacy documents continue to work with assignment_id = NULL.
     */
    public function up(): void
    {
        if (!Schema::hasTable('dts_document_assignments')) {
            Schema::create('dts_document_assignments', function (Blueprint $table) {
                $table->id();

                // Shared physical/document record.
                $table->integer('IDdoc');

                // Visible assignment marker: A, B, C ... AA, AB, etc.
                $table->string('assignment_suffix', 10);

                // Same personnel identifier used by distribution.idmapagency
                // and document.IDkeeper.
                $table->integer('idmapagency');

                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['IDdoc', 'assignment_suffix'],
                    'dts_doc_assignments_doc_suffix_unique'
                );

                $table->index(
                    ['IDdoc', 'idmapagency'],
                    'dts_doc_assignments_doc_personnel_index'
                );

                $table->index(
                    'idmapagency',
                    'dts_doc_assignments_personnel_index'
                );
            });
        }

        if (
            Schema::hasTable('distribution')
            && !Schema::hasColumn('distribution', 'assignment_id')
        ) {
            Schema::table('distribution', function (Blueprint $table) {
                $table->unsignedBigInteger('assignment_id')
                    ->nullable()
                    ->after('IDdoc');

                $table->index(
                    'assignment_id',
                    'distribution_assignment_id_index'
                );
            });
        }

        if (
            Schema::hasTable('dts_document_remarks')
            && !Schema::hasColumn('dts_document_remarks', 'assignment_id')
        ) {
            Schema::table('dts_document_remarks', function (Blueprint $table) {
                $table->unsignedBigInteger('assignment_id')
                    ->nullable()
                    ->after('IDdoc');

                $table->index(
                    'assignment_id',
                    'dts_document_remarks_assignment_id_index'
                );
            });
        }

        if (
            Schema::hasTable('docs_transactions')
            && !Schema::hasColumn('docs_transactions', 'assignment_id')
        ) {
            Schema::table('docs_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('assignment_id')
                    ->nullable()
                    ->after('IDdoc');

                $table->index(
                    'assignment_id',
                    'docs_transactions_assignment_id_index'
                );
            });
        }

        /*
         * Intentionally unchanged:
         *
         * dts_document_files:
         * Attachments remain shared by IDdoc because this is one physical
         * document with several personnel workflows.
         *
         * transactions and dts_action_types:
         * These are lookup/reference tables, not per-document workflow rows.
         *
         * document:
         * IDdoc remains the single primary document identifier.
         */
    }

    public function down(): void
    {
        if (
            Schema::hasTable('docs_transactions')
            && Schema::hasColumn('docs_transactions', 'assignment_id')
        ) {
            Schema::table('docs_transactions', function (Blueprint $table) {
                $table->dropColumn('assignment_id');
            });
        }

        if (
            Schema::hasTable('dts_document_remarks')
            && Schema::hasColumn('dts_document_remarks', 'assignment_id')
        ) {
            Schema::table('dts_document_remarks', function (Blueprint $table) {
                $table->dropColumn('assignment_id');
            });
        }

        if (
            Schema::hasTable('distribution')
            && Schema::hasColumn('distribution', 'assignment_id')
        ) {
            Schema::table('distribution', function (Blueprint $table) {
                $table->dropColumn('assignment_id');
            });
        }

        Schema::dropIfExists('dts_document_assignments');
    }
};
