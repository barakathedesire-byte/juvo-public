<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SeoSitemapTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sitemap_returns_valid_xml_with_core_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();
        $this->assertStringContainsString('<urlset', $body);
        $this->assertStringContainsString(url('/'), $body);
        $this->assertStringContainsString(route('shop.index'), $body);
    }

    public function test_sitemap_includes_visible_products_categories_and_published_pages(): void
    {
        $product = Product::visible()->first();
        $this->assertNotNull($product);
        $this->assertStringContainsString(route('product.show', $product->slug), (string) $this->get('/sitemap.xml')->getContent());

        $category = Category::whereHas('products', fn ($q) => $q->visible())->first();
        if ($category) {
            $this->assertStringContainsString(route('category.show', $category->slug), (string) $this->get('/sitemap.xml')->getContent());
        }

        $page = Page::create([
            'slug' => 'sitemap-'.uniqid(),
            'status' => 1,
            'page_type' => 'informational',
            'template' => 'content',
            'is_system' => false,
        ]);
        $page->translations()->create(['language_code' => 'en', 'title' => 'SEO Page', 'content' => '<p>seo</p>']);

        $body = (string) $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString(route('page.show', $page->slug), $body);
    }

    public function test_robots_txt_disallows_private_areas_and_links_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString('User-agent: *', $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Disallow: /checkout', $body);
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
    }
}
