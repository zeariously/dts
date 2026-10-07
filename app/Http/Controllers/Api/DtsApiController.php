<?php



namespace App\Http\Controllers\Api;



use App\Http\Controllers\Controller;

use Illuminate\Database\Query\Builder;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Schema;



class DtsApiController extends Controller

{

    public function health(): JsonResponse

    {

        return response()->json([

            'success' => true,

            'service' => 'DTS API',

            'version' => 'v1',

            'status' => 'ok',

            'server_time' => now()->toIso8601String(),

        ]);

    }



    public function summary(Request $request): JsonResponse

    {

        [$base, $context] = $this->buildBaseQuery();



        $this->applyCommonFilters($base, $request, $context, false);



        /*

         * Build the workflow status first, then aggregate from a derived table.

         *

         * This avoids MySQL ONLY_FULL_GROUP_BY errors caused by grouping

         * directly on the CASE expression that references document and

         * distribution columns such as d.is_completed.

         */

        $statusRows = clone $base;



        $statusRows->selectRaw(

            '(' . $context['status_expression'] . ') as workflow_status'

        );



        $rows = DB::query()

            ->fromSub($statusRows, 'dts_status_rows')

            ->select('workflow_status')

            ->selectRaw('COUNT(*) as total')

            ->groupBy('workflow_status')

            ->get();



        $summary = [

            'total' => 0,

            'for_receiving' => 0,

            'received' => 0,

            'addressed' => 0,

            'returned' => 0,

            'pulled_out' => 0,

            'completed' => 0,

            'pending' => 0,

        ];



        foreach ($rows as $row) {

            $key = strtolower(trim((string) $row->workflow_status));

            $key = str_replace([' ', '-'], '_', $key);



            if (array_key_exists($key, $summary)) {

                $summary[$key] = (int) $row->total;

            }



            $summary['total'] += (int) $row->total;

        }



        return response()->json([

            'success' => true,

            'data' => $summary,

            'filters' => [

                'year' => $this->normalizedYear($request),

                'personnel_id' => $request->input('personnel_id'),

            ],

        ]);

    }



    public function documents(Request $request): JsonResponse

    {

        [$query, $context] = $this->buildBaseQuery();



        $this->applyCommonFilters($query, $request, $context, true);



        $perPage = max(

            1,

            min((int) $request->input('per_page', 25), 100)

        );



        $documents = $query

            ->select($this->documentSelectColumns($context))

            ->orderByDesc(DB::raw('COALESCE(dist.distdate, d.entrydate)'))

            ->orderByDesc('d.IDdoc')

            ->orderByRaw("COALESCE(assignment.assignment_suffix, '') ASC")

            ->paginate($perPage)

            ->appends($request->query());



        return response()->json([

            'success' => true,

            'data' => $documents->items(),

            'meta' => [

                'current_page' => $documents->currentPage(),

                'last_page' => $documents->lastPage(),

                'per_page' => $documents->perPage(),

                'total' => $documents->total(),

                'from' => $documents->firstItem(),

                'to' => $documents->lastItem(),

            ],

            'links' => [

                'first' => $documents->url(1),

                'last' => $documents->url($documents->lastPage()),

                'prev' => $documents->previousPageUrl(),

                'next' => $documents->nextPageUrl(),

            ],

            'filters' => [

                'search' => trim((string) $request->input('search', '')),

                'status' => trim((string) $request->input('status', '')),

                'year' => $this->normalizedYear($request),

                'personnel_id' => $request->input('personnel_id'),

            ],

        ]);

    }



    public function show(Request $request, int $document): JsonResponse

    {

        [$query, $context] = $this->buildBaseQuery();



        $query->where('d.IDdoc', $document);



        $assignmentId = $request->input('assignment_id');



        if ($assignmentId !== null && $assignmentId !== '') {

            $query->where('assignment.id', (int) $assignmentId);

        }



        $rows = $query

            ->select($this->documentSelectColumns($context))

            ->orderByRaw("COALESCE(assignment.assignment_suffix, '') ASC")

            ->get();



        if ($rows->isEmpty()) {

            return response()->json([

                'success' => false,

                'message' => 'Document not found.',

            ], 404);

        }



        $first = $rows->first();



        return response()->json([

            'success' => true,

            'data' => [

                'document_id' => (int) $first->IDdoc,

                'subject' => $first->subject,

                'regarding' => $first->regarding,

                'entry_date' => $first->entry_date,

                'classification' => $first->classification,

                'document_type' => $first->document_type,

                'from_office' => $first->from_office,

                'to_office' => $first->to_office,

                'workflow_count' => $rows->count(),

                'workflows' => $rows->values(),

            ],

        ]);

    }



