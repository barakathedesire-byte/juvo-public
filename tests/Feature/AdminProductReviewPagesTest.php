<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class AdminProductReviewPagesTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    public function test_review_show_and_edit_render(): void
    {
        if (! Customer::query()->exists() || ! Product::query()->exists()) {
            $this->markTestSkipped('Requires an existing product and customer.');
        }

        $review = ProductReview::create([
            'customer_id' => Customer::query()->value('id'),
            'product_id' => Product::query()->value('id'),
            'rating' => 5,
            'review' => 'probe',
            'is_approved' => true,
        ]);

        $admin = $this->admin();

        $this->actingAs($admin, 'web')
            ->get(route('admin.reviews.show', $review->id))
            ->assertOk();

        $this->actingAs($admin, 'web')
            ->get(route('admin.reviews.edit', $review->id))
            ->assertOk()
            ->assertSee('Update Review');
    }
}
