<?php

namespace App\Http\Controllers\Api\Dts;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryController extends Controller
{
    public function personnel(Request $request): JsonResponse
    {
        if (! Schema::hasTable('lu_personnel')) {
            return $this->emptyResult();
        }

        $query = DB::table('lu_personnel as p');

        $hasOffice =
            Schema::hasTable('lu_office');

        if ($hasOffice) {
            $query->leftJoin(
                'lu_office as o',
                'o.ID',
                '=',
                'p.IDoffice'
            );
        }

        $search = $this->searchTerm($request);

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(
                function ($filter) use (
                    $like,
                    $hasOffice
                ) {
                    $filter->where(
                        'p.name',
                        'like',
                        $like
                    );

                    if ($hasOffice) {
                        $filter->orWhere(
                            'o.officename',
                            'like',
                            $like
                        );
                    }
                }
            );
        }

        $select = [
            'p.ID as personnel_id',
            'p.name',
            'p.IDoffice as office_id',
        ];

        $select[] = $hasOffice
            ? 'o.officename as office_name'
            : DB::raw(
                'NULL as office_name'
            );

        $rows = $query
            ->whereNotNull('p.name')
            ->whereRaw(
                "TRIM(p.name) != ''"
            )
            ->select($select)
            ->orderBy('p.name')
            ->limit($this->limit($request))
            ->get();

        return $this->collectionResult(
            $rows,
            $request
        );
    }

    public function offices(Request $request): JsonResponse
    {
        if (! Schema::hasTable('lu_office')) {
            return $this->emptyResult();
        }

        $query = DB::table('lu_office as o');

        $search = $this->searchTerm($request);

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(
                function ($filter) use ($like) {
                    $filter
                        ->where(
                            'o.officename',
                            'like',
                            $like
                        );

                    if (
                        Schema::hasColumn(
                            'lu_office',
                            'abbrev'
                        )
                    ) {
                        $filter->orWhere(
                            'o.abbrev',
                            'like',
                            $like
                        );
                    }
                }
            );
        }

        $select = [
            'o.ID as office_id',
            'o.officename as office_name',
        ];

        if (
            Schema::hasColumn(
                'lu_office',
                'abbrev'
            )
        ) {
            $select[] =
                'o.abbrev as abbreviation';
        } else {
            $select[] =
                DB::raw(
                    'NULL as abbreviation'
                );
        }

        $rows = $query
            ->select($select)
            ->orderBy('o.officename')
            ->limit($this->limit($request))
            ->get();

        return $this->collectionResult(
            $rows,
            $request
        );
    }

    public function documentTypes(Request $request): JsonResponse
    {
        if (! Schema::hasTable('lu_doctype')) {
            return $this->emptyResult();
        }

        $query = DB::table('lu_doctype as dt');

        $search = $this->searchTerm($request);

        if ($search !== '') {
            $query->where(
                'dt.description',
                'like',
                '%' . $search . '%'
            );
        }

        $rows = $query
            ->select([
                'dt.ID as document_type_id',
                'dt.description',
            ])
            ->orderBy('dt.description')
            ->limit($this->limit($request))
            ->get();

        return $this->collectionResult(
            $rows,
            $request
        );
    }

    public function classifications(Request $request): JsonResponse
    {
        /*
         * Prefer the classification lookup table when it exists.
         * If the current DTS database stores classification directly
         * in document.classification instead, return its distinct values.
         */
        if (Schema::hasTable('lu_classification')) {
            $columns =
                Schema::getColumnListing(
                    'lu_classification'
                );

            $idColumn = collect([
                'ID',
                'id',
            ])->first(
                fn ($column) =>
                    in_array(
                        $column,
                        $columns,
                        true
                    )
            );

            $labelColumn = collect([
                'description',
                'classification',
                'name',
                'label',
            ])->first(
                fn ($column) =>
                    in_array(
                        $column,
                        $columns,
                        true
                    )
            );

            if ($labelColumn) {
                $query =
                    DB::table(
                        'lu_classification'
                    );

                $search =
                    $this->searchTerm(
                        $request
                    );

                if ($search !== '') {
                    $query->where(
                        $labelColumn,
                        'like',
                        '%' . $search . '%'
                    );
                }

                $select = [];

                $select[] = $idColumn
                    ? $idColumn
                        . ' as classification_id'
                    : DB::raw(
                        'NULL as classification_id'
                    );

                $select[] =
                    $labelColumn
                    . ' as classification';

                $rows = $query
                    ->select($select)
                    ->orderBy($labelColumn)
                    ->limit(
                        $this->limit($request)
                    )
                    ->get();

                return $this->collectionResult(
                    $rows,
                    $request
                );
            }
        }

        if (
            ! Schema::hasTable('document')
            || ! Schema::hasColumn(
                'document',
                'classification'
            )
        ) {
            return $this->emptyResult();
        }

        $query = DB::table('document')
            ->whereNotNull('classification')
            ->whereRaw(
                "TRIM(classification) != ''"
            );

        $search = $this->searchTerm($request);

        if ($search !== '') {
            $query->where(
                'classification',
                'like',
                '%' . $search . '%'
            );
        }

        $rows = $query
            ->selectRaw(
                'NULL as classification_id, classification'
            )
            ->distinct()
            ->orderBy('classification')
            ->limit($this->limit($request))
            ->get();

        return $this->collectionResult(
            $rows,
            $request
        );
    }

    public function actionTypes(Request $request): JsonResponse
    {
        if (
            ! Schema::hasTable(
                'dts_action_types'
            )
        ) {
            return $this->emptyResult();
        }

        $query =
            DB::table(
                'dts_action_types as a'
            );

        $search = $this->searchTerm($request);

        if ($search !== '') {
            $query->where(
                'a.name',
                'like',
                '%' . $search . '%'
            );
        }

        $select = [
            'a.id as action_type_id',
            'a.name',
        ];

        foreach (
            [
                'code',
                'description',
                'is_active',
            ] as $column
        ) {
            if (
                Schema::hasColumn(
                    'dts_action_types',
                    $column
                )
            ) {
                $select[] =
                    'a.' . $column;
            }
        }

        $rows = $query
            ->select($select)
            ->orderBy('a.name')
            ->limit($this->limit($request))
            ->get();

        return $this->collectionResult(
            $rows,
            $request
        );
    }

    private function searchTerm(
        Request $request
    ): string {
        return trim(
            (string)
            $request->input(
                'search',
                ''
            )
        );
    }

    private function limit(
        Request $request
    ): int {
        return max(
            1,
            min(
                (int)
                $request->input(
                    'limit',
                    500
                ),
                2000
            )
        );
    }

    private function emptyResult(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [],
            'meta' => [
                'count' => 0,
            ],
        ]);
    }

    private function collectionResult(
        $rows,
        Request $request
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'count' => $rows->count(),
                'limit' => $this->limit($request),
            ],
            'filters' => [
                'search' =>
                    $this->searchTerm($request),
            ],
        ]);
    }
}
