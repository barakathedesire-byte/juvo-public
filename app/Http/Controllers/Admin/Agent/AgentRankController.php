<?php

namespace App\Http\Controllers\Admin\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentRankDefinition;
use App\Models\AgentRankHistory;
use App\Services\Sales\AgentRankService;
use Illuminate\Http\Request;

class AgentRankController extends Controller
{
    public function __construct(
        protected AgentRankService $ranks,
    ) {}

    public function index()
    {
        return view('admin.agents.ranks', [
            'ranks' => AgentRankDefinition::orderBy('level')->get(),
            'histories' => AgentRankHistory::with(['agent', 'rank'])->latest('id')->limit(30)->get(),
        ]);
    }

    public function store(Request $request)
    {
        AgentRankDefinition::create($this->validated($request));

        return back()->with('success', 'Rank created.');
    }

    public function update(Request $request, AgentRankDefinition $rank)
    {
        $rank->update($this->validated($request, $rank));

        return back()->with('success', 'Rank updated.');
    }

    public function toggle(AgentRankDefinition $rank)
    {
        $rank->update(['is_active' => ! $rank->is_active]);

        return back()->with('success', 'Rank updated.');
    }

    public function evaluate()
    {
        $count = $this->ranks->evaluateAll();

        return back()->with('success', "Evaluated {$count} agents.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?AgentRankDefinition $rank = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', 'unique:agent_rank_definitions,slug'.($rank ? ','.$rank->id : '')],
            'level' => ['required', 'integer', 'min:0'],
            'badge_color' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:500'],
            'min_delivered_orders' => ['nullable', 'integer', 'min:0'],
            'min_sales_volume' => ['nullable', 'numeric', 'min:0'],
            'min_quality_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_active_months' => ['nullable', 'integer', 'min:0'],
            'bonus_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?? $rank->slug ?? \Illuminate\Support\Str::slug($data['name'].'-'.$data['level']);
        $data['badge_color'] = $data['badge_color'] ?? 'grey';
        $data['min_delivered_orders'] = $data['min_delivered_orders'] ?? 0;
        $data['min_sales_volume'] = $data['min_sales_volume'] ?? 0;
        $data['min_active_months'] = $data['min_active_months'] ?? 0;
        $data['bonus_rate'] = $data['bonus_rate'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
