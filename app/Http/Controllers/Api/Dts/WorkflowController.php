<?php

namespace App\Http\Controllers\Api\Dts;

use App\Http\Controllers\Api\Dts\Concerns\BuildsDtsDocumentQueries;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    use BuildsDtsDocumentQueries;

    public function incoming(Request $request): JsonResponse
    {
        [$query, $context] = $this->buildBaseQuery();

        $this->applyCommonFilters(
            $query,
            $request,
            $context,
            false
        );

        $query->whereRaw(
            'LOWER(('
            . $context['status_expression']
            . ')) = ?',
            ['for receiving']
        );

        return $this->paginateDocuments(
            $query,
            $context,
            $request,
            'incoming'
        );
    }

    public function returned(Request $request): JsonResponse
    {
        [$query, $context] = $this->buildBaseQuery();

        $this->applyCommonFilters(
            $query,
            $request,
            $context,
            false
        );

        $query->whereExists(function ($subQuery) {
            $subQuery
                ->select(DB::raw(1))
                ->from('distribution as returnedApiDist')
                ->whereColumn(
                    'returnedApiDist.IDdoc',
                    'd.IDdoc'
                )
                ->where(function ($returnedQuery) {
                    $returnedQuery
                        ->whereIn(
                            'returnedApiDist.YNreturn',
                            ['True', 'true', 'Y', 'y', '1']
                        )
                        ->orWhereNotNull(
                            'returnedApiDist.returndate'
                        );
                });
        });

        $columns = $this->documentSelectColumns($context);

        $columns[] = DB::raw(
            "(SELECT COUNT(*)
              FROM distribution apiReturnCount
              WHERE apiReturnCount.IDdoc = d.IDdoc
                AND (
                    apiReturnCount.YNreturn IN ('True','true','Y','y','1')
                    OR apiReturnCount.returndate IS NOT NULL
                )
            ) as return_history_count"
        );

        $columns[] = DB::raw(
            "(SELECT MAX(apiReturnDate.returndate)
              FROM distribution apiReturnDate
              WHERE apiReturnDate.IDdoc = d.IDdoc
                AND (
                    apiReturnDate.YNreturn IN ('True','true','Y','y','1')
                    OR apiReturnDate.returndate IS NOT NULL
                )
            ) as latest_return_date"
        );

        $perPage = $this->perPage($request);

        $documents = $query
            ->select($columns)
            ->orderByDesc(
                DB::raw(
                    "(SELECT MAX(apiReturnOrder.returndate)
                      FROM distribution apiReturnOrder
                      WHERE apiReturnOrder.IDdoc = d.IDdoc
                        AND (
                            apiReturnOrder.YNreturn IN ('True','true','Y','y','1')
                            OR apiReturnOrder.returndate IS NOT NULL
                        )
                    )"
                )
            )
            ->orderByDesc('d.IDdoc')
            ->paginate($perPage)
            ->appends($request->query());

        return $this->paginatedResponse(
            $documents,
            $request,
            'returned'
        );
    }

    private function paginateDocuments(
        $query,
        array $context,
        Request $request,
        string $workflow
    ): JsonResponse {
        $perPage = $this->perPage($request);

        $documents = $query
            ->select(
                $this->documentSelectColumns(
                    $context
                )
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

        return $this->paginatedResponse(
            $documents,
            $request,
            $workflow
        );
    }

    private function paginatedResponse(
        $documents,
        Request $request,
        string $workflow
    ): JsonResponse {
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
                'last' => $documents->url(
                    $documents->lastPage()
                ),
                'prev' => $documents->previousPageUrl(),
                'next' => $documents->nextPageUrl(),
            ],
            'filters' => [
                'workflow' => $workflow,
                'search' => trim(
                    (string) $request->input('search', '')
                ),
                'year' => $this->normalizedYear($request),
                'personnel_id' => $request->input(
                    'personnel_id'
                ),
            ],
        ]);
    }

    private function perPage(Request $request): int
    {
        return max(
            1,
            min(
                (int) $request->input('per_page', 25),
                100
            )
        );
    }
}
