<?php

namespace App\Http\Controllers\Api\Dts;

use App\Http\Controllers\Api\Dts\Concerns\BuildsDtsDocumentQueries;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentController extends Controller
{
    use BuildsDtsDocumentQueries;

    public function index(Request $request): JsonResponse
    {
        [$query, $context] = $this->buildBaseQuery();
        $this->applyCommonFilters(
            $query,
            $request,
            $context,
            true
        );
        $perPage = max(
            1,
            min(
                (int) $request->input('per_page', 25),
                100
            )
        );
        $documents = $query
            ->select(
                $this->documentSelectColumns($context)
            )
            ->orderByDesc(
                DB::raw(
                    'COALESCE(dist.distdate, d.entrydate)'
                )
            )
            ->orderByDesc('d.IDdoc')
            ->orderByRaw(
                "COALESCE(assignment.assignment_suffix, '') ASC"
            )
            ->paginate($perPage)
            ->appends($request->query());
        return response()->json([
            'success' => true,
            'data' => $documents->items(),
            'meta' => [
                'current_page' =>
                    $documents->currentPage(),
                'last_page' =>
                    $documents->lastPage(),
                'per_page' =>
                    $documents->perPage(),
                'total' =>
                    $documents->total(),
                'from' =>
                    $documents->firstItem(),
                'to' =>
                    $documents->lastItem(),
            ],
            'links' => [
                'first' =>
                    $documents->url(1),
                'last' =>
                    $documents->url(
                        $documents->lastPage()
                    ),
                'prev' =>
                    $documents->previousPageUrl(),
                'next' =>
                    $documents->nextPageUrl(),
            ],
            'filters' => [
                'search' =>
                    trim(
                        (string)
                        $request->input(
                            'search',
                            ''
                        )
                    ),
                'status' =>
                    trim(
                        (string)
                        $request->input(
                            'status',
                            ''
                        )
                    ),
                'year' =>
                    $this->normalizedYear($request),
                'personnel_id' =>
                    $request->input(
                        'personnel_id'
                    ),
            ],
        ]);
    }

    public function history(
        Request $request,
        int $document
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Document
        |--------------------------------------------------------------------------
        */
        $documentRow = DB::table('document as d')
            ->leftJoin(
                'username as creator',
                'creator.ID',
                '=',
                'd.IDuser'
            )
            ->leftJoin(
                'lu_office as fromOffice',
                'fromOffice.ID',
                '=',
                'd.IDfrom'
            )
            ->where(
                'd.IDdoc',
                $document
            )
            ->select([
                'd.IDdoc',
                'd.IDuser',
                'd.IDdocstatus',
                'd.entrydate',
                'd.subject',
                'd.remarks',
                DB::raw(
                    "COALESCE(
                        NULLIF(TRIM(creator.name), ''),
                        NULLIF(TRIM(creator.loginname), ''),
                        CONCAT('Account #', creator.ID)
                    ) as created_by_name"
                ),
                'fromOffice.officename as from_office',
            ])
            ->first();
        if (! $documentRow) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found.',
            ], 404);
        }
        /*
        |--------------------------------------------------------------------------
        | Assignment / A-B-C workflow
        |--------------------------------------------------------------------------
        */
        $requestedAssignmentId =
            $request->input('assignment_id');
        $assignmentId =
            $requestedAssignmentId !== null
            && $requestedAssignmentId !== ''
                ? (int) $requestedAssignmentId
                : null;
        $assignment = null;
        $availableAssignments =
            collect();
        if (
            Schema::hasTable(
                'dts_document_assignments'
            )
        ) {
            $availableAssignments =
                DB::table(
                    'dts_document_assignments as assignment'
                )
                    ->leftJoin(
                        'lu_personnel as personnel',
                        'personnel.ID',
                        '=',
                        'assignment.idmapagency'
                    )
                    ->where(
                        'assignment.IDdoc',
                        $document
                    )
                    ->select([
                        'assignment.id',
                        'assignment.assignment_suffix',
                        'assignment.idmapagency as personnel_id',
                        'personnel.name as personnel_name',
                    ])
                    ->orderBy(
                        'assignment.assignment_suffix'
                    )
                    ->get();
            /*
             * Shared DTS number:
             *
             * Example:
             * 185613-A
             * 185613-B
             * 185613-C
             *
             * History must be scoped to one workflow.
             */
            if (
                $assignmentId === null
                && $availableAssignments->isNotEmpty()
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'This document has multiple assignment workflows. Please provide assignment_id.',
                    'document_id' =>
                        $document,
                    'assignments' =>
                        $availableAssignments->map(
                            function ($item) use ($document) {
                                return [
                                    'assignment_id' =>
                                        (int) $item->id,
                                    'document_no' =>
                                        $document
                                        . '-'
                                        . strtoupper(
                                            trim(
                                                (string)
                                                $item->assignment_suffix
                                            )
                                        ),
                                    'assignment_suffix' =>
                                        $item->assignment_suffix,
                                    'personnel_id' =>
                                        $item->personnel_id,
                                    'personnel_name' =>
                                        $item->personnel_name,
                                ];
                            }
                        )->values(),
                ], 422);
            }
            if ($assignmentId !== null) {
                $assignment =
                    $availableAssignments
                        ->firstWhere(
                            'id',
                            $assignmentId
                        );
                if (! $assignment) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Document assignment not found.',
                    ], 404);
                }
            }
        } elseif ($assignmentId !== null) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Document assignment not found.',
            ], 404);
        }
        /*
        |--------------------------------------------------------------------------
        | Display reference
        |--------------------------------------------------------------------------
        */
        $documentNo =
            (string) $document;
        if ($assignment) {
            $suffix =
                strtoupper(
                    trim(
                        (string)
                        $assignment->assignment_suffix
                    )
                );
            if ($suffix !== '') {
                $documentNo .=
                    '-' . $suffix;
            }
        }
        /*
        |--------------------------------------------------------------------------
        | Assignment scope helper
        |--------------------------------------------------------------------------
        */
        $scopeAssignment =
            function (
                $query,
                string $table,
                string $column
            ) use ($assignmentId) {
                if (
                    ! Schema::hasColumn(
                        $table,
                        'assignment_id'
                    )
                ) {
                    /*
                     * Old schema cannot represent
                     * assignment-specific rows.
                     */
                    if ($assignmentId !== null) {
                        $query->whereRaw(
                            '1 = 0'
                        );
                    }
                    return $query;
                }
                if ($assignmentId === null) {
                    $query->whereNull(
                        $column
                    );
                } else {
                    $query->where(
                        $column,
                        $assignmentId
                    );
                }
                return $query;
            };
        /*
        |--------------------------------------------------------------------------
        | Distribution history
        |--------------------------------------------------------------------------
        */
        $trueValues = [
            'True',
            'true',
            'Y',
            'y',
            '1',
            1,
        ];
        $distributionQuery =
            DB::table(
                'distribution as dist'
            )
                ->leftJoin(
                    'lu_office as office',
                    'office.ID',
                    '=',
                    'dist.IDoffice'
                )
                ->leftJoin(
                    'username as transferUser',
                    'transferUser.ID',
                    '=',
                    'dist.IDuser'
                )
                ->leftJoin(
                    'username as receiveUser',
                    'receiveUser.ID',
                    '=',
                    'dist.confirmuser'
                )
                ->leftJoin(
                    'distribution as parentDist',
                    function ($join) {
                        $join
                            ->on(
                                'parentDist.IDdist',
                                '=',
                                'dist.IDparentdist'
                            )
                            ->on(
                                'parentDist.IDdoc',
                                '=',
                                'dist.IDdoc'
                            );
                        if (
                            Schema::hasColumn(
                                'distribution',
                                'assignment_id'
                            )
                        ) {
                            $join->whereRaw(
                                'parentDist.assignment_id <=> dist.assignment_id'
                            );
                        }
                    }
                )
                ->leftJoin(
                    'distribution as returnChildDist',
                    function ($join) {
                        $join
                            ->on(
                                'returnChildDist.IDparentdist',
                                '=',
                                'dist.IDdist'
                            )
                            ->on(
                                'returnChildDist.IDdoc',
                                '=',
                                'dist.IDdoc'
                            );
                        if (
                            Schema::hasColumn(
                                'distribution',
                                'assignment_id'
                            )
                        ) {
                            $join->whereRaw(
                                'returnChildDist.assignment_id <=> dist.assignment_id'
                            );
                        }
                    }
                )
                ->leftJoin(
                    'username as returnUser',
                    'returnUser.ID',
                    '=',
                    'returnChildDist.IDuser'
                )
                ->leftJoin(
                    'username as returnConfirmUser',
                    'returnConfirmUser.ID',
                    '=',
                    'dist.confirmuser'
                )
                ->leftJoin(
                    'lu_personnel as targetPersonnel',
                    'targetPersonnel.ID',
                    '=',
                    'dist.idmapagency'
                )
                ->where(
                    'dist.IDdoc',
                    $document
                );
        $scopeAssignment(
            $distributionQuery,
            'distribution',
            'dist.assignment_id'
        );
        $distributionSelect = [
            'dist.IDdist',
            'dist.IDdoc',
            DB::raw(
                Schema::hasColumn(
                    'distribution',
                    'assignment_id'
                )
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
            DB::raw(
                'COALESCE(
                    dist.confirmuser,
                    returnChildDist.IDuser
                ) as returned_by'
            ),
            'office.officename as office_name',
            'targetPersonnel.name as target_personnel_name',
            DB::raw(
                "COALESCE(
                    NULLIF(TRIM(transferUser.name), ''),
                    NULLIF(TRIM(transferUser.loginname), ''),
                    CONCAT('Account #', transferUser.ID)
                ) as transferred_by_name"
            ),
            DB::raw(
                "COALESCE(
                    NULLIF(TRIM(receiveUser.name), ''),
                    NULLIF(TRIM(receiveUser.loginname), ''),
                    CONCAT('Account #', receiveUser.ID)
                ) as received_by_name"
            ),
            DB::raw(
                "COALESCE(
                    NULLIF(TRIM(returnConfirmUser.name), ''),
                    NULLIF(TRIM(returnConfirmUser.loginname), ''),
                    NULLIF(TRIM(returnUser.name), ''),
                    NULLIF(TRIM(returnUser.loginname), ''),
                    CONCAT(
                        'Account #',
                        COALESCE(
                            dist.confirmuser,
                            returnChildDist.IDuser
                        )
                    )
                ) as returned_by_name"
            ),
        ];
        $distributionRows =
            $distributionQuery
                ->orderBy(
                    'dist.IDdist'
                )
                ->select(
                    $distributionSelect
                )
                ->get();
        /*
         * Ignore legacy rows that existed before
         * this document's actual entry date.
         */
        $entryTimestamp =
            ! empty(
                $documentRow->entrydate
            )
                ? strtotime(
                    (string)
                    $documentRow->entrydate
                )
                : null;
        $distributionRows =
            $distributionRows
                ->filter(
                    function ($row) use (
                        $entryTimestamp
                    ) {
                        if (
                            ! $entryTimestamp
                            || empty(
                                $row->distdate
                            )
                        ) {
                            return true;
                        }
                        $distributionTimestamp =
                            strtotime(
                                (string)
                                $row->distdate
                            );
                        if (
                            ! $distributionTimestamp
                        ) {
                            return true;
                        }
                        return
                            $distributionTimestamp
                            >=
                            $entryTimestamp;
                    }
                )
                ->values();
        /*
        |--------------------------------------------------------------------------
        | Remarks / actions
        |--------------------------------------------------------------------------
        */
        $remarksHistory =
            collect();
        if (
            Schema::hasTable(
                'dts_document_remarks'
            )
        ) {
            $remarkSelect = [
                'remarks.id',
                'remarks.IDdoc',
                DB::raw(
                    Schema::hasColumn(
                        'dts_document_remarks',
                        'assignment_id'
                    )
                        ? 'remarks.assignment_id'
                        : 'NULL as assignment_id'
                ),
                'remarks.remarks',
                'remarks.created_by',
                'remarks.created_at',
                DB::raw(
                    "COALESCE(
                        NULLIF(TRIM(remarkUser.name), ''),
                        NULLIF(TRIM(remarkUser.loginname), ''),
                        CONCAT('Account #', remarkUser.ID)
                    ) as created_by_name"
                ),
            ];
            if (
                Schema::hasColumn(
                    'dts_document_remarks',
                    'action_type'
                )
            ) {
                $remarkSelect[] =
                    'remarks.action_type';
            } else {
                $remarkSelect[] =
                    DB::raw(
                        "'remark' as action_type"
                    );
            }
            if (
                Schema::hasColumn(
                    'dts_document_remarks',
                    'action_type_id'
                )
            ) {
                $remarkSelect[] =
                    'remarks.action_type_id';
                if (
                    Schema::hasTable(
                        'dts_action_types'
                    )
                ) {
                    $remarkSelect[] =
                        'actionType.name as action_name';
                } else {
                    $remarkSelect[] =
                        DB::raw(
                            'NULL as action_name'
                        );
                }
                $remarkSelect[] =
                    DB::raw(
                        Schema::hasColumn(
                            'dts_document_remarks',
                            'action_label'
                        )
                            ? 'remarks.action_label as action_label'
                            : 'NULL as action_label'
                    );
            } else {
                $remarkSelect[] =
                    DB::raw(
                        'NULL as action_type_id'
                    );
                $remarkSelect[] =
                    DB::raw(
                        'NULL as action_name'
                    );
                $remarkSelect[] =
                    DB::raw(
                        'NULL as action_label'
                    );
            }
            $remarksQuery =
                DB::table(
                    'dts_document_remarks as remarks'
                )
                    ->leftJoin(
                        'username as remarkUser',
                        'remarkUser.ID',
                        '=',
                        'remarks.created_by'
                    )
                    ->where(
                        'remarks.IDdoc',
                        $document
                    );
            if (
                Schema::hasColumn(
                    'dts_document_remarks',
                    'action_type_id'
                )
                &&
                Schema::hasTable(
                    'dts_action_types'
                )
            ) {
                $remarksQuery
                    ->leftJoin(
                        'dts_action_types as actionType',
                        'actionType.id',
                        '=',
                        'remarks.action_type_id'
                    );
            }
            $scopeAssignment(
                $remarksQuery,
                'dts_document_remarks',
                'remarks.assignment_id'
            );
            $remarksHistory =
                $remarksQuery
                    ->orderBy(
                        'remarks.created_at'
                    )
                    ->select(
                        $remarkSelect
                    )
                    ->get();
        }
        /*
        |--------------------------------------------------------------------------
        | Files
        |--------------------------------------------------------------------------
        |
        | General activity_logs are intentionally NOT used.
        |
        */
        $uploadedFiles =
            collect();
        if (
            Schema::hasTable(
                'dts_document_files'
            )
        ) {
            $uploadedFiles =
                DB::table(
                    'dts_document_files as files'
                )
                    ->leftJoin(
                        'username as fileUser',
                        'fileUser.ID',
                        '=',
                        'files.uploaded_by'
                    )
                    ->where(
                        'files.IDdoc',
                        $document
                    )
                    ->select([
                        'files.id',
                        'files.IDdoc',
                        'files.IDattachment',
                        'files.type_name',
                        'files.original_name',
                        'files.stored_name',
                        'files.path',
                        'files.mime_type',
                        'files.size',
                        'files.uploaded_by',
                        'files.created_at',
                        DB::raw(
                            "COALESCE(
                                NULLIF(TRIM(fileUser.name), ''),
                                NULLIF(TRIM(fileUser.loginname), ''),
                                CONCAT('Account #', fileUser.ID)
                            ) as uploaded_by_name"
                        ),
                    ])
                    ->orderBy(
                        'files.id'
                    )
                    ->get();
        }
        /*
        |--------------------------------------------------------------------------
        | Completion
        |--------------------------------------------------------------------------
        */
        $isCompleted =
            false;
        $completedAt =
            null;
        $completedBy =
            null;
        $completedByName =
            null;
        /*
         * Completion belongs to the base document.
         * A/B/C assignment workflows are handled
         * independently through their actions.
         */
        if ($assignmentId === null) {
            $hasManualCompletion =
                Schema::hasColumn(
                    'document',
                    'is_completed'
                )
                &&
                Schema::hasColumn(
                    'document',
                    'completed_at'
                );
            $completionSelect = [
                DB::raw(
                    Schema::hasColumn(
                        'document',
                        'is_completed'
                    )
                        ? 'COALESCE(is_completed, 0) as is_completed'
                        : '0 as is_completed'
                ),
                DB::raw(
                    Schema::hasColumn(
                        'document',
                        'completed_at'
                    )
                        ? 'completed_at'
                        : (
                            Schema::hasColumn(
                                'document',
                                'datecleared'
                            )
                                ? 'datecleared as completed_at'
                                : 'NULL as completed_at'
                        )
                ),
                DB::raw(
                    Schema::hasColumn(
                        'document',
                        'completed_by'
                    )
                        ? 'completed_by'
                        : 'NULL as completed_by'
                ),
                'IDdocstatus',
            ];
            $completion =
                DB::table('document')
                    ->where(
                        'IDdoc',
                        $document
                    )
                    ->select(
                        $completionSelect
                    )
                    ->first();
            if ($completion) {
                if ($hasManualCompletion) {
                    $isCompleted =
                        ! empty(
                            $completion->is_completed
                        )
                        ||
                        ! empty(
                            $completion->completed_at
                        );
                } else {
                    $isCompleted =
                        (int)
                        (
                            $completion->IDdocstatus
                            ?? 0
                        )
                        === 6;
                }
                $completedAt =
                    $completion->completed_at
                    ?? null;
                $completedBy =
                    $completion->completed_by
                    ?? null;
                if (
                    ! empty($completedBy)
                ) {
                    $completedByName =
                        DB::table('username')
                            ->where(
                                'ID',
                                $completedBy
                            )
                            ->selectRaw(
                                "COALESCE(
                                    NULLIF(TRIM(name), ''),
                                    NULLIF(TRIM(loginname), ''),
                                    CONCAT('Account #', ID)
                                ) as display_name"
                            )
                            ->value(
                                'display_name'
                            );
                }
            }
        }
        /*
        |--------------------------------------------------------------------------
        | Build unified Action History
        |--------------------------------------------------------------------------
        */
        $history =
            collect();
        $addHistory =
            function (
                string $type,
                string $title,
                ?string $description,
                $date,
                ?string $actor = null,
                ?string $office = null,
                ?string $remarks = null,
                array $files = []
            ) use (
                &$history,
                $document,
                $documentNo,
                $assignmentId
            ) {
                if (empty($date)) {
                    return;
                }
                /*
                 * Receive does not have remarks.
                 */
                if (
                    strtolower(
                        trim($type)
                    )
                    === 'received'
                ) {
                    $remarks = null;
                }
                $history->push([
                    'id' =>
                        $type
                        . '-'
                        . $document
                        . '-'
                        . md5(
                            $title
                            . '|'
                            . $date
                            . '|'
                            . ($actor ?? '')
                            . '|'
                            . ($office ?? '')
                        ),
                    'document_id' =>
                        $document,
                    'document_no' =>
                        $documentNo,
                    'assignment_id' =>
                        $assignmentId,
                    'type' =>
                        $type,
                    'title' =>
                        $title,
                    'description' =>
                        $description,
                    'date' =>
                        $date,
                    'actor' =>
                        $actor
                        ?: 'System',
                    'office' =>
                        $office,
                    'remarks' =>
                        $remarks,
                    'files' =>
                        $files,
                ]);
            };
        /*
         * Document Created
         */
        $addHistory(
            'created',
            'Document Created',
            'Document was encoded in the tracking system.',
            $documentRow->entrydate,
            $documentRow->created_by_name
                ?? (
                    ! empty(
                        $documentRow->IDuser
                    )
                        ? 'Account #'
                            . $documentRow->IDuser
                        : 'System'
                ),
            $documentRow->from_office,
            $documentRow->remarks
        );
        /*
         * Distribution events
         */
        foreach (
            $distributionRows
            as $distRow
        ) {
            $transferredBy =
                $distRow->transferred_by_name
                ?? (
                    $distRow->IDuser
                        ? 'Account #'
                            . $distRow->IDuser
                        : null
                );
            $receivedBy =
                $distRow->received_by_name
                ?? (
                    $distRow->confirmuser
                        ? 'Account #'
                            . $distRow->confirmuser
                        : null
                );
            $returnedBy =
                $distRow->returned_by_name
                ?? (
                    $distRow->returned_by
                        ? 'Account #'
                            . $distRow->returned_by
                        : null
                )
                ?? $transferredBy;
            $isReturnChild =
                ! empty(
                    $distRow->IDparentdist
                )
                &&
                (
                    in_array(
                        (string)
                        (
                            $distRow->parent_YNreturn
                            ?? ''
                        ),
                        array_map(
                            'strval',
                            $trueValues
                        ),
                        true
                    )
                    ||
                    ! empty(
                        $distRow->parent_returndate
                    )
                );
            /*
             * Initial distribution is part of
             * Document Created.
             *
             * Only child distributions that are
             * genuine transfers appear here.
             */
            if (
                ! empty(
                    $distRow->IDparentdist
                )
                &&
                ! $isReturnChild
            ) {
                $transferTarget =
                    $distRow
                        ->target_personnel_name
                        ? (
                            $distRow
                                ->target_personnel_name
                            . (
                                $distRow->office_name
                                    ? ' — '
                                        . $distRow
                                            ->office_name
                                    : ''
                            )
                        )
                        : (
                            $distRow->office_name
                            ?? (
                                'Office #'
                                . $distRow
                                    ->IDoffice
                            )
                        );
                $addHistory(
                    'transferred',
                    'Transferred Document',
                    'Document was transferred to '
                        . $transferTarget
                        . '.',
                    $distRow->distdate,
                    $transferredBy,
                    $distRow->office_name,
                    $distRow->remarks
                );
            }
            /*
             * Received
             */
            $addHistory(
                'received',
                'Received Document',
                'Document was tagged as received.',
                $distRow->confirmdate,
                $receivedBy,
                $distRow->office_name,
                null
            );
            /*
             * Returned
             */
            $isReturned =
                in_array(
                    (string)
                    (
                        $distRow->YNreturn
                        ?? ''
                    ),
                    array_map(
                        'strval',
                        $trueValues
                    ),
                    true
                )
                ||
                ! empty(
                    $distRow->returndate
                );
            if ($isReturned) {
                $addHistory(
                    'returned',
                    'Returned Document',
                    'Document was returned.',
                    $distRow->returndate
                        ?: $distRow->distdate,
                    $returnedBy,
                    $distRow->office_name,
                    $distRow->remarks
                );
            }
            /*
             * Pulled Out
             */
            $isPulled =
                in_array(
                    (string)
                    (
                        $distRow->YNpulled
                        ?? ''
                    ),
                    array_map(
                        'strval',
                        $trueValues
                    ),
                    true
                );
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
        }
        /*
         * Remarks and Actions
         */
        foreach (
            $remarksHistory
            as $remark
        ) {
            $actionType =
                strtolower(
                    trim(
                        (string)
                        (
                            $remark->action_type
                            ?? 'remark'
                        )
                    )
                );
            $actor =
                $remark->created_by_name
                ?? (
                    $remark->created_by
                        ? 'Account #'
                            . $remark->created_by
                        : null
                );
            if (
                in_array(
                    $actionType,
                    [
                        'action_saved',
                        'action_taken',
                    ],
                    true
                )
            ) {
                $actionLabel =
                    trim(
                        (string)
                        (
                            $remark->action_label
                            ?? ''
                        )
                    );
                $actionName =
                    trim(
                        (string)
                        (
                            $remark->action_name
                            ?? ''
                        )
                    );
                $actionTarget =
                    $actionLabel !== ''
                        ? $actionLabel
                        : (
                            $actionName !== ''
                                ? $actionName
                                : 'selected action'
                        );
                $isClosed =
                    $actionType
                    === 'action_taken';
                $addHistory(
                    'action',
                    $isClosed
                        ? 'Action Closed'
                        : 'Action Saved',
                    (
                        $isClosed
                            ? 'Closed action: '
                            : 'Saved action: '
                    )
                        . $actionTarget
                        . '.',
                    $remark->created_at,
                    $actor,
                    null,
                    $remark->remarks
                );
                continue;
            }
            $addHistory(
                'remark',
                'Added Remark',
                'A remark was added to this document.',
                $remark->created_at,
                $actor,
                null,
                $remark->remarks
            );
        }
        /*
         * Re-attached files only.
         *
         * Initial attachments are considered part
         * of Document Created.
         */
        foreach (
            $uploadedFiles
            as $file
        ) {
            $isReattached =
                strtolower(
                    trim(
                        (string)
                        (
                            $file->type_name
                            ?? ''
                        )
                    )
                )
                ===
                're-attached file';
            if (! $isReattached) {
                continue;
            }
            $fileData = [
                'id' =>
                    $file->id,
                'attachment_id' =>
                    $file->IDattachment,
                'type_name' =>
                    $file->type_name,
                'original_name' =>
                    $file->original_name,
                'stored_name' =>
                    $file->stored_name,
                'mime_type' =>
                    $file->mime_type,
                'size' =>
                    $file->size,
                'uploaded_by' =>
                    $file->uploaded_by,
                'uploaded_by_name' =>
                    $file->uploaded_by_name,
                'created_at' =>
                    $file->created_at,
            ];
            $addHistory(
                'reattached',
                'Re-attached File',
                'File was re-attached: '
                    . (
                        $file->original_name
                        ?? $file->stored_name
                        ?? 'Uploaded file'
                    ),
                $file->created_at,
                $file->uploaded_by_name
                    ?? (
                        ! empty(
                            $file->uploaded_by
                        )
                            ? 'Account #'
                                . $file->uploaded_by
                            : null
                    ),
                null,
                null,
                [
                    $fileData,
                ]
            );
        }
        /*
         * Completed base document
         */
        if ($isCompleted) {
            $addHistory(
                'completed',
                'Completed Document',
                'Document was officially marked as completed.',
                $completedAt,
                $completedByName
                    ?? (
                        ! empty(
                            $completedBy
                        )
                            ? 'Account #'
                                . $completedBy
                            : 'System'
                    ),
                null,
                null
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Final sorting
        |--------------------------------------------------------------------------
        */
        $history =
            $history
                ->map(
                    function ($item) {
                        /*
                         * Final protection:
                         * Received rows never expose
                         * distribution remarks.
                         */
                        if (
                            strtolower(
                                trim(
                                    (string)
                                    (
                                        $item['type']
                                        ?? ''
                                    )
                                )
                            )
                            === 'received'
                        ) {
                            $item['remarks'] =
                                null;
                        }
                        return $item;
                    }
                )
                ->sortByDesc(
                    function ($item) {
                        return
                            strtotime(
                                (string)
                                (
                                    $item['date']
                                    ?? ''
                                )
                            )
                            ?: 0;
                    }
                )
                ->values();
        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'success' => true,
            'data' => [
                'document_id' =>
                    $document,
                'document_no' =>
                    $documentNo,
                'assignment_id' =>
                    $assignmentId,
                'assignment_suffix' =>
                    $assignment
                        ? $assignment
                            ->assignment_suffix
                        : null,
                'subject' =>
                    $documentRow->subject,
                'history_count' =>
                    $history->count(),
                'history' =>
                    $history,
            ],
        ]);
    }

    public function remarks(
        Request $request,
        int $document
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Document
        |--------------------------------------------------------------------------
        */
        $documentRow = DB::table('document')
            ->where('IDdoc', $document)
            ->select([
                'IDdoc',
                'subject',
                'remarks',
            ])
            ->first();
        if (! $documentRow) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found.',
            ], 404);
        }
        /*
        |--------------------------------------------------------------------------
        | Assignment / A-B-C workflow
        |--------------------------------------------------------------------------
        */
        $requestedAssignmentId = $request->input('assignment_id');
        $assignmentId =
            $requestedAssignmentId !== null
            && $requestedAssignmentId !== ''
                ? (int) $requestedAssignmentId
                : null;
        $assignment = null;
        $availableAssignments = collect();
        if (Schema::hasTable('dts_document_assignments')) {
            $availableAssignments =
                DB::table('dts_document_assignments as assignment')
                    ->leftJoin(
                        'lu_personnel as personnel',
                        'personnel.ID',
                        '=',
                        'assignment.idmapagency'
                    )
                    ->where('assignment.IDdoc', $document)
                    ->select([
                        'assignment.id',
                        'assignment.assignment_suffix',
                        'assignment.idmapagency as personnel_id',
                        'personnel.name as personnel_name',
                    ])
                    ->orderBy('assignment.assignment_suffix')
                    ->get();
            /*
             * Shared document workflows must never be mixed.
             */
            if (
                $assignmentId === null
                && $availableAssignments->isNotEmpty()
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'This document has multiple assignment workflows. Please provide assignment_id.',
                    'document_id' => $document,
                    'assignments' =>
                        $availableAssignments
                            ->map(function ($item) use ($document) {
                                $suffix =
                                    strtoupper(
                                        trim(
                                            (string) $item->assignment_suffix
                                        )
                                    );
                                return [
                                    'assignment_id' => (int) $item->id,
                                    'document_no' => $document . '-' . $suffix,
                                    'assignment_suffix' => $suffix,
                                    'personnel_id' => $item->personnel_id,
                                    'personnel_name' => $item->personnel_name,
                                ];
                            })
                            ->values(),
                ], 422);
            }
            if ($assignmentId !== null) {
                $assignment =
                    $availableAssignments
                        ->firstWhere('id', $assignmentId);
                if (! $assignment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Document assignment not found.',
                    ], 404);
                }
            }
        } elseif ($assignmentId !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Document assignment not found.',
            ], 404);
        }
        /*
        |--------------------------------------------------------------------------
        | Display reference
        |--------------------------------------------------------------------------
        */
        $documentNo = (string) $document;
        if ($assignment) {
            $suffix =
                strtoupper(
                    trim(
                        (string) $assignment->assignment_suffix
                    )
                );
            if ($suffix !== '') {
                $documentNo .= '-' . $suffix;
            }
        }
        /*
        |--------------------------------------------------------------------------
        | Remarks table
        |--------------------------------------------------------------------------
        */
        if (! Schema::hasTable('dts_document_remarks')) {
            return response()->json([
                'success' => true,
                'data' => [
                    'document_id' => $document,
                    'document_no' => $documentNo,
                    'assignment_id' => $assignmentId,
                    'assignment_suffix' =>
                        $assignment
                            ? $assignment->assignment_suffix
                            : null,
                    'subject' => $documentRow->subject,
                    'document_remarks' => $documentRow->remarks,
                    'remarks_count' => 0,
                    'remarks' => [],
                ],
            ]);
        }
        /*
        |--------------------------------------------------------------------------
        | Remarks query
        |--------------------------------------------------------------------------
        */
        $query =
            DB::table('dts_document_remarks as remark')
                ->leftJoin(
                    'username as remarkUser',
                    'remarkUser.ID',
                    '=',
                    'remark.created_by'
                )
                ->where('remark.IDdoc', $document);
        if (
            Schema::hasColumn(
                'dts_document_remarks',
                'assignment_id'
            )
        ) {
            if ($assignmentId === null) {
                $query->whereNull('remark.assignment_id');
            } else {
                $query->where(
                    'remark.assignment_id',
                    $assignmentId
                );
            }
        } elseif ($assignmentId !== null) {
            /*
             * Old schema cannot safely separate A/B/C remarks.
             */
            $query->whereRaw('1 = 0');
        }
        $hasActionType =
            Schema::hasColumn(
                'dts_document_remarks',
                'action_type'
            );
        $hasActionTypeId =
            Schema::hasColumn(
                'dts_document_remarks',
                'action_type_id'
            );
        $hasActionLabel =
            Schema::hasColumn(
                'dts_document_remarks',
                'action_label'
            );
        $hasActionStatus =
            Schema::hasColumn(
                'dts_document_remarks',
                'action_status'
            );
        $hasActionCompletedAt =
            Schema::hasColumn(
                'dts_document_remarks',
                'action_completed_at'
            );
        $hasActionCompletedBy =
            Schema::hasColumn(
                'dts_document_remarks',
                'action_completed_by'
            );
        $hasUpdatedAt =
            Schema::hasColumn(
                'dts_document_remarks',
                'updated_at'
            );
        if (
            $hasActionTypeId
            && Schema::hasTable('dts_action_types')
        ) {
            $query->leftJoin(
                'dts_action_types as actionType',
                'actionType.id',
                '=',
                'remark.action_type_id'
            );
        }
        if ($hasActionCompletedBy) {
            $query->leftJoin(
                'username as completedUser',
                'completedUser.ID',
                '=',
                'remark.action_completed_by'
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Select
        |--------------------------------------------------------------------------
        */
        $select = [
            'remark.id',
            'remark.IDdoc',
            DB::raw(
                Schema::hasColumn(
                    'dts_document_remarks',
                    'assignment_id'
                )
                    ? 'remark.assignment_id'
                    : 'NULL as assignment_id'
            ),
            'remark.remarks',
            'remark.created_by',
            'remark.created_at',
            DB::raw(
                "COALESCE(
                    NULLIF(TRIM(remarkUser.name), ''),
                    NULLIF(TRIM(remarkUser.loginname), ''),
                    CONCAT('Account #', remarkUser.ID)
                ) as created_by_name"
            ),
            DB::raw(
                $hasActionType
                    ? 'remark.action_type'
                    : "'remark' as action_type"
            ),
            DB::raw(
                $hasActionTypeId
                    ? 'remark.action_type_id'
                    : 'NULL as action_type_id'
            ),
        ];
        if (
            $hasActionTypeId
            && Schema::hasTable('dts_action_types')
        ) {
            $select[] = 'actionType.name as action_name';
        } else {
            $select[] = DB::raw('NULL as action_name');
        }
        $select[] =
            DB::raw(
                $hasActionLabel
                    ? 'remark.action_label'
                    : 'NULL as action_label'
            );
        $select[] =
            DB::raw(
                $hasActionStatus
                    ? 'remark.action_status'
                    : 'NULL as action_status'
            );
        $select[] =
            DB::raw(
                $hasActionCompletedAt
                    ? 'remark.action_completed_at'
                    : 'NULL as action_completed_at'
            );
        $select[] =
            DB::raw(
                $hasActionCompletedBy
                    ? 'remark.action_completed_by'
                    : 'NULL as action_completed_by'
            );
        if ($hasActionCompletedBy) {
            $select[] =
                DB::raw(
                    "COALESCE(
                        NULLIF(TRIM(completedUser.name), ''),
                        NULLIF(TRIM(completedUser.loginname), ''),
                        CONCAT('Account #', completedUser.ID)
                    ) as action_completed_by_name"
                );
        } else {
            $select[] =
                DB::raw('NULL as action_completed_by_name');
        }
        $select[] =
            DB::raw(
                $hasUpdatedAt
                    ? 'remark.updated_at'
                    : 'NULL as updated_at'
            );
        /*
        |--------------------------------------------------------------------------
        | Retrieve
        |--------------------------------------------------------------------------
        */
        $remarks =
            $query
                ->select($select)
                ->orderByDesc('remark.created_at')
                ->orderByDesc('remark.id')
                ->get()
                ->map(function ($row) use ($documentNo) {
                    $actionType =
                        strtolower(
                            trim(
                                (string) (
                                    $row->action_type
                                    ?? 'remark'
                                )
                            )
                        );
                    $typeLabel =
                        match ($actionType) {
                            'action_saved' => 'First Action',
                            'action_taken' => 'Final Action',
                            default => 'Remark',
                        };
                    return [
                        'id' => $row->id,
                        'document_id' => (int) $row->IDdoc,
                        'document_no' => $documentNo,
                        'assignment_id' =>
                            $row->assignment_id !== null
                                ? (int) $row->assignment_id
                                : null,
                        'remarks' => $row->remarks,
                        'action_type' => $actionType,
                        'type_label' => $typeLabel,
                        'action_type_id' => $row->action_type_id,
                        'action_name' => $row->action_name,
                        'action_label' => $row->action_label,
                        'action_status' => $row->action_status,
                        'action_completed_at' =>
                            $row->action_completed_at,
                        'action_completed_by' =>
                            $row->action_completed_by,
                        'action_completed_by_name' =>
                            $row->action_completed_by_name,
                        'created_by' => $row->created_by,
                        'created_by_name' => $row->created_by_name,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                })
                ->values();
        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'success' => true,
            'data' => [
                'document_id' => $document,
                'document_no' => $documentNo,
                'assignment_id' => $assignmentId,
                'assignment_suffix' =>
                    $assignment
                        ? $assignment->assignment_suffix
                        : null,
                'subject' => $documentRow->subject,
                'document_remarks' => $documentRow->remarks,
                'remarks_count' => $remarks->count(),
                'remarks' => $remarks,
            ],
        ]);
    }

    public function attachments(
        Request $request,
        int $document
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Document
        |--------------------------------------------------------------------------
        */
        $documentRow = DB::table('document')
            ->where('IDdoc', $document)
            ->select([
                'IDdoc',
                'subject',
            ])
            ->first();
        if (! $documentRow) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found.',
            ], 404);
        }
        /*
        |--------------------------------------------------------------------------
        | Assignment / A-B-C workflow
        |--------------------------------------------------------------------------
        */
        $requestedAssignmentId = $request->input('assignment_id');
        $assignmentId =
            $requestedAssignmentId !== null
            && $requestedAssignmentId !== ''
                ? (int) $requestedAssignmentId
                : null;
        $assignment = null;
        $availableAssignments = collect();
        if (Schema::hasTable('dts_document_assignments')) {
            $availableAssignments =
                DB::table('dts_document_assignments as assignment')
                    ->leftJoin(
                        'lu_personnel as personnel',
                        'personnel.ID',
                        '=',
                        'assignment.idmapagency'
                    )
                    ->where('assignment.IDdoc', $document)
                    ->select([
                        'assignment.id',
                        'assignment.assignment_suffix',
                        'assignment.idmapagency as personnel_id',
                        'personnel.name as personnel_name',
                    ])
                    ->orderBy('assignment.assignment_suffix')
                    ->get();
            if (
                $assignmentId === null
                && $availableAssignments->isNotEmpty()
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'This document has multiple assignment workflows. Please provide assignment_id.',
                    'document_id' => $document,
                    'assignments' =>
                        $availableAssignments
                            ->map(function ($item) use ($document) {
                                $suffix = strtoupper(
                                    trim(
                                        (string) $item->assignment_suffix
                                    )
                                );
                                return [
                                    'assignment_id' => (int) $item->id,
                                    'document_no' => $document . '-' . $suffix,
                                    'assignment_suffix' => $suffix,
                                    'personnel_id' => $item->personnel_id,
                                    'personnel_name' => $item->personnel_name,
                                ];
                            })
                            ->values(),
                ], 422);
            }
            if ($assignmentId !== null) {
                $assignment =
                    $availableAssignments
                        ->firstWhere('id', $assignmentId);
                if (! $assignment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Document assignment not found.',
                    ], 404);
                }
            }
        } elseif ($assignmentId !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Document assignment not found.',
            ], 404);
        }
        /*
        |--------------------------------------------------------------------------
        | Display reference
        |--------------------------------------------------------------------------
        */
        $documentNo = (string) $document;
        if ($assignment) {
            $suffix = strtoupper(
                trim(
                    (string) $assignment->assignment_suffix
                )
            );
            if ($suffix !== '') {
                $documentNo .= '-' . $suffix;
            }
        }
        /*
        |--------------------------------------------------------------------------
        | Attachments table
        |--------------------------------------------------------------------------
        */
        if (! Schema::hasTable('dts_document_files')) {
            return response()->json([
                'success' => true,
                'data' => [
                    'document_id' => $document,
                    'document_no' => $documentNo,
                    'assignment_id' => $assignmentId,
                    'assignment_suffix' =>
                        $assignment
                            ? $assignment->assignment_suffix
                            : null,
                    'subject' => $documentRow->subject,
                    'attachment_scope' => 'base_document',
                    'attachments_count' => 0,
                    'attachments' => [],
                ],
            ]);
        }
        /*
        |--------------------------------------------------------------------------
        | Attachment query
        |--------------------------------------------------------------------------
        |
        | Existing DTS files are primarily stored at the base-document level.
        | If assignment_id is added to the file table later, base-document files
        | remain visible to every A/B/C workflow while assignment-specific files
        | are limited to the requested assignment.
        |
        */
        $hasAssignmentId = Schema::hasColumn(
            'dts_document_files',
            'assignment_id'
        );
        $query =
            DB::table('dts_document_files as files')
                ->leftJoin(
                    'username as fileUser',
                    'fileUser.ID',
                    '=',
                    'files.uploaded_by'
                )
                ->where('files.IDdoc', $document);
        if ($hasAssignmentId) {
            if ($assignmentId === null) {
                $query->whereNull('files.assignment_id');
            } else {
                $query->where(function ($scope) use ($assignmentId) {
                    $scope
                        ->whereNull('files.assignment_id')
                        ->orWhere(
                            'files.assignment_id',
                            $assignmentId
                        );
                });
            }
        }
        $select = [
            'files.id',
            'files.IDdoc',
            'files.IDattachment',
            'files.type_name',
            'files.original_name',
            'files.stored_name',
            'files.mime_type',
            'files.size',
            'files.uploaded_by',
            'files.created_at',
            DB::raw(
                $hasAssignmentId
                    ? 'files.assignment_id'
                    : 'NULL as assignment_id'
            ),
            DB::raw(
                "COALESCE(
                    NULLIF(TRIM(fileUser.name), ''),
                    NULLIF(TRIM(fileUser.loginname), ''),
                    CONCAT('Account #', fileUser.ID)
                ) as uploaded_by_name"
            ),
        ];
        $attachments =
            $query
                ->select($select)
                ->orderByDesc('files.created_at')
                ->orderByDesc('files.id')
                ->get()
                ->map(function ($file) use ($documentNo) {
                    $typeName = trim(
                        (string) ($file->type_name ?? '')
                    );
                    $isReattached =
                        strtolower($typeName)
                        === 're-attached file';
                    return [
                        'id' => (int) $file->id,
                        'document_id' => (int) $file->IDdoc,
                        'document_no' => $documentNo,
                        'assignment_id' =>
                            $file->assignment_id !== null
                                ? (int) $file->assignment_id
                                : null,
                        'scope' =>
                            $file->assignment_id !== null
                                ? 'assignment'
                                : 'base_document',
                        'attachment_id' => $file->IDattachment,
                        'type_name' => $file->type_name,
                        'is_reattached' => $isReattached,
                        'original_name' => $file->original_name,
                        'stored_name' => $file->stored_name,
                        'mime_type' => $file->mime_type,
                        'size' => $file->size !== null
                            ? (int) $file->size
                            : null,
                        'uploaded_by' => $file->uploaded_by,
                        'uploaded_by_name' => $file->uploaded_by_name,
                        'created_at' => $file->created_at,
                    ];
                })
                ->values();
        return response()->json([
            'success' => true,
            'data' => [
                'document_id' => $document,
                'document_no' => $documentNo,
                'assignment_id' => $assignmentId,
                'assignment_suffix' =>
                    $assignment
                        ? $assignment->assignment_suffix
                        : null,
                'subject' => $documentRow->subject,
                'attachment_scope' =>
                    $hasAssignmentId
                        ? 'base_document_and_assignment'
                        : 'base_document',
                'attachments_count' => $attachments->count(),
                'attachments' => $attachments,
            ],
        ]);
    }

    public function show(
        Request $request,
        int $document
    ): JsonResponse {
        [$query, $context] =
            $this->buildBaseQuery();
        $query->where(
            'd.IDdoc',
            $document
        );
        $assignmentId =
            $request->input('assignment_id');
        if (
            $assignmentId !== null
            && $assignmentId !== ''
        ) {
            $query->where(
                'assignment.id',
                (int) $assignmentId
            );
        }
        $rows = $query
            ->select(
                $this->documentSelectColumns(
                    $context
                )
            )
            ->orderByRaw(
                "COALESCE(assignment.assignment_suffix, '') ASC"
            )
            ->get();
        if ($rows->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Document not found.',
            ], 404);
        }
        $first = $rows->first();
        return response()->json([
            'success' => true,
            'data' => [
                'document_id' =>
                    (int) $first->IDdoc,
                'subject' =>
                    $first->subject,
                'regarding' =>
                    $first->regarding,
                'entry_date' =>
                    $first->entry_date,
                'classification' =>
                    $first->classification,
                'document_type' =>
                    $first->document_type,
                'from_office' =>
                    $first->from_office,
                'to_office' =>
                    $first->to_office,
                'workflow_count' =>
                    $rows->count(),
                'workflows' =>
                    $rows->values(),
            ],
        ]);
    }
}
