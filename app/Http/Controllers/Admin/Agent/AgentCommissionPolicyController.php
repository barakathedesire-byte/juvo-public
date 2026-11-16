<?php

namespace App\Http\Controllers\Admin\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentCommissionPolicy;
use App\Services\Admin\AuditLogger;
use App\Services\Sales\CommissionPolicyService;
use Illuminate\Http\Request;

class AgentCommissionPolicyController extends Controller
{
    public function __construct(
        protected CommissionPolicyService $policies,
        protected AuditLogger $audit,
    ) {}

    public function index()
    {
        return view('admin.agents.policies', [
            'policies' => AgentCommissionPolicy::orderByDesc('is_active')->orderByDesc('priority')->orderByDesc('id')->get(),
            'bases' => AgentCommissionPolicy::BASES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $policy = $this->policies->create($data, auth()->id());

        $this->audit->log('agent.policy.created', $policy, [], $policy->only(['name', 'rate', 'basis', 'version']));

        return back()->with('success', 'Commission policy published (v'.$policy->version.').');
    }

    public function update(Request $request, AgentCommissionPolicy $policy)
    {
        $data = $this->validated($request);
        $revision = $this->policies->revise($policy, $data, auth()->id());

        $this->audit->log('agent.policy.revised', $revision, [], $revision->only(['name', 'rate', 'basis', 'version']));

        return back()->with('success', 'New policy version published (v'.$revision->version.').');
    }

    public function toggle(AgentCommissionPolicy $policy)
    {
        $policy->is_active ? $this->policies->deactivate($policy) : $policy->update(['is_active' => true]);

        return back()->with('success', 'Policy updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'policy_key' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'basis' => ['required', 'in:'.implode(',', AgentCommissionPolicy::BASES)],
            'min_order_value' => ['nullable', 'numeric', 'min:0'],
            'max_commission' => ['nullable', 'numeric', 'min:0'],
            'eligible_category_ids' => ['nullable', 'array'],
            'eligible_category_ids.*' => ['integer'],
            'excluded_category_ids' => ['nullable', 'array'],
            'excluded_category_ids.*' => ['integer'],
            'include_tax' => ['nullable', 'boolean'],
            'include_delivery_fee' => ['nullable', 'boolean'],
            'allow_self_purchase' => ['nullable', 'boolean'],
            'allow_guest_orders' => ['nullable', 'boolean'],
            'team_enabled' => ['nullable', 'boolean'],
            'team_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'team_max_depth' => ['nullable', 'integer', 'min:0', 'max:5'],
            'team_max_total_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        foreach (['include_tax', 'include_delivery_fee', 'allow_self_purchase', 'allow_guest_orders', 'team_enabled', 'is_active', 'is_default'] as $bool) {
            $data[$bool] = (bool) ($data[$bool] ?? false);
        }
        $data['allow_guest_orders'] = (bool) ($data['allow_guest_orders'] ?? true);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['fixed_amount'] = $data['fixed_amount'] ?? 0;
        $data['team_rate'] = $data['team_rate'] ?? 0;
        $data['team_max_depth'] = $data['team_max_depth'] ?? 1;
        $data['team_max_total_rate'] = $data['team_max_total_rate'] ?? 0;
        $data['priority'] = $data['priority'] ?? 0;

        return $data;
    }
}
