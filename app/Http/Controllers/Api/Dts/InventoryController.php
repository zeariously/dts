<?php

namespace App\Http\Controllers\Api\Dts;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Read-only inventory list.
     *
     * Supported filters:
     * ?year=2026
     * ?category=supplies
     * ?quarter=q1
     * ?unit=REAM
     * ?search=bond
     * ?per_page=25
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryItem::query();

        $this->applyFilters(
            $query,
            $request
        );

        $perPage = $this->perPage(
            $request
        );

        $items = $query
            ->orderByRaw("
                CASE
                    WHEN LOWER(TRIM(category)) = 'supplies' THEN 1
                    WHEN LOWER(TRIM(category)) = 'ict' THEN 2
                    WHEN LOWER(TRIM(category)) IN ('furniture', 'fixtures') THEN 3
                    WHEN LOWER(TRIM(category)) = 'emergency_kits' THEN 4
                    WHEN LOWER(TRIM(category)) = 'token_giveaways' THEN 5
                    ELSE 6
                END
            ")
            ->orderBy('item')
            ->paginate($perPage)
            ->appends($request->query());

        $data = collect(
            $items->items()
        )
            ->map(
                fn (InventoryItem $item) =>
                    $this->serializeItem(
                        $item
                    )
            )
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' =>
                    $items->currentPage(),

                'last_page' =>
                    $items->lastPage(),

                'per_page' =>
                    $items->perPage(),

                'total' =>
                    $items->total(),

                'from' =>
                    $items->firstItem(),

                'to' =>
                    $items->lastItem(),
            ],
            'links' => [
                'first' =>
                    $items->url(1),

                'last' =>
                    $items->url(
                        $items->lastPage()
                    ),

                'prev' =>
                    $items->previousPageUrl(),

                'next' =>
                    $items->nextPageUrl(),
            ],
            'filters' =>
                $this->responseFilters(
                    $request
                ),
        ]);
    }

    /**
     * Inventory summary.
     *
     * The counts below are read-only API statistics.
     */
    public function summary(
        Request $request
    ): JsonResponse {
        $query = InventoryItem::query();

        $year =
            $this->normalizedYear(
                $request
            );

        if ($year !== null) {
            $query->where(
                'inventory_year',
                $year
            );
        }

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
                            'unit',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'location',
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

        $rows =
            $query->get();

        $summary = [
            'total_records' =>
                $rows->count(),

            'supplies' => 0,

            'ict' => 0,

            'furniture_fixtures' => 0,

            'emergency_kits' => 0,

            'token_giveaways' => 0,

            'other' => 0,

            /*
             * Property-record counts.
             * These come from ict_assets for physical ICT /
             * Furniture / Fixtures.
             */
            'total_property_records' => 0,

            'active_property_records' => 0,

            'available_property_records' => 0,

            'assigned_property_records' => 0,

            'returned_property_records' => 0,
        ];

        foreach ($rows as $item) {
            $category =
                strtolower(
                    trim(
                        (string)
                        $item->category
                    )
                );

            if ($category === 'supplies') {
                $summary['supplies']++;
            } elseif ($category === 'ict') {
                $summary['ict']++;
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
            } else {
                $summary['other']++;
            }

            foreach (
                $this->assets($item)
                as $asset
            ) {
                $summary[
                    'total_property_records'
                ]++;

                if (
                    $this->isReturnedAsset(
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

                $currentUser =
                    trim(
                        (string) (
                            $asset[
                                'current_user'
                            ]
                            ?? ''
                        )
                    );

                if ($currentUser === '') {
                    $summary[
                        'available_property_records'
                    ]++;
                } else {
                    $summary[
                        'assigned_property_records'
                    ]++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $summary,
            'filters' => [
                'year' => $year,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Get one inventory item.
     */
    public function show(
        int $inventoryItem
    ): JsonResponse {
        $item =
            InventoryItem::query()
                ->find(
                    $inventoryItem
                );

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Inventory item not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' =>
                $this->serializeItem(
                    $item
                ),
        ]);
    }

    /**
     * Read-only inventory audit history.
     *
     * This exposes the current history schema used by the
     * existing InventoryController, including action,
     * old_data/new_data and compatibility stock fields.
     */
    public function history(
        int $inventoryItem
    ): JsonResponse {
        $item =
            InventoryItem::query()
                ->find(
                    $inventoryItem
                );

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Inventory item not found.',
            ], 404);
        }

        $histories =
            InventoryItemHistory::query()
                ->where(
                    'inventory_item_id',
                    $item->id
                )
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc('id')
                ->get()
                ->map(
                    function ($history) {
                        $oldData =
                            is_array(
                                $history->old_data
                                ?? null
                            )
                                ? $history->old_data
                                : [];

                        $newData =
                            is_array(
                                $history->new_data
                                ?? null
                            )
                                ? $history->new_data
                                : [];

                        $oldCurrent =
                            $history
                                ->old_currently_available
                            ?? $history
                                ->old_available
                            ?? $history
                                ->legacy_old_available
                            ?? null;

                        $newCurrent =
                            $history
                                ->new_currently_available
                            ?? $history
                                ->new_available
                            ?? $history
                                ->legacy_new_available
                            ?? null;

                        return [
                            'id' =>
                                $history->id,

                            'action' =>
                                $history->action,

                            /*
                             * Full audit snapshots.
                             */
                            'old_data' =>
                                $oldData,

                            'new_data' =>
                                $newData,

                            /*
                             * Stock compatibility fields.
                             */
                            'old_currently_available' =>
                                $oldCurrent,

                            'new_currently_available' =>
                                $newCurrent,

                            'old_available' =>
                                $oldCurrent,

                            'new_available' =>
                                $newCurrent,

                            'old_remarks' =>
                                $history->old_remarks,

                            'new_remarks' =>
                                $history->new_remarks,

                            'updated_by' =>
                                $history->updated_by,

                            'updated_by_name' =>
                                $history->updated_by_name,

                            'created_at' =>
                                $history->created_at
                                    ?->timezone(
                                        'Asia/Manila'
                                    )
                                    ->toIso8601String(),
                        ];
                    }
                )
                ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'item' =>
                    $this->serializeItem(
                        $item
                    ),

                'histories' =>
                    $histories,
            ],
            'meta' => [
                'count' =>
                    $histories->count(),
            ],
        ]);
    }

    /**
     * Flatten all returned property records.
     *
     * Returned information is stored per property row inside
     * ict_assets in the current Inventory implementation.
     */
    public function returned(
        Request $request
    ): JsonResponse {
        $query =
            InventoryItem::query();

        $year =
            $this->normalizedYear(
                $request
            );

        if ($year !== null) {
            $query->where(
                'inventory_year',
                $year
            );
        }

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
            $this->applyCategory(
                $query,
                $category
            );
        }

        $search =
            strtolower(
                trim(
                    (string)
                    $request->input(
                        'search',
                        ''
                    )
                )
            );

        $returned =
            collect();

        $query
            ->orderBy('id')
            ->chunkById(
                200,
                function ($items) use (
                    &$returned,
                    $search
                ) {
                    foreach ($items as $item) {
                        foreach (
                            $this->assets($item)
                            as $asset
                        ) {
                            if (
                                ! $this->isReturnedAsset(
                                    $asset
                                )
                            ) {
                                continue;
                            }

                            $record = [
                                'inventory_item_id' =>
                                    $item->id,

                                'category' =>
                                    strtolower(
                                        trim(
                                            (string)
                                            $item->category
                                        )
                                    ),

                                'item' =>
                                    $item->item,

                                'unit' =>
                                    $item->unit,

                                'inventory_year' =>
                                    $item->inventory_year,

                                'location' =>
                                    $item->location,

                                'description' =>
                                    $asset[
                                        'description'
                                    ]
                                    ?? null,

                                'accessories' =>
                                    $asset[
                                        'accessories'
                                    ]
                                    ?? null,

                                'property_number' =>
                                    $asset[
                                        'property_number'
                                    ]
                                    ?? null,

                                'current_user' =>
                                    $asset[
                                        'current_user'
                                    ]
                                    ?? null,

                                'mr_personnel_id' =>
                                    $asset[
                                        'mr_personnel_id'
                                    ]
                                    ?? null,

                                'mr' =>
                                    $asset['mr']
                                    ?? null,

                                'date_acquired' =>
                                    $asset[
                                        'date_acquired'
                                    ]
                                    ?? null,

                                'life_span_ended' =>
                                    $asset[
                                        'life_span_ended'
                                    ]
                                    ?? null,

                                'status' =>
                                    $asset['status']
                                    ?? 'returned',

                                'returned_at' =>
                                    $asset[
                                        'returned_at'
                                    ]
                                    ?? null,

                                'returned_by' =>
                                    $asset[
                                        'returned_by'
                                    ]
                                    ?? null,

                                'returned_by_user_id' =>
                                    $asset[
                                        'returned_by_user_id'
                                    ]
                                    ?? null,

                                'return_remarks' =>
                                    $asset[
                                        'return_remarks'
                                    ]
                                    ?? null,
                            ];

                            if (
                                $search !== ''
                                && ! $this->matchesSearch(
                                    $record,
                                    $search
                                )
                            ) {
                                continue;
                            }

                            $returned
                                ->push(
                                    $record
                                );
                        }
                    }
                }
            );

        $returned =
            $returned
                ->sortByDesc(
                    fn ($row) =>
                        $row[
                            'returned_at'
                        ]
                        ?? ''
                )
                ->values();

        return response()->json([
            'success' => true,
            'data' => $returned,
            'meta' => [
                'count' =>
                    $returned->count(),
            ],
            'filters' => [
                'year' =>
                    $year,

                'category' =>
                    $category !== ''
                        ? $category
                        : null,

                'search' =>
                    $search,
            ],
        ]);
    }

    private function applyFilters(
        $query,
        Request $request
    ): void {
        $year =
            $this->normalizedYear(
                $request
            );

        if ($year !== null) {
            $query->where(
                'inventory_year',
                $year
            );
        }

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
            $this->applyCategory(
                $query,
                $category
            );
        }

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
    }

    private function applyCategory(
        $query,
        string $category
    ): void {
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

            return;
        }

        $query->whereRaw(
            'LOWER(TRIM(category)) = ?',
            [$category]
        );
    }

    /**
     * Normalize one InventoryItem for API output.
     */
    private function serializeItem(
        InventoryItem $item
    ): array {
        $assets =
            $this->assets(
                $item
            );

        $returnedAssets =
            collect($assets)
                ->filter(
                    fn ($asset) =>
                        $this->isReturnedAsset(
                            $asset
                        )
                )
                ->values()
                ->all();

        $activeAssets =
            collect($assets)
                ->reject(
                    fn ($asset) =>
                        $this->isReturnedAsset(
                            $asset
                        )
                )
                ->values()
                ->all();

        $availableAssets =
            collect($activeAssets)
                ->filter(
                    fn ($asset) =>
                        trim(
                            (string) (
                                $asset[
                                    'current_user'
                                ]
                                ?? ''
                            )
                        ) === ''
                )
                ->values()
                ->all();

        $assignedAssets =
            collect($activeAssets)
                ->reject(
                    fn ($asset) =>
                        trim(
                            (string) (
                                $asset[
                                    'current_user'
                                ]
                                ?? ''
                            )
                        ) === ''
                )
                ->values()
                ->all();

        return [
            'id' =>
                $item->id,

            'category' =>
                strtolower(
                    trim(
                        (string)
                        $item->category
                    )
                ),

            'item' =>
                $item->item,

            'description' =>
                $item->description,

            'location' =>
                $item->location,

            'unit' =>
                $item->unit,

            'inventory_year' =>
                $item->inventory_year,

            'fixed_value' =>
                $item->fixed_value,

            'currently_available' =>
                $item->currently_available,

            /*
             * Generated when Fixed Value is present.
             */
            'total_released' =>
                $item->total_released,

            /*
             * System-tracked release total when no usable
             * Fixed Value exists.
             */
            'tracked_released' =>
                (int) (
                    $item->tracked_released
                    ?? 0
                ),

            'quarters' =>
                is_array(
                    $item->quarters
                )
                    ? $item->quarters
                    : [],

            'quarter_stock' =>
                is_array(
                    $item->quarter_stock
                )
                    ? $item->quarter_stock
                    : [],

            'remarks' =>
                $item->remarks,

            /*
             * Current implementation stores physical property
             * details in ict_assets.
             */
            'property_records' =>
                $assets,

            /*
             * Compatibility alias.
             */
            'ict_assets' =>
                $assets,

            'active_property_records' =>
                $activeAssets,

            'available_property_records' =>
                $availableAssets,

            'assigned_property_records' =>
                $assignedAssets,

            'returned_property_records' =>
                $returnedAssets,

            'property_record_count' =>
                count($assets),

            'active_property_count' =>
                count($activeAssets),

            'available_property_count' =>
                count($availableAssets),

            'assigned_property_count' =>
                count($assignedAssets),

            'returned_property_count' =>
                count($returnedAssets),

            'created_at' =>
                $item->created_at
                    ?->timezone(
                        'Asia/Manila'
                    )
                    ->toIso8601String(),

            'updated_at' =>
                $item->updated_at
                    ?->timezone(
                        'Asia/Manila'
                    )
                    ->toIso8601String(),
        ];
    }

    /**
     * Return stored property rows without changing their values.
     *
     * Current property rows can contain:
     * description, accessories, property_number, current_user,
     * date_acquired, life_span_ended, status, returned_at,
     * return_remarks, returned_by, returned_by_user_id,
     * mr_personnel_id and mr.
     */
    private function assets(
        InventoryItem $item
    ): array {
        $assets =
            $item->ict_assets;

        if (is_array($assets)) {
            return array_values(
                array_filter(
                    $assets,
                    fn ($asset) =>
                        is_array($asset)
                )
            );
        }

        /*
         * Compatibility fallback in case ict_assets is not
         * cast to array in an older model version.
         */
        if (
            is_string($assets)
            && trim($assets) !== ''
        ) {
            $decoded =
                json_decode(
                    $assets,
                    true
                );

            if (is_array($decoded)) {
                return array_values(
                    array_filter(
                        $decoded,
                        fn ($asset) =>
                            is_array($asset)
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
                        $asset['status']
                        ?? ''
                    )
                )
            );

        /*
         * for_return is accepted as legacy compatibility.
         * The current InventoryController normalizes it to
         * returned when property rows are saved.
         */
        if ($status === 'for_return') {
            $status = 'returned';
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
            $status === 'returned'
            || $returnedAt !== ''
        );
    }

    private function matchesSearch(
        array $record,
        string $search
    ): bool {
        $haystack =
            strtolower(
                implode(
                    ' ',
                    array_map(
                        fn ($value) =>
                            is_scalar($value)
                                ? (string) $value
                                : '',
                        $record
                    )
                )
            );

        return str_contains(
            $haystack,
            $search
        );
    }

    private function responseFilters(
        Request $request
    ): array {
        return [
            'year' =>
                $this->normalizedYear(
                    $request
                ),

            'category' =>
                trim(
                    (string)
                    $request->input(
                        'category',
                        ''
                    )
                ),

            'quarter' =>
                trim(
                    (string)
                    $request->input(
                        'quarter',
                        ''
                    )
                ),

            'unit' =>
                trim(
                    (string)
                    $request->input(
                        'unit',
                        ''
                    )
                ),

            'search' =>
                trim(
                    (string)
                    $request->input(
                        'search',
                        ''
                    )
                ),
        ];
    }

    private function normalizedYear(
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

        $numericYear =
            (int) $year;

        if (
            $numericYear < 1900
            || $numericYear > 2200
        ) {
            return null;
        }

        return $numericYear;
    }

    private function perPage(
        Request $request
    ): int {
        return max(
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
    }
}
