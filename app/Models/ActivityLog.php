<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'username',
        'rights',
        'action',
        'module',
        'description',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public static function record(
        string $action,
        string $module,
        ?string $description = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = []
    ): void {
        $user = auth()->user();

        $fullName = null;

        if ($user) {
            $nameParts = array_filter([
                $user->firstname ?? null,
                $user->middlename ?? null,
                $user->lastname ?? null,
            ]);

            $fullName = count($nameParts)
                ? implode(' ', $nameParts)
                : ($user->name ?? $user->loginname ?? $user->username ?? 'Unknown User');
        }

        self::create([
            'user_id' => $user->ID ?? $user->id ?? null,
            'user_name' => $fullName,
            'username' => $user->loginname ?? $user->username ?? null,
            'rights' => isset($user->rights) ? (string) $user->rights : null,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'properties' => $properties,
        ]);
    }
}