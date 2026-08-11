<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use App\Models\ActivityLog;
use App\Models\DtsDistribution;
use App\Models\DtsDocStatus;
use App\Models\DtsDocTransaction;
use App\Models\DtsDocType;
use App\Models\DtsDocument;
use App\Models\DtsOffice;
use App\Models\DtsTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;


class DtsController extends Controller
{
public function index(Request $request)
{
    $perPage = max(1, min((int) $request->input('per_page', 10), 100));
    $search = trim((string) $request->input('search', ''));
    $section = strtolower(trim((string) $request->input('section', 'documents')));
    $filter = strtolower(trim((string) $request->input('filter', '')));
    $scope = strtolower(trim((string) $request->input('scope', '')));

    if ($section === 'reports') {
        $perPage = 5000;
    }

    $trueValues = ['True', 'true', 'Y', 'y', '1', 1];
    $currentRights = $this->currentUserRights();
    $viewerPersonnelIds = $this->viewerAssignedPersonnelIds();

    $availableYears = DB::table('document')
        ->selectRaw('YEAR(entrydate) as year')
        ->whereNotNull('entrydate')
        ->groupBy(DB::raw('YEAR(entrydate)'))
        ->orderByDesc('year')
        ->pluck('year')
        ->filter()
        ->map(fn ($year) => (int) $year)
        ->values();

    $requestedYear = trim((string) $request->input('year', ''));
    $selectedYear = $requestedYear;

    if ($selectedYear === '') {
        $selectedYear = (string) (
            $availableYears->contains((int) now()->year)
                ? now()->year
                : ($availableYears->first() ?? now()->year)
        );
    }

    if ($selectedYear === 'all' || $section === 'reports') {
        $selectedYear = '';
    }

    $isAllDocumentsSection = in_array($section, ['all-documents', 'all-docs', 'all_documents'], true)
        || in_array($scope, ['all', 'all-documents', 'all-docs', 'all_documents'], true)
        || $request->boolean('show_all')
        || $request->boolean('all_documents');

    if ($isAllDocumentsSection && ! in_array($currentRights, ['2', '3'], true)) {
        abort(403);
    }

    $hasAssignmentTable = Schema::hasTable('dts_document_assignments');
    $hasDistributionAssignment = Schema::hasColumn('distribution', 'assignment_id');
    $hasRemarkAssignment = Schema::hasTable('dts_document_remarks')
        && Schema::hasColumn('dts_document_remarks', 'assignment_id');

    $hasManualCompletionColumns = Schema::hasColumn('document', 'is_completed')
        && Schema::hasColumn('document', 'completed_at');

    $legacyCompletionSql = $hasManualCompletionColumns
        ? '(COALESCE(d.is_completed, 0) = 1 OR d.completed_at IS NOT NULL)'
        : '(d.IDdocstatus = 6)';

    /*
     * A document-level completion flag belongs only to legacy/single workflows.
     * Multiple assignments are completed independently through their own
     * assignment-scoped Final Address action.
     */
    $workflowCompletionSql = '(assignment.id IS NULL AND ' . $legacyCompletionSql . ')';
    $workflowNotCompletedSql = 'NOT ' . $workflowCompletionSql;

    $doctypeCodeColumn = 'dt.description';

    if (Schema::hasColumn('lu_doctype', 'abbreviation')) {
        $doctypeCodeColumn = 'dt.abbreviation';
    } elseif (Schema::hasColumn('lu_doctype', 'abbr')) {
        $doctypeCodeColumn = 'dt.abbr';
    } elseif (Schema::hasColumn('lu_doctype', 'code')) {
        $doctypeCodeColumn = 'dt.code';
    }

    $selectedActionLabelExpression = Schema::hasColumn(
        'dts_document_remarks',
        'action_label'
    )
        ? "COALESCE(selectedActionRemark.action_label, selectedActionType.name, 'Select Action')"
        : "COALESCE(selectedActionType.name, 'Select Action')";

    $makeAssignmentSource = function () use ($hasAssignmentTable) {
        if ($hasAssignmentTable) {
            return DB::table('dts_document_assignments')
                ->select([
                    'id',
                    'IDdoc',
                    'assignment_suffix',
                    'idmapagency',
                    'created_by',
                    'created_at',
                    'updated_at',
                ]);
        }

        /*
         * Keep a stable assignment alias even before the optional migration
         * exists. The empty subquery makes every document behave as legacy.
         */
        return DB::table('document')
            ->whereRaw('1 = 0')
            ->selectRaw(
                'NULL as id, IDdoc, NULL as assignment_suffix, NULL as idmapagency, '
                . 'NULL as created_by, NULL as created_at, NULL as updated_at'
            );
    };

    $makeLatestDistribution = function () use ($hasDistributionAssignment) {
        $query = DB::table('distribution as dx')
            ->select([
                'dx.IDdoc',
                DB::raw(
                    $hasDistributionAssignment
                        ? 'dx.assignment_id'
                        : 'NULL as assignment_id'
                ),
                DB::raw('MAX(CAST(dx.IDdist AS UNSIGNED)) as latest_IDdist'),
            ])
            ->groupBy('dx.IDdoc');

        if ($hasDistributionAssignment) {
            $query->groupBy('dx.assignment_id');
        }

        return $query;
    };

    $makeLatestSelectedAction = function () use (
        $makeLatestDistribution,
        $hasRemarkAssignment
    ) {
        if (! Schema::hasTable('dts_document_remarks')) {
            return DB::table('document')
                ->whereRaw('1 = 0')
                ->selectRaw(
                    'IDdoc, NULL as assignment_id, NULL as latest_selected_action_id'
                );
        }

        $remarkAssignmentExpression = $hasRemarkAssignment
            ? 'selectedActionLatest.assignment_id'
            : 'NULL';

        $query = DB::table('dts_document_remarks as selectedActionLatest')
            ->joinSub($makeLatestDistribution(), 'selectedActionLatestDist', function ($join) use ($remarkAssignmentExpression) {
                $join->on('selectedActionLatestDist.IDdoc', '=', 'selectedActionLatest.IDdoc')
                    ->whereRaw(
                        'selectedActionLatestDist.assignment_id <=> '
                        . $remarkAssignmentExpression
                    );
            })
            ->join(
                'distribution as selectedActionCurrentDist',
                'selectedActionCurrentDist.IDdist',
                '=',
                'selectedActionLatestDist.latest_IDdist'
            )
            ->select([
                'selectedActionLatest.IDdoc',
                DB::raw($remarkAssignmentExpression . ' as assignment_id'),
                DB::raw('MAX(selectedActionLatest.id) as latest_selected_action_id'),
            ])
            ->where('selectedActionLatest.action_type', 'action_taken')
            ->whereColumn(
                'selectedActionLatest.created_at',
                '>=',
                'selectedActionCurrentDist.distdate'
            )
            ->groupBy('selectedActionLatest.IDdoc');

        if ($hasRemarkAssignment) {
            $query->groupBy('selectedActionLatest.assignment_id');
        }

        return $query;
    };

    $buildWorkflowBase = function () use (
        $makeAssignmentSource,
        $makeLatestDistribution,
        $makeLatestSelectedAction,
        $hasDistributionAssignment
    ) {
        $query = DB::table('document as d')
            ->leftJoinSub($makeAssignmentSource(), 'assignment', function ($join) {
                $join->on('assignment.IDdoc', '=', 'd.IDdoc');
            })
            ->leftJoin('lu_doctype as dt', 'dt.ID', '=', 'd.IDdoctype')
            ->leftJoin('lu_office as fromOffice', 'fromOffice.ID', '=', 'd.IDfrom')
            ->leftJoin('lu_office as forOffice', 'forOffice.ID', '=', 'd.IDfor')
            ->leftJoinSub($makeLatestDistribution(), 'ld', function ($join) {
                $join->on('ld.IDdoc', '=', 'd.IDdoc')
                    ->whereRaw('ld.assignment_id <=> assignment.id');
            })
            ->leftJoin('distribution as dist', 'dist.IDdist', '=', 'ld.latest_IDdist')
            ->leftJoinSub($makeLatestSelectedAction(), 'lsa', function ($join) {
                $join->on('lsa.IDdoc', '=', 'd.IDdoc')
                    ->whereRaw('lsa.assignment_id <=> assignment.id');
            })
            ->leftJoin(
                'dts_document_remarks as selectedActionRemark',
                'selectedActionRemark.id',
                '=',
                'lsa.latest_selected_action_id'
            )
            ->leftJoin(
                'dts_action_types as selectedActionType',
                'selectedActionType.id',
                '=',
                'selectedActionRemark.action_type_id'
            )
            ->leftJoin('distribution as returnParent', function ($join) use ($hasDistributionAssignment) {
                $join->on('returnParent.IDdist', '=', 'dist.IDparentdist')
                    ->on('returnParent.IDdoc', '=', 'dist.IDdoc');

                if ($hasDistributionAssignment) {
                    $join->whereRaw(
                        'returnParent.assignment_id <=> dist.assignment_id'
                    );
                }
            })
            ->leftJoin('username as returnUser', 'returnUser.ID', '=', 'dist.IDuser')
            ->leftJoin(
                'username as returnConfirmUser',
                'returnConfirmUser.ID',
                '=',
                'returnParent.confirmuser'
            )
            ->leftJoin('lu_office as distOffice', 'distOffice.ID', '=', 'dist.IDoffice')
            ->leftJoin('lu_personnel as receiverPersonnel', function ($join) {
                $join->on(
                    'receiverPersonnel.ID',
                    '=',
                    DB::raw(
                        'COALESCE(dist.idmapagency, assignment.idmapagency, d.IDkeeper)'
                    )
                );
            });

        return $query;
    };

    $applyTaggedScope = function ($query, bool $force = false) use (
        $currentRights,
        $viewerPersonnelIds
    ) {
        $mustLimit = $force || in_array($currentRights, ['2', '4'], true);

        if (! $mustLimit) {
            return $query;
        }

        $personnelIds = collect($viewerPersonnelIds)
            ->filter(fn ($id) => $id !== null && $id !== '' && (int) $id !== 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($personnelIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($scope) use ($personnelIds) {
            /*
             * Multiple workflow:
             * use the current distribution tag. The original assignment row is
             * only a fallback before the first distribution exists.
             */
            $scope->where(function ($multiple) use ($personnelIds) {
                $multiple
                    ->whereNotNull('assignment.id')
                    ->where(function ($tag) use ($personnelIds) {
                        $tag
                            ->where(function ($currentDistribution) use ($personnelIds) {
                                $currentDistribution
                                    ->whereNotNull('dist.IDdist')
                                    ->whereIn('dist.idmapagency', $personnelIds);
                            })
                            ->orWhere(function ($noDistribution) use ($personnelIds) {
                                $noDistribution
                                    ->whereNull('dist.IDdist')
                                    ->whereIn('assignment.idmapagency', $personnelIds);
                            });
                    });
            });

            /*
             * Legacy/single workflow:
             * preserve the original IDkeeper fallback.
             */
            $scope->orWhere(function ($legacy) use ($personnelIds) {
                $legacy
                    ->whereNull('assignment.id')
                    ->where(function ($tag) use ($personnelIds) {
                        $tag
                            ->where(function ($currentDistribution) use ($personnelIds) {
                                $currentDistribution
                                    ->whereNotNull('dist.IDdist')
                                    ->whereIn('dist.idmapagency', $personnelIds);
                            })
                            ->orWhere(function ($noDistribution) use ($personnelIds) {
                                $noDistribution
                                    ->whereNull('dist.IDdist')
                                    ->whereIn('d.IDkeeper', $personnelIds);
                            });
                    });
            });
        });
    };

    $applyNotPulled = function ($query) use ($trueValues) {
        return $query->where(function ($condition) use ($trueValues) {
            $condition->whereNull('dist.YNpulled')
                ->orWhereNotIn('dist.YNpulled', $trueValues);
        });
    };

    $applyNotReturned = function ($query) use ($trueValues) {
        return $query->where(function ($condition) use ($trueValues) {
            $condition->whereNull('dist.YNreturn')
                ->orWhereNotIn('dist.YNreturn', $trueValues);
        });
    };

    $applyReturnChild = function ($query) use ($trueValues) {
        return $query
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.IDparentdist')
            ->whereNotNull('returnParent.IDdist')
            ->where(function ($returned) use ($trueValues) {
                $returned->whereIn('returnParent.YNreturn', $trueValues)
                    ->orWhereNotNull('returnParent.returndate');
            });
    };

    $applyNoReturnChild = function ($query) use ($trueValues) {
        return $query->where(function ($notReturnChild) use ($trueValues) {
            $notReturnChild
                ->whereNull('returnParent.IDdist')
                ->orWhere(function ($normalParent) use ($trueValues) {
                    $normalParent
                        ->whereNull('returnParent.returndate')
                        ->where(function ($notReturned) use ($trueValues) {
                            $notReturned->whereNull('returnParent.YNreturn')
                                ->orWhereNotIn('returnParent.YNreturn', $trueValues);
                        });
                });
        });
    };

    $applyStatusFilter = function ($query, string $status) use (
        $applyNotPulled,
        $applyNotReturned,
        $applyReturnChild,
        $applyNoReturnChild,
        $workflowCompletionSql,
        $workflowNotCompletedSql,
        $trueValues
    ) {
        if ($status === 'returned') {
            $applyReturnChild($query);
            $query->whereNull('dist.confirmdate');
            $applyNotPulled($query);

            return $query;
        }

        if ($status === 'for_receiving') {
            $query
                ->whereNotNull('dist.IDdist')
                ->whereNotNull('dist.distdate')
                ->whereNull('dist.confirmdate')
                ->whereRaw($workflowNotCompletedSql);
            $applyNotReturned($query);
            $applyNotPulled($query);
            $applyNoReturnChild($query);

            return $query;
        }

        if ($status === 'received') {
            $query
                ->whereNotNull('dist.IDdist')
                ->whereNotNull('dist.confirmdate')
                ->whereNull('selectedActionRemark.id')
                ->whereRaw($workflowNotCompletedSql);
            $applyNotReturned($query);
            $applyNotPulled($query);

            return $query;
        }

        if ($status === 'addressed') {
            $query
                ->whereNotNull('dist.IDdist')
                ->whereNotNull('dist.confirmdate')
                ->where(function ($addressed) use ($workflowCompletionSql) {
                    $addressed->whereNotNull('selectedActionRemark.id')
                        ->orWhereRaw($workflowCompletionSql);
                });
            $applyNotReturned($query);
            $applyNotPulled($query);

            return $query;
        }

        if ($status === 'pulled') {
            return $query->whereIn('dist.YNpulled', $trueValues);
        }

        if ($status === 'pending') {
            return $query->whereNull('dist.IDdist');
        }

        return $query;
    };

    /*
     * Current multiple-tagging architecture reads A/B/C from
     * dts_document_assignments. The old document_group_id and
     * document.assignment_suffix columns are optional legacy columns and must
     * never be referenced when they are absent from the active database.
     */
    $hasLegacyDocumentAssignmentColumns =
        Schema::hasColumn('document', 'document_group_id')
        && Schema::hasColumn('document', 'assignment_suffix');

    $legacyDocumentAssignmentDisplayWhen = $hasLegacyDocumentAssignmentColumns
        ? "WHEN d.document_group_id IS NOT NULL
            AND NULLIF(TRIM(d.assignment_suffix), '') IS NOT NULL
          THEN CONCAT(
            CAST(d.document_group_id AS CHAR),
            '-',
            UPPER(TRIM(d.assignment_suffix))
          )"
        : '';

    $displayDocumentNumberExpression = "CASE
        WHEN assignment.id IS NOT NULL
            AND NULLIF(TRIM(assignment.assignment_suffix), '') IS NOT NULL
        THEN CONCAT(
            CAST(d.IDdoc AS CHAR),
            '-',
            UPPER(TRIM(assignment.assignment_suffix))
        )
        {$legacyDocumentAssignmentDisplayWhen}
        ELSE CAST(d.IDdoc AS CHAR)
    END";

    $workflowStatusExpression = "CASE
        WHEN LOWER(COALESCE(CAST(dist.YNpulled AS CHAR), ''))
            IN ('true', 'y', '1')
            THEN 'Pulled Out'
        WHEN (
            returnParent.IDdist IS NOT NULL
            AND (
                LOWER(COALESCE(CAST(returnParent.YNreturn AS CHAR), ''))
                    IN ('true', 'y', '1')
                OR returnParent.returndate IS NOT NULL
            )
            AND dist.confirmdate IS NULL
        )
            THEN 'Returned'
        WHEN (
            selectedActionRemark.id IS NOT NULL
            OR {$workflowCompletionSql}
        )
            AND dist.confirmdate IS NOT NULL
            THEN 'Addressed'
        WHEN dist.confirmdate IS NOT NULL
            THEN 'Received'
        WHEN dist.distdate IS NOT NULL
            THEN 'For Receiving'
        WHEN CAST(COALESCE(d.IDdocstatus, 0) AS UNSIGNED) = 7
            THEN 'Pending 07'
        ELSE 'Pending'
    END";

    $selectColumns = [
        'd.IDdoc',
        'd.IDdoc as document_no',
        DB::raw($displayDocumentNumberExpression . ' as display_document_no'),
        'assignment.id as assignment_id',
        'assignment.IDdoc as assignment_document_id',
        DB::raw("UPPER(NULLIF(TRIM(assignment.assignment_suffix), '')) as assignment_suffix"),
        DB::raw('CASE WHEN assignment.id IS NULL THEN 0 ELSE 1 END as is_multiple_assignment'),
        DB::raw(
            $hasAssignmentTable
                ? '(SELECT COUNT(*) FROM dts_document_assignments assignmentCount WHERE assignmentCount.IDdoc = d.IDdoc) as assignment_count'
                : '1 as assignment_count'
        ),

        'd.classification',
        'd.IDdoctype',
        'd.entrydate',
        'd.IDfor',
        'd.IDfrom',
        DB::raw(Schema::hasColumn('document', 'to_name') ? 'd.to_name as to_name' : 'NULL as to_name'),
        DB::raw(Schema::hasColumn('document', 'from_name') ? 'd.from_name as from_name' : 'NULL as from_name'),
        'd.subject',
        'd.regarding',
        'd.remarks',
        'd.IDdocstatus',
        DB::raw('COALESCE(dist.idmapagency, assignment.idmapagency, d.IDkeeper) as IDkeeper'),
        DB::raw('CASE WHEN ' . $workflowCompletionSql . ' THEN 1 ELSE 0 END as is_completed'),
        DB::raw(
            Schema::hasColumn('document', 'completed_at')
                ? 'CASE WHEN assignment.id IS NULL THEN d.completed_at ELSE NULL END as completed_at'
                : 'CASE WHEN assignment.id IS NULL THEN d.datecleared ELSE NULL END as completed_at'
        ),

        DB::raw($doctypeCodeColumn . ' as code'),
        'dt.description as doctype',
        'fromOffice.officename as from_office',
        'fromOffice.abbrev as from_office_abbrev',
        'forOffice.officename as for_office',
        'distOffice.officename as current_office',
        'receiverPersonnel.name as receiver_personnel',
        'receiverPersonnel.name as to_personnel',
        'receiverPersonnel.name as staff_concern',
        'receiverPersonnel.name as personnel_name',

        'dist.IDdist',
        DB::raw(
            $hasDistributionAssignment
                ? 'dist.assignment_id as distribution_assignment_id'
                : 'NULL as distribution_assignment_id'
        ),
        'dist.IDparentdist as distribution_parent_id',
        'dist.IDoffice as distribution_office_id',
        'dist.distdate',
        'dist.distdate as date_sent',
        'dist.distdate as distribution_date',
        'dist.confirmdate',
        'dist.returndate',
        'dist.idmapagency as distribution_personnel_id',
        'dist.YNreturn',
        'dist.YNpulled',
        'dist.remarks as distribution_remarks',

        'returnParent.IDdist as return_distribution_id',
        'returnParent.returndate as return_date',
        DB::raw('COALESCE(returnParent.confirmuser, dist.IDuser) as returned_by'),
        'dist.idmapagency as returned_to_personnel_id',
        DB::raw("COALESCE(
            NULLIF(TRIM(returnConfirmUser.name), ''),
            NULLIF(TRIM(returnConfirmUser.loginname), ''),
            NULLIF(TRIM(returnUser.name), ''),
            NULLIF(TRIM(returnUser.loginname), ''),
            CONCAT('Account #', COALESCE(returnParent.confirmuser, dist.IDuser))
        ) as returned_by_name"),

        'selectedActionRemark.id as selected_action_id',
        'selectedActionRemark.action_type as action_type',
        'selectedActionRemark.action_type_id as action_type_id',
        DB::raw('CASE WHEN selectedActionRemark.id IS NULL THEN 0 ELSE 1 END as has_selected_action'),
        DB::raw($selectedActionLabelExpression . ' as selected_action'),
        DB::raw($selectedActionLabelExpression . ' as action_label'),
        'selectedActionRemark.remarks as selected_action_remarks',
        'selectedActionRemark.created_at as selected_action_date',
        DB::raw($workflowStatusExpression . ' as workflow_status'),
    ];

    $applyCommonYear = function ($query) use ($selectedYear) {
        if ($selectedYear !== '') {
            $query->whereYear('d.entrydate', (int) $selectedYear);
        }

        return $query;
    };

    /*
     * Stats use one row per assignment workflow. A legacy document contributes
     * one row; a document tagged to A/B/C contributes three independent rows.
     */
    $statsBase = $buildWorkflowBase();
    $applyCommonYear($statsBase);

    if (! $isAllDocumentsSection && $section !== 'reports') {
        $applyTaggedScope($statsBase);
    }

    $countStatus = function ($base, string $status) use ($applyStatusFilter) {
        $query = clone $base;
        $applyStatusFilter($query, $status);

        return $query->count();
    };

    $stats = [
        'total' => (clone $statsBase)->count(),
        'for_receiving' => $countStatus($statsBase, 'for_receiving'),
        'received' => $countStatus($statsBase, 'received'),
        'for_action' => $countStatus($statsBase, 'received'),
        'addressed' => $countStatus($statsBase, 'addressed'),
        'in_progress' => $countStatus($statsBase, 'addressed'),
        'completed' => 0,
        'returned' => $currentRights === '3'
            ? $countStatus($statsBase, 'returned')
            : 0,
        'pending_docs' => $countStatus($statsBase, 'pending'),
        'pending_docs_07' => (clone $statsBase)
            ->where('d.IDdocstatus', 7)
            ->count(),
    ];

    $documentsQuery = $buildWorkflowBase();

    if (! $isAllDocumentsSection && $section !== 'reports') {
        $applyTaggedScope($documentsQuery);
    }

    $applyCommonYear($documentsQuery);

    if ($section === 'reports') {
        if ($request->filled('report_classification')) {
            $documentsQuery->where(
                'd.classification',
                $request->input('report_classification')
            );
        }

        $reportUser = trim((string) $request->input('report_user', ''));

        if ($reportUser !== '' && strtolower($reportUser) !== 'all') {
            $documentsQuery->whereRaw(
                'COALESCE(dist.idmapagency, assignment.idmapagency, d.IDkeeper) = ?',
                [(int) $reportUser]
            );
        }

        if ($request->filled('start_date')) {
            $documentsQuery->whereDate(
                'd.entrydate',
                '>=',
                $request->input('start_date')
            );
        }

        if ($request->filled('end_date')) {
            $documentsQuery->whereDate(
                'd.entrydate',
                '<=',
                $request->input('end_date')
            );
        }
    }

    if ($section === 'received-docs') {
        $documentsQuery->where('d.classification', 'False');
        $applyStatusFilter($documentsQuery, 'received');

        if ($request->filled('keeper')) {
            $documentsQuery->whereRaw(
                'COALESCE(dist.idmapagency, assignment.idmapagency, d.IDkeeper) = ?',
                [(int) $request->input('keeper')]
            );
        }

        if ($request->filled('doc_type')) {
            $documentsQuery->where(
                'd.IDdoctype',
                $request->input('doc_type')
            );
        }
    } elseif ($section === 'pending-docs') {
        $applyStatusFilter($documentsQuery, 'for_receiving');
    } elseif ($section === 'pending-docs-07') {
        $applyStatusFilter($documentsQuery, 'for_receiving');
        $documentsQuery->where('d.IDdocstatus', 7);
    } elseif ($section === 'sent-docs') {
        $documentsQuery->whereNotNull('dist.distdate');
        $applyNotPulled($documentsQuery);
    } elseif ($section === 'pulled-out-docs') {
        $applyStatusFilter($documentsQuery, 'pulled');
    }

    if ($filter === 'for-receiving') {
        $applyStatusFilter($documentsQuery, 'for_receiving');
    } elseif (in_array($filter, ['received', 'collab-received', 'for-action'], true)) {
        $applyStatusFilter($documentsQuery, 'received');
    } elseif (in_array($filter, ['addressed', 'in-progress', 'completed'], true)) {
        $applyStatusFilter($documentsQuery, 'addressed');
    } elseif ($filter === 'returned') {
        if ($currentRights !== '3') {
            $documentsQuery->whereRaw('1 = 0');
        } else {
            $applyStatusFilter($documentsQuery, 'returned');
        }
    } elseif ($filter === '15days') {
        $documentsQuery
            ->whereNotNull('d.entrydate')
            ->whereDate(
                'd.entrydate',
                '<=',
                now()->subDays(15)->toDateString()
            );
    }

    if ($search !== '') {
        $searchLike = '%' . $search . '%';
        $lowerSearchLike = '%' . strtolower($search) . '%';

        $documentsQuery->where(function ($query) use (
            $searchLike,
            $lowerSearchLike,
            $displayDocumentNumberExpression,
            $workflowStatusExpression
        ) {
            $query
                ->whereRaw(
                    '(' . $displayDocumentNumberExpression . ') LIKE ?',
                    [$searchLike]
                )
                ->orWhere('d.subject', 'like', $searchLike)
                ->orWhere('d.regarding', 'like', $searchLike)
                ->orWhere('fromOffice.officename', 'like', $searchLike)
                ->orWhere('fromOffice.abbrev', 'like', $searchLike)
                ->orWhere('forOffice.officename', 'like', $searchLike)
                ->orWhere('receiverPersonnel.name', 'like', $searchLike)
                ->orWhereRaw(
                    'LOWER(' . $workflowStatusExpression . ') LIKE ?',
                    [$lowerSearchLike]
                )
                ->orWhereRaw(
                    "DATE_FORMAT(COALESCE(dist.distdate, d.entrydate), '%M %e, %Y') LIKE ?",
                    [$searchLike]
                )
                ->orWhereRaw(
                    "DATE_FORMAT(COALESCE(dist.distdate, d.entrydate), '%Y-%m-%d') LIKE ?",
                    [$searchLike]
                );
        });
    }

    $reportSummary = [
        'total' => 0,
        'for_receiving' => 0,
        'received' => 0,
        'addressed' => 0,
        'returned' => 0,
        'other' => 0,
    ];

    if ($section === 'reports') {
        $reportBase = clone $documentsQuery;

        $reportSummary['total'] = (clone $reportBase)->count();
        $reportSummary['for_receiving'] = $countStatus(
            $reportBase,
            'for_receiving'
        );
        $reportSummary['received'] = $countStatus(
            $reportBase,
            'received'
        );
        $reportSummary['addressed'] = $countStatus(
            $reportBase,
            'addressed'
        );
        $reportSummary['returned'] = $currentRights === '2'
            ? 0
            : $countStatus($reportBase, 'returned');

        $coreCount = $reportSummary['for_receiving']
            + $reportSummary['received']
            + $reportSummary['addressed']
            + $reportSummary['returned'];

        $reportSummary['other'] = max(
            $reportSummary['total'] - $coreCount,
            0
        );
    }

    $documents = $documentsQuery
        ->select($selectColumns)
        ->orderByDesc('d.IDdoc')
        ->orderByRaw("COALESCE(assignment.assignment_suffix, '') ASC")
        ->paginate($perPage)
        ->appends($request->query());

    $documents->getCollection()->transform(function ($doc) {
        /*
         * Keep all display aliases expected by the existing Index.vue.
         */
        $doc->status = $doc->workflow_status;
        $doc->status_label = $doc->workflow_status;
        $doc->current_status = $doc->workflow_status;
        $doc->tracking_no = $doc->display_document_no;
        $doc->document_no = $doc->display_document_no;
        $doc->to_office = $doc->for_office;
        $doc->to_personnel = $doc->receiver_personnel;

        return $doc;
    });

    $officesForDropdown = Schema::hasTable('lu_office')
        ? DB::table('lu_office')
            ->whereNotNull('officename')
            ->whereRaw("TRIM(officename) != ''")
            ->whereRaw("TRIM(officename) != '-'")
            ->orderBy('officename')
            ->get()
        : collect();

    $staffConcernsForDropdown = Schema::hasTable('lu_personnel')
        ? DB::table('lu_personnel as p')
            ->leftJoin('lu_office as o', 'o.ID', '=', 'p.IDoffice')
            ->whereNotNull('p.name')
            ->whereRaw("TRIM(p.name) != ''")
            ->whereRaw("TRIM(p.name) != '-'")
            ->orderBy('p.name')
            ->select([
                'p.ID',
                'p.name',
                'p.IDoffice',
                'o.officename as office_name',
            ])
            ->get()
        : collect();

    return Inertia::render('DTS/Index', [
        'documents' => $documents,
        'stats' => $stats,
        'reportSummary' => $reportSummary,
        'filters' => [
            'search' => $search,
            'per_page' => $perPage,
            'section' => $section,
            'filter' => $filter,
            'scope' => $request->input('scope'),
            'is_all_documents' => $isAllDocumentsSection,
            'year' => $selectedYear,
            'report_classification' => $request->input('report_classification'),
            'report_user' => $request->input('report_user'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ],
        'years' => $availableYears,
        'offices' => $officesForDropdown,
        'docTypes' => Schema::hasTable('lu_doctype')
            ? DB::table('lu_doctype')->orderBy('description')->get()
            : [],
        'classifications' => [
            ['name' => 'Incoming', 'value' => 'False'],
            ['name' => 'Outgoing', 'value' => 'True'],
        ],
        'attachments' => Schema::hasTable('lu_attachment')
            ? DB::table('lu_attachment')->get()
            : [],
        'staffConcerns' => $staffConcernsForDropdown,
        ...$this->dtsNotificationProps(),
        'reminderSessionToken' => hash_hmac(
    'sha256',
    $request->session()->getId(),
    (string) config('app.key')
),
    ]);
}



    public function create()
    {
    $this->ensureCanManageDts();
        return Inertia::render('DTS/Create', [
            ...$this->dtsNotificationProps(),
            'documentTypes' => DtsDocType::orderBy('description')->get(),
            'offices' => DtsOffice::orderBy('officename')->get(),
            'statuses' => DtsDocStatus::orderBy('name')->get(),
            'transactions' => DtsTransaction::orderBy('name')->get(),
        ]);
    }
public function store(Request $request)
{
    $this->ensureCanManageDts();

    $maxPdfKilobytes = 512000;

    $staffIds = $request->input('staff_concern_ids', []);

    if (! is_array($staffIds)) {
        $staffIds = [$staffIds];
    }

    if (empty(array_filter($staffIds)) && $request->filled('staff_concern_id')) {
        $staffIds = [$request->input('staff_concern_id')];
    }

    if (empty(array_filter($staffIds)) && $request->filled('IDkeeper')) {
        $staffIds = [$request->input('IDkeeper')];
    }

    $staffIds = collect($staffIds)
        ->filter(fn ($id) => $id !== null && $id !== '' && (int) $id > 0)
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values()
        ->all();

    $request->merge([
        'classification' => $request->input(
            'classification_id',
            $request->input('classification')
        ),
        'IDdoctype' => $request->input(
            'type_id',
            $request->input('IDdoctype')
        ),
        'IDfrom' => $request->input(
            'from_office_id',
            $request->input('IDfrom')
        ),
        'IDfor' => $request->input(
            'to_office_id',
            $request->input('IDfor')
        ),
        'IDkeepers' => $staffIds,
        /*
         * Keep the legacy field populated for old validation/error rendering.
         */
        'IDkeeper' => $staffIds[0] ?? null,
    ]);

    $validated = $request->validate([
        'classification' => ['required', 'in:False,True'],
        'IDdoctype' => ['required', 'integer', 'exists:lu_doctype,ID'],
        'IDtransac' => ['nullable', 'integer', 'exists:transactions,ID'],
        'IDfrom' => ['required', 'integer', 'exists:lu_office,ID'],
        'IDfor' => ['required', 'integer', 'exists:lu_office,ID'],
        'to_name' => ['nullable', 'string', 'max:255'],
        'from_name' => ['nullable', 'string', 'max:255'],
        'IDdocstatus' => ['nullable', 'integer', 'exists:lu_docstatus,ID'],

        'IDkeepers' => ['required', 'array', 'min:1'],
        'IDkeepers.*' => [
            'required',
            'integer',
            'distinct',
            'exists:lu_personnel,ID',
        ],

        'entry_month' => ['nullable', 'digits_between:1,2'],
        'entry_day' => ['nullable', 'digits_between:1,2'],
        'entry_year' => ['nullable', 'digits_between:2,4'],

        'subject' => ['required', 'string'],
        'regarding' => ['nullable', 'string'],
        'remarks' => ['nullable', 'string'],

        'attachments' => ['nullable', 'array'],
        'attachments.*.type_id' => ['nullable', 'integer'],
        'attachments.*.type_name' => ['nullable', 'string', 'max:255'],
        'attachments.*.file' => [
            'required',
            'file',
            'mimes:pdf',
            'mimetypes:application/pdf',
            "max:{$maxPdfKilobytes}",
        ],
    ], [
        'IDkeepers.required' => 'Select at least one Staff Concern.',
        'IDkeepers.min' => 'Select at least one Staff Concern.',
        'IDkeepers.*.exists' => 'One of the selected staff records was not found.',
    ]);

    $staffIds = collect($validated['IDkeepers'])
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values();

    $isMultipleAssignment = $staffIds->count() > 1;

    if (
        $isMultipleAssignment
        && (
            ! Schema::hasTable('dts_document_assignments')
            || ! Schema::hasColumn('distribution', 'assignment_id')
            || ! Schema::hasColumn('dts_document_remarks', 'assignment_id')
            || ! Schema::hasColumn('docs_transactions', 'assignment_id')
        )
    ) {
        return back()
            ->withErrors([
                'staff_concern_ids' => 'The multiple-assignment database migration is not installed.',
            ])
            ->withInput();
    }

    $personnelRows = DB::table('lu_personnel')
        ->whereIn('ID', $staffIds->all())
        ->select(['ID', 'name', 'IDoffice'])
        ->get()
        ->keyBy(fn ($personnel) => (int) $personnel->ID);

    if ($personnelRows->count() !== $staffIds->count()) {
        return back()
            ->withErrors([
                'staff_concern_ids' => 'One or more selected staff records could not be loaded.',
            ])
            ->withInput();
    }

    $entryDate = now()->format('Y-m-d H:i:s');

    if ($request->filled(['entry_month', 'entry_day', 'entry_year'])) {
        try {
            $month = str_pad((string) $request->entry_month, 2, '0', STR_PAD_LEFT);
            $day = str_pad((string) $request->entry_day, 2, '0', STR_PAD_LEFT);
            $year = (string) $request->entry_year;

            if (strlen($year) === 2) {
                $year = '20' . $year;
            }

            $entryDate = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                "{$year}-{$month}-{$day} " . now()->format('H:i:s')
            )->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            return back()
                ->withErrors([
                    'entry_date' => 'The entry date is invalid.',
                ])
                ->withInput();
        }
    }

    $defaultDocStatusId = $validated['IDdocstatus']
        ?? DB::table('lu_docstatus')
            ->whereIn('name', ['N A', 'N/A', 'Pending', 'For Receiving'])
            ->value('ID')
        ?? DB::table('lu_docstatus')
            ->orderBy('ID')
            ->value('ID')
        ?? 1;

    $creationLockName = 'dts_create_document';
    $lockResult = DB::selectOne(
        'SELECT GET_LOCK(?, 20) AS acquired',
        [$creationLockName]
    );

    if ((int) ($lockResult->acquired ?? 0) !== 1) {
        return back()
            ->withErrors([
                'document' => 'Another user is currently saving a document. Please submit again.',
            ])
            ->withInput();
    }

    try {
        $result = DB::transaction(function () use (
            $request,
            $validated,
            $entryDate,
            $defaultDocStatusId,
            $staffIds,
            $personnelRows,
            $isMultipleAssignment
        ) {
            $lastDocumentId = DB::table('document')
                ->orderByDesc('IDdoc')
                ->lockForUpdate()
                ->value('IDdoc');

            $lastDistributionId = DB::table('distribution')
                ->orderByDesc('IDdist')
                ->lockForUpdate()
                ->value('IDdist');

            $lastDocTransactionId = DB::table('docs_transactions')
                ->orderByDesc('ID')
                ->lockForUpdate()
                ->value('ID');

            $nextDocumentId = ((int) ($lastDocumentId ?? 0)) + 1;
            $nextDistributionId = ((int) ($lastDistributionId ?? 0)) + 1;
            $nextDocTransactionId = ((int) ($lastDocTransactionId ?? 0)) + 1;
            $hasAttachments = count($request->input('attachments', [])) > 0;

            /*
             * Multiple assignment uses one physical document row.
             * IDkeeper remains a legacy single-assignee field, so it is NULL
             * for a true multiple assignment.
             */
            $document = DtsDocument::create([
                'IDdoc' => $nextDocumentId,
                'classification' => $validated['classification'],
                'IDdoctype' => $validated['IDdoctype'],
                'entrydate' => $entryDate,
                'IDfor' => $validated['IDfor'],
                'IDfrom' => $validated['IDfrom'],
                'subject' => $validated['subject'],
                'regarding' => $validated['regarding'] ?? null,
                'IDdocstatus' => $defaultDocStatusId,
                'IDnote' => null,
                'IDuser' => Auth::id(),
                'remarks' => $validated['remarks'] ?? null,
                'IDkeeper' => $isMultipleAssignment
                    ? null
                    : $staffIds->first(),
                'IDprogram_pms' => null,
                'IDproject' => null,
                'IDprogram_prp' => null,
                'IDproposal' => null,
                'IDdocrq' => null,
                'YNdays' => 'False',
                'datecleared' => null,
            ]);

            $documentNameUpdates = [];

            if (Schema::hasColumn('document', 'to_name')) {
                $documentNameUpdates['to_name'] = $validated['to_name'] ?? null;
                $document->to_name = $validated['to_name'] ?? null;
            }

            if (Schema::hasColumn('document', 'from_name')) {
                $documentNameUpdates['from_name'] = $validated['from_name'] ?? null;
                $document->from_name = $validated['from_name'] ?? null;
            }

            /*
             * The old document_group_id/assignment_suffix columns are retained
             * only for historical compatibility. New multiple assignments live
             * exclusively in dts_document_assignments.
             */
            if (Schema::hasColumn('document', 'document_group_id')) {
                $documentNameUpdates['document_group_id'] = null;
            }

            if (Schema::hasColumn('document', 'assignment_suffix')) {
                $documentNameUpdates['assignment_suffix'] = null;
            }

            if (! empty($documentNameUpdates)) {
                DB::table('document')
                    ->where('IDdoc', $document->IDdoc)
                    ->update($documentNameUpdates);
            }

            $assignmentIds = [];

            foreach ($staffIds->values() as $index => $staffId) {
                $staffId = (int) $staffId;
                $personnel = $personnelRows->get($staffId);
                $assignmentId = null;

                if ($isMultipleAssignment) {
                    $assignmentId = (int) DB::table('dts_document_assignments')
                        ->insertGetId([
                            'IDdoc' => $document->IDdoc,
                            'assignment_suffix' => $this->assignmentSuffixFromIndex($index),
                            'idmapagency' => $staffId,
                            'created_by' => Auth::id(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $assignmentIds[] = $assignmentId;
                }

                $distributionData = [
                    'IDdist' => $nextDistributionId++,
                    'IDdoc' => $document->IDdoc,
                    'IDoffice' => $validated['IDfor'],
                    'distdate' => now()->format('Y-m-d H:i:s'),
                    'confirmdate' => null,
                    'confirmuser' => null,
                    'YNreturn' => 'False',
                    'returndate' => null,
                    'IDuser' => Auth::id(),
                    'remarks' => $validated['remarks'] ?? null,
                    'IDparentdist' => null,
                    'YNpulled' => 'False',
                    'idmapagency' => $staffId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('distribution', 'assignment_id')) {
                    $distributionData['assignment_id'] = $assignmentId;
                }

                DB::table('distribution')->insert($distributionData);

                if (! empty($validated['IDtransac'])) {
                    $transactionData = [
                        'ID' => $nextDocTransactionId++,
                        'IDdoc' => $document->IDdoc,
                        'IDtransac' => $validated['IDtransac'],
                        'YNattach' => $hasAttachments ? 'True' : 'False',
                        'IDparentdoc' => null,
                    ];

                    if (Schema::hasColumn('docs_transactions', 'assignment_id')) {
                        $transactionData['assignment_id'] = $assignmentId;
                    }

                    DB::table('docs_transactions')->insert($transactionData);
                }
            }

            foreach ($request->input('attachments', []) as $index => $attachment) {
                $file = $request->file("attachments.{$index}.file");

                if (! $file) {
                    continue;
                }

                $attachmentTypeId = $attachment['type_id'] ?? null;
                $attachmentTypeName = $attachment['type_name'] ?? 'Uploaded File';

                $path = $file->store(
                    "dts/documents/{$document->IDdoc}",
                    'public'
                );

                if (Schema::hasTable('dts_document_files')) {
                    DB::table('dts_document_files')->insert([
                        'IDdoc' => $document->IDdoc,
                        'IDattachment' => $attachmentTypeId ?: 0,
                        'type_name' => $attachmentTypeName,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => basename($path),
                        'path' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'uploaded_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return [
                'document' => $document,
                'first_assignment_id' => $assignmentIds[0] ?? null,
                'assignment_count' => $staffIds->count(),
            ];
        }, 3);
    } finally {
        DB::selectOne(
            'SELECT RELEASE_LOCK(?) AS released',
            [$creationLockName]
        );
    }

    /** @var \App\Models\DtsDocument $document */
    $document = $result['document'];
    $firstAssignmentId = $result['first_assignment_id'];

    $this->recordDtsActivity(
        'created document',
        'Created document #' . $document->IDdoc . ': ' . ($document->subject ?? 'No subject'),
        (int) $document->IDdoc,
        [
            'subject' => $document->subject ?? null,
            'classification' => $document->classification ?? null,
            'assignment_count' => $result['assignment_count'],
            'staff_ids' => $staffIds->all(),
        ]
    );

    $routeParameters = ['id' => $document->IDdoc];

    if ($firstAssignmentId) {
        $routeParameters['assignment_id'] = $firstAssignmentId;
    }

    $successMessage = $firstAssignmentId
        ? 'Document saved successfully as DTS - '
            . $document->IDdoc
            . ' with '
            . $result['assignment_count']
            . ' staff assignments.'
        : 'Document saved successfully as DTS - ' . $document->IDdoc . '.';

    return redirect()
        ->route('dts.show', $routeParameters)
        ->with('success', $successMessage);
}
public function show(Request $request, $id)
{
    $document = DtsDocument::query()
        ->with([
            'docType',
            'status',
            'fromOffice',
            'forOffice',
            'distributions.office',
            'docTransactions.transaction',
        ])
        ->where('IDdoc', $id)
        ->firstOrFail();

    $requestedAssignmentId = $this->requestedAssignmentId($request);
    $assignment = $this->resolveDocumentAssignment(
        (int) $document->IDdoc,
        $requestedAssignmentId
    );
    $assignmentId = $assignment ? (int) $assignment->id : null;

    $trueValues = ['True', 'true', 'Y', 'y', '1', 1];

    $hasManualCompletionColumnsForShow = Schema::hasColumn('document', 'is_completed')
        && Schema::hasColumn('document', 'completed_at');

    $completionSelect = [
        DB::raw(
            $hasManualCompletionColumnsForShow
                ? '0 as legacy_completed'
                : 'CASE WHEN IDdocstatus = 6 THEN 1 ELSE 0 END as legacy_completed'
        ),
        DB::raw(Schema::hasColumn('document', 'is_completed') ? 'COALESCE(is_completed, 0) as is_completed' : '0 as is_completed'),
        DB::raw(Schema::hasColumn('document', 'completed_at') ? 'completed_at' : 'datecleared as completed_at'),
        DB::raw(Schema::hasColumn('document', 'completed_by') ? 'completed_by' : 'NULL as completed_by'),
    ];

    $completionData = DB::table('document')
        ->where('IDdoc', $document->IDdoc)
        ->select($completionSelect)
        ->first();

    $isDocumentCompletedForSummary = $assignment
        ? false
        : (
            $hasManualCompletionColumnsForShow
                ? (
                    ! empty($completionData?->is_completed)
                    || ! empty($completionData?->completed_at)
                )
                : ! empty($completionData?->legacy_completed)
        );

    $completedByName = null;

    if (! empty($completionData?->completed_by) && Schema::hasTable('username')) {
        $completedByName = DB::table('username')
            ->where('ID', $completionData->completed_by)
            ->value('name');
    }

    abort_unless(
        $this->viewerCanAccessDocument(
            (int) $document->IDdoc,
            $assignmentId
        ),
        403
    );

    $uploadedAttachments = Schema::hasTable('dts_document_files')
        ? DB::table('dts_document_files')
            ->leftJoin('username as fileUser', 'fileUser.ID', '=', 'dts_document_files.uploaded_by')
            ->where('dts_document_files.IDdoc', $document->IDdoc)
            ->orderByDesc('dts_document_files.id')
            ->select([
                'dts_document_files.id',
                'dts_document_files.IDdoc',
                'dts_document_files.IDattachment',
                'dts_document_files.type_name',
                'dts_document_files.original_name',
                'dts_document_files.stored_name',
                'dts_document_files.path',
                'dts_document_files.mime_type',
                'dts_document_files.size',
                'dts_document_files.uploaded_by',
                'dts_document_files.created_at',
                DB::raw("COALESCE(NULLIF(TRIM(fileUser.name), ''), NULLIF(TRIM(fileUser.loginname), ''), CONCAT('Account #', fileUser.ID)) as uploaded_by_name"),
            ])
            ->get()
            ->map(function ($file) {
                return [
                    'id' => $file->id,
                    'IDattachment' => $file->IDattachment,
                    'type_name' => $file->type_name,
                    'original_name' => $file->original_name,
                    'stored_name' => $file->stored_name,
                    'path' => $file->path,

                   
                    'url' => route('dts.files.view', ['file' => $file->id]),

                    'mime_type' => $file->mime_type,
                    'size' => $file->size,
                    'uploaded_by' => $file->uploaded_by,
                    'uploaded_by_name' => $file->uploaded_by_name,
                    'created_at' => $file->created_at,
                ];
            })
        : collect();

    $staffConcerns = Schema::hasTable('lu_personnel')
        ? DB::table('lu_personnel')
            ->whereNotNull('name')
            ->whereRaw("TRIM(name) != ''")
            ->orderBy('name')
            ->get()
        : collect();

    $remarksHistory = collect();

    if (Schema::hasTable('dts_document_remarks')) {
        $remarkSelect = [
            'dts_document_remarks.id',
            'dts_document_remarks.IDdoc',
            DB::raw(
                Schema::hasColumn('dts_document_remarks', 'assignment_id')
                    ? 'dts_document_remarks.assignment_id'
                    : 'NULL as assignment_id'
            ),
            'dts_document_remarks.remarks',
            'dts_document_remarks.created_by',
            'dts_document_remarks.created_at',
            DB::raw("COALESCE(NULLIF(TRIM(remarkUser.name), ''), NULLIF(TRIM(remarkUser.loginname), ''), CONCAT('Account #', remarkUser.ID)) as created_by_name"),
        ];

        if (Schema::hasColumn('dts_document_remarks', 'action_type')) {
            $remarkSelect[] = 'dts_document_remarks.action_type';
        } else {
            $remarkSelect[] = DB::raw("'remark' as action_type");
        }

        if (Schema::hasColumn('dts_document_remarks', 'action_type_id')) {
            $remarkSelect[] = 'dts_document_remarks.action_type_id';
            $remarkSelect[] = 'actionTypeList.name as action_name';
            $remarkSelect[] = DB::raw(
                Schema::hasColumn('dts_document_remarks', 'action_label')
                    ? 'dts_document_remarks.action_label as action_label'
                    : 'NULL as action_label'
            );
        } else {
            $remarkSelect[] = DB::raw('NULL as action_type_id');
            $remarkSelect[] = DB::raw('NULL as action_name');
            $remarkSelect[] = DB::raw('NULL as action_label');
        }

        $remarksHistoryQuery = DB::table('dts_document_remarks')
            ->leftJoin('username as remarkUser', 'remarkUser.ID', '=', 'dts_document_remarks.created_by');

        if (Schema::hasColumn('dts_document_remarks', 'action_type_id') && Schema::hasTable('dts_action_types')) {
            $remarksHistoryQuery
                ->leftJoin('dts_action_types as actionTypeList', 'actionTypeList.id', '=', 'dts_document_remarks.action_type_id');
        }

        $remarksHistoryQuery
            ->where('dts_document_remarks.IDdoc', $document->IDdoc);

        if (Schema::hasColumn('dts_document_remarks', 'assignment_id')) {
            $this->applyAssignmentColumnScope(
                $remarksHistoryQuery,
                'dts_document_remarks.assignment_id',
                $assignmentId
            );
        }

        $remarksHistory = $remarksHistoryQuery
            ->orderByDesc('dts_document_remarks.created_at')
            ->select($remarkSelect)
            ->get();
    }

    $documentCreatorName = null;

    if (Schema::hasTable('username') && ! empty($document->IDuser)) {
        $documentCreatorName = DB::table('username')
            ->where('ID', $document->IDuser)
            ->selectRaw("COALESCE(NULLIF(TRIM(name), ''), NULLIF(TRIM(loginname), ''), CONCAT('Account #', ID)) as display_name")
            ->value('display_name');
    }

    $distributionRowsForHistory = DB::table('distribution as dist')
        ->leftJoin('lu_office as office', 'office.ID', '=', 'dist.IDoffice')
        ->leftJoin('username as transferUser', 'transferUser.ID', '=', 'dist.IDuser')
        ->leftJoin('username as receiveUser', 'receiveUser.ID', '=', 'dist.confirmuser')
        ->leftJoin('distribution as parentDist', function ($join) {
            /*
             * A Return to Admin creates a child distribution whose parent row
             * is marked returned. We expose the parent flags so Action History
             * can distinguish that automatic routing row from a real Transfer.
             */
            $join->on('parentDist.IDdist', '=', 'dist.IDparentdist')
                ->on('parentDist.IDdoc', '=', 'dist.IDdoc');

            if (Schema::hasColumn('distribution', 'assignment_id')) {
                $join->whereRaw(
                    'parentDist.assignment_id <=> dist.assignment_id'
                );
            }
        })
        ->leftJoin('distribution as returnChildDist', function ($join) {
            /*
             * The returned parent row's confirmuser is the safest source
             * for who returned it because that user received the document.
             * The child row IDuser is only a fallback.
             */
            $join->on('returnChildDist.IDparentdist', '=', 'dist.IDdist')
                ->on('returnChildDist.IDdoc', '=', 'dist.IDdoc');

            if (Schema::hasColumn('distribution', 'assignment_id')) {
                $join->whereRaw(
                    'returnChildDist.assignment_id <=> dist.assignment_id'
                );
            }
        })
        ->leftJoin('username as returnUser', 'returnUser.ID', '=', 'returnChildDist.IDuser')
        ->leftJoin('username as returnConfirmUser', 'returnConfirmUser.ID', '=', 'dist.confirmuser')
        ->leftJoin('lu_personnel as targetPersonnel', 'targetPersonnel.ID', '=', 'dist.idmapagency')
        ->where('dist.IDdoc', $document->IDdoc);

    if (Schema::hasColumn('distribution', 'assignment_id')) {
        $this->applyAssignmentColumnScope(
            $distributionRowsForHistory,
            'dist.assignment_id',
            $assignmentId
        );
    }

    $distributionRowsForHistory = $distributionRowsForHistory
        ->orderBy('dist.IDdist')
        ->select([
            'dist.IDdist',
            'dist.IDdoc',
            DB::raw(
                Schema::hasColumn('distribution', 'assignment_id')
                    ? 'dist.assignment_id'
                    : 'NULL as assignment_id'
            ),
            'dist.IDoffice',
            'dist.IDuser',
            'dist.IDparentdist',
            'dist.idmapagency as target_personnel_id',
            'dist.distdate',
            'dist.confirmdate',
            'dist.confirmuser',
            'dist.YNreturn',
            'dist.returndate',
            'dist.YNpulled',
            'dist.remarks',
            'parentDist.YNreturn as parent_YNreturn',
            'parentDist.returndate as parent_returndate',
            DB::raw('COALESCE(dist.confirmuser, returnChildDist.IDuser) as returned_by'),
            'office.officename as office_name',
            'targetPersonnel.name as target_personnel_name',
            DB::raw("COALESCE(NULLIF(TRIM(transferUser.name), ''), NULLIF(TRIM(transferUser.loginname), ''), CONCAT('Account #', transferUser.ID)) as transferred_by_name"),
            DB::raw("COALESCE(NULLIF(TRIM(receiveUser.name), ''), NULLIF(TRIM(receiveUser.loginname), ''), CONCAT('Account #', receiveUser.ID)) as received_by_name"),
            DB::raw("COALESCE(NULLIF(TRIM(returnConfirmUser.name), ''), NULLIF(TRIM(returnConfirmUser.loginname), ''), NULLIF(TRIM(returnUser.name), ''), NULLIF(TRIM(returnUser.loginname), ''), CONCAT('Account #', COALESCE(dist.confirmuser, returnChildDist.IDuser))) as returned_by_name"),
        ])
        ->get();

    /*
     * Entry timestamp is used to ignore legacy/unrelated records with the same
     * numeric IDdoc but dated before this newly created document.
     */
    $documentEntryTimestamp = ! empty($document->entrydate)
        ? strtotime((string) $document->entrydate)
        : null;

    $distributions = $distributionRowsForHistory
        ->filter(function ($distribution) use ($documentEntryTimestamp) {
            if (! $documentEntryTimestamp || empty($distribution->distdate)) {
                return true;
            }

            $distributionTimestamp = strtotime((string) $distribution->distdate);

            if (! $distributionTimestamp) {
                return true;
            }

            return $distributionTimestamp >= $documentEntryTimestamp;
        })
        ->sortByDesc('IDdist')
        ->values()
        ->map(function ($distribution) use ($trueValues) {
            $isReturnChild = ! empty($distribution->IDparentdist)
                && (
                    in_array(
                        (string) ($distribution->parent_YNreturn ?? ''),
                        array_map('strval', $trueValues),
                        true
                    )
                    || ! empty($distribution->parent_returndate)
                );

            return [
                'IDdist' => $distribution->IDdist,
                'IDdoc' => $distribution->IDdoc,
                'assignment_id' => $distribution->assignment_id ?? null,
                'IDoffice' => $distribution->IDoffice,
                'IDuser' => $distribution->IDuser,
                'IDparentdist' => $distribution->IDparentdist,
                'target_personnel_id' => $distribution->target_personnel_id,
                'target_personnel_name' => $distribution->target_personnel_name,
                'office' => $distribution->office_name,
                'distdate' => $distribution->distdate,
                'confirmdate' => $distribution->confirmdate,
                'confirmuser' => $distribution->confirmuser,
                'YNreturn' => $distribution->YNreturn,
                'returndate' => $distribution->returndate,
                'YNpulled' => $distribution->YNpulled,
                'remarks' => $distribution->remarks,
                'parent_YNreturn' => $distribution->parent_YNreturn,
                'parent_returndate' => $distribution->parent_returndate,
                'is_return_child' => $isReturnChild,
                'transferred_by_name' => $distribution->transferred_by_name,
                'received_by_name' => $distribution->received_by_name,
                'returned_by' => $distribution->returned_by,
                'returned_by_name' => $distribution->returned_by_name,
            ];
        });

    $latestDistributionForSummary = $distributionRowsForHistory->sortByDesc('IDdist')->first();

    $isLatestReturned = $latestDistributionForSummary
        ? (
            in_array((string) ($latestDistributionForSummary->YNreturn ?? ''), array_map('strval', $trueValues), true)
            || ! empty($latestDistributionForSummary->returndate)
        )
        : false;

    $isLatestPulled = $latestDistributionForSummary
        ? in_array((string) ($latestDistributionForSummary->YNpulled ?? ''), array_map('strval', $trueValues), true)
        : false;

    $hasSelectedActionForSummary = false;

    if (
        Schema::hasTable('dts_document_remarks')
        && Schema::hasColumn('dts_document_remarks', 'action_type')
    ) {
        $selectedActionSummaryQuery = DB::table('dts_document_remarks')
            ->where('IDdoc', $document->IDdoc)
            ->where('action_type', 'action_taken');

        if (Schema::hasColumn('dts_document_remarks', 'assignment_id')) {
            $this->applyAssignmentColumnScope(
                $selectedActionSummaryQuery,
                'assignment_id',
                $assignmentId
            );
        }

        if (! empty($latestDistributionForSummary?->distdate)) {
            $selectedActionSummaryQuery->where(
                'created_at',
                '>=',
                $latestDistributionForSummary->distdate
            );
        }

        $hasSelectedActionForSummary = $selectedActionSummaryQuery->exists();
    }

    $returnParentForSummary = null;

    if ($latestDistributionForSummary && ! empty($latestDistributionForSummary->IDparentdist)) {
        $returnParentForSummaryQuery = DB::table('distribution')
            ->where('IDdist', $latestDistributionForSummary->IDparentdist)
            ->where('IDdoc', $document->IDdoc);

        if (Schema::hasColumn('distribution', 'assignment_id')) {
            $this->applyAssignmentColumnScope(
                $returnParentForSummaryQuery,
                'assignment_id',
                $assignmentId
            );
        }

        $returnParentForSummary = $returnParentForSummaryQuery->first();
    }

    $isCurrentReturnChild = $returnParentForSummary
        && (
            in_array(
                (string) ($returnParentForSummary->YNreturn ?? ''),
                array_map('strval', $trueValues),
                true
            )
            || ! empty($returnParentForSummary->returndate)
        );

    $isPendingReturnToAdmin = $isCurrentReturnChild
        && empty($latestDistributionForSummary?->confirmdate);

    $returnActorIdForSummary = $returnParentForSummary?->confirmuser
        ?? $latestDistributionForSummary?->IDuser
        ?? null;

    $wasReturnedByCurrentRoleTwo = $this->currentUserRights() === '2'
        && ! empty($returnActorIdForSummary)
        && (string) $returnActorIdForSummary === (string) ($this->currentUserId() ?? '');

    $currentWorkflowStatus = 'Pending';

    if ($isLatestPulled) {
        $currentWorkflowStatus = 'Pulled Out';
    } elseif ($isPendingReturnToAdmin && $wasReturnedByCurrentRoleTwo) {
        /* Role 2 who performed Return to Admin. */
        $currentWorkflowStatus = 'Returned';
    } elseif ($isLatestReturned) {
        /* Legacy fallback when the latest row itself is marked returned. */
        $currentWorkflowStatus = 'Returned';
    } elseif (
        $isDocumentCompletedForSummary
        || ($hasSelectedActionForSummary && ! empty($latestDistributionForSummary?->confirmdate))
    ) {
        $currentWorkflowStatus = 'Addressed';
    } elseif (! empty($latestDistributionForSummary?->confirmdate)) {
        /* Role 3/Admin after Receive. */
        $currentWorkflowStatus = 'Received';
    } elseif (! empty($latestDistributionForSummary?->distdate)) {
        /* Role 3/Admin before Receive, including a pending return child. */
        $currentWorkflowStatus = 'For Receiving';
    } elseif ((int) ($document->IDdocstatus ?? 0) === 7) {
        $currentWorkflowStatus = 'Pending 07';
    } elseif (
        ! $hasManualCompletionColumnsForShow
        && (int) ($document->IDdocstatus ?? 0) === 6
    ) {
        /*
         * Legacy fallback only for databases without the manual completion
         * columns. New databases must use Mark as Completed.
         */
        $currentWorkflowStatus = 'Addressed';
    }

    $latestTransferDate = ! empty($latestDistributionForSummary?->distdate)
        ? Carbon::parse($latestDistributionForSummary->distdate)
        : null;

    $receiveDueDate = $latestTransferDate
        ? $latestTransferDate->copy()->addDays(7)
        : null;

    $statusSummary = [
        'current_status' => $currentWorkflowStatus,
        'current_office' => $latestDistributionForSummary?->office_name ?? $document->forOffice?->officename,
        'transferred_at' => $latestDistributionForSummary?->distdate,
        'transferred_by' => $latestDistributionForSummary?->transferred_by_name
            ?? ($latestDistributionForSummary?->IDuser ? 'Account #' . $latestDistributionForSummary->IDuser : null),
        'received_at' => $latestDistributionForSummary?->confirmdate,
        'received_by' => $latestDistributionForSummary?->received_by_name
            ?? ($latestDistributionForSummary?->confirmuser ? 'Account #' . $latestDistributionForSummary->confirmuser : null),
        'returned_at' => $latestDistributionForSummary?->returndate,
        'is_completed' => $isDocumentCompletedForSummary,
        'completed_at' => $completionData?->completed_at,
        'completed_by' => $completedByName
            ?? (! empty($completionData?->completed_by) ? 'Account #' . $completionData->completed_by : null),
        'receive_due_at' => $receiveDueDate ? $receiveDueDate->format('Y-m-d H:i:s') : null,
        'days_since_transfer' => $latestTransferDate ? $latestTransferDate->diffInDays(now()) : null,
        'is_overdue' => (
            $currentWorkflowStatus === 'For Receiving'
            && $receiveDueDate
            && now()->greaterThanOrEqualTo($receiveDueDate)
        ),
    ];

    /*
     * SUPER STRICT ACTION HISTORY FIX:
     *
     * The modal must only show history that belongs to THIS document.
     * This payload is built only from queries filtered by:
     * - distribution.IDdoc = $document->IDdoc
     * - dts_document_remarks.IDdoc = $document->IDdoc
     * - dts_document_files.IDdoc = $document->IDdoc
     *
     * It does NOT use general activity_logs.
     * It also skips the first distribution as "Transferred Document" because that
     * first distribution is created together with a new document.
     */
    $actionHistory = collect();

    $addHistory = function (
        string $type,
        string $title,
        ?string $description,
        $date,
        ?string $actor = null,
        ?string $office = null,
        ?string $remarks = null,
        array $files = []
    ) use (&$actionHistory, $document) {
        if (empty($date)) {
            return;
        }

        /*
         * Receive has no remarks textbox.
         * Even if a caller accidentally passes distribution.remarks,
         * the Received Document row must not display any remarks.
         */
        if (strtolower(trim($type)) === 'received') {
            $remarks = null;
        }

        $actionHistory->push([
            'id' => $type . '-' . $document->IDdoc . '-' . md5($title . '|' . $date . '|' . ($actor ?? '') . '|' . ($office ?? '')),
            'IDdoc' => (int) $document->IDdoc,
            'document_id' => (int) $document->IDdoc,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'date' => $date,
            'actor' => $actor ?: 'System',
            'office' => $office,
            'remarks' => $remarks,
            'files' => $files,
        ]);
    };

    $addHistory(
        'created',
        'Document Created',
        'Document was encoded in the tracking system.',
        $document->entrydate,
        $documentCreatorName ?? (! empty($document->IDuser) ? 'Account #' . $document->IDuser : 'System'),
        $document->fromOffice?->officename,
        $document->remarks
    );

    $distributionRowsForHistory
        ->filter(function ($distRow) use ($documentEntryTimestamp) {
            if (! $documentEntryTimestamp || empty($distRow->distdate)) {
                return true;
            }

            $distributionTimestamp = strtotime((string) $distRow->distdate);

            if (! $distributionTimestamp) {
                return true;
            }

            return $distributionTimestamp >= $documentEntryTimestamp;
        })
        ->values()
        ->each(function ($distRow, $index) use ($addHistory, $trueValues) {
            $transferredBy = $distRow->transferred_by_name
                ?? ($distRow->IDuser ? 'Account #' . $distRow->IDuser : null);

            $receivedBy = $distRow->received_by_name
                ?? ($distRow->confirmuser ? 'Account #' . $distRow->confirmuser : null);

            $returnedBy = $distRow->returned_by_name
                ?? ($distRow->returned_by ? 'Account #' . $distRow->returned_by : null)
                ?? $transferredBy;

            /*
             * Do not label the automatic child distribution created by
             * Return to Admin as a Transfer. Its parent is the row marked
             * returned. A genuine Transfer still has a normal parent row.
             */
            $isReturnChild = ! empty($distRow->IDparentdist)
                && (
                    in_array(
                        (string) ($distRow->parent_YNreturn ?? ''),
                        array_map('strval', $trueValues),
                        true
                    )
                    || ! empty($distRow->parent_returndate)
                );

            if (! empty($distRow->IDparentdist) && ! $isReturnChild) {
                $transferTarget = $distRow->target_personnel_name
                    ? $distRow->target_personnel_name . ($distRow->office_name ? ' — ' . $distRow->office_name : '')
                    : ($distRow->office_name ?? 'Office #' . $distRow->IDoffice);

                $addHistory(
                    'transferred',
                    'Transferred Document',
                    'Document was transferred to ' . $transferTarget . '.',
                    $distRow->distdate,
                    $transferredBy,
                    $distRow->office_name,
                    $distRow->remarks
                );
            }

            $addHistory(
                'received',
                'Received Document',
                'Document was tagged as received.',
                $distRow->confirmdate,
                $receivedBy,
                $distRow->office_name,
                null
            );

            $isReturned = in_array((string) ($distRow->YNreturn ?? ''), array_map('strval', $trueValues), true)
                || ! empty($distRow->returndate);

            if ($isReturned) {
                $addHistory(
                    'returned',
                    'Returned Document',
                    'Document was returned.',
                    $distRow->returndate ?: $distRow->distdate,
                    $returnedBy,
                    $distRow->office_name,
                    $distRow->remarks
                );
            }

            $isPulled = in_array((string) ($distRow->YNpulled ?? ''), array_map('strval', $trueValues), true);

            if ($isPulled) {
                $addHistory(
                    'pulled',
                    'Pulled Out Document',
                    'Document transfer was pulled out.',
                    $distRow->distdate,
                    $transferredBy,
                    $distRow->office_name,
                    $distRow->remarks
                );
            }
        });

    foreach ($remarksHistory as $remarkItem) {
        $remarkActionType = strtolower(trim((string) ($remarkItem->action_type ?? 'remark')));
        $remarkActor = $remarkItem->created_by_name ?? ($remarkItem->created_by ? 'Account #' . $remarkItem->created_by : null);

        if (in_array($remarkActionType, ['action_saved', 'action_taken'], true)) {
            $actionLabel = trim((string) ($remarkItem->action_label ?? ''));
            $actionName = trim((string) ($remarkItem->action_name ?? ''));
            $actionTarget = $actionLabel !== ''
                ? $actionLabel
                : ($actionName !== '' ? $actionName : 'selected action');
            $isClosedAction = $remarkActionType === 'action_taken';

            $addHistory(
                'action',
                $isClosedAction ? 'Action Closed' : 'Action Saved',
                ($isClosedAction ? 'Closed action: ' : 'Saved action: ') . $actionTarget . '.',
                $remarkItem->created_at,
                $remarkActor,
                null,
                $remarkItem->remarks
            );

            continue;
        }

        $addHistory(
            'remark',
            'Added Remark',
            'A remark was added to this document.',
            $remarkItem->created_at,
            $remarkActor,
            null,
            $remarkItem->remarks
        );
    }

    foreach ($uploadedAttachments as $fileItem) {
        $isReattached = ($fileItem['type_name'] ?? null) === 'Re-attached File';

        /*
         * Do not show initial uploaded files in Action History.
         * For a newly created document, attachments are part of Document Created.
         * Only show files that were re-attached later.
         */
        if (! $isReattached) {
            continue;
        }

        $addHistory(
            'reattached',
            'Re-attached File',
            'File was re-attached: ' . ($fileItem['original_name'] ?? $fileItem['stored_name'] ?? 'Uploaded file'),
            $fileItem['created_at'] ?? null,
            $fileItem['uploaded_by_name'] ?? (! empty($fileItem['uploaded_by']) ? 'Account #' . $fileItem['uploaded_by'] : null),
            null,
            null,
            [$fileItem]
        );
    }

    if ($isDocumentCompletedForSummary) {
        $addHistory(
            'completed',
            'Completed Document',
            'Document was officially marked as completed.',
            $completionData?->completed_at,
            $completedByName
                ?? (! empty($completionData?->completed_by) ? 'Account #' . $completionData->completed_by : 'System'),
            null,
            null
        );
    }

    $actionHistory = $actionHistory
        ->filter(function ($item) use ($document) {
            return (int) ($item['IDdoc'] ?? 0) === (int) $document->IDdoc;
        })
        ->map(function ($item) {
            /*
             * Final safety check:
             * Received Document rows must never carry remarks.
             */
            if (strtolower(trim((string) ($item['type'] ?? ''))) === 'received') {
                $item['remarks'] = null;
            }

            return $item;
        })
        ->sortByDesc(function ($item) {
            return strtotime((string) ($item['date'] ?? '')) ?: 0;
        })
        ->values();


    $statusFlags = Schema::hasTable('dts_document_status_flags')
        ? DB::table('dts_document_status_flags')
            ->where('IDdoc', $document->IDdoc)
            ->first()
        : null;

    $actionTypesForDropdown = Schema::hasTable('dts_action_types')
        ? DB::table('dts_action_types')
            ->whereRaw("LOWER(TRIM(name)) IN ('address', 'addressed')")
            ->orderBy('name')
            ->get()
        : collect();

    /*
     * To/From typed names:
     * These are separate from the office names. The old system displayed
     * personnel names for To/From, while the new DTS stores office IDs in
     * IDfor/IDfrom and stores the displayed names in to_name/from_name.
     */
    $documentToName = null;
    $documentFromName = null;

    if (Schema::hasColumn('document', 'to_name') || Schema::hasColumn('document', 'from_name')) {
        $nameSelect = [];

        $nameSelect[] = Schema::hasColumn('document', 'to_name')
            ? 'to_name'
            : DB::raw('NULL as to_name');

        $nameSelect[] = Schema::hasColumn('document', 'from_name')
            ? 'from_name'
            : DB::raw('NULL as from_name');

        $documentNames = DB::table('document')
            ->where('IDdoc', $document->IDdoc)
            ->select($nameSelect)
            ->first();

        $toNameValue = trim((string) ($documentNames->to_name ?? ''));
        $fromNameValue = trim((string) ($documentNames->from_name ?? ''));

        $documentToName = $toNameValue !== '' ? $toNameValue : null;
        $documentFromName = $fromNameValue !== '' ? $fromNameValue : null;
    }

    /*
     * Role 2 completion workflow:
     * - the document is currently tagged to the logged-in personnel;
     * - the latest distribution is already received;
     * - at least one Select Action/action_taken exists;
     * - the document is not yet completed.
     */
    $canCompleteCurrentDocumentForViewer = $assignmentId === null
        && $this->currentUserRights() === '2'
        && ! $isDocumentCompletedForSummary
        && $this->documentIsTaggedToViewer(
            (int) $document->IDdoc,
            $assignmentId
        )
        && ! empty($latestDistributionForSummary?->confirmdate)
        && $hasSelectedActionForSummary;

    /*
     * Receive belongs to the latest distribution and must remain available for
     * a newly returned/transferred document even when the document has an old
     * action_taken record from a previous workflow cycle.
     */
    $latestDistributionAwaitingReceive = $latestDistributionForSummary
        && empty($latestDistributionForSummary->confirmdate)
        && ! $isLatestPulled;

    $canUseReceiveAction = ! $isDocumentCompletedForSummary
        && $latestDistributionAwaitingReceive;

    /* Other actions are blocked only by a Final Action in the CURRENT cycle. */
    $canUseDocumentActions = ! $isDocumentCompletedForSummary
        && ! $hasSelectedActionForSummary;

    $assignmentCount = Schema::hasTable('dts_document_assignments')
        ? DB::table('dts_document_assignments')
            ->where('IDdoc', $document->IDdoc)
            ->count()
        : 0;

    $currentPersonnelId = $latestDistributionForSummary?->target_personnel_id
        ?? $assignment?->idmapagency
        ?? $document->IDkeeper;

    $currentPersonnelName = $staffConcerns
        ->firstWhere('ID', $currentPersonnelId)?->name;

    $displayDocumentNo = $this->workflowReference(
        (int) $document->IDdoc,
        $assignmentId
    );

    return Inertia::render('DTS/Show', [
        ...$this->dtsNotificationProps(),
        'isSuperAdminViewOnly' => $this->isSuperAdminViewOnly(
            (int) $document->IDdoc,
            $assignmentId
        ),
        'canReceiveDts' => $canUseReceiveAction
            && $this->canReceiveDts()
            && $this->viewerCanActOnDocument(
                (int) $document->IDdoc,
                $assignmentId
            ),
        'canTransferDts' => $canUseDocumentActions
            && $this->viewerCanTransferDocument(
                (int) $document->IDdoc,
                $assignmentId
            ),
        'canReattachDts' => $canUseDocumentActions
            && $this->viewerCanReattachDocument(
                (int) $document->IDdoc,
                $assignmentId
            ),
        'canDeleteAttachments' => $this->currentUserRights() === '3'
            && $this->viewerCanAccessDocument(
                (int) $document->IDdoc,
                $assignmentId
            ),
        'canRemarkDts' => $canUseDocumentActions
            && $this->canRemarkDts()
            && $this->viewerCanRemarkDocument(
                (int) $document->IDdoc,
                $assignmentId
            ),
        'canActionTakenDts' => $canUseDocumentActions
            && $this->canRemarkDts()
            && $this->viewerCanActOnDocument(
                (int) $document->IDdoc,
                $assignmentId
            ),
        'canCompleteDts' => $canCompleteCurrentDocumentForViewer,
                'document' => [
                'IDdoc' => $document->IDdoc,
                'document_no' => $displayDocumentNo,
                'display_document_no' => $displayDocumentNo,
                'assignment_id' => $assignmentId,
                'assignment_suffix' => $assignment
                    ? strtoupper(trim((string) $assignment->assignment_suffix))
                    : null,
                'assignment_count' => $assignmentCount,
                'is_multiple_assignment' => $assignmentId !== null,
                'created_by' => $document->IDuser,
                'created_by_name' => $documentCreatorName ?? (! empty($document->IDuser) ? 'Account #' . $document->IDuser : null),

                'classification' => $document->classification,
                'classification_label' => $document->classification === 'True' ? 'Outgoing' : 'Incoming',

                'IDdoctype' => $document->IDdoctype,
                'doctype' => $document->docType?->description,

                'entrydate' => $document->entrydate,

                'IDfor' => $document->IDfor,
                'IDfrom' => $document->IDfrom,
                'to_name' => $documentToName,
                'from_name' => $documentFromName,
                'recipient_name' => $documentToName,
                'sender_name' => $documentFromName,
                'to_person_name' => $documentToName,
                'from_person_name' => $documentFromName,
                'for_office' => $document->forOffice?->officename,
                'from_office' => $document->fromOffice?->officename,

                'subject' => $document->subject,
                'regarding' => $document->regarding,
                'remarks' => $document->remarks,

                'IDkeeper' => $currentPersonnelId,
                'staff_concern' => $currentPersonnelName,
                'assigned_personnel' => $currentPersonnelName,
                'personnel_name' => $currentPersonnelName,

                'IDdocstatus' => $document->IDdocstatus,
                'status' => $document->status?->name,
                'is_completed' => $isDocumentCompletedForSummary,
                'completed_at' => $completionData?->completed_at,
                'completed_by' => $completionData?->completed_by,
                'completed_by_name' => $completedByName
                    ?? (! empty($completionData?->completed_by) ? 'Account #' . $completionData->completed_by : null),

                'attachments' => $uploadedAttachments,
                'remarks_history' => $remarksHistory,
                'distributions' => $distributions,
                'status_summary' => $statusSummary,
                'action_history' => $actionHistory,

            'status_flags' => [
                'acknowledgement_yes_no' => (bool) ($statusFlags->acknowledgement_yes_no ?? false),
                'acknowledgement_spl_action' => (bool) ($statusFlags->acknowledgement_spl_action ?? false),

                'distribution_yes_no' => (bool) ($statusFlags->distribution_yes_no ?? false),
                'distribution_spl_action' => (bool) ($statusFlags->distribution_spl_action ?? false),

                'comments_yes_no' => (bool) ($statusFlags->comments_yes_no ?? false),
                'comments_spl_action' => (bool) ($statusFlags->comments_spl_action ?? false),

                'edit_yes_no' => (bool) ($statusFlags->edit_yes_no ?? false),
                'edit_spl_action' => (bool) ($statusFlags->edit_spl_action ?? false),

                'evaluation_yes_no' => (bool) ($statusFlags->evaluation_yes_no ?? false),
                'evaluation_spl_action' => (bool) ($statusFlags->evaluation_spl_action ?? false),

                'action_yes_no' => (bool) ($statusFlags->action_yes_no ?? false),
                'action_spl_action' => (bool) ($statusFlags->action_spl_action ?? false),
            ],
        ],
        'offices' => Schema::hasTable('lu_office')
            ? DB::table('lu_office')->orderBy('officename')->get()
            : collect(),
        'personnel' => Schema::hasTable('lu_personnel')
            ? DB::table('lu_personnel as p')
                ->leftJoin('lu_office as o', 'o.ID', '=', 'p.IDoffice')
                ->whereNotNull('p.name')
                ->whereRaw("TRIM(p.name) != ''")
                ->whereRaw("TRIM(p.name) != '-'")
                ->orderBy('p.name')
                ->select([
                    'p.ID',
                    'p.name',
                    'p.IDoffice',
                    'o.officename as office_name',
                ])
                ->get()
            : collect(),
        'actionTypes' => $actionTypesForDropdown,
    ]);
}


public function viewFile($file)
{
    $fileRecord = DB::table('dts_document_files')
        ->where('id', $file)
        ->first();

    if (! $fileRecord) {
        abort(404, 'File record not found.');
    }

    $storedPath = str_replace('\\', '/', $fileRecord->path);

    $storedPath = preg_replace('#^(storage/|public/)#', '', $storedPath);

    if (! Storage::disk('public')->exists($storedPath)) {
        abort(404, 'File not found in storage.');
    }

    $absolutePath = Storage::disk('public')->path($storedPath);

    $fileName = $fileRecord->original_name
        ?? $fileRecord->stored_name
        ?? basename($absolutePath);

    $fileName = str_replace('"', '', $fileName);

    return response()->file($absolutePath, [
        'Content-Type' => $fileRecord->mime_type ?: 'application/octet-stream',
        'Content-Disposition' => 'inline; filename="' . $fileName . '"',
    ]);
}
public function receive(Request $request, $id)
{
    $this->ensureCanReceiveDts();

    $assignmentId = $this->requestedAssignmentId($request);
    $this->ensureViewerCanActOnDocument((int) $id, $assignmentId);

    $latestDistribution = $this->latestDistributionForWorkflow(
        (int) $id,
        $assignmentId
    );

    if (! $latestDistribution) {
        return back()->withErrors([
            'receive' => 'No distribution record was found for this assignment.',
        ]);
    }

    if (! empty($latestDistribution->confirmdate)) {
        return back()->withErrors([
            'receive' => 'This assignment is already received.',
        ]);
    }

    if (
        in_array(
            (string) ($latestDistribution->YNpulled ?? ''),
            ['True', 'true', 'Y', 'y', '1'],
            true
        )
    ) {
        return back()->withErrors([
            'receive' => 'This assignment was already pulled out.',
        ]);
    }

    DB::table('distribution')
        ->where('IDdist', $latestDistribution->IDdist)
        ->update([
            'confirmdate' => now()->format('Y-m-d H:i:s'),
            'confirmuser' => Auth::id(),
            'updated_at' => now(),
        ]);

    $reference = $this->workflowReference((int) $id, $assignmentId);

    $this->recordDtsActivity(
        'received document',
        'Received document #' . $reference . '.',
        (int) $id,
        [
            'assignment_id' => $assignmentId,
            'assignment_reference' => $reference,
        ]
    );

    return back()->with('success', 'Document assignment received successfully.');
}
public function pullout(Request $request, $id)
{
    $this->ensureCanManageDts();

    $assignmentId = $this->requestedAssignmentId($request);

    if ($assignmentId !== null) {
        $this->resolveDocumentAssignment((int) $id, $assignmentId);
    }

    $latestDistribution = $this->latestDistributionForWorkflow(
        (int) $id,
        $assignmentId
    );

    if (! $latestDistribution) {
        return back()->withErrors([
            'pullout' => 'No distribution record was found for this assignment.',
        ]);
    }

    if (! empty($latestDistribution->confirmdate)) {
        return back()->withErrors([
            'pullout' => 'This assignment is already received and cannot be pulled out.',
        ]);
    }

    DB::table('distribution')
        ->where('IDdist', $latestDistribution->IDdist)
        ->update([
            'YNpulled' => 'True',
            'updated_at' => now(),
        ]);

    $reference = $this->workflowReference((int) $id, $assignmentId);

    $this->recordDtsActivity(
        'pulled out document',
        'Pulled out document #' . $reference . '.',
        (int) $id,
        [
            'assignment_id' => $assignmentId,
            'assignment_reference' => $reference,
        ]
    );

    return back()->with('success', 'Document assignment pulled out successfully.');
}
public function forward(Request $request, $id)
{
    $this->ensureCanReceiveDts();

    $assignmentId = $this->requestedAssignmentId($request);
    $this->ensureViewerCanTransferDocument((int) $id, $assignmentId);

    $validated = $request->validate([
        'IDpersonnel' => ['required', 'integer', 'exists:lu_personnel,ID'],
        'remarks' => ['required', 'string'],
        'assignment_id' => ['nullable', 'integer'],
    ]);

    $assignment = $assignmentId !== null
        ? $this->resolveDocumentAssignment((int) $id, $assignmentId)
        : null;

    $personnel = DB::table('lu_personnel as p')
        ->leftJoin('lu_office as o', 'o.ID', '=', 'p.IDoffice')
        ->where('p.ID', $validated['IDpersonnel'])
        ->select([
            'p.ID',
            'p.name',
            'p.IDoffice',
            'o.officename as office_name',
        ])
        ->first();

    if (! $personnel || empty($personnel->IDoffice)) {
        return back()->withErrors([
            'IDpersonnel' => 'Selected personnel does not have an assigned office.',
        ]);
    }

    $document = DtsDocument::findOrFail($id);

    DB::transaction(function () use (
        $document,
        $validated,
        $personnel,
        $assignmentId,
        $assignment
    ) {
        $latestDistribution = $this->latestDistributionForWorkflow(
            (int) $document->IDdoc,
            $assignmentId
        );

        if (! $latestDistribution) {
            abort(422, 'No distribution record was found for this assignment.');
        }

        $distributionData = [
            'IDdist' => $this->nextDistributionId(),
            'IDdoc' => $document->IDdoc,
            'IDoffice' => $personnel->IDoffice,
            'distdate' => now()->format('Y-m-d H:i:s'),
            'confirmdate' => null,
            'confirmuser' => null,
            'YNreturn' => 'False',
            'returndate' => null,
            'IDuser' => Auth::id(),
            'remarks' => $validated['remarks'],
            'IDparentdist' => $latestDistribution->IDdist,
            'YNpulled' => 'False',
            'idmapagency' => $personnel->ID,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('distribution', 'assignment_id')) {
            $distributionData['assignment_id'] = $assignmentId;
        }

        DB::table('distribution')->insert($distributionData);

        if ($assignment) {
            DB::table('dts_document_assignments')
                ->where('id', $assignment->id)
                ->where('IDdoc', $document->IDdoc)
                ->update([
                    'idmapagency' => $personnel->ID,
                    'updated_at' => now(),
                ]);
        } else {
            $document->update([
                'IDfor' => $personnel->IDoffice,
                'IDkeeper' => $personnel->ID,
            ]);
        }
    });

    $reference = $this->workflowReference(
        (int) $document->IDdoc,
        $assignmentId
    );

    $this->recordDtsActivity(
        'forwarded document',
        'Forwarded document #' . $reference . ' to '
            . ($personnel->name ?? 'Personnel #' . $validated['IDpersonnel'])
            . '.',
        (int) $document->IDdoc,
        [
            'assignment_id' => $assignmentId,
            'assignment_reference' => $reference,
            'to_personnel_id' => $validated['IDpersonnel'],
            'to_personnel_name' => $personnel->name ?? null,
            'to_office_id' => $personnel->IDoffice,
            'to_office_name' => $personnel->office_name ?? null,
            'remarks' => $validated['remarks'],
        ]
    );

    return back()->with('success', 'Document assignment transferred successfully.');
}
public function returnDocument(Request $request, $id)
{
    abort_unless($this->currentUserRights() === '2', 403);

    $this->ensureCanReceiveDts();

    $assignmentId = $this->requestedAssignmentId($request);
    $this->ensureViewerCanActOnDocument((int) $id, $assignmentId);

    $validated = $request->validate([
        'remarks' => ['required', 'string'],
        'assignment_id' => ['nullable', 'integer'],
    ]);

    $document = DtsDocument::findOrFail($id);
    $assignment = $assignmentId !== null
        ? $this->resolveDocumentAssignment((int) $id, $assignmentId)
        : null;

    $latestDistribution = $this->latestDistributionForWorkflow(
        (int) $document->IDdoc,
        $assignmentId
    );

    if (! $latestDistribution) {
        return back()->withErrors([
            'remarks' => 'No distribution record was found for this assignment.',
        ]);
    }

    if (
        in_array(
            (string) ($latestDistribution->YNreturn ?? ''),
            ['True', 'true', 'Y', 'y', '1'],
            true
        )
    ) {
        return back()->withErrors([
            'remarks' => 'This assignment is already returned.',
        ]);
    }

    $returnTarget = $this->resolveReturnTarget(
        $document,
        $latestDistribution
    );

    if (empty($returnTarget['personnel_id']) || empty($returnTarget['office_id'])) {
        return back()->withErrors([
            'remarks' => 'Unable to return this assignment because the encoder account is not linked to a personnel record.',
        ]);
    }

    DB::transaction(function () use (
        $document,
        $latestDistribution,
        $validated,
        $returnTarget,
        $assignmentId,
        $assignment
    ) {
        DB::table('distribution')
            ->where('IDdist', $latestDistribution->IDdist)
            ->update([
                'YNreturn' => 'True',
                'returndate' => now()->format('Y-m-d H:i:s'),
                'remarks' => $validated['remarks'],
                'updated_at' => now(),
            ]);

        $distributionData = [
            'IDdist' => $this->nextDistributionId(),
            'IDdoc' => $document->IDdoc,
            'IDoffice' => $returnTarget['office_id'],
            'distdate' => now()->format('Y-m-d H:i:s'),
            'confirmdate' => null,
            'confirmuser' => null,
            'YNreturn' => 'False',
            'returndate' => null,
            'IDuser' => Auth::id(),
            'remarks' => $validated['remarks'],
            'IDparentdist' => $latestDistribution->IDdist,
            'YNpulled' => 'False',
            'idmapagency' => $returnTarget['personnel_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('distribution', 'assignment_id')) {
            $distributionData['assignment_id'] = $assignmentId;
        }

        DB::table('distribution')->insert($distributionData);

        if ($assignment) {
            DB::table('dts_document_assignments')
                ->where('id', $assignment->id)
                ->where('IDdoc', $document->IDdoc)
                ->update([
                    'idmapagency' => $returnTarget['personnel_id'],
                    'updated_at' => now(),
                ]);
        } else {
            $document->update([
                'IDfor' => $returnTarget['office_id'],
                'IDkeeper' => $returnTarget['personnel_id'],
            ]);
        }
    });

    $reference = $this->workflowReference(
        (int) $document->IDdoc,
        $assignmentId
    );

    $this->recordDtsActivity(
        'returned document',
        'Returned document #' . $reference . ' to Admin '
            . ($returnTarget['name'] ?? 'document encoder')
            . '.',
        (int) $document->IDdoc,
        [
            'assignment_id' => $assignmentId,
            'assignment_reference' => $reference,
            'to_personnel_id' => $returnTarget['personnel_id'] ?? null,
            'to_personnel_name' => $returnTarget['name'] ?? null,
            'to_office_id' => $returnTarget['office_id'] ?? null,
            'to_office_name' => $returnTarget['office_name'] ?? null,
            'remarks' => $validated['remarks'],
        ]
    );

    return back()->with('success', 'Document assignment returned to Admin successfully.');
}



private function resolveReturnTarget($document, $latestDistribution): array
{
    /*
     * STRICT RETURN RULE:
     * Returned documents must be tagged back to the personnel who encoded/created
     * the document. That personnel becomes the current tagged user, so they will
     * be the one who can receive and take action next.
     */
    $encoderUserId = $document->IDuser
        ?? $document->created_by
        ?? $document->encoded_by
        ?? $document->IDencoder
        ?? null;

    if ($encoderUserId) {
        $encoderTarget = $this->personnelAndOfficeForUser((int) $encoderUserId);

        if (! empty($encoderTarget['personnel_id']) && ! empty($encoderTarget['office_id'])) {
            return [
                ...$encoderTarget,
                'name' => $encoderTarget['name'] ?? 'Document encoder',
            ];
        }
    }

    /*
     * No fallback to previous handler here.
     * If we cannot map the encoder account to lu_personnel, we stop the return
     * so the document will not become office-only/unassigned.
     */
    return [
        'personnel_id' => null,
        'name' => null,
        'office_id' => null,
        'office_name' => null,
    ];
}

private function personnelAndOfficeForUser(int $userId): array
{
    $user = Schema::hasTable('username')
        ? DB::table('username')->where('ID', $userId)->first()
        : null;

    $personnel = null;

    /*
     * Best mapping for DTS:
     * username.idmapagency should point to lu_personnel.ID.
     * Use this first so returned documents are tagged to the exact encoder
     * personnel, not just the encoder's office.
     */
    if ($user && Schema::hasTable('lu_personnel')) {
        foreach ([
            'idmapagency',
            'IDmapagency',
            'IDmapAgency',
            'IDpersonnel',
            'personnel_id',
            'IDkeeper',
            'staff_id',
            'employee_id',
        ] as $column) {
            if (Schema::hasColumn('username', $column) && ! empty($user->{$column})) {
                $mappedPersonnel = DB::table('lu_personnel as p')
                    ->leftJoin('lu_office as o', 'o.ID', '=', 'p.IDoffice')
                    ->where('p.ID', $user->{$column})
                    ->select([
                        'p.ID',
                        'p.name',
                        'p.IDoffice',
                        'o.officename as office_name',
                    ])
                    ->first();

                if ($mappedPersonnel && ! empty($mappedPersonnel->IDoffice)) {
                    return [
                        'personnel_id' => (int) $mappedPersonnel->ID,
                        'name' => $mappedPersonnel->name,
                        'office_id' => (int) $mappedPersonnel->IDoffice,
                        'office_name' => $mappedPersonnel->office_name,
                    ];
                }
            }
        }
    }

    if (Schema::hasTable('lu_personnel')) {
        $personnelQuery = DB::table('lu_personnel as p')
            ->leftJoin('lu_office as o', 'o.ID', '=', 'p.IDoffice')
            ->select([
                'p.ID',
                'p.name',
                'p.IDoffice',
                'o.officename as office_name',
            ]);

        $personnelQuery->where(function ($query) use ($user, $userId) {
            $hasCondition = false;

            foreach (['IDuser', 'user_id', 'IDusername', 'username_id', 'account_id'] as $column) {
                if (Schema::hasColumn('lu_personnel', $column)) {
                    $hasCondition = true;
                    $query->orWhere('p.' . $column, $userId);
                }
            }

            $loginName = trim((string) ($user->loginname ?? $user->username ?? ''));
            $displayName = trim((string) ($user->name ?? ''));

            foreach (['loginname', 'username'] as $column) {
                if ($loginName !== '' && Schema::hasColumn('lu_personnel', $column)) {
                    $hasCondition = true;
                    $query->orWhere('p.' . $column, $loginName);
                }
            }

            if ($loginName !== '' && Schema::hasColumn('lu_personnel', 'name')) {
                $hasCondition = true;
                $query->orWhereRaw('LOWER(TRIM(p.name)) = ?', [strtolower($loginName)]);
            }

            if ($displayName !== '' && Schema::hasColumn('lu_personnel', 'name')) {
                $hasCondition = true;
                $query->orWhereRaw('LOWER(TRIM(p.name)) = ?', [strtolower($displayName)]);
            }

            if (! $hasCondition) {
                $query->whereRaw('1 = 0');
            }
        });

        $personnel = $personnelQuery->first();
    }

    if ($personnel && ! empty($personnel->IDoffice)) {
        return [
            'personnel_id' => (int) $personnel->ID,
            'name' => $personnel->name,
            'office_id' => (int) $personnel->IDoffice,
            'office_name' => $personnel->office_name,
        ];
    }

    foreach (['IDoffice', 'idoffice', 'office_id', 'IDfor', 'IDagency', 'agency_id'] as $column) {
        if ($user && Schema::hasColumn('username', $column) && ! empty($user->{$column})) {
            return [
                'personnel_id' => null,
                'name' => $user->name ?? $user->loginname ?? $user->username ?? 'User #' . $userId,
                'office_id' => (int) $user->{$column},
                'office_name' => DB::table('lu_office')
                    ->where('ID', $user->{$column})
                    ->value('officename'),
            ];
        }
    }

    return [
        'personnel_id' => null,
        'name' => $user->name ?? $user->loginname ?? $user->username ?? 'User #' . $userId,
        'office_id' => null,
        'office_name' => null,
    ];
}


private function nextDistributionId()
{
    return ((int) DtsDistribution::max('IDdist')) + 1;

    }
public function library()
{
    $officesRaw = DB::table('lu_office')
        ->select('ID', 'officename', 'abbrev', 'IDsucs')
        ->orderBy('officename')
        ->get();

    $personnelRaw = DB::table('lu_personnel')
        ->select('ID', 'name', 'IDoffice')
        ->whereNotNull('name')
        ->whereRaw("TRIM(`name`) <> ''")
        ->whereRaw("TRIM(`name`) <> '-'")
        ->orderBy('name')
        ->get();

    $officeMap = $officesRaw->keyBy('ID');

    $personnel = $personnelRaw->map(function ($person) use ($officeMap) {
        $office = $officeMap->get($person->IDoffice);

        return [
            'ID' => $person->ID,
            'personnel_name' => trim($person->name),
            'name' => trim($person->name),
            'IDoffice' => $person->IDoffice,
            'officename' => $office ? $office->officename : 'not applicable',
        ];
    })->values();

    $personnelCounts = $personnelRaw
        ->groupBy('IDoffice')
        ->map(function ($items) {
            return $items->count();
        });

    $offices = $officesRaw->map(function ($office) use ($personnelCounts) {
        return [
            'ID' => $office->ID,
            'officename' => $office->officename,
            'abbrev' => $office->abbrev,
            'IDsucs' => $office->IDsucs,
            'personnel_count' => $personnelCounts->get($office->ID, 0),
        ];
    })->values();

    $docTypes = Schema::hasTable('lu_doctype')
        ? DB::table('lu_doctype')
            ->select('ID', 'code', 'description')
            ->orderBy('description')
            ->get()
        : collect();

    $docStatuses = Schema::hasTable('lu_docstatus')
        ? DB::table('lu_docstatus')
            ->select('ID', 'name')
            ->orderBy('name')
            ->get()
        : collect();

    $notes = Schema::hasTable('lu_note')
        ? DB::table('lu_note')
            ->orderBy('ID')
            ->get()
        : collect();

    $attachments = Schema::hasTable('lu_attachment')
        ? DB::table('lu_attachment')
            ->orderBy('ID')
            ->get()
        : collect();

    $addresses = Schema::hasTable('dts_action_types')
        ? DB::table('dts_action_types')
            ->orderBy('name')
            ->get()
        : collect();

    return Inertia::render('DTS/Library', [
        ...$this->dtsNotificationProps(),
        'offices' => $offices,
        'personnel' => $personnel,
        'docTypes' => $docTypes,
        'docStatuses' => $docStatuses,
        'notes' => $notes,
        'attachments' => $attachments,
        'addresses' => $addresses,
    ]);
}
public function storePersonnel(Request $request)
{
    $this->ensureCanManageDts();
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'IDoffice' => ['nullable', 'integer'],
    ]);

    $nextId = ((int) DB::table('lu_personnel')->max('ID')) + 1;

    DB::table('lu_personnel')->insert([
        'ID' => $nextId,
        'name' => trim($validated['name']),
        'IDoffice' => $validated['IDoffice'] ?: null,
    ]);

    $this->recordDtsActivity(
        'added personnel',
        'Added personnel: ' . trim($validated['name']) . '.',
        null,
        [
            'personnel_id' => $nextId,
            'personnel_name' => trim($validated['name']),
            'office_id' => $validated['IDoffice'] ?: null,
        ],
        'DTS Library',
        'lu_personnel'
    );

    return back()->with('success', 'Personnel added successfully.');
}

public function deletePersonnel(Request $request)
{
    $this->ensureCanManageDts();
    $validated = $request->validate([
        'ids' => ['required', 'array'],
        'ids.*' => ['integer'],
    ]);

    DB::table('lu_personnel')
        ->whereIn('ID', $validated['ids'])
        ->delete();

    $this->recordDtsActivity(
        'deleted personnel',
        'Deleted selected personnel record(s).',
        null,
        [
            'ids' => $validated['ids'],
        ],
        'DTS Library',
        'lu_personnel'
    );

    return back()->with('success', 'Selected personnel deleted successfully.');
}

public function storeActionType(Request $request)
{
    $this->ensureCanManageActionTypes();

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
    ]);

    if (! Schema::hasTable('dts_action_types')) {
        return back()->withErrors([
            'name' => 'Action type table not found. Please run the DTS action type setup SQL first.',
        ]);
    }

    DB::table('dts_action_types')->insert([
        'name' => trim($validated['name']),
        'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->recordDtsActivity(
        'added action type',
        'Added action type: ' . trim($validated['name']) . '.',
        null,
        [
            'action_name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
        ],
        'DTS Library',
        'dts_action_types'
    );

    return back()->with('success', 'Action type added successfully.');
}

public function updateActionType(Request $request, $id)
{
    $this->ensureCanManageActionTypes();

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
    ]);

    if (! Schema::hasTable('dts_action_types')) {
        return back()->withErrors([
            'name' => 'Action type table not found. Please run the DTS action type setup SQL first.',
        ]);
    }

    DB::table('dts_action_types')
        ->where('id', $id)
        ->update([
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'updated_at' => now(),
        ]);

    $this->recordDtsActivity(
        'updated action type',
        'Updated action type: ' . trim($validated['name']) . '.',
        null,
        [
            'action_type_id' => (int) $id,
            'action_name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
        ],
        'DTS Library',
        'dts_action_types'
    );

    return back()->with('success', 'Action type updated successfully.');
}

public function deleteActionType(Request $request)
{
    $this->ensureCanManageActionTypes();

    $validated = $request->validate([
        'ids' => ['required', 'array'],
        'ids.*' => ['integer'],
    ]);

    if (! Schema::hasTable('dts_action_types')) {
        return back()->withErrors([
            'delete' => 'Action type table not found. Please run the DTS action type setup SQL first.',
        ]);
    }

    DB::table('dts_action_types')
        ->whereIn('id', $validated['ids'])
        ->delete();

    $this->recordDtsActivity(
        'deleted action',
        'Deleted selected action type record(s).',
        null,
        [
            'ids' => $validated['ids'],
        ],
        'DTS Library',
        'dts_action_types'
    );

    return back()->with('success', 'Selected action deleted successfully.');
}

public function storeOffice(Request $request)
{
    $this->ensureCanManageDts();
    $validated = $request->validate([
        'officename' => ['required', 'string', 'max:255'],
        'abbrev' => ['nullable', 'string', 'max:100'],
    ]);

    $nextId = ((int) DB::table('lu_office')->max('ID')) + 1;

    DB::table('lu_office')->insert([
        'ID' => $nextId,
        'officename' => trim($validated['officename']),
        'abbrev' => $validated['abbrev'] ? trim($validated['abbrev']) : null,
    ]);

    $this->recordDtsActivity(
        'added office',
        'Added office: ' . trim($validated['officename']) . '.',
        null,
        [
            'office_id' => $nextId,
            'office_name' => trim($validated['officename']),
            'abbrev' => $validated['abbrev'] ? trim($validated['abbrev']) : null,
        ],
        'DTS Library',
        'lu_office'
    );

    return back()->with('success', 'Office added successfully.');
}

public function deleteOffice(Request $request)
{
    $this->ensureCanManageDts();
    $validated = $request->validate([
        'ids' => ['required', 'array'],
        'ids.*' => ['integer'],
    ]);

    $hasPersonnel = DB::table('lu_personnel')
        ->whereIn('IDoffice', $validated['ids'])
        ->exists();

    if ($hasPersonnel) {
        return back()->withErrors([
            'delete' => 'Cannot delete office because there are personnel assigned to it.',
        ]);
    }

    DB::table('lu_office')
        ->whereIn('ID', $validated['ids'])
        ->delete();

    $this->recordDtsActivity(
        'deleted office',
        'Deleted selected office record(s).',
        null,
        [
            'ids' => $validated['ids'],
        ],
        'DTS Library',
        'lu_office'
    );

    return back()->with('success', 'Selected office deleted successfully.');
}

public function updatePersonnel(Request $request, $id)
{
    $this->ensureCanManageDts();

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'IDoffice' => ['nullable', 'integer'],
    ]);

    DB::table('lu_personnel')
        ->where('ID', $id)
        ->update([
            'name' => trim($validated['name']),
            'IDoffice' => $validated['IDoffice'] ?: null,
        ]);

    $this->recordDtsActivity(
        'updated personnel',
        'Updated personnel: ' . trim($validated['name']) . '.',
        null,
        [
            'personnel_id' => (int) $id,
            'personnel_name' => trim($validated['name']),
            'office_id' => $validated['IDoffice'] ?: null,
        ],
        'DTS Library',
        'lu_personnel'
    );

    return back()->with('success', 'Personnel updated successfully.');
}

public function updateOffice(Request $request, $id)
{
    $this->ensureCanManageDts();

    $validated = $request->validate([
        'officename' => ['required', 'string', 'max:255'],
        'abbrev' => ['nullable', 'string', 'max:100'],
    ]);

    DB::table('lu_office')
        ->where('ID', $id)
        ->update([
            'officename' => trim($validated['officename']),
            'abbrev' => $validated['abbrev'] ? trim($validated['abbrev']) : null,
        ]);

    $this->recordDtsActivity(
        'updated office',
        'Updated office: ' . trim($validated['officename']) . '.',
        null,
        [
            'office_id' => (int) $id,
            'office_name' => trim($validated['officename']),
            'abbrev' => $validated['abbrev'] ? trim($validated['abbrev']) : null,
        ],
        'DTS Library',
        'lu_office'
    );

    return back()->with('success', 'Office updated successfully.');
}

public function storeDocType(Request $request)
{
    $this->ensureCanManageDts();

    $validated = $request->validate([
        'code' => ['nullable', 'string', 'max:100'],
        'description' => ['required', 'string', 'max:255'],
    ]);

    $nextId = ((int) DB::table('lu_doctype')->max('ID')) + 1;

    $data = [
        'ID' => $nextId,
    ];

    if (Schema::hasColumn('lu_doctype', 'code')) {
        $data['code'] = $validated['code'] ? trim($validated['code']) : null;
    }

    if (Schema::hasColumn('lu_doctype', 'description')) {
        $data['description'] = trim($validated['description']);
    } elseif (Schema::hasColumn('lu_doctype', 'name')) {
        $data['name'] = trim($validated['description']);
    }

    DB::table('lu_doctype')->insert($data);

    $this->recordDtsActivity(
        'added doc type',
        'Added doc type: ' . trim($validated['description']) . '.',
        null,
        [
            'doctype_id' => $nextId,
            'code' => $validated['code'] ?? null,
            'description' => trim($validated['description']),
        ],
        'DTS Library',
        'lu_doctype'
    );

    return back()->with('success', 'Doc type added successfully.');
}

public function updateDocType(Request $request, $id)
{
    $this->ensureCanManageDts();

    $validated = $request->validate([
        'code' => ['nullable', 'string', 'max:100'],
        'description' => ['required', 'string', 'max:255'],
    ]);

    $data = [];

    if (Schema::hasColumn('lu_doctype', 'code')) {
        $data['code'] = $validated['code'] ? trim($validated['code']) : null;
    }

    if (Schema::hasColumn('lu_doctype', 'description')) {
        $data['description'] = trim($validated['description']);
    } elseif (Schema::hasColumn('lu_doctype', 'name')) {
        $data['name'] = trim($validated['description']);
    }

    if (! empty($data)) {
        DB::table('lu_doctype')
            ->where('ID', $id)
            ->update($data);
    }

    $this->recordDtsActivity(
        'updated doc type',
        'Updated doc type: ' . trim($validated['description']) . '.',
        null,
        [
            'doctype_id' => (int) $id,
            'code' => $validated['code'] ?? null,
            'description' => trim($validated['description']),
        ],
        'DTS Library',
        'lu_doctype'
    );

    return back()->with('success', 'Doc type updated successfully.');
}

public function deleteDocType(Request $request)
{
    $this->ensureCanManageDts();

    $validated = $request->validate([
        'ids' => ['required', 'array'],
        'ids.*' => ['integer'],
    ]);

    $inUse = Schema::hasTable('document')
        && Schema::hasColumn('document', 'IDdoctype')
        && DB::table('document')->whereIn('IDdoctype', $validated['ids'])->exists();

    if ($inUse) {
        return back()->withErrors([
            'delete' => 'Cannot delete doc type because it is already used by existing documents.',
        ]);
    }

    DB::table('lu_doctype')
        ->whereIn('ID', $validated['ids'])
        ->delete();

    $this->recordDtsActivity(
        'deleted doc type',
        'Deleted selected doc type record(s).',
        null,
        [
            'ids' => $validated['ids'],
        ],
        'DTS Library',
        'lu_doctype'
    );

    return back()->with('success', 'Selected doc type deleted successfully.');
}

private function attachmentLibraryNameColumn(): string
{
    foreach (['name', 'attachment', 'title', 'description'] as $column) {
        if (Schema::hasColumn('lu_attachment', $column)) {
            return $column;
        }
    }

    return 'description';
}

public function storeLibraryAttachment(Request $request)
{
    $this->ensureCanManageDts();

    $validated = $request->validate([
        'code' => ['nullable', 'string', 'max:100'],
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:500'],
    ]);

    $nextId = ((int) DB::table('lu_attachment')->max('ID')) + 1;
    $nameColumn = $this->attachmentLibraryNameColumn();

    $data = [
        'ID' => $nextId,
        $nameColumn => trim($validated['name']),
    ];

    if (Schema::hasColumn('lu_attachment', 'code')) {
        $data['code'] = $validated['code'] ? trim($validated['code']) : null;
    }

    if ($nameColumn !== 'description' && Schema::hasColumn('lu_attachment', 'description')) {
        $data['description'] = $validated['description'] ? trim($validated['description']) : null;
    }

    DB::table('lu_attachment')->insert($data);

    $this->recordDtsActivity(
        'added attachment type',
        'Added attachment type: ' . trim($validated['name']) . '.',
        null,
        [
            'attachment_id' => $nextId,
            'code' => $validated['code'] ?? null,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
        ],
        'DTS Library',
        'lu_attachment'
    );

    return back()->with('success', 'Attachment added successfully.');
}

public function updateLibraryAttachment(Request $request, $id)
{
    $this->ensureCanManageDts();

    $validated = $request->validate([
        'code' => ['nullable', 'string', 'max:100'],
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:500'],
    ]);

    $nameColumn = $this->attachmentLibraryNameColumn();

    $data = [
        $nameColumn => trim($validated['name']),
    ];

    if (Schema::hasColumn('lu_attachment', 'code')) {
        $data['code'] = $validated['code'] ? trim($validated['code']) : null;
    }

    if ($nameColumn !== 'description' && Schema::hasColumn('lu_attachment', 'description')) {
        $data['description'] = $validated['description'] ? trim($validated['description']) : null;
    }

    DB::table('lu_attachment')
        ->where('ID', $id)
        ->update($data);

    $this->recordDtsActivity(
        'updated attachment type',
        'Updated attachment type: ' . trim($validated['name']) . '.',
        null,
        [
            'attachment_id' => (int) $id,
            'code' => $validated['code'] ?? null,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
        ],
        'DTS Library',
        'lu_attachment'
    );

    return back()->with('success', 'Attachment updated successfully.');
}

public function deleteLibraryAttachment(Request $request)
{
    $this->ensureCanManageDts();

    $validated = $request->validate([
        'ids' => ['required', 'array'],
        'ids.*' => ['integer'],
    ]);

    $inUse = Schema::hasTable('dts_document_files')
        && Schema::hasColumn('dts_document_files', 'IDattachment')
        && DB::table('dts_document_files')->whereIn('IDattachment', $validated['ids'])->exists();

    if ($inUse) {
        return back()->withErrors([
            'delete' => 'Cannot delete attachment because it is already used by existing document files.',
        ]);
    }

    DB::table('lu_attachment')
        ->whereIn('ID', $validated['ids'])
        ->delete();

    $this->recordDtsActivity(
        'deleted attachment type',
        'Deleted selected attachment record(s).',
        null,
        [
            'ids' => $validated['ids'],
        ],
        'DTS Library',
        'lu_attachment'
    );

    return back()->with('success', 'Selected attachment deleted successfully.');
}


public function updateEntryDate(Request $request, $id)
{
    $this->ensureCanManageDts();
    $validated = $request->validate([
        'entrydate' => ['required', 'date'],
    ]);

    $document = DtsDocument::where('IDdoc', $id)->firstOrFail();

    $document->update([
        'entrydate' => date('Y-m-d H:i:s', strtotime($validated['entrydate'])),
    ]);

    $this->recordDtsActivity(
        'updated entry date',
        'Updated entry date of document #' . $document->IDdoc . '.',
        (int) $document->IDdoc,
        [
            'entrydate' => $validated['entrydate'],
        ]
    );

    return redirect()
        ->route('dts.index')
        ->with('success', 'Entry date updated successfully.');
}


public function storeAttachment(Request $request, $id)
{
    /*
     * Attachments are shared by the physical document, while the optional
     * re-attach remark belongs to the currently opened assignment.
     */
    $assignmentId = $this->requestedAssignmentId($request);

    $this->ensureViewerCanReattachDocument(
        (int) $id,
        $assignmentId
    );

    $maxPdfKilobytes = 512000; // 500MB per PDF file. Laravel max rule uses kilobytes.

    $validated = $request->validate([
        'attachments' => ['required', 'array', 'min:1'],
        'attachments.*' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', "max:{$maxPdfKilobytes}"],
        'remarks' => ['nullable', 'string', 'max:2000'],
        'assignment_id' => ['nullable', 'integer'],
    ], [
        'attachments.required' => 'Please select at least one PDF file.',
        'attachments.*.mimes' => 'Only PDF files are allowed.',
        'attachments.*.mimetypes' => 'Only PDF files are allowed.',
        'attachments.*.max' => 'Each PDF file must not exceed 500MB.',
    ]);

    /*
     * IMPORTANT:
     * Your DTS document table is named `document`, not `dts_documents`.
     * So this method checks `document.IDdoc` directly to avoid 404.
     */
    if (! Schema::hasTable('document')) {
        return back()->with('error', 'Document table not found. Expected table name: document.');
    }

    $document = DB::table('document')
        ->where('IDdoc', $id)
        ->first();

    if (! $document) {
        return back()->with('error', 'Document not found for re-attach. Please check if document ID ' . $id . ' exists in the document table.');
    }

    if (! Schema::hasTable('dts_document_files')) {
        return back()->with('error', 'Attachment table not found. Expected table name: dts_document_files.');
    }

    $createdAt = now();

    DB::transaction(function () use (
        $request,
        $document,
        $validated,
        $createdAt,
        $assignmentId
    ) {
        foreach ($request->file('attachments', []) as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store("dts/documents/{$document->IDdoc}", 'public');

            DB::table('dts_document_files')->insert([
                'IDdoc' => $document->IDdoc,
                'IDattachment' => 0,
                'type_name' => 'Re-attached File',
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => basename($path),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $remarks = trim((string) ($validated['remarks'] ?? ''));

        if ($remarks !== '' && Schema::hasTable('dts_document_remarks')) {
            $remarkData = [
                'IDdoc' => $document->IDdoc,
                'remarks' => $remarks,
                'created_by' => Auth::id(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (Schema::hasColumn('dts_document_remarks', 'assignment_id')) {
                $remarkData['assignment_id'] = $assignmentId;
            }

            DB::table('dts_document_remarks')->insert($remarkData);

            /*
             * Do not update document.remarks here.
             * document.remarks is a shared legacy/current field.
             * Keep this as a separate Action History row only.
             */
        }
    });

    $this->recordDtsActivity(
        're-attached file',
        'Re-attached file(s) to document #' . $document->IDdoc . '.',
        (int) $document->IDdoc,
        [
            'remarks' => $validated['remarks'] ?? null,
        ]
    );

    return back()->with('success', 'File re-attached successfully.');
}

/**
 * Remove an existing document attachment.
 *
 * Only Role 3 may remove files. The attachment ID must belong to the
 * document ID in the URL, which prevents deleting a file from another
 * document by changing the request parameters.
 */
public function destroyAttachment(Request $request, $id, $file)
{
    abort_unless($this->currentUserRights() === '3', 403);
    abort_unless($this->viewerCanAccessDocument((int) $id), 403);

    if (! Schema::hasTable('dts_document_files')) {
        return back()->withErrors([
            'attachment' => 'Attachment table not found.',
        ]);
    }

    $attachment = DB::table('dts_document_files')
        ->where('id', $file)
        ->where('IDdoc', $id)
        ->first();

    if (! $attachment) {
        return back()->withErrors([
            'attachment' => 'The attached file was not found for this document.',
        ]);
    }

    $originalName = trim((string) (
        $attachment->original_name
        ?? $attachment->stored_name
        ?? 'Uploaded file'
    ));

    $storedPath = trim((string) ($attachment->path ?? ''));

    /*
     * Delete the physical file first. A missing physical file is not treated
     * as an error because the database record still needs to be cleaned up.
     */
    if (
        $storedPath !== ''
        && Storage::disk('public')->exists($storedPath)
        && ! Storage::disk('public')->delete($storedPath)
    ) {
        return back()->withErrors([
            'attachment' => 'The file could not be removed from storage. Please try again.',
        ]);
    }

    DB::table('dts_document_files')
        ->where('id', $attachment->id)
        ->where('IDdoc', $id)
        ->delete();

    $this->recordDtsActivity(
        'removed attached file',
        'Removed attached file "' . $originalName . '" from document #' . $id . '.',
        (int) $id,
        [
            'attachment_id' => (int) $attachment->id,
            'file_name' => $originalName,
            'path' => $storedPath,
        ]
    );

    return back()->with(
        'success',
        'Attached file removed successfully. You may now attach a replacement PDF.'
    );
}
public function storeRemark(Request $request, $id)
{
    $this->ensureCanRemarkDts();

    $assignmentId = $this->requestedAssignmentId($request);
    $this->ensureViewerCanRemarkDocument((int) $id, $assignmentId);

    $validated = $request->validate([
        'remarks' => ['required', 'string'],
        'assignment_id' => ['nullable', 'integer'],
    ]);

    $document = DtsDocument::where('IDdoc', $id)->firstOrFail();

    if (! Schema::hasTable('dts_document_remarks')) {
        return back()->withErrors([
            'remarks' => 'Remarks table not found. Expected table name: dts_document_remarks.',
        ]);
    }

    if ($assignmentId !== null) {
        $this->resolveDocumentAssignment(
            (int) $document->IDdoc,
            $assignmentId
        );
    }

    $insertData = [
        'IDdoc' => $document->IDdoc,
        'remarks' => $validated['remarks'],
        'created_by' => Auth::id(),
        'created_at' => now(),
        'updated_at' => now(),
    ];

    if (Schema::hasColumn('dts_document_remarks', 'assignment_id')) {
        $insertData['assignment_id'] = $assignmentId;
    }

    if (Schema::hasColumn('dts_document_remarks', 'action_type')) {
        $insertData['action_type'] = 'remark';
    }

    if (Schema::hasColumn('dts_document_remarks', 'action_type_id')) {
        $insertData['action_type_id'] = null;
    }

    if (Schema::hasColumn('dts_document_remarks', 'action_label')) {
        $insertData['action_label'] = null;
    }

    DB::table('dts_document_remarks')->insert($insertData);

    $reference = $this->workflowReference(
        (int) $document->IDdoc,
        $assignmentId
    );

    $this->recordDtsActivity(
        'added remark',
        'Added remark to document #' . $reference . '.',
        (int) $document->IDdoc,
        [
            'assignment_id' => $assignmentId,
            'assignment_reference' => $reference,
            'remarks' => $validated['remarks'],
        ]
    );

    return back()->with('success', 'Remark added successfully.');
}
public function actionTakenDocument(Request $request, $id)
{
    $this->ensureCanRemarkDts();

    $assignmentId = $this->requestedAssignmentId($request);
    $this->ensureViewerCanActOnDocument((int) $id, $assignmentId);

    if (! Schema::hasTable('dts_document_remarks')) {
        return back()->withErrors([
            'action' => 'Remarks/action table not found. Expected table name: dts_document_remarks.',
        ]);
    }

    if (
        ! Schema::hasColumn('dts_document_remarks', 'action_type')
        || ! Schema::hasColumn('dts_document_remarks', 'action_type_id')
    ) {
        return back()->withErrors([
            'action' => 'Missing action_type or action_type_id in dts_document_remarks.',
        ]);
    }

    $document = DtsDocument::where('IDdoc', $id)->firstOrFail();

    if ($assignmentId !== null) {
        $this->resolveDocumentAssignment(
            (int) $document->IDdoc,
            $assignmentId
        );
    }

    $actionStage = strtolower(
        trim((string) $request->input('action_stage', ''))
    );

    if (! in_array($actionStage, ['first', 'final'], true)) {
        $actionStage = $request->boolean('close_action')
            ? 'final'
            : 'first';
    }

    $request->merge([
        'action_stage' => $actionStage,
        'close_action' => $actionStage === 'final',
    ]);

    $validated = $request->validate([
        'IDactionType' => [
            'required',
            'string',
            'in:__address_document__',
        ],
        'action_stage' => ['required', 'string', 'in:first,final'],
        'remarks' => ['required', 'string'],
        'close_action' => ['nullable', 'boolean'],
        'assignment_id' => ['nullable', 'integer'],
    ]);

    $latestDistribution = $this->latestDistributionForWorkflow(
        (int) $document->IDdoc,
        $assignmentId
    );

    if (! $latestDistribution || empty($latestDistribution->confirmdate)) {
        return back()->withErrors([
            'action' => 'Addressed is available only after this assignment is received.',
        ]);
    }

    $existingAddressActionsQuery = DB::table('dts_document_remarks')
        ->where('IDdoc', $document->IDdoc)
        ->whereIn('action_type', ['action_saved', 'action_taken']);

    $this->applyAssignmentColumnScope(
        $existingAddressActionsQuery,
        'dts_document_remarks.assignment_id',
        $assignmentId
    );

    if (! empty($latestDistribution->distdate)) {
        $existingAddressActionsQuery->where(
            'created_at',
            '>=',
            $latestDistribution->distdate
        );
    }

    $existingAddressActions = $existingAddressActionsQuery
        ->orderBy('id')
        ->get();

    $alreadyClosed = $existingAddressActions->contains(function ($item) {
        return strtolower(trim((string) ($item->action_type ?? '')))
            === 'action_taken';
    });

    if ($alreadyClosed) {
        return back()->withErrors([
            'action' => 'The Final Action has already been saved for this assignment.',
        ]);
    }

    $existingActionCount = $existingAddressActions->count();

    if ($actionStage === 'first' && $existingActionCount >= 1) {
        return back()->withErrors([
            'action_stage' => 'First Action is already saved. Select Final Action.',
        ]);
    }

    $finalizeExistingLatestAction = $actionStage === 'final'
        && $existingActionCount >= 2;

    $remarks = trim((string) $validated['remarks']);

    $addressActionTypeId = Schema::hasTable('dts_action_types')
        ? DB::table('dts_action_types')
            ->whereRaw("LOWER(TRIM(name)) IN ('address', 'addressed')")
            ->orderBy('id')
            ->value('id')
        : null;

    DB::transaction(function () use (
        $document,
        $addressActionTypeId,
        $remarks,
        $actionStage,
        $finalizeExistingLatestAction,
        $existingAddressActions,
        $assignmentId
    ) {
        $now = now();
        $isFinalAction = $actionStage === 'final';

        if ($finalizeExistingLatestAction) {
            $latestSavedAction = $existingAddressActions
                ->reverse()
                ->first(function ($item) {
                    return strtolower(
                        trim((string) ($item->action_type ?? ''))
                    ) === 'action_saved';
                });

            if (! $latestSavedAction) {
                throw new \RuntimeException(
                    'No saved First Action was found to finalize.'
                );
            }

            $updateData = [
                'remarks' => $remarks,
                'action_type' => 'action_taken',
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('dts_document_remarks', 'action_label')) {
                $updateData['action_label'] = 'Address';
            }

            if (Schema::hasColumn('dts_document_remarks', 'action_status')) {
                $updateData['action_status'] = 'closed';
            }

            if (Schema::hasColumn('dts_document_remarks', 'action_completed_at')) {
                $updateData['action_completed_at'] = $now;
            }

            if (Schema::hasColumn('dts_document_remarks', 'action_completed_by')) {
                $updateData['action_completed_by'] = Auth::id();
            }

            DB::table('dts_document_remarks')
                ->where('id', $latestSavedAction->id)
                ->update($updateData);
        } else {
            $insertData = [
                'IDdoc' => $document->IDdoc,
                'remarks' => $remarks,
                'action_type' => $isFinalAction
                    ? 'action_taken'
                    : 'action_saved',
                'action_type_id' => $addressActionTypeId,
                'created_by' => Auth::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('dts_document_remarks', 'assignment_id')) {
                $insertData['assignment_id'] = $assignmentId;
            }

            if (Schema::hasColumn('dts_document_remarks', 'action_label')) {
                $insertData['action_label'] = 'Address';
            }

            if (Schema::hasColumn('dts_document_remarks', 'action_status')) {
                $insertData['action_status'] = $isFinalAction
                    ? 'closed'
                    : 'open';
            }

            if (
                $isFinalAction
                && Schema::hasColumn(
                    'dts_document_remarks',
                    'action_completed_at'
                )
            ) {
                $insertData['action_completed_at'] = $now;
            }

            if (
                $isFinalAction
                && Schema::hasColumn(
                    'dts_document_remarks',
                    'action_completed_by'
                )
            ) {
                $insertData['action_completed_by'] = Auth::id();
            }

            DB::table('dts_document_remarks')->insert($insertData);
        }
    });

    $reference = $this->workflowReference(
        (int) $document->IDdoc,
        $assignmentId
    );

    if ($actionStage === 'final') {
        $this->recordDtsActivity(
            'saved final document action',
            'Saved the Final Action for document #' . $reference . '.',
            (int) $document->IDdoc,
            [
                'assignment_id' => $assignmentId,
                'assignment_reference' => $reference,
                'action_name' => 'Address',
                'action_stage' => 'final',
                'remarks' => $remarks,
            ]
        );

        return back()->with(
            'success',
            'Final Action saved. This assignment is now Addressed.'
        );
    }

    $this->recordDtsActivity(
        'saved first document action',
        'Saved the First Action for document #' . $reference . '.',
        (int) $document->IDdoc,
        [
            'assignment_id' => $assignmentId,
            'assignment_reference' => $reference,
            'action_name' => 'Address',
            'action_stage' => 'first',
            'remarks' => $remarks,
        ]
    );

    return back()->with('success', 'First Action saved successfully.');
}


public function completeDocument(Request $request, $id)
{
    /*
     * Role 2 is the personnel/user role that performs Select Action and
     * decides when the currently assigned document is finished.
     */
    abort_unless(
        $this->currentUserRights() === '2',
        403,
        'Only Role 2 personnel can mark a document as completed.'
    );

    abort_unless(
        $this->documentIsTaggedToViewer((int) $id),
        403,
        'Only the personnel currently tagged to this document can complete it.'
    );

    foreach (['is_completed', 'completed_at', 'completed_by'] as $requiredColumn) {
        if (! Schema::hasColumn('document', $requiredColumn)) {
            return redirect()
                ->route('dts.show', $id)
                ->withErrors([
                    'completion' => 'Missing document.' . $requiredColumn . '. Run the completed-workflow migration first.',
                ]);
        }
    }

    $document = DtsDocument::where('IDdoc', $id)->firstOrFail();

    $freshCompletion = DB::table('document')
        ->where('IDdoc', $document->IDdoc)
        ->select(['is_completed', 'completed_at'])
        ->first();

    if (! empty($freshCompletion?->is_completed) || ! empty($freshCompletion?->completed_at)) {
        return redirect()
            ->route('dts.show', $document->IDdoc)
            ->with('success', 'Document is already completed.');
    }

    $latestDistribution = DtsDistribution::where('IDdoc', $document->IDdoc)
        ->orderByDesc('IDdist')
        ->first();

    if (! $latestDistribution || empty($latestDistribution->confirmdate)) {
        return redirect()
            ->route('dts.show', $document->IDdoc)
            ->withErrors([
                'completion' => 'The document must be received before it can be completed.',
            ]);
    }

    $hasActionTaken = Schema::hasTable('dts_document_remarks')
        && Schema::hasColumn('dts_document_remarks', 'action_type')
        && DB::table('dts_document_remarks')
            ->where('IDdoc', $document->IDdoc)
            ->where('action_type', 'action_taken')
            ->exists();

    if (! $hasActionTaken) {
        return redirect()
            ->route('dts.show', $document->IDdoc)
            ->withErrors([
                'completion' => 'Add at least one Select Action before marking the document as completed.',
            ]);
    }

    $now = now();
    $currentUserId = $this->currentUserId() ?? Auth::id();

    DB::transaction(function () use ($document, $now, $currentUserId) {
        $documentUpdate = [
            'is_completed' => 1,
            'completed_at' => $now,
            'completed_by' => $currentUserId,
        ];

        /* Legacy fields are updated only when they really exist. */
        if (Schema::hasColumn('document', 'IDdocstatus')) {
            $documentUpdate['IDdocstatus'] = 6;
        }

        if (Schema::hasColumn('document', 'datecleared')) {
            $documentUpdate['datecleared'] = $now;
        }

        /* The legacy document table may not have Laravel timestamps. */
        if (Schema::hasColumn('document', 'updated_at')) {
            $documentUpdate['updated_at'] = $now;
        }

        DB::table('document')
            ->where('IDdoc', $document->IDdoc)
            ->update($documentUpdate);

        /* Close all Select Action rows, but only update columns that exist. */
        if (Schema::hasTable('dts_document_remarks')) {
            $actionUpdate = [];

            if (Schema::hasColumn('dts_document_remarks', 'action_status')) {
                $actionUpdate['action_status'] = 'completed';
            }

            if (Schema::hasColumn('dts_document_remarks', 'action_completed_at')) {
                $actionUpdate['action_completed_at'] = $now;
            }

            if (Schema::hasColumn('dts_document_remarks', 'action_completed_by')) {
                $actionUpdate['action_completed_by'] = $currentUserId;
            }

            if (Schema::hasColumn('dts_document_remarks', 'updated_at')) {
                $actionUpdate['updated_at'] = $now;
            }

            if (! empty($actionUpdate)) {
                DB::table('dts_document_remarks')
                    ->where('IDdoc', $document->IDdoc)
                    ->where('action_type', 'action_taken')
                    ->update($actionUpdate);
            }
        }
    });

    $this->recordDtsActivity(
        'completed document',
        'Marked document #' . $document->IDdoc . ' as completed.',
        (int) $document->IDdoc,
        [
            'completed_by' => $currentUserId,
            'completed_at' => $now->format('Y-m-d H:i:s'),
        ]
    );

    /* Force a fresh GET so the badge and permissions are rebuilt from DB. */
    return redirect()
        ->route('dts.show', $document->IDdoc)
        ->with('success', 'Document marked as completed successfully.');
}

public function closeActionTaken(Request $request, $id, $remarkId)
{
    /*
     * Monitoring Dashboard is view-only.
     * Action Taken records are for monitoring per document only,
     * so the old close/open workflow is intentionally disabled.
     */
    return back()->withErrors([
        'action' => 'Monitoring Dashboard is view-only. Action Taken records cannot be closed here.',
    ]);
}
public function monitoringDashboard(Request $request)
{
    $user = auth()->user();

    abort_unless((int) ($user->rights ?? 0) === 4, 403);

    $trueValues = ['True', 'true', 'Y', 'y', '1', 1];
    $search = trim((string) $request->input('search', ''));
    $status = strtolower(trim((string) $request->input('status', '')));
    $perPage = max(1, min((int) $request->input('per_page', 15), 100));

    $allowedStatuses = [
        '',
        'for-receiving',
        'received',
        'addressed',
        'returned',
        'pulled-out',
        'completed',
    ];

    if (! in_array($status, $allowedStatuses, true)) {
        $status = '';
    }

    $availableYears = DB::table('document')
        ->selectRaw('YEAR(entrydate) as year')
        ->whereNotNull('entrydate')
        ->groupBy(DB::raw('YEAR(entrydate)'))
        ->orderByDesc('year')
        ->pluck('year')
        ->filter()
        ->map(fn ($year) => (int) $year)
        ->values();

    $selectedYear = trim((string) $request->input('year', ''));

    if ($selectedYear === '') {
        $selectedYear = (string) (
            $availableYears->contains((int) now()->year)
                ? now()->year
                : ($availableYears->first() ?? now()->year)
        );
    }

    if (strtolower($selectedYear) === 'all') {
        $selectedYear = '';
    }

    $hasAssignmentTable = Schema::hasTable('dts_document_assignments');
    $hasDistributionAssignment = Schema::hasColumn(
        'distribution',
        'assignment_id'
    );
    $hasRemarkAssignment = Schema::hasTable('dts_document_remarks')
        && Schema::hasColumn(
            'dts_document_remarks',
            'assignment_id'
        );

    $hasManualCompletionColumns = Schema::hasColumn(
        'document',
        'is_completed'
    ) && Schema::hasColumn('document', 'completed_at');

    $legacyCompletionSql = $hasManualCompletionColumns
        ? '(COALESCE(d.is_completed, 0) = 1 OR d.completed_at IS NOT NULL)'
        : '(d.datecleared IS NOT NULL)';

    $workflowCompletionSql = '(assignment.id IS NULL AND '
        . $legacyCompletionSql
        . ')';

    $workflowNotCompletedSql = 'NOT ' . $workflowCompletionSql;

    $makeAssignmentSource = function () use ($hasAssignmentTable) {
        if ($hasAssignmentTable) {
            return DB::table('dts_document_assignments')
                ->select([
                    'id',
                    'IDdoc',
                    'assignment_suffix',
                    'idmapagency',
                ]);
        }

        return DB::table('document')
            ->whereRaw('1 = 0')
            ->selectRaw(
                'NULL as id, IDdoc, NULL as assignment_suffix, '
                . 'NULL as idmapagency'
            );
    };

    $makeLatestDistribution = function () use ($hasDistributionAssignment) {
        $query = DB::table('distribution as monitoringDx')
            ->select([
                'monitoringDx.IDdoc',
                DB::raw(
                    $hasDistributionAssignment
                        ? 'monitoringDx.assignment_id'
                        : 'NULL as assignment_id'
                ),
                DB::raw(
                    'MAX(CAST(monitoringDx.IDdist AS UNSIGNED)) '
                    . 'as latest_IDdist'
                ),
            ])
            ->groupBy('monitoringDx.IDdoc');

        if ($hasDistributionAssignment) {
            $query->groupBy('monitoringDx.assignment_id');
        }

        return $query;
    };

    $makeLatestFinalAction = function () use (
        $makeLatestDistribution,
        $hasRemarkAssignment
    ) {
        if (! Schema::hasTable('dts_document_remarks')) {
            return DB::table('document')
                ->whereRaw('1 = 0')
                ->selectRaw(
                    'IDdoc, NULL as assignment_id, NULL as latest_action_id'
                );
        }

        $remarkAssignmentExpression = $hasRemarkAssignment
            ? 'finalRemark.assignment_id'
            : 'NULL';

        $query = DB::table('dts_document_remarks as finalRemark')
            ->joinSub(
                $makeLatestDistribution(),
                'finalCycle',
                function ($join) use ($remarkAssignmentExpression) {
                    $join->on('finalCycle.IDdoc', '=', 'finalRemark.IDdoc')
                        ->whereRaw(
                            'finalCycle.assignment_id <=> '
                            . $remarkAssignmentExpression
                        );
                }
            )
            ->join(
                'distribution as finalDist',
                'finalDist.IDdist',
                '=',
                'finalCycle.latest_IDdist'
            )
            ->where('finalRemark.action_type', 'action_taken')
            ->whereColumn(
                'finalRemark.created_at',
                '>=',
                'finalDist.distdate'
            )
            ->select([
                'finalRemark.IDdoc',
                DB::raw($remarkAssignmentExpression . ' as assignment_id'),
                DB::raw('MAX(finalRemark.id) as latest_action_id'),
            ])
            ->groupBy('finalRemark.IDdoc');

        if ($hasRemarkAssignment) {
            $query->groupBy('finalRemark.assignment_id');
        }

        return $query;
    };

    $buildBase = function () use (
        $makeAssignmentSource,
        $makeLatestDistribution,
        $makeLatestFinalAction,
        $hasDistributionAssignment
    ) {
        return DB::table('document as d')
            ->leftJoinSub(
                $makeAssignmentSource(),
                'assignment',
                function ($join) {
                    $join->on('assignment.IDdoc', '=', 'd.IDdoc');
                }
            )
            ->leftJoinSub(
                $makeLatestDistribution(),
                'monitoringLatest',
                function ($join) {
                    $join->on('monitoringLatest.IDdoc', '=', 'd.IDdoc')
                        ->whereRaw(
                            'monitoringLatest.assignment_id <=> assignment.id'
                        );
                }
            )
            ->leftJoin(
                'distribution as dist',
                'dist.IDdist',
                '=',
                'monitoringLatest.latest_IDdist'
            )
            ->leftJoin('distribution as returnParent', function ($join) use ($hasDistributionAssignment) {
                $join->on('returnParent.IDdist', '=', 'dist.IDparentdist')
                    ->on('returnParent.IDdoc', '=', 'dist.IDdoc');

                if ($hasDistributionAssignment) {
                    $join->whereRaw(
                        'returnParent.assignment_id <=> dist.assignment_id'
                    );
                }
            })
            ->leftJoinSub(
                $makeLatestFinalAction(),
                'latestFinalAction',
                function ($join) {
                    $join->on('latestFinalAction.IDdoc', '=', 'd.IDdoc')
                        ->whereRaw(
                            'latestFinalAction.assignment_id <=> assignment.id'
                        );
                }
            )
            ->leftJoin(
                'dts_document_remarks as finalAction',
                'finalAction.id',
                '=',
                'latestFinalAction.latest_action_id'
            )
            ->leftJoin(
                'dts_action_types as finalActionType',
                'finalActionType.id',
                '=',
                'finalAction.action_type_id'
            )
            ->leftJoin('lu_doctype as dt', 'dt.ID', '=', 'd.IDdoctype')
            ->leftJoin('lu_personnel as assignedPersonnel', function ($join) {
                $join->on(
                    'assignedPersonnel.ID',
                    '=',
                    DB::raw(
                        'COALESCE(dist.idmapagency, assignment.idmapagency, d.IDkeeper)'
                    )
                );
            })
            ->leftJoin(
                'lu_office as assignedOffice',
                'assignedOffice.ID',
                '=',
                'assignedPersonnel.IDoffice'
            );
    };

    $displayExpression = "CASE
        WHEN assignment.id IS NOT NULL
            AND NULLIF(TRIM(assignment.assignment_suffix), '') IS NOT NULL
        THEN CONCAT(
            CAST(d.IDdoc AS CHAR),
            '-',
            UPPER(TRIM(assignment.assignment_suffix))
        )
        ELSE CAST(d.IDdoc AS CHAR)
    END";

    $notPulledSql = "(
        dist.YNpulled IS NULL
        OR dist.YNpulled NOT IN ('True', 'true', 'Y', 'y', '1')
    )";

    $pendingReturnSql = "(
        returnParent.IDdist IS NOT NULL
        AND (
            returnParent.YNreturn IN ('True', 'true', 'Y', 'y', '1')
            OR returnParent.returndate IS NOT NULL
        )
        AND dist.confirmdate IS NULL
        AND {$notPulledSql}
    )";

    $statusExpression = "CASE
        WHEN {$workflowCompletionSql}
            THEN 'Completed'
        WHEN dist.YNpulled IN ('True', 'true', 'Y', 'y', '1')
            THEN 'Pulled Out'
        WHEN dist.confirmdate IS NOT NULL
            AND finalAction.id IS NOT NULL
            THEN 'Addressed'
        WHEN {$pendingReturnSql}
            THEN 'Returned'
        WHEN dist.confirmdate IS NOT NULL
            THEN 'Received'
        WHEN dist.distdate IS NOT NULL
            AND {$notPulledSql}
            THEN 'For Receiving'
        ELSE 'Pending'
    END";

    $applyYear = function ($query) use ($selectedYear) {
        if ($selectedYear !== '') {
            $query->whereYear('d.entrydate', (int) $selectedYear);
        }

        return $query;
    };

    $applyStatus = function ($query, string $statusKey) use (
        $workflowCompletionSql,
        $workflowNotCompletedSql,
        $notPulledSql,
        $pendingReturnSql,
        $trueValues
    ) {
        if ($statusKey === 'completed') {
            return $query->whereRaw($workflowCompletionSql);
        }

        if ($statusKey === 'pulled-out') {
            return $query
                ->whereRaw($workflowNotCompletedSql)
                ->whereIn('dist.YNpulled', $trueValues);
        }

        if ($statusKey === 'returned') {
            return $query
                ->whereRaw($workflowNotCompletedSql)
                ->whereRaw($pendingReturnSql);
        }

        if ($statusKey === 'addressed') {
            return $query
                ->whereRaw($workflowNotCompletedSql)
                ->whereNotNull('dist.confirmdate')
                ->whereNotNull('finalAction.id')
                ->whereRaw($notPulledSql);
        }

        if ($statusKey === 'received') {
            return $query
                ->whereRaw($workflowNotCompletedSql)
                ->whereNotNull('dist.confirmdate')
                ->whereNull('finalAction.id')
                ->whereRaw($notPulledSql);
        }

        if ($statusKey === 'for-receiving') {
            return $query
                ->whereRaw($workflowNotCompletedSql)
                ->whereNotNull('dist.distdate')
                ->whereNull('dist.confirmdate')
                ->whereRaw($notPulledSql)
                ->whereRaw('NOT ' . $pendingReturnSql);
        }

        return $query;
    };

    $transactionsQuery = $buildBase();
    $applyYear($transactionsQuery);

    if ($search !== '') {
        $searchLike = '%' . $search . '%';

        $transactionsQuery->where(function ($query) use (
            $searchLike,
            $displayExpression,
            $statusExpression
        ) {
            $query
                ->whereRaw(
                    '(' . $displayExpression . ') LIKE ?',
                    [$searchLike]
                )
                ->orWhere('d.subject', 'like', $searchLike)
                ->orWhere('dt.description', 'like', $searchLike)
                ->orWhere('assignedPersonnel.name', 'like', $searchLike)
                ->orWhere('assignedOffice.officename', 'like', $searchLike)
                ->orWhere('finalAction.remarks', 'like', $searchLike)
                ->orWhereRaw(
                    '(' . $statusExpression . ') LIKE ?',
                    [$searchLike]
                );
        });
    }

    if ($status !== '') {
        $applyStatus($transactionsQuery, $status);
    }

    $actionLabelExpression = Schema::hasColumn(
        'dts_document_remarks',
        'action_label'
    )
        ? "COALESCE(finalAction.action_label, finalActionType.name, 'Addressed')"
        : "COALESCE(finalActionType.name, 'Addressed')";

    $completedAtExpression = Schema::hasColumn('document', 'completed_at')
        ? 'CASE WHEN assignment.id IS NULL THEN d.completed_at ELSE NULL END'
        : 'CASE WHEN assignment.id IS NULL THEN d.datecleared ELSE NULL END';

    $transactions = $transactionsQuery
        ->select([
            'dist.IDdist',
            'dist.IDparentdist as distribution_parent_id',
            'returnParent.IDdist as return_parent_distribution_id',
            'd.IDdoc',
            DB::raw($displayExpression . ' as document_no'),
            DB::raw($displayExpression . ' as display_document_no'),
            'assignment.id as assignment_id',
            DB::raw(
                "UPPER(NULLIF(TRIM(assignment.assignment_suffix), '')) "
                . 'as assignment_suffix'
            ),
            'dist.distdate',
            'dist.confirmdate',
            'dist.YNreturn',
            'dist.returndate',
            'dist.YNpulled',
            'd.subject',
            'd.entrydate',
            DB::raw(
                'COALESCE(dist.idmapagency, assignment.idmapagency, d.IDkeeper) '
                . 'as IDkeeper'
            ),
            DB::raw(
                'CASE WHEN ' . $workflowCompletionSql
                . ' THEN 1 ELSE 0 END as is_completed'
            ),
            DB::raw($completedAtExpression . ' as completed_at'),
            'dt.description as document_type',
            'assignedPersonnel.name as assigned_personnel',
            'assignedOffice.officename as assigned_office',
            DB::raw($actionLabelExpression . ' as latest_action_label'),
            'finalAction.created_at as latest_action_at',
            DB::raw($statusExpression . ' as workflow_status'),
            DB::raw(
                'CASE WHEN finalAction.id IS NULL THEN 0 ELSE 1 END '
                . 'as has_current_cycle_final_action'
            ),
            DB::raw("
                CASE
                    WHEN dist.confirmdate IS NULL
                        AND dist.distdate IS NOT NULL
                        AND {$notPulledSql}
                        AND NOT {$pendingReturnSql}
                    THEN DATEDIFF(NOW(), dist.distdate)
                    ELSE 0
                END as days_pending
            "),
        ])
        ->orderByDesc(DB::raw('COALESCE(dist.distdate, d.entrydate)'))
        ->orderByDesc('d.IDdoc')
        ->orderByRaw("COALESCE(assignment.assignment_suffix, '') ASC")
        ->paginate($perPage)
        ->appends($request->query());

    $statsBase = $buildBase();
    $applyYear($statsBase);

    $countStatus = function (string $statusKey) use (
        $statsBase,
        $applyStatus
    ) {
        $query = clone $statsBase;
        $applyStatus($query, $statusKey);

        return $query->count();
    };

    $totalWorkflows = (clone $statsBase)->count();

    $stats = [
        'total_documents' => $totalWorkflows,
        'total_transactions' => $totalWorkflows,
        'completed' => $countStatus('completed'),
        'for_receiving' => $countStatus('for-receiving'),
        'received' => $countStatus('received'),
        'addressed' => $countStatus('addressed'),
        'returned' => $countStatus('returned'),
        'pulled_out' => $countStatus('pulled-out'),
    ];

    $stats['no_action'] = $stats['for_receiving'];
    $stats['action_taken'] = $stats['addressed'];
    $stats['action_taken_documents'] = $stats['addressed'];
    $stats['final_action_documents'] = $stats['addressed'];
    $stats['pending_returned'] = $stats['returned'];
    $stats['current_returned'] = $stats['returned'];

    $pendingBase = $buildBase();
    $applyYear($pendingBase);
    $applyStatus($pendingBase, 'for-receiving');

    $peopleNoAction = (clone $pendingBase)
        ->select([
            'assignedPersonnel.ID as personnel_id',
            DB::raw(
                "COALESCE(assignedPersonnel.name, 'Unassigned') "
                . 'as personnel_name'
            ),
            DB::raw(
                "COALESCE(assignedOffice.officename, 'No office') "
                . 'as office_name'
            ),
            DB::raw('COUNT(*) as pending_transactions'),
            DB::raw(
                'MAX(DATEDIFF(NOW(), dist.distdate)) as max_days_pending'
            ),
            DB::raw('MIN(dist.distdate) as oldest_pending_date'),
        ])
        ->groupBy(
            'assignedPersonnel.ID',
            'assignedPersonnel.name',
            'assignedOffice.officename'
        )
        ->orderByDesc('max_days_pending')
        ->orderByDesc('pending_transactions')
        ->limit(20)
        ->get();

    $pendingDocumentsForPeople = (clone $pendingBase)
        ->select([
            'assignedPersonnel.ID as personnel_id',
            DB::raw(
                "COALESCE(assignedPersonnel.name, 'Unassigned') "
                . 'as personnel_name'
            ),
            DB::raw(
                "COALESCE(assignedOffice.officename, 'No office') "
                . 'as office_name'
            ),
            'd.IDdoc',
            'assignment.id as assignment_id',
            DB::raw($displayExpression . ' as document_no'),
            DB::raw($displayExpression . ' as display_document_no'),
            'd.subject',
            'dist.distdate',
            DB::raw('DATEDIFF(NOW(), dist.distdate) as days_pending'),
        ])
        ->orderByDesc('days_pending')
        ->orderByDesc('d.IDdoc')
        ->limit(500)
        ->get()
        ->groupBy(function ($doc) {
            return $doc->personnel_id
                ? (string) $doc->personnel_id
                : 'unassigned';
        });

    $peopleNoAction = $peopleNoAction->map(function ($person) use (
        $pendingDocumentsForPeople
    ) {
        $key = $person->personnel_id
            ? (string) $person->personnel_id
            : 'unassigned';

        $person->documents = $pendingDocumentsForPeople
            ->get($key, collect())
            ->values();

        return $person;
    });

    $actionTakenItems = collect();

    if (
        Schema::hasTable('dts_document_remarks')
        && Schema::hasColumn('dts_document_remarks', 'action_type')
    ) {
        $actionTakenBase = DB::table('dts_document_remarks as remarksTable')
            ->join('document as d', 'd.IDdoc', '=', 'remarksTable.IDdoc')
            ->leftJoinSub(
                $makeAssignmentSource(),
                'assignment',
                function ($join) use ($hasRemarkAssignment) {
                    $join->on('assignment.IDdoc', '=', 'd.IDdoc');

                    if ($hasRemarkAssignment) {
                        $join->whereRaw(
                            'assignment.id <=> remarksTable.assignment_id'
                        );
                    } else {
                        $join->whereNull('assignment.id');
                    }
                }
            )
            ->leftJoinSub(
                $makeLatestDistribution(),
                'latestActionDist',
                function ($join) use ($hasRemarkAssignment) {
                    $join->on('latestActionDist.IDdoc', '=', 'd.IDdoc');

                    if ($hasRemarkAssignment) {
                        $join->whereRaw(
                            'latestActionDist.assignment_id '
                            . '<=> remarksTable.assignment_id'
                        );
                    } else {
                        $join->whereNull('latestActionDist.assignment_id');
                    }
                }
            )
            ->leftJoin(
                'distribution as dist',
                'dist.IDdist',
                '=',
                'latestActionDist.latest_IDdist'
            )
            ->leftJoin(
                'dts_action_types as actionType',
                'actionType.id',
                '=',
                'remarksTable.action_type_id'
            )
            ->leftJoin(
                'username as remarkUser',
                'remarkUser.ID',
                '=',
                'remarksTable.created_by'
            )
            ->leftJoin('lu_personnel as assignedPersonnel', function ($join) {
                $join->on(
                    'assignedPersonnel.ID',
                    '=',
                    DB::raw(
                        'COALESCE(dist.idmapagency, assignment.idmapagency, d.IDkeeper)'
                    )
                );
            })
            ->whereIn(
                'remarksTable.action_type',
                ['action_saved', 'action_taken']
            )
            ->whereNotNull('dist.confirmdate')
            ->whereNotNull('dist.distdate')
            ->whereColumn(
                'remarksTable.created_at',
                '>=',
                'dist.distdate'
            )
            ->whereExists(function ($query) use ($hasRemarkAssignment) {
                $query->select(DB::raw(1))
                    ->from('dts_document_remarks as finalAddressedAction')
                    ->whereColumn(
                        'finalAddressedAction.IDdoc',
                        'd.IDdoc'
                    )
                    ->where(
                        'finalAddressedAction.action_type',
                        'action_taken'
                    )
                    ->whereColumn(
                        'finalAddressedAction.created_at',
                        '>=',
                        'dist.distdate'
                    );

                if ($hasRemarkAssignment) {
                    $query->whereRaw(
                        'finalAddressedAction.assignment_id '
                        . '<=> assignment.id'
                    );
                }
            });

        $applyYear($actionTakenBase);

        if ($search !== '') {
            $searchLike = '%' . $search . '%';

            $actionTakenBase->where(function ($query) use ($searchLike) {
                $query
                    ->where('d.IDdoc', 'like', $searchLike)
                    ->orWhere('d.subject', 'like', $searchLike)
                    ->orWhere('remarksTable.remarks', 'like', $searchLike)
                    ->orWhere('actionType.name', 'like', $searchLike)
                    ->orWhere(
                        'assignedPersonnel.name',
                        'like',
                        $searchLike
                    )
                    ->orWhere('remarkUser.name', 'like', $searchLike)
                    ->orWhere('remarkUser.loginname', 'like', $searchLike);
            });
        }

        $actionTakenLabelExpression = Schema::hasColumn(
            'dts_document_remarks',
            'action_label'
        )
            ? "COALESCE(remarksTable.action_label, actionType.name, 'Addressed')"
            : "COALESCE(actionType.name, 'Addressed')";

        $actionTakenItems = $actionTakenBase
            ->select([
                'remarksTable.id',
                'remarksTable.IDdoc',
                DB::raw(
                    $hasRemarkAssignment
                        ? 'remarksTable.assignment_id'
                        : 'NULL as assignment_id'
                ),
                DB::raw($displayExpression . ' as document_no'),
                DB::raw($displayExpression . ' as display_document_no'),
                'd.subject',
                'd.entrydate',
                DB::raw(
                    'COALESCE(dist.idmapagency, assignment.idmapagency, d.IDkeeper) '
                    . 'as IDkeeper'
                ),
                'assignedPersonnel.name as assigned_personnel',
                'remarksTable.remarks',
                'remarksTable.action_type',
                'remarksTable.created_at',
                DB::raw(
                    "COALESCE(
                        NULLIF(TRIM(remarkUser.name), ''),
                        NULLIF(TRIM(remarkUser.loginname), ''),
                        CONCAT('Account #', remarkUser.ID)
                    ) as actor_name"
                ),
                DB::raw(
                    $actionTakenLabelExpression . ' as action_label'
                ),
            ])
            ->orderByDesc('remarksTable.created_at')
            ->limit(300)
            ->get();
    }

    return Inertia::render('DTS/MonitoringDashboard', [
        ...$this->dtsNotificationProps(),
        'stats' => $stats,
        'transactions' => $transactions,
        'peopleNoAction' => $peopleNoAction,
        'actionTakenItems' => $actionTakenItems,
        'years' => $availableYears,
        'filters' => [
            'search' => $search,
            'status' => $status,
            'per_page' => $perPage,
            'year' => $selectedYear,
        ],
    ]);
}


private function recordDtsActivity(
    string $action,
    string $description,
    ?int $documentId = null,
    array $properties = [],
    string $module = 'DTS Documents',
    string $subjectType = 'dts_document'
): void {
    if (! Schema::hasTable('activity_logs')) {
        return;
    }

    try {
        ActivityLog::record(
            $action,
            $module,
            $description,
            $subjectType,
            $documentId,
            $properties
        );
    } catch (\Throwable $e) {
        // Activity logging should never block the main DTS transaction.
    }
}


private function currentUserId(): ?int
{
    $user = auth()->user();

    if (! $user) {
        return null;
    }

    $id = $user->ID ?? $user->id ?? auth()->id();

    return $id !== null ? (int) $id : null;
}

private function cleanIntegerIds(array $ids): array
{
    return collect($ids)
        ->filter(fn ($id) => $id !== null && $id !== '' && is_numeric($id))
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->unique()
        ->values()
        ->all();
}
private function isSuperAdminViewOnly(
    ?int $documentId = null,
    ?int $assignmentId = null
): bool {
    if ($this->currentUserRights() !== '4') {
        return false;
    }

    if (! $documentId) {
        return true;
    }

    return ! $this->documentIsTaggedToViewer(
        $documentId,
        $assignmentId
    );
}


private function shouldLimitDtsToTaggedDocuments(): bool
{
    /*
     * Direct document viewing rule:
     * Role 2 can now VIEW all document details like Role 3.
     * Actions are still protected separately by viewerCanActOnDocument().
     */
    return false;
}

private function shouldLimitDtsActionToTaggedDocuments(): bool
{
    /*
     * Applies to all DTS roles:
     * Receive / Transfer / Return / Action Taken are only allowed when the
     * document is tagged to the user's mapped personnel record.
     *
     * If the document is not tagged to them, they may only View Action History
     * and Add Remarks. Re-attach is only allowed if they added/encoded the document.
     */
    return in_array($this->currentUserRights(), ['1', '2', '3', '4'], true);
}

private function viewerAssignedPersonnelIds(): array
{
    $user = auth()->user();

    if (! $user) {
        return [];
    }

    $ids = [];
    $userId = $this->currentUserId();

    /*
     * Best mapping: username.idmapagency / personnel columns must match lu_personnel.ID.
     */
    foreach ([
        'idmapagency',
        'IDmapagency',
        'IDmapAgency',
        'IDpersonnel',
        'personnel_id',
        'IDkeeper',
        'staff_id',
        'employee_id',
    ] as $field) {
        $value = $user->{$field} ?? null;

        if ($value !== null && $value !== '') {
            $ids[] = $value;
        }
    }

    if ($userId && Schema::hasTable('username')) {
        foreach ([
            'idmapagency',
            'IDmapagency',
            'IDmapAgency',
            'IDpersonnel',
            'personnel_id',
            'IDkeeper',
            'staff_id',
            'employee_id',
        ] as $column) {
            if (Schema::hasColumn('username', $column)) {
                $value = DB::table('username')
                    ->where('ID', $userId)
                    ->value($column);

                if ($value !== null && $value !== '') {
                    $ids[] = $value;
                }
            }
        }
    }

    if (Schema::hasTable('lu_personnel')) {
        $loginName = trim((string) ($user->loginname ?? $user->username ?? ''));
        $displayName = trim((string) ($user->name ?? ''));
        $email = trim((string) ($user->email ?? ''));

        $personnelQuery = DB::table('lu_personnel');

        $personnelQuery->where(function ($query) use ($userId, $loginName, $displayName, $email) {
            $hasCondition = false;

            foreach (['IDuser', 'user_id', 'IDusername', 'username_id', 'account_id'] as $column) {
                if ($userId && Schema::hasColumn('lu_personnel', $column)) {
                    $hasCondition = true;
                    $query->orWhere($column, $userId);
                }
            }

            foreach (['loginname', 'username'] as $column) {
                if ($loginName !== '' && Schema::hasColumn('lu_personnel', $column)) {
                    $hasCondition = true;
                    $query->orWhere($column, $loginName);
                }
            }

            if (Schema::hasColumn('lu_personnel', 'name')) {
                if ($loginName !== '') {
                    $hasCondition = true;
                    $query->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower($loginName)])
                        ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ['%' . strtolower($loginName) . '%']);
                }

                if ($displayName !== '') {
                    $hasCondition = true;
                    $query->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower($displayName)])
                        ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ['%' . strtolower($displayName) . '%']);

                    $tokens = collect(preg_split('/\s+/', strtolower($displayName)))
                        ->map(fn ($token) => trim($token))
                        ->filter(fn ($token) => strlen($token) >= 2)
                        ->values()
                        ->all();

                    if (! empty($tokens)) {
                        $query->orWhere(function ($tokenQuery) use ($tokens) {
                            foreach ($tokens as $token) {
                                $tokenQuery->whereRaw('LOWER(TRIM(name)) LIKE ?', ['%' . $token . '%']);
                            }
                        });
                    }
                }
            }

            if ($email !== '' && Schema::hasColumn('lu_personnel', 'email')) {
                $hasCondition = true;
                $query->orWhere('email', $email);
            }

            if (! $hasCondition) {
                $query->whereRaw('1 = 0');
            }
        });

