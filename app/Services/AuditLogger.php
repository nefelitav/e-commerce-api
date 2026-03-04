<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class AuditLogger
{
    /**
     * @param array<string, mixed> $properties
     */
    public function log(string $action, string $entity, int|string|null $entityId, array $properties = []): void
    {
        $user = Auth::user();

        Log::channel('audit')->info($action, [
            'entity' => $entity,
            'entity_id' => $entityId,
            'user_id' => $user?->getAuthIdentifier(),
            'action' => $action,
            'properties' => $properties,
            'ip' => request()->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

