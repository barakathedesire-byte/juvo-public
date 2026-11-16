<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\AdminPortalTab;
use App\Models\SiteSetting;
use App\Services\Admin\AdminSettingsService;
use App\Services\Admin\AuditLogger;
use App\Support\Access\AdminTabRegistry;
use Illuminate\Http\Request;

/**
 * Admin portal self-management. Governs appearance (colours, feel, animations)
 * and the custom sidebar tabs an administrator can add, order and disable.
 * Built-in tabs are listed read-only from AdminTabRegistry.
 */
class AdminSettingsController extends Controller
{
    public function __construct(
        protected AdminSettingsService $settings,
    ) {}

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'appearance');

        return view('admin.settings.index', [
            'tab' => $tab,
            'settings' => $this->settings->all(),
            'site' => SiteSetting::first(),
            'builtInTabs' => AdminTabRegistry::builtIn(),
            'groups' => AdminTabRegistry::groups(),
            'customTabs' => AdminPortalTab::orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function updateAppearance(Request $request)
    {
        $data = $request->validate([
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_bg' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_bg_end' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_text' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'content_bg' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{0,6}$/'],
            'border_radius' => ['required', 'string', 'max:12'],
            'font_family' => ['required', 'string', 'max:60'],
            'brand_name' => ['required', 'string', 'max:60'],
            'animations' => ['nullable', 'boolean'],
            'compact_sidebar' => ['nullable', 'boolean'],
        ]);

        $data['animations'] = $request->boolean('animations');
        $data['compact_sidebar'] = $request->boolean('compact_sidebar');

        $before = $this->settings->all();
        $this->settings->put($data);

        AuditLogger::record('admin_settings.appearance', null, $before, $data);

        return back()->with('success', 'Admin appearance updated.');
    }

    public function updateGeneral(Request $request)
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'footer_text' => ['nullable', 'string'],
        ]);

        $site = SiteSetting::firstOrCreate([], ['site_name' => $data['site_name']]);
        $before = $site->only(array_keys($data));
        $site->update($data);

        AuditLogger::record('admin_settings.general', $site, $before, $data);

        return back()->with('success', 'General settings updated.');
    }

    public function storeTab(Request $request)
    {
        $data = $this->validateTab($request);
        $tab = AdminPortalTab::create($data + ['is_custom' => true]);
        $this->settings->flush();

        AuditLogger::record('admin_settings.tab.created', $tab, [], $tab->only(['label', 'url', 'group']));

        return back()->with('success', 'Custom tab added.');
    }

    public function updateTab(Request $request, AdminPortalTab $tab)
    {
        $data = $this->validateTab($request);
        $before = $tab->only(array_keys($data));

        $tab->update($data);
        $this->settings->flush();

        AuditLogger::record('admin_settings.tab.updated', $tab, $before, $data);

        return back()->with('success', 'Tab updated.');
    }

    public function toggleTab(AdminPortalTab $tab)
    {
        $tab->update(['is_enabled' => ! $tab->is_enabled]);
        $this->settings->flush();

        AuditLogger::record('admin_settings.tab.toggled', $tab, [], ['is_enabled' => $tab->is_enabled]);

        return back()->with('success', 'Tab visibility updated.');
    }

    public function destroyTab(AdminPortalTab $tab)
    {
        $before = $tab->only(['label', 'url']);
        $tab->delete();
        $this->settings->flush();

        AuditLogger::record('admin_settings.tab.deleted', $tab, $before, []);

        return back()->with('success', 'Tab removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateTab(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:60'],
            'group' => ['required', 'string', 'max:40'],
            'permission' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $data['is_enabled'] = $request->boolean('is_enabled');
        $data['sort'] = $data['sort'] ?? 100;

        return $data;
    }
}
