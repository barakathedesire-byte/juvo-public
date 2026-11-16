<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->update([
            'images_count' => DB::raw('(SELECT COUNT(*) FROM product_images WHERE product_images.product_id = products.id)'),
        ]);
    }

    public function down(): void
    {
        DB::table('products')->update(['images_count' => 0]);
    }
};
