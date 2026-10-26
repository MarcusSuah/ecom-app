<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes one row to the audit log for a change made by the signed-in user,
 * on the current marketplace (none for platform-level changes).
 */
final class Audit
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function record(string $action, string $table, string $description, ?array $oldValues = null, ?array $newValues = null, int|string|null $recordId = null): AuditLog
    {
        $user = Auth::user();
        $user = $user instanceof User ? $user : null;

        $log = new AuditLog([
            'action' => $action,
            'table_name' => $table,
            'record_id' => $recordId !== null ? (string) $recordId : null,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'performed_by' => $user ? (string) $user->getAuthIdentifier() : 'system',
            'performed_by_name' => $user?->name,
            'performed_by_role' => $user?->getRoleNames()->first() ?? ($user?->isPlatformAdmin() ? 'platform_admin' : 'system'),
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255),
            'module' => $table,
        ]);
        $log->forceFill(['tenant_id' => app(CurrentTenant::class)->id()])->save();

        return $log;
    }
}
