<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Services\Homepage\HomepageService;

class StoreController extends Controller
{
    public function index(HomepageService $homepage)
    {
        $categories = Category::where('status', 1)
            ->whereNull('parent_category_id')
            ->with('translation')
            ->orderBy('id')
            ->get();

        $slides = $homepage->slidesForDisplay();

        if ($slides->isEmpty()) {
            $slides = Banner::where('status', 1)
                ->with('translation')
                ->orderBy('id')
                ->take(5)
                ->get()
                ->map(fn ($b) => (object) [
                    'title' => optional($b->translation)->title ?? $b->title ?? 'Welcome to Juvo',
                    'subtitle' => null,
                    'description' => optional($b->translation)->description ?? 'Discover the biggest variety of products at the best prices on Juvo.',
                    'cta_label' => 'Shop Now',
                    'cta_url' => route('shop.index'),
                    'image' => optional($b->translation)->image_url,
                    'mobile_image' => null,
                    'badge' => null,
                    'text_align' => 'left',
                ]);
        }

        $tiles = $homepage->tilesForDisplay();
        $sections = $homepage->sections();
        $settings = $homepage->settings();

        $recommender = app(\App\Services\Intelligence\RecommendationService::class);
        $recommended = collect();
        if ($recommender->isEnabled()) {
            $recommended = $recommender->forSurface('home_personalized', [
                'subject_id' => auth('customer')->id(),
            ], 12);
        }

        return view('themes.xylo.home', [
            'categories' => $categories,
            'slides' => $slides,
            'tiles' => $tiles,
            'sections' => $sections,
            'homeSettings' => $settings,
            'recommended' => $recommended,
        ]);
    }
}
