<?php

namespace App\Http\Controllers\Admin\Homepage;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use App\Models\HomepageSetting;
use App\Models\HomepageSlide;
use App\Models\HomepageTile;
use App\Services\Homepage\HomepageService;
use App\Support\Homepage\FooterContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomepageController extends Controller
{
    public function index(HomepageService $homepage)
    {
        $stats = [
            'slides' => HomepageSlide::count(),
            'slides_published' => HomepageSlide::where('status', 'published')->count(),
            'tiles' => HomepageTile::count(),
            'sections' => HomepageSection::count(),
            'sections_published' => HomepageSection::where('status', 'published')->count(),
        ];

        $settings = $homepage->settings();
        $footer = FooterContent::settings();
        $footerLinkCount = collect($footer['columns'])->sum(fn ($column) => count($column['links'] ?? []));
        $footerCustom = HomepageSetting::whereIn('key', [
            'footer_description', 'footer_social_links', 'footer_newsletter',
            'footer_columns', 'footer_copyright', 'footer_note',
        ])->exists();

        return view('admin.homepage.index', compact(
            'stats', 'settings', 'footer', 'footerLinkCount', 'footerCustom'
        ));
    }

    public function settings()
    {
        $values = [
            'autoplay' => HomepageSetting::get('autoplay', '1'),
            'autoplay_speed' => HomepageSetting::get('autoplay_speed', '5000'),
            'show_category_strip' => HomepageSetting::get('show_category_strip', '1'),
            'show_category_sidebar' => HomepageSetting::get('show_category_sidebar', '1'),
            'home_bg_color' => HomepageSetting::get('home_bg_color', ''),
            'home_text_color' => HomepageSetting::get('home_text_color', ''),
            'home_accent_color' => HomepageSetting::get('home_accent_color', ''),
            'home_card_color' => HomepageSetting::get('home_card_color', ''),
            'home_effects' => HomepageSetting::get('home_effects', '0'),
        ];

        return view('admin.homepage.settings', compact('values'));
    }

    public function updateSettings(Request $request)
    {
        $hex = ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];

        $data = $request->validate([
            'autoplay' => 'nullable|in:0,1',
            'autoplay_speed' => 'required|integer|min:2000|max:15000',
            'show_category_strip' => 'nullable|in:0,1',
            'show_category_sidebar' => 'nullable|in:0,1',
            'home_bg_color' => $hex,
            'home_text_color' => $hex,
            'home_accent_color' => $hex,
            'home_card_color' => $hex,
            'home_effects' => 'nullable|in:0,1',
        ]);

        HomepageSetting::put('autoplay', $request->input('autoplay', '0'));
        HomepageSetting::put('autoplay_speed', (string) $data['autoplay_speed']);
        HomepageSetting::put('show_category_strip', $request->input('show_category_strip', '0'));
        HomepageSetting::put('show_category_sidebar', $request->input('show_category_sidebar', '0'));
        HomepageSetting::put('home_bg_color', (string) $request->input('home_bg_color', ''));
        HomepageSetting::put('home_text_color', (string) $request->input('home_text_color', ''));
        HomepageSetting::put('home_accent_color', (string) $request->input('home_accent_color', ''));
        HomepageSetting::put('home_card_color', (string) $request->input('home_card_color', ''));
        HomepageSetting::put('home_effects', $request->input('home_effects', '0'));

        Cache::forget('homepage_config');

        return back()->with('success', 'Homepage settings saved.');
    }

    public function preview(HomepageService $homepage)
    {
        $slides = $homepage->slidesForDisplay(true);
        $tiles = $homepage->tilesForDisplay(true);
        $sections = $homepage->sections(true);
        $homeSettings = $homepage->settings();
        $categories = \App\Models\Category::where('status', 1)->whereNull('parent_category_id')
            ->with('translation')->orderBy('id')->take(12)->get();
        $currency = activeCurrency();

        return view('admin.homepage.preview', compact('slides', 'tiles', 'sections', 'homeSettings', 'categories', 'currency'));
    }
}
