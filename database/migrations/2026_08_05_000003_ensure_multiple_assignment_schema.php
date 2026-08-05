<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair/ensure the schema required by the A/B/C multiple-tagging flow.
     *
     * This migration is intentionally idempotent because some DTS databases
     * were copied without the newer tables/columns while their migration logs
     * came from another environment.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dts_document_assignments')) {
            Schema::create('dts_document_assignments', function (Blueprint $table) {
                $table->id();
                $table->integer('IDdoc');
                $table->string('assignment_suffix', 10);
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
            });
        } else {
            Schema::table('dts_document_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('dts_document_assignments', 'IDdoc')) {
                    $table->integer('IDdoc')->nullable();
                }

                if (! Schema::hasColumn('dts_document_assignments', 'assignment_suffix')) {
                    $table->string('assignment_suffix', 10)->nullable();
                }

                if (! Schema::hasColumn('dts_document_assignments', 'idmapagency')) {
                    $table->integer('idmapagency')->nullable();
                }

                if (! Schema::hasColumn('dts_document_assignments', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }

                if (! Schema::hasColumn('dts_document_assignments', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }

                if (! Schema::hasColumn('dts_document_assignments', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }

        $this->addAssignmentColumn('distribution', 'IDdoc');
        $this->addAssignmentColumn('dts_document_remarks', 'IDdoc');
        $this->addAssignmentColumn('docs_transactions', 'IDdoc');
    }

    private function addAssignmentColumn(string $tableName, string $afterColumn): void
    {
        if (
            ! Schema::hasTable($tableName)
            || Schema::hasColumn($tableName, 'assignment_id')
        ) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($afterColumn) {
            $table->unsignedBigInteger('assignment_id')
                ->nullable()
                ->after($afterColumn);

            $table->index('assignment_id');
        });
    }

    /**
     * No destructive rollback. This migration may be repairing structures
     * created by an earlier migration or imported database.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }
};
