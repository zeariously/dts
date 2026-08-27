<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminUserManagementController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdmin();

        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 15, 20, 50], true) ? $perPage : 10;

        $documentPerPage = (int) $request->input('document_per_page', 15);
        $documentPerPage = in_array($documentPerPage, [10, 15, 20, 50], true)
            ? $documentPerPage
            : 15;

        $search = trim((string) $request->input('search', ''));
        $documentSearch = trim((string) $request->input('document_search', ''));
        $roleId = $request->input('role_id');
        $tab = $request->input('tab', 'role-management');

        $roles = $this->roles();
        $users = $this->buildUsersPaginator($search, $roleId, $perPage, $roles);
        $documents = $this->buildDocumentsPaginator($documentSearch, $documentPerPage);
        $activityLogs = $this->buildActivityLogs();
        $announcements = $this->buildAnnouncements();

        return Inertia::render('Admin/UserManagement', [
            'users' => $users,
            'documents' => $documents,
            'roles' => $roles->values(),
            'stats' => [
                'total_users' => Schema::hasTable('username')
                    ? DB::table('username')->count()
                    : 0,
                'admin_users' => $this->adminUsersCount(),
                'total_documents' => Schema::hasTable('document')
                    ? DB::table('document')->count()
                    : 0,
                'total_announcements' => count($announcements),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
            'activityLogs' => $activityLogs,
            'announcements' => $announcements,
            'filters' => [
                'search' => $search,
                'role_id' => $roleId,
                'per_page' => $perPage,
                'document_search' => $documentSearch,
                'document_per_page' => $documentPerPage,
                'tab' => $tab,
            ],
            'authUser' => [
                'id' => $this->authUserId(),
                'name' => $this->authLoginName(),
                'role_id' => (string) $this->authRights(),
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        abort_unless(
            Schema::hasTable('username'),
            500,
            'The username table does not exist.'
        );

        $allowedRights = $this->roles()
            ->pluck('id')
            ->map(fn ($right) => (string) $right)
            ->values()
            ->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'loginname' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('username', 'loginname'),
            ],
            'role_id' => ['required', 'string', Rule::in($allowedRights)],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
        ], [
            'loginname.regex' => 'The username may only contain letters, numbers, dots, underscores, and hyphens.',
            'loginname.unique' => 'This username is already being used.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $insertData = [
            'loginname' => trim($validated['loginname']),
            'password' => Hash::make($validated['password']),
            'rights' => (string) $validated['role_id'],
        ];

        if (Schema::hasColumn('username', 'name')) {
            $insertData['name'] = trim($validated['name']);
        }

        if (Schema::hasColumn('username', 'idoffice')) {
            $insertData['idoffice'] = 0;
        }

        if (Schema::hasColumn('username', 'idmapagency')) {
            $insertData['idmapagency'] = 0;
        }

        $newUserId = DB::table('username')->insertGetId($insertData, 'ID');

        if (Schema::hasTable('activity_logs')) {
            ActivityLog::record(
                'created user',
                'Admin User Management',
                'Created DTS account for ' . trim($validated['name'])
                    . ' (' . trim($validated['loginname']) . ').',
                'username',
                (int) $newUserId,
                [
                    'target_name' => trim($validated['name']),
                    'target_loginname' => trim($validated['loginname']),
                    'rights' => (string) $validated['role_id'],
                    'role' => $this->formatRightsName($validated['role_id']),
                ]
            );
        }

        return back()->with('success', 'User account added successfully.');
    }

    public function updateRole(Request $request, $id)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'role_id' => ['required', 'string', 'max:100'],
        ]);

        if ((int) $this->authUserId() === (int) $id) {
            return back()->with(
                'error',
                'You cannot change your own role while signed in.'
            );
        }

        $allowedRights = $this->roles()
            ->pluck('id')
            ->map(fn ($right) => (string) $right)
            ->values()
            ->all();

        if (! in_array((string) $validated['role_id'], $allowedRights, true)) {
            return back()->with('error', 'Selected role is invalid.');
        }

        $targetUser = DB::table('username')
            ->where('ID', $id)
            ->first();

        if (! $targetUser) {
            return back()->with('error', 'User account not found.');
        }

        $oldRights = (string) ($targetUser->rights ?? '');
        $newRights = (string) $validated['role_id'];

        DB::table('username')
            ->where('ID', $id)
            ->update([
                'rights' => $newRights,
            ]);

        if (Schema::hasTable('activity_logs')) {
            ActivityLog::record(
                'updated role',
                'Admin User Management',
                'Updated role of ' . ($targetUser->loginname ?? 'Unknown Account')
                    . ' from ' . $this->formatRightsName($oldRights)
                    . ' to ' . $this->formatRightsName($newRights) . '.',
                'username',
                (int) $id,
                [
                    'target_loginname' => $targetUser->loginname ?? null,
                    'old_rights' => $oldRights,
                    'old_role' => $this->formatRightsName($oldRights),
                    'new_rights' => $newRights,
                    'new_role' => $this->formatRightsName($newRights),
                ]
            );
        }

        return back()->with('success', 'User role updated successfully.');
    }

    public function storeAnnouncement(Request $request)
    {
        $this->ensureAdmin();

        abort_unless(
            Schema::hasTable('dts_admin_announcements'),
            500,
            'The announcements table does not exist. Run php artisan migrate.'
        );

        $allowedRoleIds = $this->roles()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            'target_type' => ['required', Rule::in(['all', 'role'])],
            'target_value' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (
            $validated['target_type'] === 'role'
            && ! in_array(
                (string) ($validated['target_value'] ?? ''),
                $allowedRoleIds,
                true
            )
        ) {
            return back()->withErrors([
                'target_value' => 'Please select a valid role.',
            ])->withInput();
        }

        $startsAt = ! empty($validated['starts_at'])
            ? Carbon::parse($validated['starts_at'])
            : null;

        $endsAt = ! empty($validated['ends_at'])
            ? Carbon::parse($validated['ends_at'])
            : null;

        if ($startsAt && $endsAt && $endsAt->lt($startsAt)) {
            return back()->withErrors([
                'ends_at' => 'End date and time must be later than the start date and time.',
            ])->withInput();
        }

        $announcementId = DB::table('dts_admin_announcements')
            ->insertGetId([
                'title' => trim($validated['title']),
                'message' => trim($validated['message']),
                'target_type' => $validated['target_type'],
                'target_value' => $validated['target_type'] === 'role'
                    ? (string) $validated['target_value']
                    : null,
                'is_active' => (bool) $validated['is_active'],
                'starts_at' => $startsAt?->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
                'created_by' => $this->authUserId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('activity_logs')) {
            ActivityLog::record(
                'created announcement',
                'Admin Announcements',
                'Created announcement: ' . trim($validated['title']) . '.',
                'dts_admin_announcements',
                (int) $announcementId,
                [
                    'target_type' => $validated['target_type'],
                    'target_value' => $validated['target_type'] === 'role'
                        ? (string) $validated['target_value']
                        : null,
                    'is_active' => (bool) $validated['is_active'],
                ]
            );
        }

        return back()->with('success', 'Announcement published successfully.');
    }

    public function destroyAnnouncement($announcement)
    {
        $this->ensureAdmin();

        abort_unless(
            Schema::hasTable('dts_admin_announcements'),
            404
        );

        $record = DB::table('dts_admin_announcements')
            ->where('id', $announcement)
            ->first();

        if (! $record) {
            return back()->with('error', 'Announcement not found.');
        }

        DB::table('dts_admin_announcements')
            ->where('id', $announcement)
            ->delete();

        if (Schema::hasTable('activity_logs')) {
            ActivityLog::record(
                'deleted announcement',
                'Admin Announcements',
                'Deleted announcement: ' . ($record->title ?? 'Untitled') . '.',
                'dts_admin_announcements',
                (int) $announcement,
                [
                    'title' => $record->title ?? null,
                ]
            );
        }

        return back()->with('success', 'Announcement deleted successfully.');
    }

    private function buildUsersPaginator(
        string $search,
        $roleId,
        int $perPage,
        Collection $roles
    ) {
        if (! Schema::hasTable('username')) {
            return $this->emptyPaginator($perPage, 'page');
        }

        $usersQuery = DB::table('username as u');

        if ($search !== '') {
            $usersQuery->where(function ($query) use ($search) {
                $query->where('u.loginname', 'like', '%' . $search . '%')
                    ->orWhere('u.ID', 'like', '%' . $search . '%');

                if (Schema::hasColumn('username', 'name')) {
                    $query->orWhere('u.name', 'like', '%' . $search . '%');
                }
            });
        }

        if ($roleId !== null && $roleId !== '') {
            $usersQuery->where('u.rights', $roleId);
        }

        $selectColumns = [
            'u.ID',
            'u.loginname',
            'u.rights',
        ];

        $selectColumns[] = Schema::hasColumn('username', 'name')
            ? 'u.name'
            : DB::raw('NULL as name');

        $selectColumns[] = Schema::hasColumn('username', 'lastlogin')
            ? 'u.lastlogin'
            : DB::raw('NULL as lastlogin');

        return $usersQuery
            ->select($selectColumns)
            ->orderByDesc('u.ID')
            ->paginate($perPage, ['*'], 'page')
            ->withQueryString()
            ->through(function ($user) use ($roles) {
                $rights = (string) ($user->rights ?? '');
                $role = $roles->firstWhere('id', $rights);

                return [
                    'id' => $user->ID,
                    'name' => $user->name ?: $user->loginname,
                    'username' => $user->loginname,
                    'lastlogin' => $user->lastlogin ?? null,
                    'role_id' => $rights,
                    'role_name' => $role['name']
                        ?? $this->formatRightsName($rights),
                ];
            });
    }

    private function buildDocumentsPaginator(
        string $search,
        int $perPage
    ) {
        if (! Schema::hasTable('document')) {
            return $this->emptyPaginator($perPage, 'document_page');
        }

        $query = DB::table('document as d')
            ->select([
                'd.IDdoc',
                'd.subject',
                'd.regarding',
                'd.entrydate',
                'd.classification',
                'd.IDdoctype',
                'd.IDfrom',
                'd.IDfor',
                'd.IDkeeper',
            ]);

        if ($search !== '') {
            $query->where(function ($documentQuery) use ($search) {
                $documentQuery
                    ->where('d.IDdoc', 'like', '%' . $search . '%')
                    ->orWhere('d.subject', 'like', '%' . $search . '%')
                    ->orWhere('d.regarding', 'like', '%' . $search . '%');
            });
        }

        $documents = $query
            ->orderByDesc('d.IDdoc')
            ->paginate($perPage, ['*'], 'document_page')
            ->withQueryString();

        $documentIds = $documents->getCollection()
            ->pluck('IDdoc')
            ->filter()
            ->values();

        $doctypeMap = Schema::hasTable('lu_doctype')
            ? DB::table('lu_doctype')->pluck('description', 'ID')
            : collect();

        $officeMap = Schema::hasTable('lu_office')
            ? DB::table('lu_office')->pluck('officename', 'ID')
            : collect();

        $personnelMap = Schema::hasTable('lu_personnel')
            ? DB::table('lu_personnel')->pluck('name', 'ID')
            : collect();

        $latestDistributions = collect();

        if (
            Schema::hasTable('distribution')
            && $documentIds->isNotEmpty()
        ) {
            $latestDistributions = DB::table('distribution')
                ->whereIn('IDdoc', $documentIds)
                ->orderByDesc('IDdist')
                ->get()
                ->unique('IDdoc')
                ->keyBy('IDdoc');
        }

        $documents->setCollection(
            $documents->getCollection()->map(function ($document) use (
                $doctypeMap,
                $officeMap,
                $personnelMap,
                $latestDistributions
            ) {
                $latestDistribution = $latestDistributions->get($document->IDdoc);

                $assignedPersonnelId = $document->IDkeeper;

                if (
                    $latestDistribution
                    && Schema::hasColumn('distribution', 'idmapagency')
                    && ! empty($latestDistribution->idmapagency)
                ) {
                    $assignedPersonnelId = $latestDistribution->idmapagency;
                }

                return [
                    'IDdoc' => $document->IDdoc,
                    'document_no' => $document->IDdoc,
                    'subject' => $document->subject,
                    'regarding' => $document->regarding,
                    'entrydate' => $document->entrydate,
                    'classification' => $document->classification,
                    'classification_label' => $this->classificationLabel(
                        $document->classification
                    ),
                    'doctype' => $doctypeMap->get($document->IDdoctype),
                    'from_office' => $officeMap->get($document->IDfrom),
                    'for_office' => $officeMap->get($document->IDfor),
                    'staff_concern' => $personnelMap->get($assignedPersonnelId),
                    'transferred_to' => $personnelMap->get($assignedPersonnelId),
                    'workflow_status' => $this->workflowStatus(
                        $latestDistribution,
                        $document->IDdoc
                    ),
                ];
            })
        );

        return $documents;
    }

    private function buildActivityLogs(): array
    {
        if (! Schema::hasTable('activity_logs')) {
            return [];
        }

        return DB::table('activity_logs')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function ($log) {
                return [
                    'action' => $log->action ?? '-',
                    'module' => $log->module ?? '-',
                    'user' => $log->user_name
                        ?? $log->username
                        ?? 'Unknown User',
                    'ip_address' => $log->ip_address ?? '-',
                    'date' => $log->created_at ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function buildAnnouncements(): array
    {
        if (! Schema::hasTable('dts_admin_announcements')) {
            return [];
        }

        $announcements = DB::table('dts_admin_announcements')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $creatorIds = $announcements
            ->pluck('created_by')
            ->filter()
            ->unique()
            ->values();

        $creatorMap = collect();

        if (
            $creatorIds->isNotEmpty()
            && Schema::hasTable('username')
        ) {
            $creatorQuery = DB::table('username')
                ->whereIn('ID', $creatorIds)
                ->select([
                    'ID',
                    'loginname',
                    Schema::hasColumn('username', 'name')
                        ? 'name'
                        : DB::raw('NULL as name'),
                ])
                ->get();

            $creatorMap = $creatorQuery->mapWithKeys(function ($user) {
                return [
                    $user->ID => $user->name ?: $user->loginname,
                ];
            });
        }

        return $announcements
            ->map(function ($announcement) use ($creatorMap) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'message' => $announcement->message,
                    'target_type' => $announcement->target_type,
                    'target_value' => $announcement->target_value,
                    'is_active' => (bool) $announcement->is_active,
                    'starts_at' => $announcement->starts_at,
                    'ends_at' => $announcement->ends_at,
                    'created_by' => $announcement->created_by,
                    'created_by_name' => $creatorMap->get(
                        $announcement->created_by
                    ),
                    'created_at' => $announcement->created_at,
                    'updated_at' => $announcement->updated_at,
                ];
            })
            ->values()
            ->all();
    }

    private function workflowStatus($distribution, $documentId): string
    {
        if (! $distribution) {
            return 'Unassigned';
        }

        $trueValues = ['true', 'y', '1'];

      
        if (
            Schema::hasColumn('distribution', 'YNpulled')
            && in_array(
                strtolower((string) ($distribution->YNpulled ?? '')),
                $trueValues,
                true
            )
        ) {
            return 'Pulled Out';
        }

        if (
            Schema::hasColumn('distribution', 'YNreturn')
            && in_array(
                strtolower((string) ($distribution->YNreturn ?? '')),
                $trueValues,
                true
            )
        ) {
            return 'Returned';
        }

        if (! empty($distribution->confirmdate)) {
            if ($this->hasCurrentFinalAction($documentId, $distribution)) {
                return 'Addressed';
            }

            return 'Received';
        }

        return 'For Receiving';
    }

    private function hasCurrentFinalAction($documentId, $distribution): bool
    {
        if (
            ! Schema::hasTable('dts_document_remarks')
            || ! Schema::hasColumn('dts_document_remarks', 'action_type')
        ) {
            return false;
        }

        $query = DB::table('dts_document_remarks')
            ->where('IDdoc', $documentId)
            ->where('action_type', 'action_taken');

       
        if (
            ! empty($distribution->distdate)
            && Schema::hasColumn('dts_document_remarks', 'created_at')
        ) {
            $query->where(
                'created_at',
                '>=',
                $distribution->distdate
            );
        }

        if (
            Schema::hasColumn('distribution', 'assignment_id')
            && Schema::hasColumn('dts_document_remarks', 'assignment_id')
        ) {
            $assignmentId = $distribution->assignment_id ?? null;

            if ($assignmentId === null) {
                $query->whereNull('assignment_id');
            } else {
                $query->where('assignment_id', $assignmentId);
            }
        }

        return $query->exists();
    }

    private function classificationLabel($classification): string
    {
        return in_array(
            strtolower((string) $classification),
            ['true', '1', 'y', 'yes', 'outgoing'],
            true
        )
            ? 'Outgoing'
            : 'Incoming';
    }

    private function emptyPaginator(int $perPage, string $pageName)
    {
        return new \Illuminate\Pagination\LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            1,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );
    }

    private function ensureAdmin(): void
    {
        $rights = (string) $this->authRights();

        abort_unless(in_array($rights, ['1', '4'], true), 403);
    }

    private function authUserId()
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if (isset($user->ID)) {
            return $user->ID;
        }

        if (isset($user->id)) {
            return $user->id;
        }

        $loginname = $user->loginname ?? $user->username ?? null;

        if ($loginname && Schema::hasTable('username')) {
            return DB::table('username')
                ->where('loginname', $loginname)
                ->value('ID');
        }

        return null;
    }

    private function authLoginName(): string
    {
        $user = auth()->user();

        if (! $user) {
            return 'Admin';
        }

        return $user->loginname
            ?? $user->username
            ?? $user->name
            ?? 'Admin';
    }

    private function authRights()
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if (isset($user->rights)) {
            return $user->rights;
        }

        $loginname = $user->loginname ?? $user->username ?? null;

        if (
            $loginname
            && Schema::hasTable('username')
            && Schema::hasColumn('username', 'rights')
        ) {
            return DB::table('username')
                ->where('loginname', $loginname)
                ->value('rights');
        }

        $id = $user->ID ?? $user->id ?? null;

        if (
            $id
            && Schema::hasTable('username')
            && Schema::hasColumn('username', 'rights')
        ) {
            return DB::table('username')
                ->where('ID', $id)
                ->value('rights');
        }

        return null;
    }

    private function roles(): Collection
    {
        return collect([
            ['id' => '1', 'name' => 'Admin'],
            ['id' => '2', 'name' => 'User'],
            ['id' => '3', 'name' => 'Staff'],
            ['id' => '4', 'name' => 'Super Admin'],
        ]);
    }

    private function formatRightsName($rights): string
    {
        return match ((string) $rights) {
            '1' => 'Admin',
            '2' => 'User',
            '3' => 'Staff',
            '4' => 'Super Admin',
            default => 'No Role',
        };
    }

    private function adminRightsValues(): array
    {
        return [
            '1',
            '4',
            'admin',
            'administrator',
            'superadmin',
            'super admin',
        ];
    }

    private function adminUsersCount(): int
    {
        if (
            ! Schema::hasTable('username')
            || ! Schema::hasColumn('username', 'rights')
        ) {
            return 0;
        }

        return DB::table('username')
            ->whereIn(
                DB::raw('LOWER(CAST(rights AS CHAR))'),
                $this->adminRightsValues()
            )
            ->count();
    }
}
