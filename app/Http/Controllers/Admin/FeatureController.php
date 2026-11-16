<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Services\Admin\ApprovalService;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\DecisionAuthority;
use App\Services\Features\FeatureFlagService;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function __construct(
        protected FeatureFlagService $flags,
        protected DecisionAuthority $authority,
        protected ApprovalService $approvals,
        protected AuditLogger $audit,
    ) {}

    public function index()
    {
        return view('admin.features.index', [
            'features' => Feature::orderBy('module')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $feature = Feature::create($data + ['created_by' => auth()->id()]);
        $this->flags->flush();

        $this->audit->log('feature.registered', $feature, [], [
            'key' => $feature->key,
            'is_enabled' => $feature->is_enabled,
        ]);

        return back()->with('success', 'Feature registered.');
    }

    public function update(Request $request, Feature $feature)
    {
        $data = $this->validated($request, $feature);
        $before = $feature->only(['name', 'description', 'module', 'rollout_percentage']);

        $feature->update($data + ['updated_by' => auth()->id()]);
        $this->flags->flush();

        $this->audit->log('feature.updated', $feature, $before, $feature->only(array_keys($data)));

        return back()->with('success', 'Feature updated.');
    }

    public function destroy(Feature $feature)
    {
        $before = $feature->only(['key', 'name', 'is_enabled']);
        $feature->delete();
        $this->flags->flush();

        $this->audit->log('feature.deleted', $feature, $before, []);

        return back()->with('success', 'Feature removed.');
    }

    public function toggle(Request $request, Feature $feature)
    {
        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $enabled = (bool) $data['is_enabled'];
        $decision = $this->authority->evaluate(auth()->user(), 'feature.toggle');

        abort_unless($decision['allowed'], 403, $decision['mode'] === DecisionAuthority::PROHIBITED
            ? 'You are not permitted to toggle features.'
            : 'Not allowed.');

        $governed = $decision['requires_approval'] || $this->approvals->requiresApproval('feature.toggle');

        if ($governed) {
            $approved = $this->approvals->approvedFor('feature.toggle', $feature);

            if (! $approved) {
                $pending = $this->approvals->pendingFor('feature.toggle', $feature)
                    ?? $this->approvals->submit(
                        auth()->user(),
                        'feature.toggle',
                        $feature,
                        ['key' => $feature->key, 'is_enabled' => $enabled],
                        $data['reason'] ?? null,
                    );

                return back()->with('success', 'This feature toggle is awaiting approval ('.$pending->reference.').');
            }
        }

        $before = ['is_enabled' => $feature->is_enabled];
        $this->flags->setEnabled($feature, $enabled, auth()->id());

        if (isset($approved) && $approved) {
            $this->approvals->markExecuted($approved, auth()->user());
        }

        $this->audit->log('feature.toggled', $feature, $before, ['is_enabled' => $enabled], $data['reason'] ?? null);

        return back()->with('success', 'Feature '.($enabled ? 'enabled' : 'disabled').'.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Feature $feature = null): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:features,key'.($feature ? ','.$feature->id : '')],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'module' => ['nullable', 'string', 'max:100'],
            'rollout_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $data['rollout_percentage'] = $data['rollout_percentage'] ?? 100;
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);

        return $data;
    }
}