        $ids = array_merge($ids, $personnelQuery->pluck('ID')->all());
    }

    return $this->cleanIntegerIds($ids);
}

private function viewerAssignedOfficeIds(?array $personnelIds = null): array
{
    $user = auth()->user();

    if (! $user) {
        return [];
    }

    $ids = [];

    foreach (['IDoffice', 'idoffice', 'office_id', 'IDfor', 'IDagency', 'agency_id'] as $field) {
        $value = $user->{$field} ?? null;

        if ($value !== null && $value !== '') {
            $ids[] = $value;
        }
    }

    $userId = $this->currentUserId();

    if ($userId && Schema::hasTable('username')) {
        foreach (['IDoffice', 'idoffice', 'office_id', 'IDfor', 'IDagency', 'agency_id'] as $column) {
            if (Schema::hasColumn('username', $column)) {
                $value = DB::table('username')
                    ->where('ID', $userId)
                    ->value($column);

                if ($value !== null && $value !== '') {
                    $ids[] = $value;
                }
            }
        }
    }

    $personnelIds = $personnelIds ?? $this->viewerAssignedPersonnelIds();

    if (! empty($personnelIds) && Schema::hasTable('lu_personnel') && Schema::hasColumn('lu_personnel', 'IDoffice')) {
        $ids = array_merge(
            $ids,
            DB::table('lu_personnel')
                ->whereIn('ID', $personnelIds)
                ->pluck('IDoffice')
                ->all()
        );
    }

    return $this->cleanIntegerIds($ids);
}

