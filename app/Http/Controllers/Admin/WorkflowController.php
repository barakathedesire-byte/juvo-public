<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function index()
    {
        $workflows = Workflow::orderBy('id')->get();

        return view('admin.workflows.index', compact('workflows'));
    }

    public function create()
    {
        return view('admin.workflows.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'trigger' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'config' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        Workflow::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'trigger' => $data['trigger'] ?? null,
            'description' => $data['description'] ?? null,
            'config' => $data['config'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.workflows.index')->with('success', 'Workflow created.');
    }

    public function edit(Workflow $workflow)
    {
        return view('admin.workflows.edit', compact('workflow'));
    }

    public function update(Request $request, Workflow $workflow)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'trigger' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'config' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $workflow->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'trigger' => $data['trigger'] ?? null,
            'description' => $data['description'] ?? null,
            'config' => $data['config'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.workflows.index')->with('success', 'Workflow updated.');
    }

    public function destroy(Workflow $workflow)
    {
        $workflow->delete();

        return redirect()->route('admin.workflows.index')->with('success', 'Workflow deleted.');
    }

    public function toggle(Workflow $workflow)
    {
        $workflow->update(['is_active' => ! $workflow->is_active]);

        return back()->with('success', 'Workflow status updated.');
    }
}
