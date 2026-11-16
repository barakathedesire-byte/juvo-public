<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Support\Integrations\IntegrationCatalog;
use App\Support\Integrations\IntegrationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IntegrationController extends Controller
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function catalog(): array
    {
        return IntegrationCatalog::all();
    }

    public function index()
    {
        $settings = StoreSetting::pluck('value', 'key')->all();

        $added = $this->addedIntegrations($settings);

        return view('admin.integrations.index', compact('settings', 'added'));
    }

    public function create()
    {
        $settings = StoreSetting::pluck('value', 'key')->all();
        $addedKeys = array_keys($this->addedIntegrations($settings));
        $catalog = $this->catalog();

        return view('admin.integrations.create', compact('catalog', 'addedKeys'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'key' => 'nullable|string|max:60',
            'name' => 'required|string|max:120',
        ]);

        $key = $request->filled('key')
            ? preg_replace('/[^a-z0-9_]/', '', strtolower($request->input('key')))
            : preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $request->input('name'))));

        $catalog = $this->catalog();
        $defaults = $catalog[$key]['defaults'] ?? [];

        StoreSetting::updateOrCreate(['key' => 'integration_enabled_'.$key], ['value' => '0']);
        StoreSetting::updateOrCreate(['key' => 'integration_name_'.$key], ['value' => $request->input('name')]);

        if ($defaults !== []) {
            StoreSetting::updateOrCreate(
                ['key' => 'integration_config_'.$key],
                ['value' => json_encode($defaults)]
            );
        }

        IntegrationSettings::forget();

        return redirect()->route('admin.integrations.configure', $key)->with('success', 'Integration added. Configure its credentials.');
    }

    public function configure(string $integration)
    {
        $settings = StoreSetting::pluck('value', 'key')->all();
        $catalog = $this->catalog();
        $meta = $catalog[$integration] ?? [
            'name' => $settings['integration_name_'.$integration] ?? ucfirst(str_replace('_', ' ', $integration)),
            'category' => 'Custom',
            'icon' => 'fas fa-plug',
            'description' => 'Custom integration.',
            'fields' => [],
        ];
        $stored = json_decode($settings['integration_config_'.$integration] ?? '{}', true) ?: [];
        $config = array_merge($meta['defaults'] ?? [], $stored);
        $enabled = ($settings['integration_enabled_'.$integration] ?? '0') === '1';

        return view('admin.integrations.configure', compact('integration', 'meta', 'config', 'enabled'));
    }

    public function saveConfig(Request $request, string $integration)
    {
        $catalog = $this->catalog();
        $meta = $catalog[$integration] ?? ['fields' => []];

        $config = [];
        foreach (array_keys($meta['fields']) as $field) {
            $config[$field] = $request->input('config.'.$field, '');
        }
        // allow custom fields for unknown integrations
        if (empty($meta['fields'])) {
            $config = $request->input('config', []);
        }

        StoreSetting::updateOrCreate(['key' => 'integration_config_'.$integration], ['value' => json_encode($config)]);
        StoreSetting::updateOrCreate(['key' => 'integration_enabled_'.$integration], ['value' => $request->boolean('enabled') ? '1' : '0']);

        IntegrationSettings::forget();
        Cache::forget('mobile_money_providers');

        return redirect()->route('admin.integrations.index')->with('success', 'Integration saved.');
    }

    public function destroy(string $integration)
    {
        foreach (['integration_enabled_', 'integration_config_', 'integration_name_'] as $prefix) {
            StoreSetting::where('key', $prefix.$integration)->delete();
        }

        IntegrationSettings::forget();
        Cache::forget('mobile_money_providers');

        return redirect()->route('admin.integrations.index')->with('success', 'Integration removed.');
    }

    protected function addedIntegrations(array $settings): array
    {
        $catalog = $this->catalog();

        $added = [];
        foreach ($settings as $key => $value) {
            if (str_starts_with($key, 'integration_enabled_')) {
                $slug = substr($key, strlen('integration_enabled_'));
                $added[$slug] = [
                    'name' => $settings['integration_name_'.$slug] ?? ($catalog[$slug]['name'] ?? ucfirst(str_replace('_', ' ', $slug))),
                    'category' => $catalog[$slug]['category'] ?? 'Custom',
                    'icon' => $catalog[$slug]['icon'] ?? 'fas fa-plug',
                    'logo' => $catalog[$slug]['logo'] ?? null,
                    'enabled' => $value === '1',
                ];
            }
        }

        return $added;
    }

    public function update(Request $request)
    {
        $request->validate([
            'announcement_text' => 'nullable|string|max:500',
            'fleetbase_enabled' => 'nullable|boolean',
        ]);

        $text = ['announcement_text'];
        $bool = ['fleetbase_enabled'];

        foreach ($text as $key) {
            StoreSetting::updateOrCreate(['key' => $key], ['value' => $request->input($key, '')]);
        }

        foreach ($bool as $key) {
            StoreSetting::updateOrCreate(['key' => $key], ['value' => $request->boolean($key) ? '1' : '0']);
        }

        foreach (array_merge($text, $bool) as $key) {
            Cache::forget("store_setting_{$key}");
        }

        return redirect()->route('admin.integrations.index')->with('success', 'Integrations updated successfully.');
    }
}