private function applyViewerDocumentScope($query, string $documentAlias = 'd', string $distributionAlias = 'dist', ?array $officeIds = null, ?array $personnelIds = null)
{
    if (! $this->shouldLimitDtsToTaggedDocuments()) {
        return $query;
    }

    $personnelIds = $personnelIds ?? $this->viewerAssignedPersonnelIds();
    $officeIds = $officeIds ?? $this->viewerAssignedOfficeIds($personnelIds);
    $userId = $this->currentUserId();

    if (empty($personnelIds)) {
        return $query->whereRaw('1 = 0');
    }

    return $query->where(function ($scope) use ($documentAlias, $distributionAlias, $personnelIds) {
        $scope->whereIn($documentAlias . '.IDkeeper', $personnelIds);

        if (Schema::hasColumn('distribution', 'idmapagency')) {
            $scope->orWhereIn($distributionAlias . '.idmapagency', $personnelIds);
        }
    });
}


private function applyViewerActionScope($query, string $documentAlias = 'd', string $distributionAlias = 'dist', ?array $officeIds = null, ?array $personnelIds = null)
{
    if (! $this->shouldLimitDtsActionToTaggedDocuments()) {
        return $query;
    }

    $personnelIds = $personnelIds ?? $this->viewerAssignedPersonnelIds();
    $officeIds = $officeIds ?? $this->viewerAssignedOfficeIds($personnelIds);

    if (empty($personnelIds)) {
        return $query->whereRaw('1 = 0');
    }

    if (! Schema::hasColumn('distribution', 'idmapagency')) {
        return $query->whereIn($documentAlias . '.IDkeeper', $personnelIds);
    }

    return $query->where(function ($scope) use ($documentAlias, $distributionAlias, $personnelIds) {
        $scope
            ->where(function ($currentTag) use ($distributionAlias, $personnelIds) {
                $currentTag
                    ->whereNotNull($distributionAlias . '.IDdist')
                    ->whereIn($distributionAlias . '.idmapagency', $personnelIds);
            })
            ->orWhere(function ($noDistributionFallback) use (
                $documentAlias,
                $distributionAlias,
                $personnelIds
            ) {
                $noDistributionFallback
                    ->whereNull($distributionAlias . '.IDdist')
                    ->whereIn($documentAlias . '.IDkeeper', $personnelIds);
            });
    });
}
private function viewerCanAccessDocument(
    int $documentId,
    ?int $assignmentId = null
): bool {
    if (! DB::table('document')->where('IDdoc', $documentId)->exists()) {
        return false;
    }

    if ($assignmentId !== null) {
        if (! Schema::hasTable('dts_document_assignments')) {
            return false;
        }

        if (
            ! DB::table('dts_document_assignments')
                ->where('id', $assignmentId)
                ->where('IDdoc', $documentId)
                ->exists()
        ) {
            return false;
        }
    }

    /*
     * The current DTS allows users to open document details through
     * All Documents. Action permissions remain assignment-specific.
     */
    return true;
}
private function documentIsTaggedToViewer(
    int $documentId,
    ?int $assignmentId = null
): bool {
    $personnelIds = $this->viewerAssignedPersonnelIds();

    if (empty($personnelIds)) {
        return false;
    }

    if ($assignmentId !== null) {
        $assignment = $this->resolveDocumentAssignment(
            $documentId,
            $assignmentId
        );

        $latestDistribution = $this->latestDistributionForWorkflow(
            $documentId,
            $assignmentId
        );

        if ($latestDistribution) {
            return in_array(
                (int) ($latestDistribution->idmapagency ?? 0),
                $personnelIds,
                true
            );
        }

        return in_array(
            (int) ($assignment->idmapagency ?? 0),
            $personnelIds,
            true
        );
    }

    /*
     * Legacy/single document workflow uses assignment_id = NULL.
     */
    $latestDistribution = $this->latestDistributionForWorkflow(
        $documentId,
        null
    );

    if ($latestDistribution) {
        return in_array(
            (int) ($latestDistribution->idmapagency ?? 0),
            $personnelIds,
            true
        );
    }

    $keeperId = DB::table('document')
        ->where('IDdoc', $documentId)
        ->value('IDkeeper');

    return in_array((int) $keeperId, $personnelIds, true);
}
private function viewerCanTransferDocument(
    int $documentId,
    ?int $assignmentId = null
): bool {
    /*
     * Preserve the Role 3 quick-transfer rule, but verify that the requested
     * assignment belongs to the document.
     */
    if ($this->currentUserRights() === '3') {
        return $this->viewerCanAccessDocument(
            $documentId,
            $assignmentId
        );
    }

    if (! $this->canReceiveDts()) {
        return false;
    }

    return $this->viewerCanActOnDocument(
        $documentId,
        $assignmentId
    );
}
private function ensureViewerCanTransferDocument(
    int $documentId,
    ?int $assignmentId = null
): void {
    abort_unless(
        $this->viewerCanTransferDocument(
            $documentId,
            $assignmentId
        ),
        403
    );
}
private function viewerCanActOnDocument(
    int $documentId,
    ?int $assignmentId = null
): bool {
    if (! $this->canReceiveDts() && ! $this->canRemarkDts()) {
        return false;
    }

    if (! $this->shouldLimitDtsActionToTaggedDocuments()) {
        return true;
    }

    return $this->documentIsTaggedToViewer(
        $documentId,
        $assignmentId
    );
}
private function viewerCanRemarkDocument(
    int $documentId,
    ?int $assignmentId = null
): bool {
    if ($this->currentUserRights() === '2') {
        return $this->viewerCanActOnDocument(
            $documentId,
            $assignmentId
        );
    }

    return $this->viewerCanAccessDocument(
        $documentId,
        $assignmentId
    );
}
private function ensureViewerCanRemarkDocument(
    int $documentId,
    ?int $assignmentId = null
): void {
    abort_unless(
        $this->canRemarkDts()
            && $this->viewerCanRemarkDocument(
                $documentId,
                $assignmentId
            ),
        403
    );
}
private function viewerCanReattachDocument(
    int $documentId,
    ?int $assignmentId = null
): bool {
    if (! $this->canReattachDts()) {
        return false;
    }

    if ($this->currentUserRights() === '3') {
        return $this->viewerCanAccessDocument(
            $documentId,
            $assignmentId
        );
    }

    if (
        $this->currentUserRights() === '2'
        && ! $this->viewerCanActOnDocument(
            $documentId,
            $assignmentId
        )
    ) {
        return false;
    }

    $currentUserId = $this->currentUserId();

    if (! $currentUserId || ! Schema::hasTable('document')) {
        return false;
    }

    return DB::table('document')
        ->where('IDdoc', $documentId)
        ->where('IDuser', $currentUserId)
        ->exists();
}
private function ensureViewerCanReattachDocument(
    int $documentId,
    ?int $assignmentId = null
): void {
    abort_unless(
        $this->viewerCanReattachDocument(
            $documentId,
            $assignmentId
        ),
        403
    );
}
private function ensureViewerCanActOnDocument(
    int $documentId,
    ?int $assignmentId = null
): void {
    abort_unless(
        $this->viewerCanActOnDocument(
            $documentId,
            $assignmentId
        ),
        403
    );
}


