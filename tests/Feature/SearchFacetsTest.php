<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SearchFacetsTest extends TestCase
{
    use DatabaseTransactions;

    protected function searchable(): ?Product
    {
        return Product::visible()->whereHas('translations', fn ($q) => $q->where('language_code', 'en'))
            ->whereHas('variants')
            ->with('translation')
            ->first();
    }

    public function test_search_renders_facet_sidebar(): void
    {
        $this->get(route('search').'?q=shirt')
            ->assertOk()
            ->assertSee('id="facetSidebar"', false)
            ->assertSee('name="price_min"', false);
    }

    public function test_search_matches_visible_products_by_name(): void
    {
        $product = $this->searchable();
        if (! $product || ! $product->translation) {
            $this->markTestSkipped('No translatable visible product with variants.');
        }

        $this->get(route('search').'?q='.urlencode($product->translation->name))
            ->assertOk()
            ->assertSee('/product/'.$product->slug, false);
    }

    public function test_category_facet_excludes_products_outside_the_category(): void
    {
        $product = $this->searchable();
        if (! $product || ! $product->translation || ! $product->category_id) {
            $this->markTestSkipped('No categorized visible product available.');
        }

        $other = Product::visible()->whereNotNull('category_id')
            ->where('category_id', '!=', $product->category_id)
            ->first();
        if (! $other) {
            $this->markTestSkipped('No second category available to filter by.');
        }

        $this->get(route('search').'?q='.urlencode($product->translation->name).'&category[]='.$other->category_id)
            ->assertOk()
            ->assertDontSee('/product/'.$product->slug, false);
    }

    public function test_price_facet_excludes_products_out_of_range(): void
    {
        $product = $this->searchable();
        if (! $product || ! $product->translation) {
            $this->markTestSkipped('No visible product with variants.');
        }

        $this->get(route('search').'?q='.urlencode($product->translation->name).'&price_max=-1')
            ->assertOk()
            ->assertDontSee('/product/'.$product->slug, false);
    }

    public function test_search_sort_options_are_supported(): void
    {
        foreach (['newest', 'price_asc', 'price_desc', 'rating'] as $sort) {
            $this->get(route('search').'?q=shirt&sort='.$sort)->assertOk();
        }
    }
}
