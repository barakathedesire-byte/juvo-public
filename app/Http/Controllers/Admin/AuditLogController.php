<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AuditLogController extends Controller
{
    public function index()
    {
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit.index', compact('actions'));
    }

    public function getData(Request $request)
    {
        $query = AuditLog::with('actor')->select('audit_logs.*');

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        return DataTables::of($query)
            ->addColumn('actor_name', fn ($log) => $log->actor?->name ?? 'System')
            ->addColumn('target', fn ($log) => $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—')
            ->editColumn('created_at', fn ($log) => $log->created_at?->format('Y-m-d H:i:s'))
            ->rawColumns([])
            ->make(true);
    }
}