private function requestedAssignmentId(Request $request): ?int
{
    $value = $request->input(
        'assignment_id',
        $request->query('assignment_id')
    );

    if ($value === null || $value === '' || ! is_numeric($value)) {
        return null;
    }

    $assignmentId = (int) $value;

    return $assignmentId > 0 ? $assignmentId : null;
}

private function assignmentSuffixFromIndex(int $index): string
{
    /*
     * Excel-style letters:
     * 0 = A, 25 = Z, 26 = AA, 27 = AB, ...
     */
    $number = $index + 1;
    $suffix = '';

    while ($number > 0) {
        $number--;
        $suffix = chr(65 + ($number % 26)) . $suffix;
        $number = intdiv($number, 26);
    }

    return $suffix;
}

private function resolveDocumentAssignment(
    int $documentId,
    ?int $assignmentId = null
): ?object {
    if (! Schema::hasTable('dts_document_assignments')) {
        abort_if($assignmentId !== null, 404);

        return null;
    }

    $query = DB::table('dts_document_assignments')
        ->where('IDdoc', $documentId)
        ->orderBy('id');

    if (! $query->exists()) {
        abort_if($assignmentId !== null, 404);

        return null;
    }

    if ($assignmentId !== null) {
        $assignment = (clone $query)
            ->where('id', $assignmentId)
            ->first();

        abort_unless($assignment, 404);

        return $assignment;
    }

    /*
     * A manually opened shared-ID URL may omit assignment_id.
     * Prefer the assignment currently tagged to the logged-in personnel.
     */
    $viewerPersonnelIds = $this->viewerAssignedPersonnelIds();

    if (! empty($viewerPersonnelIds)) {
        $viewerAssignment = (clone $query)
            ->whereIn('idmapagency', $viewerPersonnelIds)
            ->first();

        if ($viewerAssignment) {
            return $viewerAssignment;
        }
    }

    return $query->first();
}

