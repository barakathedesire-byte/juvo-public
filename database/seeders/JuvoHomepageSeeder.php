<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JuvoHomepageSeeder extends Seeder
{
    /**
     * Idempotently build the Juvo homepage: slides, promo tiles, collections
     * and the themed product rails. Safe to re-run.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->slides();
            $this->tiles();
            $this->collections();
            $this->settings();
            $this->sections();
        });
    }

    private function slides(): void
    {
        $slides = [
            ['title' => 'Juvo Foods', 'subtitle' => 'Fresh picks, delivered fast', 'description' => '<p>Stock the pantry with everyday essentials at Juvo prices.</p>', 'badge' => 'Groceries', 'sort_order' => 1],
            ['title' => 'Daily Deals', 'subtitle' => 'New markdowns every few hours', 'description' => '<p>Deals of the day, right where you need them.</p>', 'badge' => 'Save big', 'sort_order' => 2],
            ['title' => 'Rush Hour Deals', 'subtitle' => 'Blink and they are gone', 'description' => '<p>Grab the best bargains during our rush-hour windows.</p>', 'badge' => 'Limited', 'sort_order' => 3],
            ['title' => 'Mega Sale Week', 'subtitle' => 'Up to 50% off sitewide', 'description' => '<p>Up to 50% off across the marketplace.</p>', 'badge' => 'Mega Sale', 'sort_order' => 4],
        ];

        foreach ($slides as $slide) {
            DB::table('homepage_slides')->updateOrInsert(
                ['title' => $slide['title']],
                $slide + [
                    'cta_label' => 'Shop Now',
                    'cta_url' => '/products',
                    'text_align' => 'left',
                    'status' => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('homepage_slides')
            ->whereNotIn('title', array_column($slides, 'title'))
            ->delete();
    }

    private function tiles(): void
    {
        $tiles = [
            ['title' => 'Just Landed', 'subtitle' => 'New arrivals fresh in store', 'link_type' => 'collection', 'link_value' => 'new_arrivals', 'sort_order' => 1],
            ['title' => 'Last Chance Deals', 'subtitle' => 'Final markdowns before they are gone', 'link_type' => 'collection', 'link_value' => 'clearance', 'sort_order' => 2],
            ['title' => 'Little Wonders', 'subtitle' => 'Kids, toys and baby essentials', 'link_type' => 'category', 'link_value' => 'baby', 'sort_order' => 3],
            ['title' => 'Hidden Gems', 'subtitle' => 'Hand-picked favourites shoppers love', 'link_type' => 'collection', 'link_value' => 'hidden_gems', 'sort_order' => 4],
        ];

        foreach ($tiles as $tile) {
            DB::table('homepage_tiles')->updateOrInsert(
                ['title' => $tile['title']],
                $tile + ['status' => 'published', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        DB::table('homepage_tiles')
            ->whereNotIn('title', array_column($tiles, 'title'))
            ->delete();
    }

    private function collections(): void
    {
        $collections = [
            ['name' => 'New Arrivals', 'slug' => 'new-arrivals', 'rule_type' => 'newest', 'sort_order' => 1],
            ['name' => 'Little Prices', 'slug' => 'little-prices', 'rule_type' => 'discounted', 'sort_order' => 2],
            ['name' => 'Clearance', 'slug' => 'clearance', 'rule_type' => 'discounted', 'sort_order' => 3],
            ['name' => 'Hidden Gems', 'slug' => 'hidden-gems', 'rule_type' => 'hidden_gems', 'sort_order' => 4],
        ];

        foreach ($collections as $c) {
            DB::table('collections')->updateOrInsert(
                ['slug' => $c['slug']],
                $c + ['vendor_id' => null, 'rule_value' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function settings(): void
    {
        $settings = [
            'autoplay' => '1',
            'autoplay_speed' => '5000',
            'show_category_strip' => '1',
        ];

        foreach ($settings as $key => $value) {
            DB::table('homepage_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function sections(): void
    {
        $countdown = now()->addHours(8)->toIso8601String();

        $sections = [
            // Flash sale with live countdown
            ['title' => 'Flash Sale', 'subtitle' => 'Price drops, every few hours', 'type' => 'product_carousel', 'source' => 'daily_deals', 'icon' => 'fas fa-bolt', 'layout' => 'carousel', 'limit' => 12, 'settings' => ['countdown' => $countdown, 'bg_color' => '#e03131', 'text_color' => '#ffffff']],
            // Sponsored products (admin-managed, paid placements)
            ['title' => 'Sponsored Products', 'subtitle' => 'Partner picks worth a closer look', 'type' => 'product_carousel', 'source' => 'sponsored', 'icon' => 'fas fa-handshake', 'layout' => 'carousel', 'limit' => 12],
            // Themed category rails
            ['title' => 'Pocket-Sized Power', 'subtitle' => 'Smartphones & tablets that keep up', 'type' => 'product_carousel', 'source' => 'category', 'icon' => 'fas fa-mobile-screen', 'category_slug' => 'phones-tablets', 'layout' => 'carousel', 'limit' => 10],
            ['title' => 'Sound & Vision', 'subtitle' => 'TVs, audio and cameras', 'type' => 'product_carousel', 'source' => 'category', 'icon' => 'fas fa-tv', 'category_slug' => 'electronics', 'layout' => 'carousel', 'limit' => 10],
            ['title' => 'Your Computing Hub', 'subtitle' => 'Laptops, monitors and gear', 'type' => 'product_carousel', 'source' => 'category', 'icon' => 'fas fa-laptop', 'category_slug' => 'computing', 'layout' => 'carousel', 'limit' => 10],
            ['title' => 'Power Up Your Home', 'subtitle' => 'Appliances built to last', 'type' => 'product_carousel', 'source' => 'category', 'icon' => 'fas fa-blender', 'category_slug' => 'appliances', 'layout' => 'carousel', 'limit' => 10],
            ['title' => 'Home Comforts', 'subtitle' => 'Furniture, decor and kitchenware', 'type' => 'product_carousel', 'source' => 'category', 'icon' => 'fas fa-couch', 'category_slug' => 'home-office', 'layout' => 'carousel', 'limit' => 10],
            ['title' => 'Style Without the Price Tag', 'subtitle' => 'Fashion for every day', 'type' => 'product_carousel', 'source' => 'category', 'icon' => 'fas fa-shirt', 'category_slug' => 'fashion', 'layout' => 'carousel', 'limit' => 10],
            ['title' => 'Glow Every Day', 'subtitle' => 'Beauty and personal care', 'type' => 'product_carousel', 'source' => 'category', 'icon' => 'fas fa-spray-can-sparkles', 'category_slug' => 'health-beauty', 'layout' => 'carousel', 'limit' => 10],
            ['title' => 'Pantry Plus', 'subtitle' => 'Everyday groceries at Juvo prices', 'type' => 'product_carousel', 'source' => 'category', 'icon' => 'fas fa-basket-shopping', 'category_slug' => 'groceries', 'layout' => 'carousel', 'limit' => 10],
            ['title' => 'Play, Learn, Grow', 'subtitle' => 'Baby and kids essentials', 'type' => 'product_carousel', 'source' => 'category', 'icon' => 'fas fa-baby', 'category_slug' => 'baby', 'layout' => 'carousel', 'limit' => 10],
            ['title' => 'Move More', 'subtitle' => 'Fitness and outdoor gear', 'type' => 'product_carousel', 'source' => 'category', 'icon' => 'fas fa-dumbbell', 'category_slug' => 'sporting', 'layout' => 'carousel', 'limit' => 10],
            ['title' => 'Game On', 'subtitle' => 'Consoles, controllers and more', 'type' => 'product_carousel', 'source' => 'category', 'icon' => 'fas fa-gamepad', 'category_slug' => 'gaming', 'layout' => 'carousel', 'limit' => 10],
            // Named collection rail (exercises the "collection" source)
            ['title' => 'Little Prices, Big Smiles', 'subtitle' => 'Small budgets, happy carts', 'type' => 'product_grid', 'source' => 'collection', 'icon' => 'fas fa-tags', 'layout' => 'grid', 'limit' => 12, 'settings' => ['collection' => 'little-prices']],
            // Merchandising rails
            ['title' => 'Fresh Off the Van', 'subtitle' => 'Just listed on Juvo', 'type' => 'product_carousel', 'source' => 'new_arrivals', 'icon' => 'fas fa-box-open', 'layout' => 'carousel', 'limit' => 12],
            ['title' => 'Top-Rated Picks', 'subtitle' => 'Loved by Juvo shoppers', 'type' => 'product_carousel', 'source' => 'bestsellers', 'icon' => 'fas fa-star', 'layout' => 'carousel', 'limit' => 12],
            ['title' => 'Trending Now', 'subtitle' => 'What everyone is buying', 'type' => 'product_carousel', 'source' => 'trending', 'icon' => 'fas fa-fire', 'layout' => 'carousel', 'limit' => 12],
            // Promo grids (kept; existing card settings preserved on update)
            ['title' => 'Why shop with Juvo', 'subtitle' => null, 'type' => 'promo_grid', 'source' => 'promo', 'icon' => null, 'layout' => 'carousel', 'limit' => 3],
            ['title' => 'Juvo Foods', 'subtitle' => null, 'type' => 'promo_grid', 'source' => 'promo', 'icon' => null, 'layout' => 'carousel', 'limit' => 10],
        ];

        $catIndex = DB::table('categories')->pluck('id', 'slug');
        $titles = [];

        foreach ($sections as $i => $s) {
            $titles[] = $s['title'];

            $attrs = [
                'type' => $s['type'],
                'subtitle' => $s['subtitle'] ?? null,
                'icon' => $s['icon'] ?? null,
                'source' => $s['source'],
                'category_id' => isset($s['category_slug']) ? ($catIndex[$s['category_slug']] ?? null) : null,
                'layout' => $s['layout'] ?? 'carousel',
                'limit' => $s['limit'] ?? 10,
                'status' => 'published',
                'sort_order' => $i + 1,
                'updated_at' => now(),
            ];

            // Only set settings when explicitly defined; preserve existing promo
            // card settings for promo_grid rows on re-run.
            if (array_key_exists('settings', $s)) {
                $attrs['settings'] = json_encode($s['settings']);
            }

            $existing = DB::table('homepage_sections')->where('title', $s['title'])->first();

            if ($existing) {
                DB::table('homepage_sections')->where('id', $existing->id)->update($attrs);
            } else {
                $attrs['title'] = $s['title'];
                $attrs['settings'] = $attrs['settings'] ?? null;
                $attrs['created_at'] = now();
                DB::table('homepage_sections')->insert($attrs);
            }
        }

        DB::table('homepage_sections')->whereNotIn('title', $titles)->delete();
    }
}
