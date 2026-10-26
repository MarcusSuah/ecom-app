<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Changes people made on this marketplace: who, what, when, and the
 * values before and after. Read-only. Guarded by audit-logs.view.
 */
class AuditLogController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        $search = mb_substr($request->string('search')->trim()->toString(), 0, 100);
        $action = $request->string('action')->toString();
        $module = $request->string('module')->toString();
        $from = $this->date($request->string('from')->toString());
        $to = $this->date($request->string('to')->toString());

        $base = AuditLog::query()->where('tenant_id', $currentTenant->require()->id);

        $logs = (clone $base)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('description', 'like', "%{$search}%")
                ->orWhere('performed_by_name', 'like', "%{$search}%")
                ->orWhere('record_id', $search)))
            ->when(in_array($action, ['create', 'update', 'delete'], true), fn (Builder $query) => $query->where('action', $action))
            ->when($module !== '', fn (Builder $query) => $query->where('table_name', $module))
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<=', $to->endOfDay()))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'module' => $log->table_name,
                'record_id' => $log->record_id,
                'description' => $log->description,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'performed_by' => $log->performed_by_name ?? ($log->performed_by === 'system' ? 'System' : "User #{$log->performed_by}"),
                'role' => $log->performed_by_role,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toISOString(),
            ]);

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'modules' => (clone $base)->distinct()->orderBy('table_name')->pluck('table_name'),
            'filters' => [
                'search' => $search,
                'action' => $action,
                'module' => $module,
                'from' => $from?->toDateString() ?? '',
                'to' => $to?->toDateString() ?? '',
            ],
        ]);
    }

    private function date(string $value): ?Carbon
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? Carbon::parse($value) : null;
    }
}
