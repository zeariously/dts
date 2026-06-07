<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class AdminUserManagementController extends Controller
{
    public function index(Request $request)
{
    $this->ensureAdmin();

    $perPage = (int) $request->input('per_page', 10);
    $perPage = in_array($perPage, [10, 15, 20, 50], true) ? $perPage : 10;

    $search = trim((string) $request->input('search', ''));
    $roleId = $request->input('role_id');
    $tab = $request->input('tab', 'role-management');

    $roles = $this->roles();

   
    $usersQuery = DB::table('username as u');

    if ($search !== '') {
        $usersQuery->where(function ($query) use ($search) {
            $query->where('u.loginname', 'like', '%' . $search . '%')
                ->orWhere('u.ID', 'like', '%' . $search . '%');
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

    if (Schema::hasColumn('username', 'lastlogin')) {
        $selectColumns[] = 'u.lastlogin';
    } else {
        $selectColumns[] = DB::raw('NULL as lastlogin');
    }

    $users = $usersQuery
        ->select($selectColumns)
        ->orderByDesc('u.ID')
        ->paginate($perPage)
        ->withQueryString()
        ->through(function ($user) use ($roles) {
            $rights = (string) ($user->rights ?? '');
            $role = $roles->firstWhere('id', $rights);

            return [
                'id' => $user->ID,
                'name' => $user->loginname,
                'username' => $user->loginname,
                'email' => null,
                'lastlogin' => $user->lastlogin ?? null,

                /*
                 * The Vue page still uses role_id as the v-model name.
                 * In the database, this value is saved to username.rights.
                 */
                'role_id' => $rights,
                'role_name' => $role['name'] ?? $this->formatRightsName($rights),

                'created_at' => null,
                'updated_at' => null,
            ];
        });

   
    $activityLogs = [];

    if (Schema::hasTable('activity_logs')) {
        $activityLogs = DB::table('activity_logs')
            ->orderByDesc('id')
            ->limit(50)
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

    return Inertia::render('Admin/UserManagement', [
        'users' => $users,
        'roles' => $roles->values(),
        'stats' => [
            'total_users' => DB::table('username')->count(),
            'admin_users' => $this->adminUsersCount(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
        ],
        'activityLogs' => $activityLogs,
        'filters' => [
            'search' => $search,
            'role_id' => $roleId,
            'per_page' => $perPage,
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

    public function updateRole(Request $request, $id)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'role_id' => ['required', 'string', 'max:100'],
        ]);

        if ((int) $this->authUserId() === (int) $id) {
            return back()->with('error', 'You cannot change your own role while signed in.');
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

        ActivityLog::record(
            'updated role',
            'Admin User Management',
            'Updated role of ' . ($targetUser->loginname ?? 'Unknown Account') . ' from ' . $this->formatRightsName($oldRights) . ' to ' . $this->formatRightsName($newRights) . '.',
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

        return back()->with('success', 'User role updated successfully.');
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

        if ($loginname && Schema::hasTable('username') && Schema::hasColumn('username', 'rights')) {
            return DB::table('username')
                ->where('loginname', $loginname)
                ->value('rights');
        }

        $id = $user->ID ?? $user->id ?? null;

        if ($id && Schema::hasTable('username') && Schema::hasColumn('username', 'rights')) {
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
        if (! Schema::hasTable('username') || ! Schema::hasColumn('username', 'rights')) {
            return 0;
        }

        return DB::table('username')
            ->whereIn(DB::raw('LOWER(CAST(rights AS CHAR))'), $this->adminRightsValues())
            ->count();
    }

    private function activityLogs(): array
    {
        if (! Schema::hasTable('activity_logs')) {
            return [];
        }

        return DB::table('activity_logs')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(function ($log) {
                return [
                    'action' => $log->action ?? $log->description ?? $log->event ?? 'activity',
                    'module' => $log->module ?? $log->subject_type ?? '-',
                    'user' => $log->user_name ?? $log->causer_name ?? $log->username ?? '-',
                    'ip_address' => $log->ip_address ?? $log->ip ?? '-',
                    'date' => $log->created_at ?? $log->date_created ?? null,
                ];
            })
            ->toArray();
    }
}
