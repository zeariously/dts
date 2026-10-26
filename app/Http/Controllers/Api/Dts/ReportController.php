<?php

namespace App\Http\Controllers\Api\Dts;

use App\Http\Controllers\Api\Dts\Concerns\BuildsDtsDocumentQueries;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use BuildsDtsDocumentQueries;

    /**
     * ============================================================
     * DOCUMENT REPORT
     * ============================================================
     *
     * Examples:
     *
     * /api/v1/dts/reports/documents
     *
     * /api/v1/dts/reports/documents
     * ?report_classification=incoming
     * &start_date=2026-10-01
     * &end_date=2026-10-31
     *
     */
    public function documents(
        Request $request
    ): JsonResponse {
        $this->validateDates($request);

        [$query, $context] =
            $this->buildBaseQuery();

        /*
         * Existing API filters:
         * search
         * status
         * year
         * personnel_id
         */
        $this->applyCommonFilters(
            $query,
            $request,
            $context,
            true
        );

        /*
         * Reports-specific filters.
         */
        $this->applyReportFilters(
            $query,
            $request
        );

        $perPage = max(
            1,
            min(
                (int) $request->input(
                    'per_page',
                    100
                ),
                500
            )
        );

        $documents = $query
            ->select(
                $this->documentSelectColumns(
                    $context
                )
            )
            ->orderByDesc('d.entrydate')
            ->orderByDesc('d.IDdoc')
            ->orderByRaw(
                "COALESCE(
                    assignment.assignment_suffix,
                    ''
                ) ASC"
            )
            ->paginate($perPage)
            ->appends(
                $request->query()
            );

        return response()->json([
            'success' => true,

            'summary' =>
                $this->documentSummary(
                    $request
                ),

            'data' =>
                $documents->items(),

            'meta' => [
                'current_page' =>
                    $documents->currentPage(),

                'last_page' =>
                    $documents->lastPage(),

                'per_page' =>
                    $documents->perPage(),

                'total_rows' =>
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

            'filters' =>
                $this->reportFilters(
                    $request
                ),
        ]);
    }


    /**
     * ============================================================
     * STATUS SUMMARY
     * ============================================================
     */
    public function statusSummary(
        Request $request
    ): JsonResponse {
        $this->validateDates($request);

        return response()->json([
            'success' => true,

            'data' =>
                $this->documentSummary(
                    $request
                ),

            'filters' =>
                $this->reportFilters(
                    $request
                ),
        ]);
    }


    /**
     * ============================================================
     * INVENTORY REPORT
     * ============================================================
     */
    public function inventory(
        Request $request
    ): JsonResponse {
        $query =
            InventoryItem::query();

        /*
         * Inventory Year
         */
        $year =
            $this->inventoryYear(
                $request
            );

        if ($year !== null) {
            $query->where(
                'inventory_year',
                $year
            );
        }


        /*
         * Category
         */
        $category =
            strtolower(
                trim(
                    (string)
                    $request->input(
                        'category',
                        ''
                    )
                )
            );

        if ($category !== '') {
            if (
                $category ===
                'furniture_fixtures'
            ) {
                $query->whereIn(
                    'category',
                    [
                        'furniture',
                        'fixtures',
                    ]
                );
            } else {
                $query->whereRaw(
                    'LOWER(TRIM(category)) = ?',
                    [$category]
                );
            }
        }


        /*
         * Unit
         */
        $unit =
            strtoupper(
                trim(
                    (string)
                    $request->input(
                        'unit',
                        ''
                    )
                )
            );

        if ($unit !== '') {
            $query->whereRaw(
                'UPPER(TRIM(unit)) = ?',
                [$unit]
            );
        }


        /*
         * Quarter
         */
        $quarter =
            strtolower(
                trim(
                    (string)
                    $request->input(
                        'quarter',
                        ''
                    )
                )
            );

        if (
            in_array(
                $quarter,
                [
                    'q1',
                    'q2',
                    'q3',
                    'q4',
                ],
                true
            )
        ) {
            $query->whereJsonContains(
                'quarters',
                $quarter
            );
        }


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
                            'item',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'description',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'location',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'unit',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'remarks',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'ict_assets',
                            'like',
                            $like
                        );
                }
            );
        }


        /*
         * Report = full filtered dataset.
         * Safety limit prevents unlimited API response.
         */
        $items = $query
            ->orderByRaw("
                CASE

                    WHEN LOWER(TRIM(category))
                        = 'supplies'
                        THEN 1

                    WHEN LOWER(TRIM(category))
                        = 'ict'
                        THEN 2

                    WHEN LOWER(TRIM(category))
                        IN ('furniture', 'fixtures')
                        THEN 3

                    WHEN LOWER(TRIM(category))
                        = 'emergency_kits'
                        THEN 4

                    WHEN LOWER(TRIM(category))
                        = 'token_giveaways'
                        THEN 5

                    ELSE 6

                END
            ")
            ->orderBy('item')
            ->limit(5000)
            ->get();


        /*
         * Summary
         */
        $summary = [
            'total_records' =>
                $items->count(),

            'supplies' => 0,

            'ict' => 0,

            'furniture_fixtures' => 0,

            'emergency_kits' => 0,

            'token_giveaways' => 0,

            'total_property_records' => 0,

            'active_property_records' => 0,

            'returned_property_records' => 0,

            'assigned_property_records' => 0,
        ];


        $data =
            $items
                ->map(
                    function (
                        InventoryItem $item
                    ) use (&$summary) {

                        $category =
                            strtolower(
                                trim(
                                    (string)
                                    $item->category
                                )
                            );


                        /*
                         * Category counts
                         */
                        if (
                            $category ===
                            'supplies'
                        ) {
                            $summary[
                                'supplies'
                            ]++;

                        } elseif (
                            $category ===
                            'ict'
                        ) {
                            $summary[
                                'ict'
                            ]++;

                        } elseif (
                            in_array(
                                $category,
                                [
                                    'furniture',
                                    'fixtures',
                                ],
                                true
                            )
                        ) {
                            $summary[
                                'furniture_fixtures'
                            ]++;

                        } elseif (
                            $category ===
                            'emergency_kits'
                        ) {
                            $summary[
                                'emergency_kits'
                            ]++;

                        } elseif (
                            $category ===
                            'token_giveaways'
                        ) {
                            $summary[
                                'token_giveaways'
                            ]++;
                        }


                        /*
                         * Physical property rows
                         */
                        $assets =
                            $this->inventoryAssets(
                                $item
                            );

                        foreach (
                            $assets
                            as $asset
                        ) {
                            $summary[
                                'total_property_records'
                            ]++;

                            if (
                                $this
                                    ->isReturnedAsset(
                                        $asset
                                    )
                            ) {
                                $summary[
                                    'returned_property_records'
                                ]++;

                                continue;
                            }

                            $summary[
                                'active_property_records'
                            ]++;

                            if (
                                trim(
                                    (string) (
                                        $asset[
                                            'current_user'
                                        ]
                                        ?? ''
                                    )
                                ) !== ''
                            ) {
                                $summary[
                                    'assigned_property_records'
                                ]++;
                            }
                        }


                        return [
                            'id' =>
                                $item->id,

                            'category' =>
                                $category,

                            'item' =>
                                $item->item,

                            'unit' =>
                                $item->unit,

                            'inventory_year' =>
                                $item->inventory_year,

                            'location' =>
                                $item->location,

                            'fixed_value' =>
                                $item->fixed_value,

                            'currently_available' =>
                                $item
                                    ->currently_available,

                            'total_released' =>
                                $item
                                    ->total_released,

                            'tracked_released' =>
                                (int) (
                                    $item
                                        ->tracked_released
                                    ?? 0
                                ),

                            'quarters' =>
                                $item->quarters
                                ?? [],

                            'quarter_stock' =>
                                $item
                                    ->quarter_stock
                                ?? [],

                            'remarks' =>
                                $item->remarks,

                            'property_records' =>
                                $assets,
                        ];
                    }
                )
                ->values();


        return response()->json([
            'success' => true,

            'summary' =>
                $summary,

            'data' =>
                $data,

            'meta' => [
                'count' =>
                    $data->count(),

                'safety_limit' =>
                    5000,
            ],

            'filters' => [
                'year' =>
                    $year,

                'category' =>
                    $category !== ''
                        ? $category
                        : null,

                'quarter' =>
                    $quarter !== ''
                        ? $quarter
                        : null,

                'unit' =>
                    $unit !== ''
                        ? $unit
                        : null,

                'search' =>
                    $search,
            ],
        ]);
    }


    /**
     * ============================================================
     * DOCUMENT SUMMARY
     * ============================================================
     */
    private function documentSummary(
    Request $request
): array {
    [$query, $context] =
        $this->buildBaseQuery();

    $this->applyCommonFilters(
        $query,
        $request,
        $context,
        true
    );

    $this->applyReportFilters(
        $query,
        $request
    );

    /*
     * Each row represents one DTS workflow/
     * assignment record.
     *
     * Important:
     * 185613-A, 185613-B, 185613-C must be
     * counted separately because they can have
     * different statuses.
     */
    $statusRows =
        clone $query;

    $statusRows->selectRaw(
        '('
        . $context['status_expression']
        . ') as workflow_status'
    );

    $rows =
        DB::query()
            ->fromSub(
                $statusRows,
                'report_status_rows'
            )
            ->select(
                'workflow_status'
            )
            ->selectRaw(
                'COUNT(*) as total'
            )
            ->groupBy(
                'workflow_status'
            )
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

        'other' => 0,
    ];

    foreach ($rows as $row) {
        $key =
            strtolower(
                trim(
                    (string)
                    $row->workflow_status
                )
            );

        $key =
            str_replace(
                [
                    ' ',
                    '-',
                ],
                '_',
                $key
            );

        $count =
            (int)
            $row->total;

        if (
            array_key_exists(
                $key,
                $summary
            )
            && $key !== 'total'
        ) {
            $summary[$key] +=
                $count;
        } else {
            $summary['other'] +=
                $count;
        }

        /*
         * Total workflow rows.
         */
        $summary['total'] +=
            $count;
    }

    return $summary;
}


    /**
     * ============================================================
     * REPORT FILTERS
     * ============================================================
     */
    private function applyReportFilters(
        $query,
        Request $request
    ): void {
        /*
         * Classification
         *
         * DTS:
         * False = Incoming
         * True  = Outgoing
         */
        $classification =
            trim(
                (string)
                $request->input(
                    'report_classification',
                    $request->input(
                        'classification',
                        ''
                    )
                )
            );


        if (
            $classification !== ''
            && strtolower(
                $classification
            ) !== 'all'
        ) {
            $lower =
                strtolower(
                    $classification
                );


            if (
                $lower ===
                'incoming'
            ) {
                $classification =
                    'False';

            } elseif (
                $lower ===
                'outgoing'
            ) {
                $classification =
                    'True';
            }


            if (
                in_array(
                    $classification,
                    [
                        'False',
                        'True',
                    ],
                    true
                )
            ) {
                $query->where(
                    'd.classification',
                    $classification
                );
            }
        }


        /*
         * Selected Personnel/User
         */
        $reportUser =
            trim(
                (string)
                $request->input(
                    'report_user',
                    ''
                )
            );


        if (
            strtolower($classification) !== 'true'
            && $reportUser !== ''
            && strtolower(
                $reportUser
            ) !== 'all'
            && ctype_digit(
                $reportUser
            )
        ) {
            $query->whereRaw(
                '
                COALESCE(
                    dist.idmapagency,
                    assignment.idmapagency,
                    d.IDkeeper
                ) = ?
                ',
                [
                    (int)
                    $reportUser,
                ]
            );
        }


        // Status-only filter for outgoing (no assigned user).
        if (strtolower($classification) === 'true') {
            $outgoingStatus = trim((string) $request->input('report_status', ''));
            if ($outgoingStatus !== '' && in_array($outgoingStatus, ['Forwarded to Records', 'Recorded'], true)) {
                $query->where('d.outgoing_status', $outgoingStatus);
            }
        }

        /*
         * Date range
         */
        if (
            $request->filled(
                'start_date'
            )
        ) {
            $query->whereDate(
                'd.entrydate',
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
                'd.entrydate',
                '<=',
                $request->input(
                    'end_date'
                )
            );
        }
    }


    private function validateDates(
        Request $request
    ): void {
        $request->validate([
            'start_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'end_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
        ]);
    }


    private function reportFilters(
        Request $request
    ): array {
        return [
            'classification' =>
                $request->input(
                    'report_classification',
                    $request->input(
                        'classification'
                    )
                ),

            'report_user' =>
                $request->input(
                    'report_user'
                ),

            'report_status' => $request->input('report_status'),

            'start_date' =>
                $request->input(
                    'start_date'
                ),

            'end_date' =>
                $request->input(
                    'end_date'
                ),

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
        ];
    }


    /**
     * ============================================================
     * INVENTORY HELPERS
     * ============================================================
     */
    private function inventoryYear(
        Request $request
    ): ?int {
        $year =
            trim(
                (string)
                $request->input(
                    'year',
                    ''
                )
            );


        if (
            $year === ''
            || strtolower(
                $year
            ) === 'all'
        ) {
            return null;
        }


        if (
            !ctype_digit(
                $year
            )
            || strlen(
                $year
            ) !== 4
        ) {
            return null;
        }


        $numericYear =
            (int)
            $year;


        if (
            $numericYear < 1900
            || $numericYear > 2200
        ) {
            return null;
        }


        return $numericYear;
    }


    private function inventoryAssets(
        InventoryItem $item
    ): array {
        $assets =
            $item->ict_assets;


        if (
            is_array(
                $assets
            )
        ) {
            return array_values(
                array_filter(
                    $assets,
                    fn ($asset) =>
                        is_array(
                            $asset
                        )
                )
            );
        }


        if (
            is_string(
                $assets
            )
            && trim(
                $assets
            ) !== ''
        ) {
            $decoded =
                json_decode(
                    $assets,
                    true
                );


            if (
                is_array(
                    $decoded
                )
            ) {
                return array_values(
                    array_filter(
                        $decoded,
                        fn ($asset) =>
                            is_array(
                                $asset
                            )
                    )
                );
            }
        }


        return [];
    }


    private function isReturnedAsset(
        array $asset
    ): bool {
        $status =
            strtolower(
                trim(
                    (string) (
                        $asset[
                            'status'
                        ]
                        ?? ''
                    )
                )
            );


        /*
         * Legacy compatibility.
         */
        if (
            $status ===
            'for_return'
        ) {
            $status =
                'returned';
        }


        $returnedAt =
            trim(
                (string) (
                    $asset[
                        'returned_at'
                    ]
                    ?? ''
                )
            );


        return (
            $status ===
            'returned'
            || $returnedAt !== ''
        );
    }
}