    public function personnel(Request $request): JsonResponse

    {

        if (! Schema::hasTable('lu_personnel')) {

            return response()->json([

                'success' => true,

                'data' => [],

            ]);

        }



        $query = DB::table('lu_personnel as p');



        if (Schema::hasTable('lu_office')) {

            $query->leftJoin('lu_office as o', 'o.ID', '=', 'p.IDoffice');

        }



        $search = trim((string) $request->input('search', ''));



        if ($search !== '') {

            $like = '%' . $search . '%';



            $query->where(function ($filter) use ($like) {

                $filter->where('p.name', 'like', $like);



                if (Schema::hasTable('lu_office')) {

                    $filter->orWhere('o.officename', 'like', $like);

                }

            });

        }



        $limit = max(

            1,

            min((int) $request->input('limit', 500), 2000)

        );



        $select = [

            'p.ID as personnel_id',

            'p.name',

            'p.IDoffice as office_id',

        ];



        $select[] = Schema::hasTable('lu_office')

            ? 'o.officename as office_name'

            : DB::raw('NULL as office_name');



        $rows = $query

            ->whereNotNull('p.name')

            ->whereRaw("TRIM(p.name) != ''")

            ->select($select)

            ->orderBy('p.name')

            ->limit($limit)

            ->get();



        return response()->json([

            'success' => true,

            'data' => $rows,

            'meta' => [

                'count' => $rows->count(),

                'limit' => $limit,

            ],

        ]);

    }



    private function buildBaseQuery(): array

