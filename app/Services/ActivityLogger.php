<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public static function log(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        ?int $popId = null,
        ?int $userId = null
    ): ActivityLog {
        $user = auth()->user();
        $finalUserId = $userId ?? $user?->id;

        $finalPopId = $popId;
        if ($finalPopId === null && $user instanceof User && $user->pop_id !== null) {
            $finalPopId = $user->pop_id;
        }

        $ip = request()?->ip();

        return ActivityLog::create([
            'user_id'      => $finalUserId,
            'pop_id'       => $finalPopId,
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'description'  => $description,
            'ip'           => $ip,
        ]);
    }
}

