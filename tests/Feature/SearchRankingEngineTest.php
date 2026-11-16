<?php

namespace Tests\Feature;

use App\Models\Algorithm;
use App\Models\Product;
use App\Models\SearchSynonym;
use App\Services\Search\SearchIndexService;
use App\Services\Search\SearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class SearchRankingEngineTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function service(): SearchService
    {
        return app(SearchService::class);
    }

    protected function index(): SearchIndexService
    {
        return app(SearchIndexService::class);
    }

    protected function purchasableProduct(): ?Product
    {
        return Product::visible()
            ->whereHas('primaryVariant', fn ($q) => $q->where('stock', '>', 0))
            ->whereHas('translations')
            ->first();
    }

    protected function productName(Product $product, string $locale = 'en'): string
    {
        return (string) ($product->translations->firstWhere('language_code', $locale)?->name
            ?? $product->translations->first()?->name
            ?? '');
    }

    /* ------------------------------ Registry ------------------------------ */

    public function test_search_algorithm_is_registered_as_implemented(): void
    {
        $algorithm = Algorithm::where('key', SearchService::ALGORITHM_KEY)->first();

        $this->assertNotNull($algorithm, 'search_ranking algorithm must be registered');
        $this->assertTrue((bool) $algorithm->is_implemented, 'search_ranking must be marked implemented');
    }

    /* ------------------------------- Indexing ------------------------------ */

    public function test_rebuild_populates_the_index(): void
    {
        $written = $this->index()->rebuild();

        $this->assertGreaterThan(0, $written);
        $this->assertGreaterThan(0, $this->index()->documentCount());
    }

    /* ------------------------------ Relevance ------------------------------ */

    public function test_exact_title_match_ranks_first(): void
    {
        $product = $this->purchasableProduct();
        $this->assertNotNull($product);
        $this->index()->refreshProduct($product);

        $name = $this->productName($product);
        $result = $this->service()->search($name, ['per_page' => 10, 'include_sponsored' => false]);

        $first = $result['products']->items()[0] ?? null;
        $this->assertNotNull($first, 'Expected at least one result for an exact product name');
        $this->assertSame($product->id, $first->id);
        $this->assertGreaterThan(0, $first->search_score);
    }

    public function test_identical_requests_are_deterministic(): void
    {
        $product = $this->purchasableProduct();
        $this->index()->refreshProduct($product);
        $name = $this->productName($product);

        $first = collect($this->service()->search($name, ['per_page' => 12])['products']->items())
            ->pluck('id')->all();
        $second = collect($this->service()->search($name, ['per_page' => 12])['products']->items())
            ->pluck('id')->all();

        $this->assertSame($first, $second);
    }

    /* ---------------------------- Eligibility ----------------------------- */

    public function test_hidden_product_is_excluded_from_search_and_autocomplete(): void
    {
        $product = $this->purchasableProduct();
        $this->assertNotNull($product);
        $this->index()->refreshProduct($product);

        $name = $this->productName($product);
        $query = mb_substr($name, 0, max(3, (int) (mb_strlen($name) / 2)));

        $before = $this->service()->search($name, ['per_page' => 24])['products']->pluck('id')->all();
        $this->assertContains($product->id, $before, 'Product should be searchable before being hidden');

        $product->status = 0;
        $product->save();

        $after = $this->service()->search($name, ['per_page' => 24])['products']->pluck('id')->all();
        $this->assertNotContains($product->id, $after, 'Hidden product must not be searchable');

        $suggest = $this->service()->autocomplete($query, ['locale' => 'en']);
        $suggestIds = collect($suggest['products'])->pluck('id')->all();
        $this->assertNotContains($product->id, $suggestIds, 'Hidden product must not autocomplete');
    }

    public function test_out_of_stock_product_is_excluded_when_stock_required(): void
    {
        $product = $this->purchasableProduct();
        $this->assertNotNull($product);
        $this->index()->refreshProduct($product);
        $name = $this->productName($product);

        foreach ($product->variants as $variant) {
            $variant->stock = 0;
            $variant->save();
        }

        $ids = $this->service()->search($name, ['per_page' => 24])['products']->pluck('id')->all();
        $this->assertNotContains($product->id, $ids);
    }

    /* ------------------------------ Filters ------------------------------- */

    public function test_facets_only_count_eligible_and_filtered_products(): void
    {
        $this->index()->rebuild();

        $query = 'a';
        $broader = $this->service()->search($query, ['per_page' => 48, 'include_sponsored' => false]);
        $total = $broader['meta']['total'];

        $this->assertContainsOnly('int', array_map('intval', array_column($broader['facets']['categories'], 'count')));

        foreach ($broader['facets']['categories'] as $facet) {
            $this->assertLessThanOrEqual($total, $facet['count'], 'Facet counts cannot exceed the matched total');
        }

        if (! empty($broader['facets']['categories'])) {
            $categoryId = $broader['facets']['categories'][0]['id'];
            $filtered = $this->service()->search($query, ['category' => [$categoryId], 'per_page' => 48]);
            $this->assertLessThanOrEqual($total, $filtered['meta']['total']);
        }
    }

    public function test_price_ascending_sort_is_ordered(): void
    {
        $this->index()->rebuild();

        $result = $this->service()->search('a', ['sort' => 'price_asc', 'per_page' => 48]);
        $prices = collect($result['products']->items())->map(function (Product $p) {
            $variant = $p->variants->sortBy(fn ($v) => (float) ($v->discount_price ?: $v->price))->first();

            return (float) ($variant ? ($variant->discount_price ?: $variant->price) : 0);
        })->all();

        $sorted = $prices;
        sort($sorted);
        $this->assertSame($sorted, $prices, 'price_asc results must be ascending by effective price');
    }

    /* ---------------------------- Autocomplete ---------------------------- */

    public function test_autocomplete_returns_structured_payload(): void
    {
        $product = $this->purchasableProduct();
        $this->index()->refreshProduct($product);
        $name = $this->productName($product);

        $payload = $this->service()->autocomplete(mb_substr($name, 0, 4), ['locale' => 'en']);

        $this->assertArrayHasKey('products', $payload);
        $this->assertArrayHasKey('categories', $payload);
        $this->assertArrayHasKey('brands', $payload);
        $this->assertArrayHasKey('queries', $payload);
        $this->assertArrayHasKey('did_you_mean', $payload);
    }

    public function test_autocomplete_ranks_prefix_category_matches_first(): void
    {
        $payload = $this->service()->autocomplete('phon', ['locale' => 'en', 'limit' => 10]);

        $this->assertNotEmpty($payload['categories'], 'Expected category suggestions for "phon"');

        $matches = array_column($payload['categories'], 'match');
        $sorted = $matches;
        sort($sorted);
        $this->assertSame($sorted, $matches, 'Category suggestions must be ordered by match position');

        foreach ($payload['categories'] as $category) {
            $this->assertArrayHasKey('count', $category, 'Category suggestions carry a product count');
            $this->assertGreaterThanOrEqual(0, $category['match']);
        }
    }

    public function test_autocomplete_suggests_did_you_mean_for_a_near_miss(): void
    {
        $canonical = 'zzheadphones';
        DB::table('search_queries')->insert([
            'query' => $canonical,
            'normalized' => $canonical,
            'locale' => 'en',
            'surface' => SearchService::SURFACE_RESULTS,
            'result_count' => 5,
            'is_zero_result' => false,
            'latency_ms' => 10,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();

        $payload = $this->service()->autocomplete('zzheadphanes', ['locale' => 'en']);

        $this->assertSame($canonical, $payload['did_you_mean']);
    }

    public function test_trending_returns_popular_completed_queries(): void
    {
        $unique = 'zzq gaming laptop';

        foreach ([$unique, $unique, $unique, 'zzq gaming mouse'] as $term) {
            DB::table('search_queries')->insert([
                'query' => $term,
                'normalized' => $term,
                'locale' => 'en',
                'surface' => SearchService::SURFACE_RESULTS,
                'result_count' => 3,
                'is_zero_result' => false,
                'latency_ms' => 8,
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::flush();

        $trending = $this->service()->trending('en', 50);

        $this->assertContains($unique, $trending);
        $this->assertContains('zzq gaming mouse', $trending);
        $this->assertLessThan(
            array_search('zzq gaming mouse', $trending, true),
            array_search($unique, $trending, true),
            'The more frequent query ranks above the less frequent one'
        );
    }

    public function test_suggestions_endpoint_returns_trending_when_query_is_empty(): void
    {
        $unique = 'zzq suggestion trending';

        DB::table('search_queries')->insert([
            'query' => $unique,
            'normalized' => $unique,
            'locale' => 'en',
            'surface' => SearchService::SURFACE_RESULTS,
            'result_count' => 4,
            'is_zero_result' => false,
            'latency_ms' => 5,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();

        $response = $this->getJson('/search-suggestions?q=')
            ->assertOk()
            ->assertJsonStructure(['products', 'categories', 'brands', 'queries', 'did_you_mean', 'trending']);

        $this->assertContains($unique, $response->json('trending'));
    }

    /* ------------------------------ Synonyms ------------------------------ */

    public function test_synonyms_expand_query_tokens(): void
    {
        SearchSynonym::create([
            'term' => 'mobiles',
            'locale' => 'en',
            'synonyms' => ['smartphone'],
            'is_active' => true,
        ]);

        $parsed = app(\App\Services\Search\SearchQueryParser::class)->parse('mobiles', 'en');

        $this->assertContains('mobiles', $parsed['tokens']);
        $this->assertArrayHasKey('mobiles', $parsed['expanded']);
        $this->assertContains('smartphone', $parsed['expanded']['mobiles']);
    }

    /* ------------------------------- Sponsored ---------------------------- */

    public function test_sponsored_placements_are_absent_when_not_requested(): void
    {
        $result = $this->service()->search('a', ['include_sponsored' => false]);

        $this->assertCount(0, $result['sponsored']);
    }

    /* ------------------------------ Analytics ----------------------------- */

    public function test_search_is_logged_for_analytics(): void
    {
        $this->service()->search('analytics-probe-query', ['per_page' => 5]);

        $this->assertDatabaseHas('search_queries', [
            'normalized' => 'analytics probe query',
            'surface' => SearchService::SURFACE_RESULTS,
        ]);
    }

    /* -------------------------------- Admin ------------------------------- */

    public function test_admin_search_page_requires_authentication(): void
    {
        $this->get(route('admin.search.index'))->assertRedirect();
    }

    public function test_admin_can_view_search_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.search.index'))
            ->assertOk()
            ->assertSee('Search Engine');
    }

    public function test_admin_can_reindex_and_manage_synonyms(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.search.reindex'))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.search.synonyms.store'), [
                'term' => 'mobile',
                'locale' => 'en',
                'synonyms' => 'phone, smartphone',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('search_synonyms', ['term' => 'mobile']);

        $synonym = SearchSynonym::where('term', 'mobile')->first();
        $this->assertContains('phone', $synonym->synonyms);
    }

    public function test_algorithm_dry_run_supports_search(): void
    {
        $algorithm = Algorithm::where('key', SearchService::ALGORITHM_KEY)->firstOrFail();

        $this->actingAs($this->admin())
            ->postJson(route('admin.algorithms.test', $algorithm), ['q' => 'a'])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
