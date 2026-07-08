<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnnouncementController extends Controller
{
    public function active(): JsonResponse
    {
        if (! Schema::hasTable('dts_admin_announcements')) {
            return response()->json([
                'announcements' => [],
            ]);
        }

        $rights = $this->currentUserRights();

        $query = DB::table('dts_admin_announcements')
            ->where('is_active', true)
            ->where(function ($dateQuery) {
                $dateQuery
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($dateQuery) {
                $dateQuery
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->where(function ($audienceQuery) use ($rights) {
                $audienceQuery->where('target_type', 'all');

                if ($rights !== null && $rights !== '') {
                    $audienceQuery->orWhere(function ($roleQuery) use ($rights) {
                        $roleQuery
                            ->where('target_type', 'role')
                            ->where('target_value', (string) $rights);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50);

        $announcements = $query
            ->get([
                'id',
                'title',
                'message',
                'target_type',
                'target_value',
                'starts_at',
                'ends_at',
                'created_at',
            ])
            ->map(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'message' => $announcement->message,
                    'target_type' => $announcement->target_type,
                    'target_value' => $announcement->target_value,
                    'starts_at' => $announcement->starts_at,
                    'ends_at' => $announcement->ends_at,
                    'created_at' => $announcement->created_at,
                ];
            })
            ->values();

        return response()->json([
            'announcements' => $announcements,
        ]);
    }

    private function currentUserRights(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if (isset($user->rights)) {
            return (string) $user->rights;
        }

        if (isset($user->role_id)) {
            return (string) $user->role_id;
        }

        if (! Schema::hasTable('username')) {
            return null;
        }

        $loginname = $user->loginname
            ?? $user->username
            ?? null;

        if ($loginname) {
            $rights = DB::table('username')
                ->where('loginname', $loginname)
                ->value('rights');

            return $rights !== null ? (string) $rights : null;
        }

        $userId = $user->ID ?? $user->id ?? null;

        if ($userId) {
            $rights = DB::table('username')
                ->where('ID', $userId)
                ->value('rights');

            return $rights !== null ? (string) $rights : null;
        }

        return null;
    }
}
