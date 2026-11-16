<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homepage_slides')) {
            Schema::create('homepage_slides', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('subtitle')->nullable();
                $table->text('description')->nullable();
                $table->string('cta_label')->nullable();
                $table->string('cta_url')->nullable();
                $table->string('image')->nullable();
                $table->string('mobile_image')->nullable();
                $table->string('badge')->nullable();
                $table->string('text_align')->default('left');
                $table->string('status')->default('published');
                $table->integer('sort_order')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('homepage_tiles')) {
            Schema::create('homepage_tiles', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->string('image')->nullable();
                $table->string('link_type')->default('url');
                $table->string('link_value')->nullable();
                $table->string('status')->default('published');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('homepage_sections')) {
            Schema::create('homepage_sections', function (Blueprint $table) {
                $table->id();
                $table->string('type')->default('product_carousel');
                $table->string('title')->nullable();
                $table->string('subtitle')->nullable();
                $table->string('icon')->nullable();
                $table->string('source')->default('featured');
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('layout')->default('carousel');
                $table->integer('limit')->default(10);
                $table->json('settings')->nullable();
                $table->string('status')->default('published');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('homepage_settings')) {
            Schema::create('homepage_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        $this->seedDefaults();
    }

    protected function seedDefaults(): void
    {
        if (DB::table('homepage_settings')->count() === 0) {
            $now = now();
            DB::table('homepage_settings')->insert([
                ['key' => 'autoplay', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'autoplay_speed', 'value' => '5000', 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'show_category_strip', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (DB::table('homepage_tiles')->count() === 0) {
            $now = now();
            $tiles = [
                ['Just Landed', 'New arrivals fresh in store', 'new_arrivals', 'New arrivals fresh in store', 1],
                ['Last Chance Deals', 'Final markdowns before they are gone', 'clearance', 'Final markdowns before they are gone', 2],
                ['Little Wonders', 'Kids, toys and baby essentials', 'category', 'baby', 3],
                ['Hidden Gems', 'Curated finds worth discovering', 'hidden_gems', 'Curated finds worth discovering', 4],
            ];

            foreach ($tiles as $i => $t) {
                DB::table('homepage_tiles')->insert([
                    'title' => $t[0],
                    'subtitle' => $t[1],
                    'image' => null,
                    'link_type' => $t[2] === 'category' ? 'category' : 'collection',
                    'link_value' => $t[2] === 'category' ? $t[3] : $t[2],
                    'status' => 'published',
                    'sort_order' => $t[4],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (DB::table('homepage_sections')->count() === 0) {
            $now = now();
            $sections = [
                ['product_carousel', 'Daily Deals', 'Grab them before they are gone', 'fa-solid fa-tags', 'daily_deals', null, 'carousel', 10, null, 'published', 1],
                ['promo_grid', 'Why shop with Juvo', null, null, 'promo', null, 'grid', 3, json_encode(['cards' => [
                    ['title' => 'Fast & Secure Delivery', 'subtitle' => 'Doorstep delivery on eligible orders across the country.', 'cta_label' => 'Shop now', 'url' => '/products', 'size' => 'lg', 'badge' => 'Juvo Express', 'image' => null, 'theme' => 'orange'],
                    ['title' => "Today's Deals", 'subtitle' => 'Fresh markdowns every day.', 'cta_label' => 'See deals', 'url' => '/products?sort=daily_deals', 'size' => 'sm', 'badge' => 'Daily Deals', 'image' => null, 'theme' => 'dark'],
                    ['title' => 'Sell on Juvo', 'subtitle' => 'Open a store and reach more customers.', 'cta_label' => 'Become a vendor', 'url' => '/vendor/register', 'size' => 'sm', 'badge' => 'Vendors', 'image' => null, 'theme' => 'light'],
                ]]), 'published', 2],
                ['product_carousel', 'Featured Products', 'Handpicked highlights from our vendors', 'fa-solid fa-star', 'featured', null, 'carousel', 12, null, 'published', 3],
                ['product_carousel', 'Best Sellers', 'What shoppers love most', 'fa-solid fa-fire', 'bestsellers', null, 'carousel', 10, null, 'published', 4],
                ['product_carousel', 'Trending Now', 'Popular products right now', 'fa-solid fa-arrow-trend-up', 'trending', null, 'carousel', 10, null, 'published', 5],
            ];

            foreach ($sections as $s) {
                DB::table('homepage_sections')->insert([
                    'type' => $s[0],
                    'title' => $s[1],
                    'subtitle' => $s[2],
                    'icon' => $s[3],
                    'source' => $s[4],
                    'category_id' => $s[5],
                    'layout' => $s[6],
                    'limit' => $s[7],
                    'settings' => $s[8],
                    'status' => $s[9],
                    'sort_order' => $s[10],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (DB::table('homepage_slides')->count() === 0) {
            $now = now();
            $banners = DB::table('banners')
                ->join('banner_translations', 'banner_translations.banner_id', '=', 'banners.id')
                ->where('banners.status', 1)
                ->where('banner_translations.language_code', 'en')
                ->orderBy('banners.id')
                ->select('banner_translations.title as title', 'banner_translations.description as description', 'banner_translations.image_url as image')
                ->get();

            $order = 1;
            foreach ($banners as $b) {
                DB::table('homepage_slides')->insert([
                    'title' => $b->title ?: 'Welcome to Juvo',
                    'subtitle' => null,
                    'description' => $b->description ?: 'Discover the biggest variety of products at the best prices on Juvo.',
                    'cta_label' => 'Shop Now',
                    'cta_url' => '/products',
                    'image' => $b->image,
                    'status' => 'published',
                    'sort_order' => $order++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($order === 1) {
                DB::table('homepage_slides')->insert([
                    'title' => 'Welcome to Juvo',
                    'subtitle' => 'Everyday marketplace, everyday value',
                    'description' => 'Shop from thousands of vendors, delivered fast.',
                    'cta_label' => 'Start Shopping',
                    'cta_url' => '/products',
                    'status' => 'published',
                    'sort_order' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_settings');
        Schema::dropIfExists('homepage_sections');
        Schema::dropIfExists('homepage_tiles');
        Schema::dropIfExists('homepage_slides');
    }
};