private function applyAssignmentColumnScope(
    $query,
    string $column,
    ?int $assignmentId
) {
    if ($assignmentId === null) {
        return $query->whereNull($column);
    }

    return $query->where($column, $assignmentId);
}

private function latestDistributionForWorkflow(
    int $documentId,
    ?int $assignmentId
): ?object {
    $query = DB::table('distribution')
        ->where('IDdoc', $documentId);

    if (Schema::hasColumn('distribution', 'assignment_id')) {
        $this->applyAssignmentColumnScope(
            $query,
            'assignment_id',
            $assignmentId
        );
    } elseif ($assignmentId !== null) {
        return null;
    }

    return $query
        ->orderByDesc('IDdist')
        ->first();
}

private function workflowReference(
    int $documentId,
    ?int $assignmentId
): string {
    if (
        $assignmentId !== null
        && Schema::hasTable('dts_document_assignments')
    ) {
        $suffix = DB::table('dts_document_assignments')
            ->where('id', $assignmentId)
            ->where('IDdoc', $documentId)
            ->value('assignment_suffix');

        $suffix = strtoupper(trim((string) $suffix));

        if ($suffix !== '') {
            return $documentId . '-' . $suffix;
        }
    }

    if (
        Schema::hasColumn('document', 'document_group_id')
        && Schema::hasColumn('document', 'assignment_suffix')
    ) {
        $legacyDisplay = DB::table('document')
            ->where('IDdoc', $documentId)
            ->select(['document_group_id', 'assignment_suffix'])
            ->first();

        $legacySuffix = strtoupper(
            trim((string) ($legacyDisplay->assignment_suffix ?? ''))
        );

        if (
            ! empty($legacyDisplay?->document_group_id)
            && $legacySuffix !== ''
        ) {
            return $legacyDisplay->document_group_id
                . '-'
                . $legacySuffix;
        }
    }

    return (string) $documentId;
}

