<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JuvoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // ---- English only ----
            DB::table('languages')->update(['active' => 0]);
            DB::table('languages')->where('code', 'en')->update(['active' => 1]);

            // ---- Shop for the demo vendor (shops.vendor_id is NOT NULL + FK) ----
            $vendor = DB::table('vendors')->orderBy('id')->first();
            if (! $vendor) {
                $this->command?->warn('No vendor found; skipping catalog seed.');

                return;
            }
            $shopId = DB::table('shops')->where('vendor_id', $vendor->id)->value('id');
            if (! $shopId) {
                $shopId = DB::table('shops')->insertGetId([
                    'vendor_id' => $vendor->id,
                    'name' => 'Juvo Official Store',
                    'slug' => 'juvo-official-store',
                    'logo' => 'brands/logo-ready.png',
                    'description' => 'Official Juvo marketplace store.',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // ---- Brands ----
            $brands = [
                'techpro' => 'TechPro',
                'powertech' => 'PowerTech',
                'homemax' => 'HomeMax',
                'stylehub' => 'StyleHub',
                'sportx' => 'SportX',
                'beautyglow' => 'BeautyGlow',
                'kidjoy' => 'KidJoy',
                'gamerzone' => 'GamerZone',
                'bookworld' => 'BookWorld',
                'freshmart' => 'FreshMart',
            ];
            $brandIds = [];
            foreach ($brands as $slug => $name) {
                $id = DB::table('brands')->where('slug', $slug)->value('id');
                if (! $id) {
                    $id = DB::table('brands')->insertGetId([
                        'slug' => $slug,
                        'logo_url' => 'brands/logo-ready.png',
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('brand_translations')->updateOrInsert(
                    ['brand_id' => $id, 'locale' => 'en'],
                    ['name' => $name, 'description' => $name.' brand on Juvo.', 'created_at' => now(), 'updated_at' => now()]
                );
                $brandIds[$slug] = $id;
            }

            // ---- Categories (Jumia-style tree) ----
            $catImages = [
                'categories/07-300x300-1-1-removebg-preview.png',
                'categories/cat1-removebg-preview.png',
            ];
            $categories = [
                ['phones-tablets', 'Phones & Tablets', null],
                ['smartphones', 'Smartphones', 'phones-tablets'],
                ['phone-accessories', 'Phone Accessories', 'phones-tablets'],
                ['tablets', 'Tablets', 'phones-tablets'],
                ['electronics', 'Electronics', null],
                ['tvs-video', 'TVs & Video', 'electronics'],
                ['audio-music', 'Audio & Music', 'electronics'],
                ['cameras', 'Cameras', 'electronics'],
                ['computing', 'Computing', null],
                ['laptops', 'Laptops', 'computing'],
                ['desktops', 'Desktops', 'computing'],
                ['monitors', 'Monitors', 'computing'],
                ['printers', 'Printers', 'computing'],
                ['computer-accessories', 'Computer Accessories', 'computing'],
                ['home-office', 'Home & Office', null],
                ['furniture', 'Furniture', 'home-office'],
                ['kitchen-dining', 'Kitchen & Dining', 'home-office'],
                ['home-decor', 'Home Decor', 'home-office'],
                ['bedding', 'Bedding', 'home-office'],
                ['appliances', 'Appliances', null],
                ['refrigerators', 'Refrigerators', 'appliances'],
                ['washing-machines', 'Washing Machines', 'appliances'],
                ['air-conditioners', 'Air Conditioners', 'appliances'],
                ['cookers', 'Cookers', 'appliances'],
                ['fashion', 'Fashion', null],
                ['mens-fashion', "Men's Fashion", 'fashion'],
                ['womens-fashion', "Women's Fashion", 'fashion'],
                ['t-shirts', 'T-Shirts', 'fashion'],
                ['shoes', 'Shoes', 'fashion'],
                ['bags', 'Bags', 'fashion'],
                ['health-beauty', 'Health & Beauty', null],
                ['makeup', 'Makeup', 'health-beauty'],
                ['skincare', 'Skincare', 'health-beauty'],
                ['fragrances', 'Fragrances', 'health-beauty'],
                ['groceries', 'Groceries', null],
                ['beverages', 'Beverages', 'groceries'],
                ['snacks', 'Snacks', 'groceries'],
                ['baby', 'Baby Products', null],
                ['diapers', 'Diapers', 'baby'],
                ['toys', 'Toys', 'baby'],
                ['sporting', 'Sports & Outdoors', null],
                ['sport-shoes', 'Sport Shoes', 'sporting'],
                ['fitness', 'Fitness', 'sporting'],
                ['gaming', 'Gaming', null],
                ['books', 'Books & Stationery', null],
                ['wines-spirits', 'Wines & Spirits', null],
            ];

            // First pass: create categories
            foreach ($categories as $i => [$slug, $name, $parent]) {
                DB::table('categories')->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'parent_category_id' => null,
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
            // Second pass: parents + translations
            $catIndex = [];
            foreach ($categories as $i => [$slug, $name, $parent]) {
                $catId = DB::table('categories')->where('slug', $slug)->value('id');
                $catIndex[$slug] = $catId;
                if ($parent) {
                    DB::table('categories')->where('id', $catId)->update(['parent_category_id' => $catIndex[$parent] ?? null]);
                }
                DB::table('category_translations')->updateOrInsert(
                    ['category_id' => $catId, 'language_code' => 'en'],
                    [
                        'name' => $name,
                        'description' => $name.' on Juvo.',
                        'image_url' => $catImages[$i % count($catImages)],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // ---- Products ----
            $images = [
                'products/images-removebg-preview.png',
                'products/images-1-removebg-preview-2.png',
                'products/images-2-removebg-preview-1.png',
                'products/T-Shirt-removebg-preview.png',
            ];
            $products = $this->products();

            $i = 0;
            foreach ($products as $p) {
                $catId = $catIndex[$p['category']] ?? null;
                if (! $catId) {
                    continue;
                }
                $brandId = $brandIds[$p['brand']] ?? null;
                $image = $images[$i % count($images)];
                $i++;

                $productId = DB::table('products')->where('slug', $p['slug'])->value('id');
                if (! $productId) {
                    $productId = DB::table('products')->insertGetId([
                        'shop_id' => $shopId,
                        'vendor_id' => $vendor->id,
                        'slug' => $p['slug'],
                        'category_id' => $catId,
                        'brand_id' => $brandId,
                        'product_type' => 'variable',
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('products')->where('id', $productId)->update([
                        'category_id' => $catId,
                        'brand_id' => $brandId,
                        'status' => 1,
                        'updated_at' => now(),
                    ]);
                }

                DB::table('product_translations')->updateOrInsert(
                    ['product_id' => $productId, 'language_code' => 'en'],
                    [
                        'name' => $p['name'],
                        'description' => $p['name'].' — quality checked and delivered fast across Uganda.',
                        'short_description' => Str::limit($p['name'].' available now on Juvo.', 120),
                        'tags' => strtolower($p['name']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                DB::table('product_images')->where('product_id', $productId)->delete();
                DB::table('product_images')->insert([
                    'name' => basename($image),
                    'image_url' => $image,
                    'type' => 'thumb',
                    'product_id' => $productId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('product_variants')->where('product_id', $productId)->delete();
                DB::table('product_variants')->insert([
                    'product_id' => $productId,
                    'variant_slug' => Str::slug($p['name']).'-'.uniqid(),
                    'price' => $p['price'],
                    'discount_price' => $p['discount'] ?? null,
                    'stock' => rand(15, 200),
                    'SKU' => strtoupper(Str::random(3)).rand(1000, 9999),
                    'barcode' => null,
                    'weight' => '0.50',
                    'dimensions' => '20x15x10 cm',
                    'is_primary' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function products(): array
    {
        return [
            // Phones & Tablets
            ['name' => 'Galaxy A15 Smartphone', 'slug' => 'galaxy-a15-smartphone', 'category' => 'smartphones', 'brand' => 'techpro', 'price' => 899000, 'discount' => 799000],
            ['name' => 'iPhone 13 128GB', 'slug' => 'iphone-13-128gb', 'category' => 'smartphones', 'brand' => 'techpro', 'price' => 3200000, 'discount' => 2999000],
            ['name' => 'Tecno Spark 20', 'slug' => 'tecno-spark-20', 'category' => 'smartphones', 'brand' => 'techpro', 'price' => 549000, 'discount' => 499000],
            ['name' => 'Infinix Hot 40i', 'slug' => 'infinix-hot-40i', 'category' => 'smartphones', 'brand' => 'techpro', 'price' => 489000, 'discount' => 449000],
            ['name' => 'Redmi Note 13', 'slug' => 'redmi-note-13', 'category' => 'smartphones', 'brand' => 'techpro', 'price' => 799000, 'discount' => 749000],
            ['name' => 'Tablet 10" WiFi', 'slug' => 'tablet-10-wifi', 'category' => 'tablets', 'brand' => 'techpro', 'price' => 650000, 'discount' => 599000],
            ['name' => 'USB-C Fast Charger', 'slug' => 'usb-c-fast-charger', 'category' => 'phone-accessories', 'brand' => 'techpro', 'price' => 45000, 'discount' => 35000],
            ['name' => 'Wireless Earbuds Pro', 'slug' => 'wireless-earbuds-pro', 'category' => 'audio-music', 'brand' => 'techpro', 'price' => 120000, 'discount' => 89000],
            // Electronics
            ['name' => '55" 4K Smart TV', 'slug' => 'smart-tv-55-4k', 'category' => 'tvs-video', 'brand' => 'techpro', 'price' => 2100000, 'discount' => 1899000],
            ['name' => '32" HD LED TV', 'slug' => 'smart-tv-32-hd', 'category' => 'tvs-video', 'brand' => 'techpro', 'price' => 750000, 'discount' => 699000],
            ['name' => 'Bluetooth Soundbar', 'slug' => 'bluetooth-soundbar', 'category' => 'audio-music', 'brand' => 'techpro', 'price' => 380000, 'discount' => 320000],
            ['name' => 'DSLR Camera Pro', 'slug' => 'dslr-camera-pro', 'category' => 'cameras', 'brand' => 'techpro', 'price' => 2500000, 'discount' => 2299000],
            ['name' => 'Wireless Headphones', 'slug' => 'wireless-headphones', 'category' => 'audio-music', 'brand' => 'techpro', 'price' => 150000, 'discount' => 119000],
            ['name' => 'Home Theatre System', 'slug' => 'home-theatre-system', 'category' => 'audio-music', 'brand' => 'techpro', 'price' => 990000, 'discount' => 899000],
            // Computing
            ['name' => '15" Core i5 Laptop', 'slug' => 'core-i5-laptop-15', 'category' => 'laptops', 'brand' => 'techpro', 'price' => 3200000, 'discount' => 2850000],
            ['name' => '14" Ultrabook Core i7', 'slug' => 'ultrabook-core-i7', 'category' => 'laptops', 'brand' => 'techpro', 'price' => 4800000, 'discount' => 4499000],
            ['name' => 'Slim Laptop Pro', 'slug' => 'slim-laptop-pro', 'category' => 'laptops', 'brand' => 'techpro', 'price' => 5200000, 'discount' => 4899000],
            ['name' => '24" IPS Monitor', 'slug' => 'ips-monitor-24', 'category' => 'monitors', 'brand' => 'techpro', 'price' => 620000, 'discount' => 549000],
            ['name' => 'Wireless Mouse', 'slug' => 'wireless-mouse', 'category' => 'computer-accessories', 'brand' => 'techpro', 'price' => 35000, 'discount' => 25000],
            ['name' => 'Mechanical Keyboard', 'slug' => 'mechanical-keyboard', 'category' => 'computer-accessories', 'brand' => 'techpro', 'price' => 145000, 'discount' => 119000],
            ['name' => 'Laser Printer', 'slug' => 'laser-printer', 'category' => 'printers', 'brand' => 'techpro', 'price' => 850000, 'discount' => 799000],
            ['name' => 'Gaming Desktop PC', 'slug' => 'gaming-desktop-pc', 'category' => 'desktops', 'brand' => 'gamerzone', 'price' => 6500000, 'discount' => 5999000],
            // Appliances
            ['name' => 'Double Door Refrigerator', 'slug' => 'double-door-fridge', 'category' => 'refrigerators', 'brand' => 'powertech', 'price' => 2800000, 'discount' => 2599000],
            ['name' => 'Single Door Fridge 180L', 'slug' => 'single-door-fridge-180l', 'category' => 'refrigerators', 'brand' => 'powertech', 'price' => 1350000, 'discount' => 1199000],
            ['name' => 'Front Load Washing Machine', 'slug' => 'front-load-washer', 'category' => 'washing-machines', 'brand' => 'powertech', 'price' => 1900000, 'discount' => 1749000],
            ['name' => 'Split Air Conditioner 1.5HP', 'slug' => 'split-ac-15hp', 'category' => 'air-conditioners', 'brand' => 'powertech', 'price' => 2100000, 'discount' => 1899000],
            ['name' => '4-Burner Gas Cooker', 'slug' => 'gas-cooker-4burner', 'category' => 'cookers', 'brand' => 'powertech', 'price' => 780000, 'discount' => 699000],
            ['name' => 'Microwave Oven 25L', 'slug' => 'microwave-oven-25l', 'category' => 'kitchen-dining', 'brand' => 'homemax', 'price' => 480000, 'discount' => 429000],
            // Home & Office
            ['name' => '3-Seater Fabric Sofa', 'slug' => 'fabric-sofa-3-seater', 'category' => 'furniture', 'brand' => 'homemax', 'price' => 1850000, 'discount' => 1650000],
            ['name' => '6-Seater Dining Set', 'slug' => 'dining-set-6-seater', 'category' => 'furniture', 'brand' => 'homemax', 'price' => 2200000, 'discount' => 1999000],
            ['name' => 'Queen Bed Frame', 'slug' => 'queen-bed-frame', 'category' => 'furniture', 'brand' => 'homemax', 'price' => 1200000, 'discount' => 1099000],
            ['name' => 'Memory Foam Mattress', 'slug' => 'memory-foam-mattress', 'category' => 'bedding', 'brand' => 'homemax', 'price' => 890000, 'discount' => 799000],
            ['name' => 'Non-Stick Cookware Set', 'slug' => 'cookware-set-nonstick', 'category' => 'kitchen-dining', 'brand' => 'homemax', 'price' => 320000, 'discount' => 269000],
            ['name' => 'Electric Kettle 1.8L', 'slug' => 'electric-kettle-18l', 'category' => 'kitchen-dining', 'brand' => 'homemax', 'price' => 85000, 'discount' => 69000],
            ['name' => 'Modern Wall Clock', 'slug' => 'wall-clock-modern', 'category' => 'home-decor', 'brand' => 'homemax', 'price' => 45000, 'discount' => 35000],
            // Fashion
            ['name' => 'Cool T-Shirt', 'slug' => 'cool-tshirt', 'category' => 't-shirts', 'brand' => 'stylehub', 'price' => 45000, 'discount' => 38000],
            ['name' => "Men's Slim Fit Jeans", 'slug' => 'mens-slim-jeans', 'category' => 'mens-fashion', 'brand' => 'stylehub', 'price' => 120000, 'discount' => 89000],
            ['name' => "Women's Floral Dress", 'slug' => 'womens-floral-dress', 'category' => 'womens-fashion', 'brand' => 'stylehub', 'price' => 150000, 'discount' => 119000],
            ['name' => 'Sport Shoes', 'slug' => 'sport-shoes', 'category' => 'sport-shoes', 'brand' => 'sportx', 'price' => 180000, 'discount' => 145000],
            ['name' => 'Leather Handbag', 'slug' => 'leather-handbag', 'category' => 'bags', 'brand' => 'stylehub', 'price' => 220000, 'discount' => 179000],
            ['name' => 'Unisex Sneakers', 'slug' => 'unisex-sneakers', 'category' => 'shoes', 'brand' => 'sportx', 'price' => 180000, 'discount' => 149000],
            // Health & Beauty
            ['name' => 'Matte Lipstick Set', 'slug' => 'matte-lipstick-set', 'category' => 'makeup', 'brand' => 'beautyglow', 'price' => 65000, 'discount' => 49000],
            ['name' => 'Vitamin C Face Serum', 'slug' => 'vitamin-c-face-serum', 'category' => 'skincare', 'brand' => 'beautyglow', 'price' => 89000, 'discount' => 69000],
            ['name' => 'Eau de Parfum 100ml', 'slug' => 'eau-de-parfum-100ml', 'category' => 'fragrances', 'brand' => 'beautyglow', 'price' => 250000, 'discount' => 199000],
            // Groceries
            ['name' => 'Premium Coffee Beans 1kg', 'slug' => 'coffee-beans-1kg', 'category' => 'beverages', 'brand' => 'freshmart', 'price' => 78000, 'discount' => 65000],
            ['name' => 'Assorted Snack Box', 'slug' => 'assorted-snack-box', 'category' => 'snacks', 'brand' => 'freshmart', 'price' => 55000, 'discount' => 45000],
            ['name' => 'Bottled Water 12-Pack', 'slug' => 'bottled-water-12pack', 'category' => 'beverages', 'brand' => 'freshmart', 'price' => 30000, 'discount' => 25000],
            // Baby
            ['name' => 'Baby Diapers Jumbo Pack', 'slug' => 'baby-diapers-jumbo', 'category' => 'diapers', 'brand' => 'kidjoy', 'price' => 95000, 'discount' => 79000],
            ['name' => 'Educational Building Blocks', 'slug' => 'building-blocks', 'category' => 'toys', 'brand' => 'kidjoy', 'price' => 120000, 'discount' => 99000],
            // Sports
            ['name' => 'Anti-Slip Yoga Mat', 'slug' => 'yoga-mat-antislip', 'category' => 'fitness', 'brand' => 'sportx', 'price' => 65000, 'discount' => 49000],
            ['name' => 'Dumbbell Set 20kg', 'slug' => 'dumbbell-set-20kg', 'category' => 'fitness', 'brand' => 'sportx', 'price' => 250000, 'discount' => 219000],
            ['name' => 'Travel Backpack', 'slug' => 'travel-backpack', 'category' => 'bags', 'brand' => 'sportx', 'price' => 160000, 'discount' => 129000],
            // Gaming
            ['name' => 'Wireless Game Controller', 'slug' => 'wireless-game-controller', 'category' => 'gaming', 'brand' => 'gamerzone', 'price' => 180000, 'discount' => 149000],
            ['name' => 'RGB Gaming Headset', 'slug' => 'gaming-headset-rgb', 'category' => 'gaming', 'brand' => 'gamerzone', 'price' => 220000, 'discount' => 189000],
            // Books
            ['name' => 'Notebook & Pen Set', 'slug' => 'notebook-pen-set', 'category' => 'books', 'brand' => 'bookworld', 'price' => 45000, 'discount' => 35000],

            // ---- Top-up so every themed homepage rail has enough products ----
            // Appliances
            ['name' => 'Digital Air Fryer 5L', 'slug' => 'digital-air-fryer-5l', 'category' => 'cookers', 'brand' => 'powertech', 'price' => 420000, 'discount' => 369000],
            // Health & Beauty
            ['name' => 'Liquid Foundation 30ml', 'slug' => 'liquid-foundation-30ml', 'category' => 'makeup', 'brand' => 'beautyglow', 'price' => 78000, 'discount' => 59000],
            ['name' => 'Aloe Vera Soothing Gel', 'slug' => 'aloe-vera-gel', 'category' => 'skincare', 'brand' => 'beautyglow', 'price' => 42000, 'discount' => 32000],
            ['name' => 'Refreshing Body Mist 250ml', 'slug' => 'body-mist-250ml', 'category' => 'fragrances', 'brand' => 'beautyglow', 'price' => 65000, 'discount' => 49000],
            // Groceries
            ['name' => 'Instant Coffee 200g', 'slug' => 'instant-coffee-200g', 'category' => 'beverages', 'brand' => 'freshmart', 'price' => 48000, 'discount' => 39000],
            ['name' => 'Chocolate Gift Box', 'slug' => 'chocolate-gift-box', 'category' => 'snacks', 'brand' => 'freshmart', 'price' => 60000, 'discount' => 48000],
            ['name' => 'Roasted Peanuts 500g', 'slug' => 'roasted-peanuts-500g', 'category' => 'snacks', 'brand' => 'freshmart', 'price' => 25000, 'discount' => 19000],
            // Baby
            ['name' => 'Baby Wipes 3-Pack', 'slug' => 'baby-wipes-3pack', 'category' => 'diapers', 'brand' => 'kidjoy', 'price' => 38000, 'discount' => 29000],
            ['name' => 'Gentle Baby Lotion 400ml', 'slug' => 'baby-lotion-400ml', 'category' => 'diapers', 'brand' => 'kidjoy', 'price' => 45000, 'discount' => 35000],
            ['name' => 'Cuddly Plush Teddy Bear', 'slug' => 'plush-teddy-bear', 'category' => 'toys', 'brand' => 'kidjoy', 'price' => 55000, 'discount' => 42000],
            ['name' => 'Wooden Puzzle Set', 'slug' => 'wooden-puzzle-set', 'category' => 'toys', 'brand' => 'kidjoy', 'price' => 68000, 'discount' => 52000],
            // Sports & Outdoors
            ['name' => 'Resistance Bands Set', 'slug' => 'resistance-bands-set', 'category' => 'fitness', 'brand' => 'sportx', 'price' => 55000, 'discount' => 42000],
            ['name' => 'Skipping Rope Pro', 'slug' => 'skipping-rope-pro', 'category' => 'fitness', 'brand' => 'sportx', 'price' => 28000, 'discount' => 21000],
            ['name' => 'Elite Running Shoes', 'slug' => 'elite-running-shoes', 'category' => 'sport-shoes', 'brand' => 'sportx', 'price' => 240000, 'discount' => 199000],
            // Gaming
            ['name' => 'RGB Gaming Mouse', 'slug' => 'rgb-gaming-mouse', 'category' => 'gaming', 'brand' => 'gamerzone', 'price' => 130000, 'discount' => 99900],
            ['name' => 'Ergonomic Gaming Chair', 'slug' => 'ergonomic-gaming-chair', 'category' => 'gaming', 'brand' => 'gamerzone', 'price' => 850000, 'discount' => 749000],
            ['name' => 'Portable Handheld Console', 'slug' => 'portable-handheld-console', 'category' => 'gaming', 'brand' => 'gamerzone', 'price' => 1250000, 'discount' => 1099000],
            ['name' => 'Full HD 1080p Webcam', 'slug' => 'full-hd-webcam-1080p', 'category' => 'gaming', 'brand' => 'gamerzone', 'price' => 95000, 'discount' => 74000],
            // Books & Stationery
            ['name' => 'Fiction Bestseller Bundle', 'slug' => 'fiction-bestseller-bundle', 'category' => 'books', 'brand' => 'bookworld', 'price' => 90000, 'discount' => 72000],
            ['name' => 'Art Sketchbook A4', 'slug' => 'art-sketchbook-a4', 'category' => 'books', 'brand' => 'bookworld', 'price' => 35000, 'discount' => 27000],
            ['name' => 'Kids Illustrated Storybook', 'slug' => 'kids-storybook', 'category' => 'books', 'brand' => 'bookworld', 'price' => 42000, 'discount' => 33000],
            ['name' => 'Desk Organizer Set', 'slug' => 'desk-organizer-set', 'category' => 'books', 'brand' => 'bookworld', 'price' => 55000, 'discount' => 44000],
            ['name' => 'Executive Leather Journal', 'slug' => 'executive-leather-journal', 'category' => 'books', 'brand' => 'bookworld', 'price' => 65000, 'discount' => 52000],
            // Wines & Spirits
            ['name' => 'Red Wine 750ml', 'slug' => 'red-wine-750ml', 'category' => 'wines-spirits', 'brand' => 'freshmart', 'price' => 85000, 'discount' => 69000],
            ['name' => 'Premium Whisky 700ml', 'slug' => 'premium-whisky-700ml', 'category' => 'wines-spirits', 'brand' => 'freshmart', 'price' => 190000, 'discount' => 159000],
            ['name' => 'Sparkling Wine 750ml', 'slug' => 'sparkling-wine-750ml', 'category' => 'wines-spirits', 'brand' => 'freshmart', 'price' => 72000, 'discount' => 58000],
        ];
    }
}
