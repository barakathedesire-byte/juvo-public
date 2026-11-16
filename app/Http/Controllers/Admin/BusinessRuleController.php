<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessRule;
use App\Services\Rules\RuleEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BusinessRuleController extends Controller
{
    public function __construct(protected RuleEngine $engine) {}

    public function index()
    {
        $rules = BusinessRule::orderBy('category')->orderBy('key')->get();

        return view('admin.business_rules.index', compact('rules'));
    }

    public function create()
    {
        return view('admin.business_rules.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:120|unique:business_rules,key',
            'name' => 'required|string|max:190',
            'description' => 'nullable|string|max:1000',
            'value_type' => 'required|in:string,number,boolean,select',
            'value' => 'nullable|string|max:255',
            'options' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        BusinessRule::create([
            'key' => strtolower(preg_replace('/[^a-z0-9_]/', '_', $request->input('key'))),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'value_type' => $request->input('value_type'),
            'value' => $request->input('value', ''),
            'options' => $request->filled('options') ? $request->input('options') : null,
            'is_active' => $request->boolean('is_active', true),
            'is_managed' => false,
        ]);

        return redirect()->route('admin.business-rules.index')->with('success', 'Business rule created.');
    }

    public function update(Request $request)
    {
        $values = (array) $request->input('rules', []);
        $active = (array) $request->input('active', []);
        $reason = $request->filled('reason') ? (string) $request->input('reason') : 'Admin update.';
        $actorId = optional($request->user())->id;

        $errors = [];

        foreach (BusinessRule::all() as $rule) {
            $key = $rule->key;
            $editable = ! $rule->is_managed || $rule->is_editable;

            if (! $editable) {
                continue;
            }

            if (array_key_exists($key, $values) && (string) $rule->value !== (string) $values[$key]) {
                try {
                    $this->engine->setValue($key, (string) $values[$key], $actorId, $reason);
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $messages) {
                        $errors[$key] = $messages[0] ?? 'Invalid value.';
                    }
                }
            }

            $shouldBeActive = array_key_exists($key, $active);

            if ((bool) $rule->is_active !== $shouldBeActive) {
                $this->engine->setActive($key, $shouldBeActive, $actorId, $reason);
            }
        }

        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        return redirect()->route('admin.business-rules.index')->with('success', 'Business rules saved.');
    }
}