    {

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



        $makeLatestDistribution = function () use (

            $hasDistributionAssignment

        ) {

            $query = DB::table('distribution as apiDx')

                ->select([

                    'apiDx.IDdoc',

                    DB::raw(

                        $hasDistributionAssignment

                            ? 'apiDx.assignment_id'

                            : 'NULL as assignment_id'

                    ),

                    DB::raw(

                        'MAX(CAST(apiDx.IDdist AS UNSIGNED)) as latest_IDdist'

                    ),

                ])

                ->groupBy('apiDx.IDdoc');



            if ($hasDistributionAssignment) {

                $query->groupBy('apiDx.assignment_id');

            }



            return $query;

        };



        $makeLatestFinalAction = function () use (

            $makeLatestDistribution,

            $hasRemarkAssignment

        ) {

            if (

                ! Schema::hasTable('dts_document_remarks')

                || ! Schema::hasColumn('dts_document_remarks', 'action_type')

            ) {

                return DB::table('document')

                    ->whereRaw('1 = 0')

                    ->selectRaw(

                        'IDdoc, NULL as assignment_id, NULL as latest_action_id'

                    );

            }



            $remarkAssignmentExpression = $hasRemarkAssignment

                ? 'apiFinalRemark.assignment_id'

                : 'NULL';



            $query = DB::table(

                'dts_document_remarks as apiFinalRemark'

            )

                ->joinSub(

                    $makeLatestDistribution(),

                    'apiFinalCycle',

                    function ($join) use (

                        $remarkAssignmentExpression

                    ) {

                        $join

                            ->on(

                                'apiFinalCycle.IDdoc',

                                '=',

                                'apiFinalRemark.IDdoc'

                            )

                            ->whereRaw(

                                'apiFinalCycle.assignment_id <=> '

                                . $remarkAssignmentExpression

                            );

                    }

                )

                ->join(

                    'distribution as apiFinalDist',

                    'apiFinalDist.IDdist',

                    '=',

                    'apiFinalCycle.latest_IDdist'

                )

                ->where(

                    'apiFinalRemark.action_type',

                    'action_taken'

                )

                ->whereColumn(

                    'apiFinalRemark.created_at',

                    '>=',

                    'apiFinalDist.distdate'

                )

                ->select([

                    'apiFinalRemark.IDdoc',

                    DB::raw(

                        $remarkAssignmentExpression

                        . ' as assignment_id'

                    ),

                    DB::raw(

                        'MAX(apiFinalRemark.id) as latest_action_id'

                    ),

                ])

                ->groupBy('apiFinalRemark.IDdoc');



            if ($hasRemarkAssignment) {

                $query->groupBy(

                    'apiFinalRemark.assignment_id'

                );

            }



            return $query;

        };



        $query = DB::table('document as d')

            ->leftJoinSub(

                $makeAssignmentSource(),

                'assignment',

                function ($join) {

                    $join->on(

                        'assignment.IDdoc',

                        '=',

                        'd.IDdoc'

                    );

                }

            )

            ->leftJoinSub(

                $makeLatestDistribution(),

                'apiLatest',

                function ($join) {

                    $join

                        ->on(

                            'apiLatest.IDdoc',

                            '=',

                            'd.IDdoc'

                        )

                        ->whereRaw(

                            'apiLatest.assignment_id <=> assignment.id'

                        );

                }

            )

            ->leftJoin(

                'distribution as dist',

                'dist.IDdist',

                '=',

                'apiLatest.latest_IDdist'

            )

            ->leftJoin(

                'distribution as returnParent',

                function ($join) use (

                    $hasDistributionAssignment

                ) {

                    $join

                        ->on(

                            'returnParent.IDdist',

                            '=',

                            'dist.IDparentdist'

                        )

                        ->on(

                            'returnParent.IDdoc',

                            '=',

                            'dist.IDdoc'

                        );



                    if ($hasDistributionAssignment) {

                        $join->whereRaw(

                            'returnParent.assignment_id <=> dist.assignment_id'

                        );

                    }

                }

            )

            ->leftJoinSub(

                $makeLatestFinalAction(),

                'apiLatestFinalAction',

                function ($join) {

                    $join

                        ->on(

                            'apiLatestFinalAction.IDdoc',

                            '=',

                            'd.IDdoc'

                        )

                        ->whereRaw(

                            'apiLatestFinalAction.assignment_id <=> assignment.id'

                        );

                }

            )

            ->leftJoin(

                'dts_document_remarks as finalAction',

                'finalAction.id',

                '=',

                'apiLatestFinalAction.latest_action_id'

            )

            ->leftJoin(

                'dts_action_types as finalActionType',

                'finalActionType.id',

                '=',

                'finalAction.action_type_id'

            )

            ->leftJoin(

                'lu_doctype as dt',

                'dt.ID',

                '=',

                'd.IDdoctype'

            )

            ->leftJoin(

                'lu_office as fromOffice',

                'fromOffice.ID',

                '=',

                'd.IDfrom'

            )

            ->leftJoin(

                'lu_office as toOffice',

                'toOffice.ID',

                '=',

                'd.IDfor'

            )

            ->leftJoin(

                'lu_personnel as assignedPersonnel',

                function ($join) {

                    $join->on(

                        'assignedPersonnel.ID',

                        '=',

                        DB::raw(

                            'COALESCE('

                            . 'dist.idmapagency, '

                            . 'assignment.idmapagency, '

                            . 'd.IDkeeper'

                            . ')'

                        )

                    );

                }

            )

            ->leftJoin(

                'lu_office as assignedOffice',

                'assignedOffice.ID',

                '=',

                'assignedPersonnel.IDoffice'

            )

            ->leftJoin(

                'lu_office as currentOffice',

                'currentOffice.ID',

                '=',

                'dist.IDoffice'

            );



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

            AND dist.IDparentdist IS NOT NULL

            AND returnParent.confirmdate IS NOT NULL

            AND returnParent.YNreturn IN ('True', 'true', 'Y', 'y', '1')

            AND returnParent.returndate IS NOT NULL

            AND (

                d.entrydate IS NULL

                OR returnParent.distdate IS NULL

                OR returnParent.distdate >= d.entrydate

            )

            AND (

                d.entrydate IS NULL

                OR returnParent.returndate >= d.entrydate

            )

            AND dist.distdate IS NOT NULL

            AND dist.distdate >= returnParent.returndate

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



        $actionLabelExpression = (

            Schema::hasTable('dts_document_remarks')

            && Schema::hasColumn(

                'dts_document_remarks',

                'action_label'

            )

        )

            ? "CASE
                WHEN finalAction.id IS NULL THEN NULL
                ELSE COALESCE(finalAction.action_label, finalActionType.name, 'Addressed')
              END"

            : "CASE
                WHEN finalAction.id IS NULL THEN NULL
                ELSE COALESCE(finalActionType.name, 'Addressed')
              END";



        return [

            $query,

            [

                'display_expression' => $displayExpression,

                'status_expression' => $statusExpression,

                'pending_return_sql' => $pendingReturnSql,

                'not_pulled_sql' => $notPulledSql,

                'action_label_expression' => $actionLabelExpression,

                'workflow_completion_sql' => $workflowCompletionSql,

            ],

        ];

    }



