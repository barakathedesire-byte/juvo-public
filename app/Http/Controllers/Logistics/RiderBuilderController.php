<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\HomepageSetting;
use App\Support\Rider\RiderBuilderContent;
use Illuminate\Http\Request;

class RiderBuilderController extends Controller
{
    public function index()
    {
        $this->authorizeBuilder();

        return view('logistics.rider-builder.index', [
            'builder' => RiderBuilderContent::settings(),
            'defaults' => RiderBuilderContent::defaults(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeBuilder();

        $data = $request->validate([
            'brand_name' => 'required|string|max:80',
            'brand_tagline' => 'nullable|string|max:120',
            'brand_icon' => 'nullable|string|max:80',
            'app_title' => 'nullable|string|max:80',
            'primary_color' => ['required', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'primary_dark' => ['required', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'primary_light' => ['required', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'canvas_color' => ['required', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'success_color' => ['required', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'danger_color' => ['required', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'support_phone' => 'nullable|string|max:40',
            'support_email' => 'nullable|email|max:120',
            'hero_greeting_suffix' => 'nullable|string|max:160',
            'dashboard_earnings_label' => 'nullable|string|max:80',
            'empty_assignment_copy' => 'nullable|string|max:240',
            'features' => 'nullable|array',
            'features.show_earnings' => 'nullable|boolean',
            'features.show_orders' => 'nullable|boolean',
            'features.show_profile' => 'nullable|boolean',
            'features.show_notifications' => 'nullable|boolean',
            'features.show_availability' => 'nullable|boolean',
            'features.show_cod' => 'nullable|boolean',
            'features.show_map' => 'nullable|boolean',
            'navigation' => 'nullable|array',
            'navigation.*.key' => 'required_with:navigation|string|in:dashboard,orders,earnings,profile',
            'navigation.*.label' => 'nullable|string|max:40',
            'navigation.*.icon' => 'nullable|string|max:80',
            'navigation.*.enabled' => 'nullable|boolean',
        ]);

        $payload = [
            'brand_name' => $data['brand_name'],
            'brand_tagline' => $data['brand_tagline'] ?? '',
            'brand_icon' => $data['brand_icon'] ?? '',
            'app_title' => $data['app_title'] ?? '',
            'primary_color' => $data['primary_color'],
            'primary_dark' => $data['primary_dark'],
            'primary_light' => $data['primary_light'],
            'canvas_color' => $data['canvas_color'],
            'success_color' => $data['success_color'],
            'danger_color' => $data['danger_color'],
            'support_phone' => $data['support_phone'] ?? '',
            'support_email' => $data['support_email'] ?? '',
            'hero_greeting_suffix' => $data['hero_greeting_suffix'] ?? '',
            'dashboard_earnings_label' => $data['dashboard_earnings_label'] ?? '',
            'empty_assignment_copy' => $data['empty_assignment_copy'] ?? '',
            'features' => [
                'show_earnings' => $request->boolean('features.show_earnings'),
                'show_orders' => $request->boolean('features.show_orders'),
                'show_profile' => $request->boolean('features.show_profile'),
                'show_notifications' => $request->boolean('features.show_notifications'),
                'show_availability' => $request->boolean('features.show_availability'),
                'show_cod' => $request->boolean('features.show_cod'),
                'show_map' => $request->boolean('features.show_map'),
            ],
            'navigation' => collect($request->input('navigation', []))->map(function ($item) {
                return [
                    'key' => $item['key'],
                    'label' => trim((string) ($item['label'] ?? '')),
                    'icon' => trim((string) ($item['icon'] ?? '')),
                    'enabled' => (bool) ($item['enabled'] ?? false),
                ];
            })->values()->all(),
        ];

        HomepageSetting::put('rider_builder', json_encode($payload));

        return redirect()->route('logistics.rider-builder.index')
            ->with('success', 'Rider builder settings saved.');
    }

    protected function authorizeBuilder(): void
    {
        $admin = auth('logistics_admin')->user();

        abort_unless($admin && $admin->canAccessLogisticsModule('settings'), 403);
    }
}
