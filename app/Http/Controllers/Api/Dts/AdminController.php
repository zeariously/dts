<?php

namespace App\Http\Controllers\Api\Dts;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    /**
     * ============================================================
     * ACTIVITY LOGS
     * ============================================================
     *
     * Examples:
     *
     * /api/v1/dts/activity-logs
     *
     * /api/v1/dts/activity-logs
     * ?search=document
     * &module=DTS Documents
     * &action=created document
     * &per_page=25
     */
    public function activityLogs(
        Request $request
    ): JsonResponse {
        if (
            ! Schema::hasTable(
                'activity_logs'
            )
        ) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 25,
                    'total' => 0,
                ],
                'message' =>
                    'Activity logs table is not available.',
            ]);
        }

        $query =
            DB::table(
                'activity_logs'
            );


        /*
         * Search
         */
        $search =
            trim(
                (string)
                $request->input(
                    'search',
                    ''
                )
            );

        if ($search !== '') {
            $like =
                '%' . $search . '%';

            $query->where(
                function ($filter) use ($like) {
                    $filter
                        ->where(
                            'action',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'module',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'description',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'user_name',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'username',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'subject_type',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'subject_id',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'ip_address',
                            'like',
                            $like
                        );
                }
            );
        }


        /*
         * Module
         */
        $module =
            trim(
                (string)
                $request->input(
                    'module',
                    ''
                )
            );

        if ($module !== '') {
            $query->where(
                'module',
                $module
            );
        }


        /*
         * Action
         */
        $action =
            trim(
                (string)
                $request->input(
                    'action',
                    ''
                )
            );

        if ($action !== '') {
            $query->where(
                'action',
                $action
            );
        }


        /*
         * User
         */
        $userId =
            trim(
                (string)
                $request->input(
                    'user_id',
                    ''
                )
            );

        if (
            $userId !== ''
            && ctype_digit(
                $userId
            )
        ) {
            $query->where(
                'user_id',
                (int) $userId
            );
        }


        /*
         * Subject
         */
        $subjectType =
            trim(
                (string)
                $request->input(
                    'subject_type',
                    ''
                )
            );

        if ($subjectType !== '') {
            $query->where(
                'subject_type',
                $subjectType
            );
        }


        $subjectId =
            trim(
                (string)
                $request->input(
                    'subject_id',
                    ''
                )
            );

        if ($subjectId !== '') {
            $query->where(
                'subject_id',
                $subjectId
            );
        }


        /*
         * Date filters
         */
        if (
            $request->filled(
                'start_date'
            )
        ) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->input(
                    'start_date'
                )
            );
        }

        if (
            $request->filled(
                'end_date'
            )
        ) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->input(
                    'end_date'
                )
            );
        }


        /*
         * Pagination
         */
        $perPage = max(
            1,
            min(
                (int)
                $request->input(
                    'per_page',
                    25
                ),
                100
            )
        );


        $logs =
            $query
                ->orderByDesc(
                    'id'
                )
                ->paginate(
                    $perPage
                )
                ->appends(
                    $request->query()
                );


        $data =
            collect(
                $logs->items()
            )
                ->map(
                    function ($log) {
                        return [
                            'id' =>
                                $log->id,

                            'user_id' =>
                                $log->user_id,

                            'user_name' =>
                                $log->user_name,

                            'username' =>
                                $log->username,

                            'rights' =>
                                $log->rights,

                            'action' =>
                                $log->action,

                            'module' =>
                                $log->module,

                            'description' =>
                                $log->description,

                            'subject_type' =>
                                $log->subject_type,

                            'subject_id' =>
                                $log->subject_id,

                            'ip_address' =>
                                $log->ip_address,

                            'properties' =>
                                $this->decodeJson(
                                    $log->properties
                                ),

                            'created_at' =>
                                $log->created_at,

                            'updated_at' =>
                                $log->updated_at,
                        ];
                    }
                )
                ->values();


        return response()->json([
            'success' => true,

            'data' =>
                $data,

            'meta' => [
                'current_page' =>
                    $logs->currentPage(),

                'last_page' =>
                    $logs->lastPage(),

                'per_page' =>
                    $logs->perPage(),

                'total' =>
                    $logs->total(),

                'from' =>
                    $logs->firstItem(),

                'to' =>
                    $logs->lastItem(),
            ],

            'links' => [
                'first' =>
                    $logs->url(1),

                'last' =>
                    $logs->url(
                        $logs->lastPage()
                    ),

                'prev' =>
                    $logs->previousPageUrl(),

                'next' =>
                    $logs->nextPageUrl(),
            ],

            'filters' => [
                'search' =>
                    $search,

                'module' =>
                    $module,

                'action' =>
                    $action,

                'user_id' =>
                    $userId !== ''
                        ? $userId
                        : null,

                'subject_type' =>
                    $subjectType,

                'subject_id' =>
                    $subjectId,

                'start_date' =>
                    $request->input(
                        'start_date'
                    ),

                'end_date' =>
                    $request->input(
                        'end_date'
                    ),
            ],
        ]);
    }


    /**
     * ============================================================
     * ANNOUNCEMENTS
     * ============================================================
     *
     * Examples:
     *
     * /api/v1/dts/announcements
     *
     * /api/v1/dts/announcements?active=1
     *
     * /api/v1/dts/announcements?target_type=all
     */
    public function announcements(
        Request $request
    ): JsonResponse {
        if (
            ! Schema::hasTable(
                'dts_admin_announcements'
            )
        ) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'count' => 0,
                ],
                'message' =>
                    'Announcements table is not available.',
            ]);
        }


        $query =
            DB::table(
                'dts_admin_announcements as a'
            );


        /*
         * Active announcements only.
         */
        if (
            $request->boolean(
                'active'
            )
        ) {
            $query
                ->where(
                    'a.is_active',
                    1
                )
                ->where(
                    function ($dateQuery) {
                        $dateQuery
                            ->whereNull(
                                'a.starts_at'
                            )
                            ->orWhere(
                                'a.starts_at',
                                '<=',
                                now()
                            );
                    }
                )
                ->where(
                    function ($dateQuery) {
                        $dateQuery
                            ->whereNull(
                                'a.ends_at'
                            )
                            ->orWhere(
                                'a.ends_at',
                                '>=',
                                now()
                            );
                    }
                );
        }


        /*
         * Target type:
         * all / role
         */
        $targetType =
            trim(
                (string)
                $request->input(
                    'target_type',
                    ''
                )
            );

        if ($targetType !== '') {
            $query->where(
                'a.target_type',
                $targetType
            );
        }


        /*
         * Specific target role/value.
         */
        $targetValue =
            trim(
                (string)
                $request->input(
                    'target_value',
                    ''
                )
            );

        if ($targetValue !== '') {
            $query->where(
                'a.target_value',
                $targetValue
            );
        }


        /*
         * Search title/message.
         */
        $search =
            trim(
                (string)
                $request->input(
                    'search',
                    ''
                )
            );

        if ($search !== '') {
            $like =
                '%' . $search . '%';

            $query->where(
                function ($filter) use ($like) {
                    $filter
                        ->where(
                            'a.title',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'a.message',
                            'like',
                            $like
                        );
                }
            );
        }


        $announcements =
            $query
                ->orderByDesc(
                    'a.id'
                )
                ->limit(500)
                ->get();


        /*
         * Resolve creator names.
         */
        $creatorIds =
            $announcements
                ->pluck(
                    'created_by'
                )
                ->filter()
                ->unique()
                ->values();


        $creatorMap =
            collect();


        if (
            $creatorIds->isNotEmpty()
            && Schema::hasTable(
                'username'
            )
        ) {
            $creatorQuery =
                DB::table(
                    'username'
                )
                    ->whereIn(
                        'ID',
                        $creatorIds
                    )
                    ->select([
                        'ID',
                        'loginname',

                        Schema::hasColumn(
                            'username',
                            'name'
                        )
                            ? 'name'
                            : DB::raw(
                                'NULL as name'
                            ),
                    ])
                    ->get();


            $creatorMap =
                $creatorQuery
                    ->mapWithKeys(
                        function ($user) {
                            return [
                                $user->ID =>
                                    $user->name
                                    ?: $user->loginname,
                            ];
                        }
                    );
        }


        $data =
            $announcements
                ->map(
                    function (
                        $announcement
                    ) use (
                        $creatorMap
                    ) {
                        return [
                            'id' =>
                                $announcement
                                    ->id,

                            'title' =>
                                $announcement
                                    ->title,

                            'message' =>
                                $announcement
                                    ->message,

                            'target_type' =>
                                $announcement
                                    ->target_type,

                            'target_value' =>
                                $announcement
                                    ->target_value,

                            'is_active' =>
                                (bool)
                                $announcement
                                    ->is_active,

                            'starts_at' =>
                                $announcement
                                    ->starts_at,

                            'ends_at' =>
                                $announcement
                                    ->ends_at,

                            'created_by' =>
                                $announcement
                                    ->created_by,

                            'created_by_name' =>
                                $creatorMap
                                    ->get(
                                        $announcement
                                            ->created_by
                                    ),

                            'created_at' =>
                                $announcement
                                    ->created_at,

                            'updated_at' =>
                                $announcement
                                    ->updated_at,
                        ];
                    }
                )
                ->values();


        return response()->json([
            'success' => true,

            'data' =>
                $data,

            'meta' => [
                'count' =>
                    $data->count(),

                'safety_limit' =>
                    500,
            ],

            'filters' => [
                'active' =>
                    $request->boolean(
                        'active'
                    ),

                'target_type' =>
                    $targetType !== ''
                        ? $targetType
                        : null,

                'target_value' =>
                    $targetValue !== ''
                        ? $targetValue
                        : null,

                'search' =>
                    $search,
            ],
        ]);
    }


    /**
     * Safely convert activity-log JSON properties.
     */
    private function decodeJson(
        mixed $value
    ): mixed {
        if (
            $value === null
            || $value === ''
        ) {
            return [];
        }


        if (
            is_array(
                $value
            )
        ) {
            return $value;
        }


        if (
            !is_string(
                $value
            )
        ) {
            return $value;
        }


        $decoded =
            json_decode(
                $value,
                true
            );


        return (
            json_last_error()
            === JSON_ERROR_NONE
        )
            ? $decoded
            : $value;
    }
}