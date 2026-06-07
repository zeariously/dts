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
    $perPage = (int) $request->input('per_page', 10);
    $search = trim((string) $request->input('search', ''));
    $section = $request->input('section', 'documents');
    $filter = $request->input('filter');

    if ($perPage < 1) {
        $perPage = 10;
    }

    if ($perPage > 100) {
        $perPage = 100;
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

    $requestedYear = $request->input('year');
    $selectedYear = $requestedYear !== null
        ? trim((string) $requestedYear)
        : (string) ($availableYears->contains((int) now()->year)
            ? now()->year
            : ($availableYears->first() ?? now()->year));

    if (strtolower($selectedYear) === 'all') {
        $selectedYear = '';
    }

    $trueValues = ['True', 'true', 'Y', 'y', '1', 1];

    $viewerOfficeIds = $this->viewerAssignedOfficeIds();
    $viewerPersonnelIds = $this->viewerAssignedPersonnelIds();

    $doctypeCodeColumn = 'dt.description';

    if (Schema::hasColumn('lu_doctype', 'abbreviation')) {
        $doctypeCodeColumn = 'dt.abbreviation';
    } elseif (Schema::hasColumn('lu_doctype', 'abbr')) {
        $doctypeCodeColumn = 'dt.abbr';
    } elseif (Schema::hasColumn('lu_doctype', 'code')) {
        $doctypeCodeColumn = 'dt.code';
    }

    $makeLatestDistribution = function () {
        return DB::table('distribution as dx')
            ->select([
                'dx.IDdoc',
                DB::raw('MAX(dx.IDdist) as latest_IDdist'),
            ])
            ->groupBy('dx.IDdoc');
    };

    $latestDistribution = $makeLatestDistribution();

    /*
     * Separate latest returned distribution.
     * Reason: returnDocument() marks the current distribution as returned,
     * then creates a new distribution back to the sender/previous handler.
     * Because of that, the latest distribution may already be the new
     * "For Receiving" row with no returndate. This join keeps the actual
     * return date available for Outgoing / Sent Documents.
     */
    $latestReturnedDistribution = DB::table('distribution as rx')
        ->select([
            'rx.IDdoc',
            DB::raw('MAX(rx.IDdist) as latest_returned_IDdist'),
        ])
        ->where(function ($query) use ($trueValues) {
            $query->whereIn('rx.YNreturn', $trueValues)
                ->orWhereNotNull('rx.returndate');
        })
        ->groupBy('rx.IDdoc');

    $documentsQuery = DB::table('document as d')
        ->leftJoin('lu_doctype as dt', 'dt.ID', '=', 'd.IDdoctype')
        ->leftJoin('lu_office as fromOffice', 'fromOffice.ID', '=', 'd.IDfrom')
        ->leftJoin('lu_office as forOffice', 'forOffice.ID', '=', 'd.IDfor')
        ->leftJoinSub($latestDistribution, 'ld', function ($join) {
            $join->on('ld.IDdoc', '=', 'd.IDdoc');
        })
        ->leftJoin('distribution as dist', 'dist.IDdist', '=', 'ld.latest_IDdist')
        ->leftJoinSub($latestReturnedDistribution, 'lrd', function ($join) {
            $join->on('lrd.IDdoc', '=', 'd.IDdoc');
        })
        ->leftJoin('distribution as returnDist', 'returnDist.IDdist', '=', 'lrd.latest_returned_IDdist')
        ->leftJoin('lu_office as distOffice', 'distOffice.ID', '=', 'dist.IDoffice')
        ->leftJoin('lu_personnel as receiverPersonnel', 'receiverPersonnel.ID', '=', 'd.IDkeeper')
        ->select([
            'd.IDdoc',
            'd.IDdoc as document_no',
            'd.classification',
            'd.IDdoctype',
            'd.entrydate',
            'd.IDfor',
            'd.IDfrom',
            'd.subject',
            'd.regarding',
            'd.remarks',
            'd.IDdocstatus',
            'd.IDkeeper',

            DB::raw($doctypeCodeColumn . ' as code'),
            'dt.description as doctype',

            'fromOffice.officename as from_office',
            'forOffice.officename as for_office',
            'distOffice.officename as current_office',
            'receiverPersonnel.name as receiver_personnel',
            'receiverPersonnel.name as to_personnel',

            'dist.IDdist',
            'dist.IDoffice as distribution_office_id',
            'dist.distdate',
            'dist.distdate as date_sent',
            'dist.distdate as distribution_date',
            'dist.confirmdate',
            'dist.returndate',
            DB::raw('COALESCE(returnDist.returndate, dist.returndate) as return_date'),
            'returnDist.IDdist as return_distribution_id',
            'dist.YNreturn',
            'dist.YNpulled',
            'dist.remarks as distribution_remarks',
        ]);

    $this->applyViewerDocumentScope($documentsQuery, 'd', 'dist', $viewerOfficeIds, $viewerPersonnelIds);

    if ($selectedYear !== '') {
        $documentsQuery->whereYear('d.entrydate', (int) $selectedYear);
    }

    if ($search !== '') {
        $documentsQuery->where(function ($query) use ($search) {
            $query->where('d.IDdoc', 'like', "%{$search}%")
                ->orWhere('d.subject', 'like', "%{$search}%")
                ->orWhere('d.regarding', 'like', "%{$search}%")
                ->orWhere('d.remarks', 'like', "%{$search}%")
                ->orWhere('dt.description', 'like', "%{$search}%")
                ->orWhere('fromOffice.officename', 'like', "%{$search}%")
                ->orWhere('forOffice.officename', 'like', "%{$search}%")
                ->orWhere('distOffice.officename', 'like', "%{$search}%");
        });
    }

    if ($section === 'reports') {
        if ($request->filled('report_classification')) {
            $documentsQuery->where('d.classification', $request->input('report_classification'));
        }

        if ($request->filled('subject_keyword')) {
            $documentsQuery->where('d.subject', 'like', '%' . $request->input('subject_keyword') . '%');
        }

        if ($request->filled('regarding_keyword')) {
            $documentsQuery->where('d.regarding', 'like', '%' . $request->input('regarding_keyword') . '%');
        }

        if ($request->filled('start_date')) {
            $documentsQuery->whereDate('d.entrydate', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $documentsQuery->whereDate('d.entrydate', '<=', $request->input('end_date'));
        }
    }

    if ($section === 'incoming' && $filter === '') {
        $documentsQuery
            ->whereNotNull('dist.IDdist')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            });
    }

    if ($section === 'received-docs') {
        $documentsQuery
            ->where('d.classification', 'False')
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.confirmdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            });

        if ($request->filled('keeper')) {
            $documentsQuery->where('d.IDkeeper', $request->input('keeper'));
        }

        if ($request->filled('doc_type')) {
            $documentsQuery->where('d.IDdoctype', $request->input('doc_type'));
        }
    }

    if ($section === 'pending-docs') {
        $documentsQuery
            ->whereNotNull('dist.IDdist')
            ->whereNull('dist.confirmdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            });
    }

    if ($section === 'pending-docs-07') {
        $documentsQuery
            ->whereNotNull('dist.IDdist')
            ->whereNull('dist.confirmdate')
            ->where('d.IDdocstatus', 7)
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            });
    }

    if ($section === 'sent-docs') {
        $documentsQuery
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.distdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            });
    }

    if ($section === 'pulled-out-docs') {
        $documentsQuery
            ->whereNotNull('dist.IDdist')
            ->whereIn('dist.YNpulled', $trueValues);
    }

    if ($filter === 'for-receiving') {
        $documentsQuery
            ->whereNotNull('dist.IDdist')
            ->whereNull('dist.confirmdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            });
    }

    if (in_array($filter, ['collab-received', 'received'], true)) {
        $documentsQuery
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.confirmdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            });
    }

    if ($filter === 'for-action') {
        $documentsQuery
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.confirmdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            });
    }

    if ($filter === 'returned') {
        $documentsQuery
            ->whereNotNull('dist.IDdist')
            ->whereIn('dist.YNreturn', $trueValues);
    }

    if ($filter === '15days') {
        $documentsQuery
            ->whereNotNull('d.entrydate')
            ->whereDate('d.entrydate', '<=', now()->subDays(15)->toDateString());
    }

    $documents = $documentsQuery
        ->orderByDesc(DB::raw('COALESCE(dist.distdate, d.entrydate)'))
        ->orderByDesc('d.IDdoc')
        ->paginate($perPage)
        ->appends($request->query());

    if (isset($documents) && method_exists($documents, 'getCollection')) {
        $documents->getCollection()->transform(function ($doc) use ($trueValues) {
            $isReturned = in_array((string) ($doc->YNreturn ?? ''), array_map('strval', $trueValues), true)
                || ! empty($doc->returndate);

            $isPulled = in_array((string) ($doc->YNpulled ?? ''), array_map('strval', $trueValues), true);

            if ($isReturned) {
                $doc->workflow_status = 'Returned';
            } elseif ($isPulled) {
                $doc->workflow_status = 'Pulled Out';
            } elseif (! empty($doc->confirmdate)) {
                $doc->workflow_status = 'Received';
            } elseif (! empty($doc->distdate)) {
                $doc->workflow_status = 'For Receiving';
            } elseif ((int) ($doc->IDdocstatus ?? 0) === 7) {
                $doc->workflow_status = 'Pending 07';
            } else {
                $doc->workflow_status = 'Pending';
            }

            return $doc;
        });
    }

    $statsLatestDistribution = DB::table('distribution as dx')
        ->select([
            'dx.IDdoc',
            DB::raw('MAX(dx.IDdist) as latest_IDdist'),
        ])
        ->groupBy('dx.IDdoc');

    $makeStatsBaseQuery = function () use ($statsLatestDistribution, $selectedYear, $viewerOfficeIds, $viewerPersonnelIds) {
        $query = DB::table('document as d')
            ->leftJoinSub($statsLatestDistribution, 'ldStats', function ($join) {
                $join->on('ldStats.IDdoc', '=', 'd.IDdoc');
            })
            ->leftJoin('distribution as dist', 'dist.IDdist', '=', 'ldStats.latest_IDdist')
            ->when($selectedYear !== '', function ($query) use ($selectedYear) {
                $query->whereYear('d.entrydate', (int) $selectedYear);
            });

        $this->applyViewerDocumentScope($query, 'd', 'dist', $viewerOfficeIds, $viewerPersonnelIds);

        return $query;
    };

    $stats = [
        'total' => (clone $makeStatsBaseQuery())
            ->distinct()
            ->count('d.IDdoc'),
       
        'for_receiving' => (clone $makeStatsBaseQuery())
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.distdate')
            ->whereNull('dist.confirmdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            })
            ->distinct()
            ->count('d.IDdoc'),

        'received' => (clone $makeStatsBaseQuery())
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.confirmdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            })
            ->distinct()
            ->count('d.IDdoc'),

        'returned' => (clone $makeStatsBaseQuery())
            ->whereNotNull('dist.IDdist')
            ->where(function ($query) use ($trueValues) {
                $query->whereIn('dist.YNreturn', $trueValues)
                    ->orWhereNotNull('dist.returndate');
            })
            ->distinct()
            ->count('d.IDdoc'),

        'pending_docs' => (clone $makeStatsBaseQuery())
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.distdate')
            ->whereNull('dist.confirmdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            })
            ->distinct()
            ->count('d.IDdoc'),

        'pending_docs_07' => (clone $makeStatsBaseQuery())
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.distdate')
            ->whereNull('dist.confirmdate')
            ->where('d.IDdocstatus', 7)
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            })
            ->distinct()
            ->count('d.IDdoc'),
    ];
  
    $viewerNotifications = collect();
    $creatorReceivedNotifications = collect();

    if ($this->currentUserRights() === '2') {
        $viewerNotificationsQuery = DB::table('document as d')
            ->leftJoin('lu_doctype as dt', 'dt.ID', '=', 'd.IDdoctype')
            ->leftJoin('lu_office as fromOffice', 'fromOffice.ID', '=', 'd.IDfrom')
            ->leftJoinSub($makeLatestDistribution(), 'viewerLd', function ($join) {
                $join->on('viewerLd.IDdoc', '=', 'd.IDdoc');
            })
            ->leftJoin('distribution as dist', 'dist.IDdist', '=', 'viewerLd.latest_IDdist')
            ->leftJoin('lu_office as distOffice', 'distOffice.ID', '=', 'dist.IDoffice')
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.distdate')
            ->whereNull('dist.confirmdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            })
            ->when($selectedYear !== '', function ($query) use ($selectedYear) {
                $query->whereYear('d.entrydate', (int) $selectedYear);
            });

        $this->applyViewerDocumentScope($viewerNotificationsQuery, 'd', 'dist', $viewerOfficeIds, $viewerPersonnelIds);

        $viewerNotifications = $viewerNotificationsQuery
            ->select([
                'd.IDdoc',
                'd.IDdoc as document_no',
                'd.subject',
                'd.entrydate',
                DB::raw($doctypeCodeColumn . ' as code'),
                'dt.description as doctype',
                'fromOffice.officename as from_office',
                'distOffice.officename as transferred_to',
                'dist.distdate as transfer_date',
                DB::raw('DATE_ADD(dist.distdate, INTERVAL 7 DAY) as due_date'),
            ])
            ->orderBy('dist.distdate')
            ->limit(20)
            ->get()
            ->map(function ($doc) {
                $transferDate = $doc->transfer_date ? Carbon::parse($doc->transfer_date) : null;
                $dueDate = $transferDate ? $transferDate->copy()->addDays(7) : null;

                return [
                    'IDdoc' => $doc->IDdoc,
                    'document_no' => $doc->document_no,
                    'subject' => $doc->subject,
                    'entrydate' => $doc->entrydate,
                    'code' => $doc->code,
                    'doctype' => $doc->doctype,
                    'from_office' => $doc->from_office,
                    'transferred_to' => $doc->transferred_to,
                    'transfer_date' => $doc->transfer_date,
                    'due_date' => $dueDate ? $dueDate->format('Y-m-d H:i:s') : null,
                    'days_since_transfer' => $transferDate ? $transferDate->diffInDays(now()) : 0,
                    'is_overdue' => $dueDate ? now()->greaterThanOrEqualTo($dueDate) : false,
                ];
            })
            ->values();
    }


    $currentUserId = $this->currentUserId();

    if ($currentUserId) {
        $creatorReceivedNotifications = DB::table('document as d')
            ->leftJoin('lu_doctype as dt', 'dt.ID', '=', 'd.IDdoctype')
            ->leftJoin('lu_office as fromOffice', 'fromOffice.ID', '=', 'd.IDfrom')
            ->leftJoinSub($makeLatestDistribution(), 'creatorLd', function ($join) {
                $join->on('creatorLd.IDdoc', '=', 'd.IDdoc');
            })
            ->leftJoin('distribution as dist', 'dist.IDdist', '=', 'creatorLd.latest_IDdist')
            ->leftJoin('lu_office as distOffice', 'distOffice.ID', '=', 'dist.IDoffice')
            ->leftJoin('username as receiveUser', 'receiveUser.ID', '=', 'dist.confirmuser')
            ->where('d.IDuser', $currentUserId)
            ->whereNotNull('dist.IDdist')
            ->whereNotNull('dist.confirmdate')
            ->when($selectedYear !== '', function ($query) use ($selectedYear) {
                $query->whereYear('d.entrydate', (int) $selectedYear);
            })
            ->select([
                'd.IDdoc',
                'd.IDdoc as document_no',
                'd.subject',
                'd.entrydate',
                DB::raw($doctypeCodeColumn . ' as code'),
                'dt.description as doctype',
                'fromOffice.officename as from_office',
                'distOffice.officename as received_office',
                'dist.distdate as transfer_date',
                'dist.confirmdate as received_date',
                'receiveUser.loginname as received_by',
            ])
            ->orderByDesc('dist.confirmdate')
            ->limit(20)
            ->get()
            ->map(function ($doc) {
                return [
                    'notification_type' => 'received_by_addressee',
                    'IDdoc' => $doc->IDdoc,
                    'document_no' => $doc->document_no,
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

    $officesForDropdown = Schema::hasTable('lu_office')
        ? DB::table('lu_office')
            ->select([
                'ID',
                'officename',
                'abbrev',
                'IDsucs',
            ])
            ->when(Schema::hasColumn('lu_office', 'IDsucs'), function ($query) {
                $query->whereNotNull('IDsucs')
                    ->where('IDsucs', '<>', 0);
            })
            ->whereNotNull('officename')
            ->whereRaw("TRIM(officename) != ''")
            ->whereRaw("TRIM(officename) != '-'")
            ->orderBy('officename')
            ->get()
        : collect();

    $staffConcernsForDropdown = Schema::hasTable('lu_personnel')
        ? DB::table('lu_personnel as p')
            ->leftJoin('lu_office as o', 'o.ID', '=', 'p.IDoffice')
            ->select([
                'p.ID',
                'p.name',
                'p.IDoffice',
                'o.officename as office_name',
            ])
            ->whereNotNull('p.name')
            ->whereRaw("TRIM(p.name) != ''")
            ->whereRaw("TRIM(p.name) != '-'")
            ->orderBy('p.name')
            ->get()
        : collect();

    return Inertia::render('DTS/Index', [
        'documents' => $documents,
        'stats' => $stats,
        'filters' => [
            'search' => $search,
            'per_page' => $perPage,
            'section' => $section,
            'filter' => $filter,
            'year' => $selectedYear,
            'report_classification' => $request->input('report_classification'),
            'subject_keyword' => $request->input('subject_keyword'),
            'regarding_keyword' => $request->input('regarding_keyword'),
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
            ? DB::table('lu_attachment')
                ->when(Schema::hasColumn('lu_attachment', 'description'), function ($query) {
                    $query->orderBy('description');
                })
                ->when(! Schema::hasColumn('lu_attachment', 'description') && Schema::hasColumn('lu_attachment', 'name'), function ($query) {
                    $query->orderBy('name');
                })
                ->when(! Schema::hasColumn('lu_attachment', 'description') && ! Schema::hasColumn('lu_attachment', 'name') && Schema::hasColumn('lu_attachment', 'ID'), function ($query) {
                    $query->orderBy('ID');
                })
                ->get()
            : [],
        'staffConcerns' => $staffConcernsForDropdown,
        'viewerNotifications' => $viewerNotifications,
        'creatorReceivedNotifications' => $creatorReceivedNotifications,
    ]);
}


    public function create()
    {
    $this->ensureCanManageDts();
        return Inertia::render('DTS/Create', [
            'documentTypes' => DtsDocType::orderBy('description')->get(),
            'offices' => DtsOffice::orderBy('officename')->get(),
            'statuses' => DtsDocStatus::orderBy('name')->get(),
            'transactions' => DtsTransaction::orderBy('name')->get(),
        ]);
    }

  public function store(Request $request)
{
    $this->ensureCanManageDts();
    
    $request->merge([
        'classification' => $request->input('classification_id', $request->input('classification')),
        'IDdoctype' => $request->input('type_id', $request->input('IDdoctype')),
        'IDfrom' => $request->input('from_office_id', $request->input('IDfrom')),
        'IDfor' => $request->input('to_office_id', $request->input('IDfor')),
        'IDkeeper' => $request->input('staff_concern_id', $request->input('IDkeeper')),
    ]);

    $validated = $request->validate([
        'classification' => ['required', 'in:False,True'],
        'IDdoctype' => ['required', 'integer', 'exists:lu_doctype,ID'],
        'IDtransac' => ['nullable', 'integer', 'exists:transactions,ID'],
        'IDfrom' => ['required', 'integer', 'exists:lu_office,ID'],
        'IDfor' => ['required', 'integer', 'exists:lu_office,ID'],
        'IDdocstatus' => ['nullable', 'integer', 'exists:lu_docstatus,ID'],
        'IDkeeper' => ['nullable', 'integer', 'exists:lu_personnel,ID'],

        'entry_month' => ['nullable', 'digits_between:1,2'],
        'entry_day' => ['nullable', 'digits_between:1,2'],
        'entry_year' => ['nullable', 'digits_between:2,4'],

        'subject' => ['required', 'string', 'max:255'],
        'regarding' => ['nullable', 'string'],
        'remarks' => ['nullable', 'string'],

        'attachments' => ['nullable', 'array'],
        'attachments.*.type_id' => ['nullable', 'integer'],
        'attachments.*.type_name' => ['nullable', 'string', 'max:255'],
        'attachments.*.file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
    ]);

    $entryDate = now()->format('Y-m-d H:i:s');

    if ($request->filled(['entry_month', 'entry_day', 'entry_year'])) {
        try {
            $month = str_pad($request->entry_month, 2, '0', STR_PAD_LEFT);
            $day = str_pad($request->entry_day, 2, '0', STR_PAD_LEFT);
            $year = $request->entry_year;

            if (strlen($year) === 2) {
                $year = '20' . $year;
            }

            $entryDate = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                "{$year}-{$month}-{$day} " . now()->format('H:i:s')
            )->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            $entryDate = now()->format('Y-m-d H:i:s');
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

    $document = DB::transaction(function () use ($request, $validated, $entryDate, $defaultDocStatusId) {
        $nextDocumentId = ((int) DtsDocument::max('IDdoc')) + 1;
        $nextDistributionId = ((int) DtsDistribution::max('IDdist')) + 1;
        $nextDocTransactionId = ((int) DtsDocTransaction::max('ID')) + 1;

        $hasAttachments = count($request->input('attachments', [])) > 0;

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
            'IDkeeper' => $validated['IDkeeper'] ?? null,
            'IDprogram_pms' => null,
            'IDproject' => null,
            'IDprogram_prp' => null,
            'IDproposal' => null,
            'IDdocrq' => null,
            'YNdays' => 'False',
            'datecleared' => null,
        ]);

        if (! empty($validated['IDtransac'])) {
            DtsDocTransaction::create([
                'ID' => $nextDocTransactionId,
                'IDdoc' => $document->IDdoc,
                'IDtransac' => $validated['IDtransac'],
                'YNattach' => $hasAttachments ? 'True' : 'False',
                'IDparentdoc' => null,
            ]);
        }

        DtsDistribution::create([
            'IDdist' => $nextDistributionId,
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
            'idmapagency' => $validated['IDkeeper'] ?? null,
        ]);

        $attachments = $request->input('attachments', []);

        foreach ($attachments as $index => $attachment) {
            $file = $request->file("attachments.{$index}.file");

            if (! $file) {
                continue;
            }

            $attachmentTypeId = $attachment['type_id'] ?? null;
            $attachmentTypeName = $attachment['type_name'] ?? 'Uploaded File';

            $path = $file->store("dts/documents/{$document->IDdoc}", 'public');

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

        return $document;
    });

    $this->recordDtsActivity(
        'created document',
        'Created document #' . $document->IDdoc . ': ' . ($document->subject ?? 'No subject'),
        (int) $document->IDdoc,
        [
            'subject' => $document->subject ?? null,
            'classification' => $document->classification ?? null,
        ]
    );

    return redirect()->route('dts.show', $document->IDdoc);
}
  public function show($id)
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

    $trueValues = ['True', 'true', 'Y', 'y', '1', 1];

    abort_unless($this->viewerCanAccessDocument((int) $document->IDdoc), 403);

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
                'fileUser.loginname as uploaded_by_name',
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

    $remarksHistory = Schema::hasTable('dts_document_remarks')
        ? DB::table('dts_document_remarks')
            ->leftJoin('username as remarkUser', 'remarkUser.ID', '=', 'dts_document_remarks.created_by')
            ->where('dts_document_remarks.IDdoc', $document->IDdoc)
            ->orderByDesc('dts_document_remarks.created_at')
            ->select([
                'dts_document_remarks.id',
                'dts_document_remarks.IDdoc',
                'dts_document_remarks.remarks',
                'dts_document_remarks.created_by',
                'dts_document_remarks.created_at',
                'remarkUser.loginname as created_by_name',
            ])
            ->get()
        : collect();

    $documentCreatorName = null;

    if (Schema::hasTable('username') && ! empty($document->IDuser)) {
        $documentCreatorName = DB::table('username')
            ->where('ID', $document->IDuser)
            ->value('loginname');
    }

    $distributionRowsForHistory = DB::table('distribution as dist')
        ->leftJoin('lu_office as office', 'office.ID', '=', 'dist.IDoffice')
        ->leftJoin('username as transferUser', 'transferUser.ID', '=', 'dist.IDuser')
        ->leftJoin('username as receiveUser', 'receiveUser.ID', '=', 'dist.confirmuser')
        ->leftJoin('lu_personnel as targetPersonnel', 'targetPersonnel.ID', '=', 'dist.idmapagency')
        ->where('dist.IDdoc', $document->IDdoc)
        ->orderBy('dist.IDdist')
        ->select([
            'dist.IDdist',
            'dist.IDdoc',
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
            'office.officename as office_name',
            'targetPersonnel.name as target_personnel_name',
            'transferUser.loginname as transferred_by_name',
            'receiveUser.loginname as received_by_name',
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
        ->map(function ($distribution) {
            return [
                'IDdist' => $distribution->IDdist,
                'IDdoc' => $distribution->IDdoc,
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
                'transferred_by_name' => $distribution->transferred_by_name,
                'received_by_name' => $distribution->received_by_name,
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

    $currentWorkflowStatus = 'Pending';

    if ($isLatestReturned) {
        $currentWorkflowStatus = 'Returned';
    } elseif ($isLatestPulled) {
        $currentWorkflowStatus = 'Pulled Out';
    } elseif (! empty($latestDistributionForSummary?->confirmdate)) {
        $currentWorkflowStatus = 'Received';
    } elseif (! empty($latestDistributionForSummary?->distdate)) {
        $currentWorkflowStatus = 'For Receiving';
    } elseif ((int) ($document->IDdocstatus ?? 0) === 7) {
        $currentWorkflowStatus = 'Pending 07';
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

            if (! empty($distRow->IDparentdist)) {
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
                $distRow->remarks
            );

            $isReturned = in_array((string) ($distRow->YNreturn ?? ''), array_map('strval', $trueValues), true)
                || ! empty($distRow->returndate);

            if ($isReturned) {
                $addHistory(
                    'returned',
                    'Returned Document',
                    'Document was returned.',
                    $distRow->returndate ?: $distRow->distdate,
                    $transferredBy,
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
        $addHistory(
            'remark',
            'Added Remark',
            'A remark was added to this document.',
            $remarkItem->created_at,
            $remarkItem->created_by_name ?? ($remarkItem->created_by ? 'Account #' . $remarkItem->created_by : null),
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

    $actionHistory = $actionHistory
        ->filter(function ($item) use ($document) {
            return (int) ($item['IDdoc'] ?? 0) === (int) $document->IDdoc;
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

    return Inertia::render('DTS/Show', [
                'document' => [
                'IDdoc' => $document->IDdoc,
                'document_no' => $document->IDdoc,
                'created_by' => $document->IDuser,
                'created_by_name' => $documentCreatorName ?? (! empty($document->IDuser) ? 'Account #' . $document->IDuser : null),

                'classification' => $document->classification,
                'classification_label' => $document->classification === 'True' ? 'Outgoing' : 'Incoming',

                'IDdoctype' => $document->IDdoctype,
                'doctype' => $document->docType?->description,

                'entrydate' => $document->entrydate,

                'IDfor' => $document->IDfor,
                'IDfrom' => $document->IDfrom,
                'for_office' => $document->forOffice?->officename,
                'from_office' => $document->fromOffice?->officename,

                'subject' => $document->subject,
                'regarding' => $document->regarding,
                'remarks' => $document->remarks,

                'IDkeeper' => $document->IDkeeper,
                'staff_concern' => $staffConcerns->firstWhere('ID', $document->IDkeeper)?->name,

                'IDdocstatus' => $document->IDdocstatus,
                'status' => $document->status?->name,

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

   public function receive($id)
{
    $this->ensureCanReceiveDts();
    $this->ensureViewerCanActOnDocument((int) $id);

    $latestDistribution = DB::table('distribution')
        ->where('IDdoc', $id)
        ->orderByDesc('IDdist')
        ->first();

    if (! $latestDistribution) {
        return back()->withErrors([
            'receive' => 'No distribution record found for this document.',
        ]);
    }

    if (! empty($latestDistribution->confirmdate)) {
        return back()->withErrors([
            'receive' => 'This document is already received.',
        ]);
    }

    DB::table('distribution')
        ->where('IDdist', $latestDistribution->IDdist)
        ->update([
            'confirmdate' => now()->format('Y-m-d H:i:s'),
            'confirmuser' => Auth::id(),
        ]);

    $this->recordDtsActivity(
        'received document',
        'Received document #' . $id . '.',
        (int) $id
    );

    return back()->with('success', 'Document received successfully.');
}

public function pullout($id)
{
    $this->ensureCanManageDts();
    $latestDistribution = DB::table('distribution')
        ->where('IDdoc', $id)
        ->orderByDesc('IDdist')
        ->first();

    if (! $latestDistribution) {
        return back()->withErrors([
            'pullout' => 'No distribution record found for this document.',
        ]);
    }

    if (! empty($latestDistribution->confirmdate)) {
        return back()->withErrors([
            'pullout' => 'This document is already received and cannot be pulled out.',
        ]);
    }

    DB::table('distribution')
        ->where('IDdist', $latestDistribution->IDdist)
        ->update([
            'YNpulled' => 'True',
        ]);

    $this->recordDtsActivity(
        'pulled out document',
        'Pulled out document #' . $id . '.',
        (int) $id
    );

    return back()->with('success', 'Document pulled out successfully.');
}
public function forward(Request $request, $id)
{
    $this->ensureCanReceiveDts();
    $this->ensureViewerCanActOnDocument((int) $id);

    $validated = $request->validate([
        'IDpersonnel' => ['required', 'integer', 'exists:lu_personnel,ID'],
        'remarks' => ['nullable', 'string'],
    ]);

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

    DB::transaction(function () use ($document, $validated, $personnel) {
        $latestDistribution = DtsDistribution::where('IDdoc', $document->IDdoc)
            ->orderByDesc('IDdist')
            ->first();

        if (! $latestDistribution) {
            abort(422, 'No distribution record found for this document.');
        }

        DtsDistribution::create([
            'IDdist' => $this->nextDistributionId(),
            'IDdoc' => $document->IDdoc,
            'IDoffice' => $personnel->IDoffice,
            'distdate' => now()->format('Y-m-d H:i:s'),
            'confirmdate' => null,
            'confirmuser' => null,
            'YNreturn' => 'False',
            'returndate' => null,
            'IDuser' => Auth::id(),
            'remarks' => $validated['remarks'] ?? null,
            'IDparentdist' => $latestDistribution?->IDdist,
            'YNpulled' => 'False',
            'idmapagency' => $personnel->ID,
        ]);

        $document->update([
            'IDfor' => $personnel->IDoffice,
            'IDkeeper' => $personnel->ID,
        ]);
    });

    $this->recordDtsActivity(
        'forwarded document',
        'Forwarded document #' . $document->IDdoc . ' to ' . ($personnel->name ?? 'Personnel #' . $validated['IDpersonnel']) . '.',
        (int) $document->IDdoc,
        [
            'to_personnel_id' => $validated['IDpersonnel'],
            'to_personnel_name' => $personnel->name ?? null,
            'to_office_id' => $personnel->IDoffice,
            'to_office_name' => $personnel->office_name ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]
    );

    return back()->with('success', 'Document transferred successfully.');
}

public function returnDocument(Request $request, $id)
{
    $this->ensureCanReceiveDts();
    $this->ensureViewerCanActOnDocument((int) $id);

    $validated = $request->validate([
        'remarks' => ['required', 'string'],
    ]);

    $document = DtsDocument::findOrFail($id);

    $latestDistribution = DtsDistribution::where('IDdoc', $document->IDdoc)
        ->orderByDesc('IDdist')
        ->first();

    if (! $latestDistribution) {
        return back()->withErrors([
            'remarks' => 'No distribution record found for this document.',
        ]);
    }

    if (in_array($latestDistribution->YNreturn, ['True', 'true', 'Y', 'y', '1', 1], true)) {
        return back()->withErrors([
            'remarks' => 'This document is already returned.',
        ]);
    }

    /*
     * Returning a document should also create a new transfer back to the sender/previous handler.
     * The current distribution is marked as returned, then a new distribution is created
     * so the returned document will appear in the target user's For Receiving list.
     */
    $returnTarget = $this->resolveReturnTarget($document, $latestDistribution);

    if (empty($returnTarget['office_id'])) {
        return back()->withErrors([
            'remarks' => 'Unable to return this document because the sender/previous handler has no assigned office.',
        ]);
    }

    DB::transaction(function () use ($document, $latestDistribution, $validated, $returnTarget) {
        $latestDistribution->update([
            'YNreturn' => 'True',
            'returndate' => now()->format('Y-m-d H:i:s'),
            'remarks' => $validated['remarks'],
        ]);

        DtsDistribution::create([
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
            'idmapagency' => $returnTarget['personnel_id'] ?? null,
        ]);

        $document->update([
            'IDfor' => $returnTarget['office_id'],
            'IDkeeper' => $returnTarget['personnel_id'],
        ]);
    });

    $this->recordDtsActivity(
        'returned document',
        'Returned document #' . $document->IDdoc . ' to ' . ($returnTarget['name'] ?? 'previous handler') . '.',
        (int) $document->IDdoc,
        [
            'to_personnel_id' => $returnTarget['personnel_id'] ?? null,
            'to_personnel_name' => $returnTarget['name'] ?? null,
            'to_office_id' => $returnTarget['office_id'] ?? null,
            'to_office_name' => $returnTarget['office_name'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]
    );

    return back()->with('success', 'Document returned and transferred back successfully.');
}


private function resolveReturnTarget($document, $latestDistribution): array
{
    $targetUserId = $latestDistribution->IDuser ?? $document->IDuser ?? null;

    if ($targetUserId) {
        $target = $this->personnelAndOfficeForUser((int) $targetUserId);

        if (! empty($target['office_id'])) {
            return $target;
        }
    }

    if (! empty($latestDistribution->IDparentdist)) {
        $parentDistribution = DB::table('distribution')
            ->where('IDdist', $latestDistribution->IDparentdist)
            ->first();

        if ($parentDistribution) {
            if (! empty($parentDistribution->IDuser)) {
                $target = $this->personnelAndOfficeForUser((int) $parentDistribution->IDuser);

                if (! empty($target['office_id'])) {
                    return $target;
                }
            }

            if (! empty($parentDistribution->IDoffice)) {
                return [
                    'personnel_id' => null,
                    'name' => 'Previous handler',
                    'office_id' => (int) $parentDistribution->IDoffice,
                    'office_name' => DB::table('lu_office')
                        ->where('ID', $parentDistribution->IDoffice)
                        ->value('officename'),
                ];
            }
        }
    }

    if (! empty($document->IDfrom)) {
        return [
            'personnel_id' => null,
            'name' => 'Origin office',
            'office_id' => (int) $document->IDfrom,
            'office_name' => DB::table('lu_office')
                ->where('ID', $document->IDfrom)
                ->value('officename'),
        ];
    }

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
                'name' => $user->loginname ?? $user->username ?? 'User #' . $userId,
                'office_id' => (int) $user->{$column},
                'office_name' => DB::table('lu_office')
                    ->where('ID', $user->{$column})
                    ->value('officename'),
            ];
        }
    }

    return [
        'personnel_id' => null,
        'name' => $user->loginname ?? $user->username ?? 'User #' . $userId,
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

    return Inertia::render('DTS/Library', [
        'offices' => $offices,
        'personnel' => $personnel,
        'docTypes' => $docTypes,
        'docStatuses' => $docStatuses,
        'notes' => $notes,
        'attachments' => $attachments,
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
    $this->ensureCanManageDts();

    $validated = $request->validate([
        'attachments' => ['required', 'array', 'min:1'],
        'attachments.*' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
        'remarks' => ['nullable', 'string', 'max:2000'],
    ], [
        'attachments.required' => 'Please select at least one PDF file.',
        'attachments.*.mimes' => 'Only PDF files are allowed.',
        'attachments.*.mimetypes' => 'Only PDF files are allowed.',
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

    DB::transaction(function () use ($request, $document, $validated, $createdAt) {
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
            DB::table('dts_document_remarks')->insert([
                'IDdoc' => $document->IDdoc,
                'remarks' => $remarks,
                'created_by' => Auth::id(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            DB::table('document')
                ->where('IDdoc', $document->IDdoc)
                ->update([
                    'remarks' => $remarks,
                ]);
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

public function storeRemark(Request $request, $id)
{
    $this->ensureCanManageDts();
    $validated = $request->validate([
        'remarks' => ['required', 'string'],
    ]);

    $document = DtsDocument::where('IDdoc', $id)->firstOrFail();

    DB::transaction(function () use ($document, $validated) {
        DB::table('dts_document_remarks')->insert([
            'IDdoc' => $document->IDdoc,
            'remarks' => $validated['remarks'],
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Keep latest remark visible sa dashboard/detail summary
        $document->update([
            'remarks' => $validated['remarks'],
        ]);
    });

    $this->recordDtsActivity(
        'added remark',
        'Added remark to document #' . $document->IDdoc . '.',
        (int) $document->IDdoc,
        [
            'remarks' => $validated['remarks'],
        ]
    );

    return back()->with('success', 'Remark added successfully.');
}


public function monitoringDashboard(Request $request)
{
     $user = auth()->user();

    if ((int) ($user->rights ?? 0) !== 4) {
        abort(403, 'BAWAL KA RITO  .');
    }
    $trueValues = ['True', 'true', 'Y', 'y', '1', 1];

    $search = trim((string) $request->input('search', ''));
    $status = trim((string) $request->input('status', ''));
    $perPage = (int) $request->input('per_page', 15);

    if ($perPage < 1) {
        $perPage = 15;
    }

    if ($perPage > 100) {
        $perPage = 100;
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

    $requestedYear = $request->input('year');

    $selectedYear = $requestedYear !== null
        ? trim((string) $requestedYear)
        : (string) ($availableYears->contains((int) now()->year)
            ? now()->year
            : ($availableYears->first() ?? now()->year));

    if (strtolower($selectedYear) === 'all') {
        $selectedYear = '';
    }

    /*
     * Main table: simplified list of document transactions.
     * Columns needed in Vue:
     * Doc ID, Subject, Assigned Personnel, Days Pending.
     */
    $transactionsQuery = DB::table('distribution as dist')
        ->leftJoin('document as d', 'd.IDdoc', '=', 'dist.IDdoc')
        ->leftJoin('lu_doctype as dt', 'dt.ID', '=', 'd.IDdoctype')
        ->leftJoin('lu_personnel as assignedPersonnel', 'assignedPersonnel.ID', '=', 'd.IDkeeper')
        ->when($selectedYear !== '', function ($query) use ($selectedYear) {
            $query->whereYear('d.entrydate', (int) $selectedYear);
        })
        ->select([
            'dist.IDdist',
            'dist.IDdoc',
            'dist.distdate',
            'dist.confirmdate',
            'dist.YNreturn',
            'dist.returndate',
            'dist.YNpulled',
            'd.subject',
            'd.entrydate',
            'd.IDkeeper',
            'dt.description as document_type',
            'assignedPersonnel.name as assigned_personnel',
            DB::raw("\n                CASE\n                    WHEN dist.confirmdate IS NULL\n                         AND dist.distdate IS NOT NULL\n                         AND (dist.YNreturn IS NULL OR dist.YNreturn NOT IN ('True', 'true', 'Y', 'y', '1'))\n                         AND (dist.YNpulled IS NULL OR dist.YNpulled NOT IN ('True', 'true', 'Y', 'y', '1'))\n                    THEN DATEDIFF(NOW(), dist.distdate)\n                    ELSE 0\n                END as days_pending\n            "),
        ]);

    if ($search !== '') {
        $transactionsQuery->where(function ($query) use ($search) {
            $query->where('d.IDdoc', 'like', "%{$search}%")
                ->orWhere('d.subject', 'like', "%{$search}%")
                ->orWhere('assignedPersonnel.name', 'like', "%{$search}%");
        });
    }

    if ($status === 'no-action') {
        $transactionsQuery
            ->whereNotNull('dist.distdate')
            ->whereNull('dist.confirmdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            });
    }

    if ($status === 'received') {
        $transactionsQuery->whereNotNull('dist.confirmdate');
    }

    if ($status === 'returned') {
        $transactionsQuery->where(function ($query) use ($trueValues) {
            $query->whereIn('dist.YNreturn', $trueValues)
                ->orWhereNotNull('dist.returndate');
        });
    }

    if ($status === 'pulled-out') {
        $transactionsQuery->whereIn('dist.YNpulled', $trueValues);
    }

    $transactions = $transactionsQuery
        ->orderByDesc('dist.IDdist')
        ->paginate($perPage)
        ->appends($request->query());

    /*
     * Dashboard cards should count DOCUMENTS, not distribution/transaction rows.
     * The normal DTS dashboard counts documents, so this Monitoring Dashboard
     * must also use document count to avoid inflated numbers.
     */
    $totalDocuments = DB::table('document as d')
        ->when($selectedYear !== '', function ($query) use ($selectedYear) {
            $query->whereYear('d.entrydate', (int) $selectedYear);
        })
        ->count('d.IDdoc');

    $statsBase = DB::table('distribution as dist')
        ->leftJoin('document as d', 'd.IDdoc', '=', 'dist.IDdoc')
        ->when($selectedYear !== '', function ($query) use ($selectedYear) {
            $query->whereYear('d.entrydate', (int) $selectedYear);
        });

    $stats = [
        /*
         * Correct value for the first card.
         * total_transactions is kept only as fallback for old Vue code,
         * but its value is now also document count.
         */
        'total_documents' => $totalDocuments,
        'total_transactions' => $totalDocuments,

        'no_action' => (clone $statsBase)
            ->whereNotNull('dist.distdate')
            ->whereNull('dist.confirmdate')
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNreturn')
                    ->orWhereNotIn('dist.YNreturn', $trueValues);
            })
            ->where(function ($query) use ($trueValues) {
                $query->whereNull('dist.YNpulled')
                    ->orWhereNotIn('dist.YNpulled', $trueValues);
            })
            ->distinct()
            ->count('dist.IDdoc'),

        'received' => (clone $statsBase)
            ->whereNotNull('dist.confirmdate')
            ->distinct()
            ->count('dist.IDdoc'),

        'returned' => (clone $statsBase)
            ->where(function ($query) use ($trueValues) {
                $query->whereIn('dist.YNreturn', $trueValues)
                    ->orWhereNotNull('dist.returndate');
            })
            ->distinct()
            ->count('dist.IDdoc'),

        'pulled_out' => (clone $statsBase)
            ->whereIn('dist.YNpulled', $trueValues)
            ->distinct()
            ->count('dist.IDdoc'),
    ];

    /*
     * Table: Sino ang hindi uma-action?
     * Group pending documents by assigned personnel, then attach the document list per person.
     */
    $peopleNoAction = DB::table('distribution as dist')
        ->leftJoin('document as d', 'd.IDdoc', '=', 'dist.IDdoc')
        ->leftJoin('lu_personnel as p', 'p.ID', '=', 'd.IDkeeper')
        ->leftJoin('lu_office as o', 'o.ID', '=', 'p.IDoffice')
        ->when($selectedYear !== '', function ($query) use ($selectedYear) {
            $query->whereYear('d.entrydate', (int) $selectedYear);
        })
        ->whereNotNull('dist.distdate')
        ->whereNull('dist.confirmdate')
        ->where(function ($query) use ($trueValues) {
            $query->whereNull('dist.YNreturn')
                ->orWhereNotIn('dist.YNreturn', $trueValues);
        })
        ->where(function ($query) use ($trueValues) {
            $query->whereNull('dist.YNpulled')
                ->orWhereNotIn('dist.YNpulled', $trueValues);
        })
        ->select([
            'p.ID as personnel_id',
            DB::raw("COALESCE(p.name, 'Unassigned') as personnel_name"),
            DB::raw("COALESCE(o.officename, 'No office') as office_name"),
            DB::raw('COUNT(dist.IDdist) as pending_transactions'),
            DB::raw('MAX(DATEDIFF(NOW(), dist.distdate)) as max_days_pending'),
            DB::raw('MIN(dist.distdate) as oldest_pending_date'),
        ])
        ->groupBy('p.ID', 'p.name', 'o.officename')
        ->orderByDesc('max_days_pending')
        ->orderByDesc('pending_transactions')
        ->limit(20)
        ->get();

    $pendingDocumentsForPeople = DB::table('distribution as dist')
        ->leftJoin('document as d', 'd.IDdoc', '=', 'dist.IDdoc')
        ->leftJoin('lu_personnel as p', 'p.ID', '=', 'd.IDkeeper')
        ->leftJoin('lu_office as o', 'o.ID', '=', 'p.IDoffice')
        ->when($selectedYear !== '', function ($query) use ($selectedYear) {
            $query->whereYear('d.entrydate', (int) $selectedYear);
        })
        ->whereNotNull('dist.distdate')
        ->whereNull('dist.confirmdate')
        ->where(function ($query) use ($trueValues) {
            $query->whereNull('dist.YNreturn')
                ->orWhereNotIn('dist.YNreturn', $trueValues);
        })
        ->where(function ($query) use ($trueValues) {
            $query->whereNull('dist.YNpulled')
                ->orWhereNotIn('dist.YNpulled', $trueValues);
        })
        ->select([
            'p.ID as personnel_id',
            DB::raw("COALESCE(p.name, 'Unassigned') as personnel_name"),
            DB::raw("COALESCE(o.officename, 'No office') as office_name"),
            'd.IDdoc',
            'd.subject',
            'dist.distdate',
            DB::raw('DATEDIFF(NOW(), dist.distdate) as days_pending'),
        ])
        ->orderByDesc('days_pending')
        ->orderBy('d.IDdoc')
        ->limit(500)
        ->get()
        ->groupBy(function ($doc) {
            return $doc->personnel_id ? (string) $doc->personnel_id : 'unassigned';
        });

    $peopleNoAction = $peopleNoAction->map(function ($person) use ($pendingDocumentsForPeople) {
        $key = $person->personnel_id ? (string) $person->personnel_id : 'unassigned';

        $person->documents = $pendingDocumentsForPeople
            ->get($key, collect())
            ->values();

        return $person;
    });

    return Inertia::render('DTS/MonitoringDashboard', [
        'stats' => $stats,
        'transactions' => $transactions,
        'peopleNoAction' => $peopleNoAction,
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

private function viewerAssignedPersonnelIds(): array
{
    if ($this->currentUserRights() !== '2') {
        return [];
    }

    $user = auth()->user();

    if (! $user) {
        return [];
    }

    $ids = [];

    foreach (['IDpersonnel', 'personnel_id', 'IDkeeper', 'staff_id', 'employee_id'] as $field) {
        $value = $user->{$field} ?? null;

        if ($value !== null && $value !== '') {
            $ids[] = $value;
        }
    }

    if (Schema::hasTable('lu_personnel')) {
        $personnelQuery = DB::table('lu_personnel');

        $hasCondition = false;
        $userId = $this->currentUserId();

        foreach (['IDuser', 'user_id', 'IDusername', 'username_id', 'account_id'] as $column) {
            if ($userId && Schema::hasColumn('lu_personnel', $column)) {
                $hasCondition = true;
                $personnelQuery->orWhere($column, $userId);
            }
        }

        $loginName = trim((string) ($user->loginname ?? $user->username ?? ''));
        $displayName = trim((string) ($user->name ?? ''));

        foreach (['loginname', 'username'] as $column) {
            if ($loginName !== '' && Schema::hasColumn('lu_personnel', $column)) {
                $hasCondition = true;
                $personnelQuery->orWhere($column, $loginName);
            }
        }

        /*
         * Fallback:
         * Some viewer accounts are not linked to lu_personnel by ID/user_id.
         * In that case, match the account loginname/display name to lu_personnel.name.
         * Example: username/loginname "charlene" -> personnel name "Charlene".
         */
        if (Schema::hasColumn('lu_personnel', 'name')) {
            if ($loginName !== '') {
                $hasCondition = true;
                $personnelQuery->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower($loginName)]);
            }

            if ($displayName !== '') {
                $hasCondition = true;
                $personnelQuery->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower($displayName)]);
            }
        }

        $email = $user->email ?? null;

        if ($email && Schema::hasColumn('lu_personnel', 'email')) {
            $hasCondition = true;
            $personnelQuery->orWhere('email', $email);
        }

        if ($hasCondition) {
            $ids = array_merge($ids, $personnelQuery->pluck('ID')->all());
        }
    }

    return $this->cleanIntegerIds($ids);
}

private function viewerAssignedOfficeIds(?array $personnelIds = null): array
{
    if ($this->currentUserRights() !== '2') {
        return [];
    }

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
    if ($this->currentUserRights() !== '2') {
        return $query;
    }

    $personnelIds = $personnelIds ?? $this->viewerAssignedPersonnelIds();
    $officeIds = $officeIds ?? $this->viewerAssignedOfficeIds($personnelIds);
    $userId = $this->currentUserId();

    if (empty($officeIds) && empty($personnelIds) && ! $userId) {
        return $query->whereRaw('1 = 0');
    }

    return $query->where(function ($scope) use ($documentAlias, $distributionAlias, $officeIds, $personnelIds, $userId) {
        if (! empty($officeIds)) {
            $scope->orWhereIn($distributionAlias . '.IDoffice', $officeIds)
                ->orWhereIn($documentAlias . '.IDfor', $officeIds);
        }

        if (! empty($personnelIds)) {
            $scope->orWhereIn($documentAlias . '.IDkeeper', $personnelIds);
        }

        if ($userId) {
            $scope->orWhere($distributionAlias . '.confirmuser', $userId);
        }
    });
}

private function viewerCanAccessDocument(int $documentId): bool
{
    if ($this->currentUserRights() !== '2') {
        return true;
    }

    $personnelIds = $this->viewerAssignedPersonnelIds();
    $officeIds = $this->viewerAssignedOfficeIds($personnelIds);

    if (empty($officeIds) && empty($personnelIds) && ! $this->currentUserId()) {
        return false;
    }

    $latestDistribution = DB::table('distribution as accessDx')
        ->select([
            'accessDx.IDdoc',
            DB::raw('MAX(accessDx.IDdist) as latest_IDdist'),
        ])
        ->groupBy('accessDx.IDdoc');

    $query = DB::table('document as d')
        ->leftJoinSub($latestDistribution, 'accessLd', function ($join) {
            $join->on('accessLd.IDdoc', '=', 'd.IDdoc');
        })
        ->leftJoin('distribution as dist', 'dist.IDdist', '=', 'accessLd.latest_IDdist')
        ->where('d.IDdoc', $documentId);

    $this->applyViewerDocumentScope($query, 'd', 'dist', $officeIds, $personnelIds);

    return $query->exists();
}

private function ensureViewerCanActOnDocument(int $documentId): void
{
    abort_unless($this->viewerCanAccessDocument($documentId), 403);
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

private function canReceiveDts(): bool
{
    return in_array((string) optional(Auth::user())->rights, ['1', '2', '3', '4'], true);
}

private function ensureCanReceiveDts(): void
{
    abort_unless($this->canReceiveDts(), 403);
}
private function canAccessMonitoringDashboard(): bool
{
    return in_array((string) optional(Auth::user())->rights, ['1', '3', '4'], true);
}
}