private function currentUserRights(): string
{
    return trim((string) (auth()->user()->rights ?? ''));
}

private function ensureCanManageDts(): void
{
    abort_unless(
        in_array($this->currentUserRights(), ['1', '3'], true),
        403
    );
}

private function canManageActionTypes(): bool
{
    /*
     * Action Taken library:
     * Role 1 and 3 can manage all DTS libraries.
     * Role 2 can add, edit, and delete Action Taken choices only.
     */
    return in_array($this->currentUserRights(), ['1', '2', '3'], true);
}

private function ensureCanManageActionTypes(): void
{
    abort_unless($this->canManageActionTypes(), 403);
}

private function canReattachDts(): bool
{
    /*
     * Base permission for re-attach.
     * Actual document-level permission is checked by viewerCanReattachDocument().
     * If the user added/encoded the document, they can re-attach.
     */
    return in_array($this->currentUserRights(), ['1', '2', '3', '4'], true);
}

private function ensureCanReattachDts(): void
{
    abort_unless($this->canReattachDts(), 403);
}

private function canReceiveDts(): bool
{
    /*
     * Role 4 can receive/act ONLY when the document is tagged to them.
     * The tagged-only protection is handled by viewerCanActOnDocument().
     */
    return in_array((string) optional(Auth::user())->rights, ['1', '2', '3', '4'], true);
}

