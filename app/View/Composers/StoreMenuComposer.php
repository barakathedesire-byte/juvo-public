<?php

namespace App\View\Composers;

use App\Models\Category;
use App\Models\Menu;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StoreMenuComposer
{
    public function compose(View $view)
    {
        if (Schema::hasTable('menus')) {
            $locale = app()->getLocale();

            $headerMenu = Menu::where('status', 1)
                ->with([
                    'menuItems' => function ($query) use ($locale) {
                        $query->orderBy('order_number', 'asc')
                            ->with(['translation' => function ($query) use ($locale) {
                                $query->where('language_code', $locale);
                            }]);
                    },
                ])
                ->first();

            $view->with('headerMenu', $headerMenu);
        }

        if (Schema::hasTable('categories')) {
            $view->with('navCategories', $this->navCategories());
        }

        if (Schema::hasTable('homepage_settings')) {
            $view->with('showCategoryStrip', \App\Models\HomepageSetting::get('show_category_strip', '1') === '1');
            $view->with('showCategorySidebar', \App\Models\HomepageSetting::get('show_category_sidebar', '1') === '1');
        } else {
            $view->with('showCategoryStrip', true);
            $view->with('showCategorySidebar', true);
        }
    }

    protected function navCategories()
    {
        return cache()->remember(Category::NAV_CACHE_KEY, 600, function () {
            $categories = Category::whereNull('parent_category_id')
                ->where('status', 1)
                ->with([
                    'translation',
                    'children' => function ($query) {
                        $query->where('status', 1);
                    },
                    'children.translation',
                    'children.children' => function ($query) {
                        $query->where('status', 1);
                    },
                    'children.children.translation',
                ])
                ->get();

            return Category::sortTreeByName($categories);
        });
    }
}
