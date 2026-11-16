<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\AdModerationLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $action = $request->query('action');

        $logs = AdModerationLog::with(['campaign', 'admin'])
            ->when($action, fn ($q) => $q->where('action', $action))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $actions = AdModerationLog::query()->distinct()->orderBy('action')->pluck('action');

        return view('admin.ad_manager.audit_index', compact('logs', 'action', 'actions'));
    }
}
