<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductImage;

class ProductImageObserver
{
    public function created(ProductImage $image): void
    {
        $this->sync($image);
    }

    public function deleted(ProductImage $image): void
    {
        $this->sync($image);
    }

    protected function sync(ProductImage $image): void
    {
        if (! $image->product_id) {
            return;
        }

        Product::whereKey($image->product_id)->update([
            'images_count' => ProductImage::where('product_id', $image->product_id)->count(),
        ]);
    }
}
