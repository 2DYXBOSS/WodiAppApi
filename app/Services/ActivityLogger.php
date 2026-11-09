<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/** Journal des opérations sensibles (création, modification, statut, horaires, permissions). */
class ActivityLogger
{
    public function log(string $action, ?Model $subject = null, array $properties = []): void
    {
        $request = request();
        ActivityLog::query()->create([
            'user_id' => $request?->user()?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
        ]);
    }
}
