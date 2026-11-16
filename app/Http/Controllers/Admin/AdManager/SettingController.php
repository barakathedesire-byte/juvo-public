<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\BusinessRule;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $rules = BusinessRule::where('key', 'like', 'ad\_%')->orderBy('id')->get();

        return view('admin.ad_manager.settings_index', compact('rules'));
    }

    public function update(Request $request)
    {
        $values = $request->input('rules', []);
        $active = $request->input('active', []);

        foreach (BusinessRule::where('key', 'like', 'ad\_%')->get() as $rule) {
            if (array_key_exists($rule->key, $values)) {
                $rule->value = (string) $values[$rule->key];
            }
            $rule->is_active = array_key_exists($rule->key, $active);
            $rule->save();
        }

        return redirect()->route('admin.ad-manager.settings.index')->with('success', 'Advertising settings saved.');
    }
}
