<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Pages\PageRenderer;

class PageController extends Controller
{
    public function show($slug, PageRenderer $renderer)
    {
        $page = Page::with('translations')
            ->where('slug', $slug)
            ->where('status', 1)
            ->where('is_system', false)
            ->firstOrFail();

        $translation = $page->translations->firstWhere('language_code', app()->getLocale())
            ?? $page->translations->first();

        $blocks = $page->hasBuilder() ? $renderer->render($page) : [];

        abort_if(! $translation && empty($blocks), 404);

        return view('themes.xylo.page', compact('page', 'translation', 'blocks'));
    }
}