    private function applyCommonFilters(

        Builder $query,

        Request $request,

        array $context,

        bool $includeStatus

    ): void {

        $year = $this->normalizedYear($request);



        if ($year !== null) {

            $query->whereYear('d.entrydate', $year);

        }



        $personnelId = $request->input('personnel_id');



        if (

            $personnelId !== null

            && $personnelId !== ''

            && is_numeric($personnelId)

        ) {

            $query->whereRaw(

                'COALESCE(dist.idmapagency, assignment.idmapagency, d.IDkeeper) = ?',

                [(int) $personnelId]

            );

        }



        $search = trim(

            (string) $request->input('search', '')

        );



        if ($search !== '') {

            $like = '%' . $search . '%';



            $query->where(function ($filter) use (

                $like,

                $context

            ) {

                $filter

                    ->whereRaw(

                        '('

                        . $context['display_expression']

                        . ') LIKE ?',

                        [$like]

                    )

                    ->orWhere(

                        'd.subject',

                        'like',

                        $like

                    )

                    ->orWhere(

                        'd.regarding',

                        'like',

                        $like

                    )

                    ->orWhere(

                        'dt.description',

                        'like',

                        $like

                    )

                    ->orWhere(

                        'assignedPersonnel.name',

                        'like',

                        $like

                    )

                    ->orWhere(

                        'fromOffice.officename',

                        'like',

                        $like

                    )

                    ->orWhere(

                        'toOffice.officename',

                        'like',

                        $like

                    );

            });

        }



        if ($includeStatus) {

            $status = trim(

                (string) $request->input('status', '')

            );



            if ($status !== '') {

                $normalizedStatus = strtolower(

                    str_replace(

                        ['-', '_'],

                        ' ',

                        $status

                    )

                );



                $query->whereRaw(

                    'LOWER(('

                    . $context['status_expression']

                    . ')) = ?',

                    [$normalizedStatus]

                );

            }

        }

    }



    private function documentSelectColumns(

        array $context

    ): array {

        return [

            'd.IDdoc',

            DB::raw(

                $context['display_expression']

                . ' as document_no'

            ),

            'assignment.id as assignment_id',

            DB::raw(

                "UPPER(NULLIF(TRIM(assignment.assignment_suffix), '')) "

                . 'as assignment_suffix'

            ),

            'd.subject',

            'd.regarding',

            'd.entrydate as entry_date',

            'd.classification',

            'dt.description as document_type',

            'fromOffice.officename as from_office',

            'fromOffice.abbrev as from_office_abbrev',

            'toOffice.officename as to_office',

            DB::raw(

                'COALESCE('

                . 'dist.idmapagency, '

                . 'assignment.idmapagency, '

                . 'd.IDkeeper'

                . ') as assigned_personnel_id'

            ),

            'assignedPersonnel.name as assigned_personnel',

            'assignedOffice.officename as assigned_office',

            'currentOffice.officename as current_office',

            'dist.IDdist as distribution_id',

            'dist.distdate as date_sent',

            'dist.confirmdate as date_received',

            'dist.returndate as return_date',

            'dist.YNreturn as is_returned',

            'dist.YNpulled as is_pulled_out',

            DB::raw(

                $context['status_expression']

                . ' as status'

            ),

            DB::raw(

                $context['action_label_expression']

                . ' as latest_action'

            ),

            'finalAction.remarks as latest_action_remarks',

            'finalAction.created_at as latest_action_at',

            DB::raw(

                "CASE

                    WHEN ({$context['workflow_completion_sql']})

                        OR finalAction.id IS NOT NULL

                    THEN NULL

                    WHEN dist.YNpulled IN ('True', 'true', 'Y', 'y', '1')

                        OR {$context['pending_return_sql']}

                    THEN 0

                    WHEN dist.confirmdate IS NOT NULL

                    THEN GREATEST(

                        DATEDIFF(NOW(), dist.confirmdate),

                        0

                    )

                    WHEN dist.distdate IS NOT NULL

                        AND {$context['not_pulled_sql']}

                    THEN GREATEST(

                        DATEDIFF(NOW(), dist.distdate),

                        0

                    )

                    ELSE 0

                END as days_pending"

            ),

        ];

    }



    private function normalizedYear(

        Request $request

    ): ?int {

        $year = trim(

            (string) $request->input('year', '')

        );



        if (

            $year === ''

            || strtolower($year) === 'all'

        ) {

            return null;

        }



        if (

            ! ctype_digit($year)

            || strlen($year) !== 4

        ) {

            return null;

        }



        $numericYear = (int) $year;



        if (

            $numericYear < 1900

            || $numericYear > 2200

        ) {

            return null;

        }


        return $numericYear;

    }

}
