<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\BusinessRule;
use App\Services\Rules\RuleEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function __construct(protected RuleEngine $engine) {}

    public function index()
    {
        $this->authorizeSettings();

        $rules = BusinessRule::where('category', 'logistics')
            ->orderBy('key')
            ->get();

        $sections = [];

        foreach ($rules as $rule) {
            $parts = explode('.', $rule->key, 3);
            $section = $parts[1] ?? 'general';

            $sections[$section][] = $rule;
        }

        ksort($sections);

        return view('logistics.settings.index', compact('sections'));
    }

    public function update(Request $request)
    {
        $this->authorizeSettings();

        $values = (array) $request->input('rules', []);
        $active = (array) $request->input('active', []);
        $reason = $request->filled('reason') ? (string) $request->input('reason') : 'Logistics settings update.';
        $actorId = optional(auth('logistics_admin')->user())->id;

        $errors = [];

        foreach (BusinessRule::where('category', 'logistics')->get() as $rule) {
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
                try {
                    $this->engine->setActive($key, $shouldBeActive, $actorId, $reason);
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $messages) {
                        $errors[$key] = $messages[0] ?? 'Invalid value.';
                    }
                }
            }
        }

        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        return redirect()->route('logistics.settings.index')
            ->with('success', 'Logistics settings saved.');
    }

    protected function authorizeSettings(): void
    {
        $admin = auth('logistics_admin')->user();

        abort_unless($admin && $admin->canAccessLogisticsModule('settings'), 403);
    }
}