private function ensureCanReceiveDts(): void
{
    abort_unless($this->canReceiveDts(), 403);
}

private function canRemarkDts(): bool
{
    /*
     * All DTS users who can view the document can add remarks.
     * This is separate from receive/transfer/return/action taken.
     */
    return in_array($this->currentUserRights(), ['1', '2', '3', '4'], true);
}

private function ensureCanRemarkDts(): void
{
    abort_unless($this->canRemarkDts(), 403);
}
private function dtsNotificationProps(): array
{
    $viewerNotifications = collect();
    $creatorReceivedNotifications = collect();
    $automaticStatusReminders = collect();

    if (! Schema::hasTable('document') || ! Schema::hasTable('distribution')) {
        return [
            'viewerNotifications' => [],
            'creatorReceivedNotifications' => [],
            'automaticStatusReminders' => [],
        ];
    }

    $trueValues = ['True', 'true', 'Y', 'y', '1', 1];
    $viewerPersonnelIds = $this->viewerAssignedPersonnelIds();
    $currentUserId = $this->currentUserId();

    $hasAssignmentTable = Schema::hasTable('dts_document_assignments');
    $hasDistributionAssignment = Schema::hasColumn(
        'distribution',
        'assignment_id'
    );
    $hasRemarkAssignment = Schema::hasTable('dts_document_remarks')
        && Schema::hasColumn(
            'dts_document_remarks',
            'assignment_id'
        );

    $doctypeCodeColumn = 'dt.description';

    if (Schema::hasTable('lu_doctype')) {
        if (Schema::hasColumn('lu_doctype', 'abbreviation')) {
            $doctypeCodeColumn = 'dt.abbreviation';
        } elseif (Schema::hasColumn('lu_doctype', 'abbr')) {
            $doctypeCodeColumn = 'dt.abbr';
        } elseif (Schema::hasColumn('lu_doctype', 'code')) {
            $doctypeCodeColumn = 'dt.code';
        }
    }

    $makeAssignmentSource = function () use ($hasAssignmentTable) {
        if ($hasAssignmentTable) {
            return DB::table('dts_document_assignments')
                ->select([
                    'id',
                    'IDdoc',
                    'assignment_suffix',
                    'idmapagency',
                ]);
        }

        return DB::table('document')
            ->whereRaw('1 = 0')
            ->selectRaw(
                'NULL as id, IDdoc, NULL as assignment_suffix, '
                . 'NULL as idmapagency'
            );
    };

    $makeLatestDistribution = function () use ($hasDistributionAssignment) {
        $query = DB::table('distribution as notificationDx')
            ->select([
                'notificationDx.IDdoc',
                DB::raw(
                    $hasDistributionAssignment
                        ? 'notificationDx.assignment_id'
                        : 'NULL as assignment_id'
                ),
                DB::raw(
                    'MAX(CAST(notificationDx.IDdist AS UNSIGNED)) '
                    . 'as latest_IDdist'
                ),
            ])
            ->groupBy('notificationDx.IDdoc');

        if ($hasDistributionAssignment) {
            $query->groupBy('notificationDx.assignment_id');
        }

        return $query;
    };

    $buildNotificationBase = function () use (
        $makeAssignmentSource,
        $makeLatestDistribution
    ) {
        return DB::table('document as d')
            ->leftJoinSub(
                $makeAssignmentSource(),
                'assignment',
                function ($join) {
                    $join->on('assignment.IDdoc', '=', 'd.IDdoc');
                }
            )
            ->leftJoin('lu_doctype as dt', 'dt.ID', '=', 'd.IDdoctype')
            ->leftJoin(
                'lu_office as fromOffice',
                'fromOffice.ID',
                '=',
                'd.IDfrom'
            )
            ->leftJoinSub(
                $makeLatestDistribution(),
                'notificationLd',
                function ($join) {
                    $join->on('notificationLd.IDdoc', '=', 'd.IDdoc')
                        ->whereRaw(
                            'notificationLd.assignment_id <=> assignment.id'
                        );
                }
            )
            ->leftJoin(
                'distribution as dist',
                'dist.IDdist',
                '=',
                'notificationLd.latest_IDdist'
            )
            ->leftJoin(
                'lu_office as distOffice',
                'distOffice.ID',
                '=',
                'dist.IDoffice'
            )
            ->leftJoin(
                'username as receiveUser',
                'receiveUser.ID',
                '=',
                'dist.confirmuser'
            );
    };

    $applyViewerTag = function ($query) use ($viewerPersonnelIds) {
        $personnelIds = collect($viewerPersonnelIds)
            ->filter(fn ($id) => $id !== null && $id !== '' && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($personnelIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($scope) use ($personnelIds) {
            $scope->where(function ($multiple) use ($personnelIds) {
                $multiple
                    ->whereNotNull('assignment.id')
                    ->where(function ($tag) use ($personnelIds) {
                        $tag
                            ->where(function ($current) use ($personnelIds) {
                                $current
                                    ->whereNotNull('dist.IDdist')
                                    ->whereIn(
                                        'dist.idmapagency',
                                        $personnelIds
                                    );
                            })
                            ->orWhere(function ($fallback) use ($personnelIds) {
                                $fallback
                                    ->whereNull('dist.IDdist')
                                    ->whereIn(
                                        'assignment.idmapagency',
                                        $personnelIds
                                    );
                            });
                    });
            });

            $scope->orWhere(function ($legacy) use ($personnelIds) {
                $legacy
                    ->whereNull('assignment.id')
                    ->where(function ($tag) use ($personnelIds) {
                        $tag
                            ->where(function ($current) use ($personnelIds) {
                                $current
                                    ->whereNotNull('dist.IDdist')
                                    ->whereIn(
                                        'dist.idmapagency',
                                        $personnelIds
                                    );
                            })
                            ->orWhere(function ($fallback) use ($personnelIds) {
                                $fallback
                                    ->whereNull('dist.IDdist')
                                    ->whereIn('d.IDkeeper', $personnelIds);
                            });
                    });
            });
        });
    };

    $applyActiveDistribution = function ($query) use ($trueValues) {
        $query->where(function ($condition) use ($trueValues) {
            $condition->whereNull('dist.YNreturn')
                ->orWhereNotIn('dist.YNreturn', $trueValues);
        });

        $query->where(function ($condition) use ($trueValues) {
            $condition->whereNull('dist.YNpulled')
                ->orWhereNotIn('dist.YNpulled', $trueValues);
        });

        return $query;
    };

    $displayExpression = "CASE
        WHEN assignment.id IS NOT NULL
            AND NULLIF(TRIM(assignment.assignment_suffix), '') IS NOT NULL
        THEN CONCAT(
            CAST(d.IDdoc AS CHAR),
            '-',
            UPPER(TRIM(assignment.assignment_suffix))
        )
        ELSE CAST(d.IDdoc AS CHAR)
    END";

    $notificationSelect = [
        'd.IDdoc',
        'assignment.id as assignment_id',
        DB::raw(
            "UPPER(NULLIF(TRIM(assignment.assignment_suffix), '')) "
            . 'as assignment_suffix'
        ),
        DB::raw($displayExpression . ' as document_no'),
        DB::raw($displayExpression . ' as display_document_no'),
        'd.subject',
        'd.entrydate',
        DB::raw($doctypeCodeColumn . ' as code'),
        'dt.description as doctype',
        'fromOffice.officename as from_office',
        'distOffice.officename as transferred_to',
        'dist.distdate as transfer_date',
    ];

    if (! empty($viewerPersonnelIds)) {
        $viewerQuery = $buildNotificationBase()
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.distdate')
            ->whereNull('dist.confirmdate');

        $applyViewerTag($viewerQuery);
        $applyActiveDistribution($viewerQuery);

        $viewerNotifications = $viewerQuery
            ->select(array_merge($notificationSelect, [
                DB::raw(
                    'DATE_ADD(dist.distdate, INTERVAL 7 DAY) as due_date'
                ),
            ]))
            ->orderBy('dist.distdate')
            ->limit(50)
            ->get()
            ->map(function ($doc) {
                $transferDate = $doc->transfer_date
                    ? Carbon::parse($doc->transfer_date)
                    : null;

                $dueDate = $transferDate
                    ? $transferDate->copy()->addDays(7)
                    : null;

                return [
                    'notification_type' => 'for_receiving',
                    'IDdoc' => $doc->IDdoc,
                    'assignment_id' => $doc->assignment_id,
                    'assignment_suffix' => $doc->assignment_suffix,
                    'document_no' => $doc->document_no,
                    'display_document_no' => $doc->display_document_no,
                    'subject' => $doc->subject,
                    'entrydate' => $doc->entrydate,
                    'code' => $doc->code,
                    'doctype' => $doc->doctype,
                    'from_office' => $doc->from_office,
                    'transferred_to' => $doc->transferred_to,
                    'transfer_date' => $doc->transfer_date,
                    'due_date' => $dueDate
                        ? $dueDate->format('Y-m-d H:i:s')
                        : null,
                    'days_since_transfer' => $transferDate
                        ? $transferDate->diffInDays(now())
                        : 0,
                    'is_overdue' => $dueDate
                        ? now()->greaterThanOrEqualTo($dueDate)
                        : false,
                ];
            })
            ->values();

        /*
         * Three-day reminders are assignment-specific.
         */
        $automaticStatusReminders = $viewerNotifications
            ->filter(function ($item) {
                return (int) ($item['days_since_transfer'] ?? 0) >= 3;
            })
            ->map(function ($item) {
                return array_merge($item, [
                    'current_status' => 'For Receiving',
                    'days_pending' => $item['days_since_transfer'] ?? 0,
                    'status_started_at' => $item['transfer_date'] ?? null,
                ]);
            })
            ->values();

        $receivedReminderQuery = $buildNotificationBase()
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.confirmdate')
            ->whereDate('dist.confirmdate', '<=', now()->subDays(3));

        $applyViewerTag($receivedReminderQuery);
        $applyActiveDistribution($receivedReminderQuery);

        if (Schema::hasTable('dts_document_remarks')) {
            $receivedReminderQuery->whereNotExists(function ($subQuery) use ($hasRemarkAssignment) {
                $subQuery->select(DB::raw(1))
                    ->from('dts_document_remarks as reminderRemark')
                    ->whereColumn('reminderRemark.IDdoc', 'd.IDdoc')
                    ->where('reminderRemark.action_type', 'action_taken')
                    ->whereColumn(
                        'reminderRemark.created_at',
                        '>=',
                        'dist.distdate'
                    );

                if ($hasRemarkAssignment) {
                    $subQuery->whereRaw(
                        'reminderRemark.assignment_id <=> assignment.id'
                    );
                } else {
                    $subQuery->whereNull('assignment.id');
                }
            });
        }

        $receivedReminders = $receivedReminderQuery
            ->select(array_merge($notificationSelect, [
                'dist.confirmdate as received_date',
            ]))
            ->orderBy('dist.confirmdate')
            ->limit(50)
            ->get()
            ->map(function ($doc) {
                $receivedAt = $doc->received_date
                    ? Carbon::parse($doc->received_date)
                    : null;

                return [
                    'notification_type' => 'received',
                    'IDdoc' => $doc->IDdoc,
                    'assignment_id' => $doc->assignment_id,
                    'assignment_suffix' => $doc->assignment_suffix,
                    'document_no' => $doc->document_no,
                    'display_document_no' => $doc->display_document_no,
                    'subject' => $doc->subject,
                    'entrydate' => $doc->entrydate,
                    'code' => $doc->code,
                    'doctype' => $doc->doctype,
                    'from_office' => $doc->from_office,
                    'transferred_to' => $doc->transferred_to,
                    'transfer_date' => $doc->transfer_date,
                    'received_date' => $doc->received_date,
                    'current_status' => 'Received',
                    'status_started_at' => $doc->received_date,
                    'days_pending' => $receivedAt
                        ? $receivedAt->diffInDays(now())
                        : 0,
                    'is_overdue' => true,
                ];
            })
            ->values();

        $automaticStatusReminders = $automaticStatusReminders
            ->concat($receivedReminders)
            ->values();
    }

    if ($currentUserId) {
        $creatorQuery = $buildNotificationBase()
            ->where('d.IDuser', $currentUserId)
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.confirmdate');

        $applyActiveDistribution($creatorQuery);

        $creatorReceivedNotifications = $creatorQuery
            ->select(array_merge($notificationSelect, [
                'dist.confirmdate as received_date',
                'distOffice.officename as received_office',
                DB::raw(
                    "COALESCE(
                        NULLIF(TRIM(receiveUser.name), ''),
                        NULLIF(TRIM(receiveUser.loginname), ''),
                        CONCAT('Account #', receiveUser.ID)
                    ) as received_by"
                ),
            ]))
            ->orderByDesc('dist.confirmdate')
            ->limit(50)
            ->get()
            ->map(function ($doc) {
                return [
                    'notification_type' => 'received_by_addressee',
                    'IDdoc' => $doc->IDdoc,
                    'assignment_id' => $doc->assignment_id,
                    'assignment_suffix' => $doc->assignment_suffix,
                    'document_no' => $doc->document_no,
                    'display_document_no' => $doc->display_document_no,
                    'subject' => $doc->subject,
                    'entrydate' => $doc->entrydate,
                    'code' => $doc->code,
                    'doctype' => $doc->doctype,
                    'from_office' => $doc->from_office,
                    'transferred_to' => $doc->received_office,
                    'received_office' => $doc->received_office,
                    'transfer_date' => $doc->transfer_date,
                    'received_date' => $doc->received_date,
                    'received_by' => $doc->received_by,
                    'is_overdue' => false,
                ];
            })
            ->values();
    }

    return [
        'viewerNotifications' => $viewerNotifications,
        'creatorReceivedNotifications' => $creatorReceivedNotifications,
        'automaticStatusReminders' => $automaticStatusReminders,
    ];
}


private function canAccessMonitoringDashboard(): bool
{
    return in_array((string) optional(Auth::user())->rights, ['1', '3', '4'], true);
}
}