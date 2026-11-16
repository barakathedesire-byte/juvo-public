<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportSlaPolicy;
use App\Services\Admin\AuditLogger;
use App\Services\Support\SupportSlaService;
use Illuminate\Http\Request;

class SupportSlaController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index()
    {
        $priorities = array_keys(SupportSlaService::DEFAULTS);
        $policies = SupportSlaPolicy::whereIn('priority', $priorities)->get()->keyBy('priority');

        return view('admin.support.sla.index', [
            'priorities' => $priorities,
            'policies' => $policies,
        ]);
    }

    public function update(Request $request, string $priority)
    {
        abort_unless(array_key_exists($priority, SupportSlaService::DEFAULTS), 404);

        $data = $request->validate([
            'first_response_minutes' => ['required', 'integer', 'min:1', 'max:100000'],
            'resolution_minutes' => ['required', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $policy = SupportSlaPolicy::firstOrNew(['priority' => $priority]);
        $before = $policy->only(['first_response_minutes', 'resolution_minutes', 'is_active']);

        $policy->fill([
            'first_response_minutes' => $data['first_response_minutes'],
            'resolution_minutes' => $data['resolution_minutes'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ])->save();

        $this->audit->log('support.sla.updated', $policy, $before, $policy->only([
            'first_response_minutes', 'resolution_minutes', 'is_active',
        ]), null);

        return back()->with('success', ucfirst($priority).' SLA updated.');
    }
}
