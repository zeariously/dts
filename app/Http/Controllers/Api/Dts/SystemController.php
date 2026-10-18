<?php

namespace App\Http\Controllers\Api\Dts;

use App\Http\Controllers\Api\Dts\Concerns\BuildsDtsDocumentQueries;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    use BuildsDtsDocumentQueries;

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

        $this->applyCommonFilters(
            $base,
            $request,
            $context,
            false
        );

        /*
         * Compute the workflow status first, then aggregate from a derived table.
         * This preserves the existing working ONLY_FULL_GROUP_BY-safe behavior.
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
            $key = strtolower(
                trim((string) $row->workflow_status)
            );

            $key = str_replace(
                [' ', '-'],
                '_',
                $key
            );

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
                'personnel_id' =>
                    $request->input('personnel_id'),
            ],
        ]);
    }
}
