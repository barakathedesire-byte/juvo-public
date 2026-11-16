<?php

namespace Tests\Feature;

use App\Models\HomepageSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class HomepageFooterManagementTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        HomepageSetting::whereIn('key', [
            'footer_description', 'footer_social_links', 'footer_newsletter',
            'footer_columns', 'footer_copyright', 'footer_note',
        ])->delete();
    }

    public function test_admin_footer_lists_every_storefront_footer_link(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/homepage/footer')
            ->assertOk()
            ->getContent();

        foreach ([
            'Brand column', 'Social links', 'Show the newsletter signup form', 'Footer columns', 'Bottom bar',
            'Facebook', 'fab fa-facebook-f', 'Get deals in your inbox',
            'Shop', 'All Products', 'Account', 'Create account', 'Vendors', 'Sell on Juvo',
            'Deliver with Juvo', 'Join JTeam', 'Vendor sign in', 'Help Center', 'Shipping',
            'Returns', 'Company', 'Our Company', 'About Us', 'Terms', 'Privacy',
            'Powered by Juvo Marketplace', 'Load storefront defaults',
        ] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_admin_can_manage_the_whole_footer_and_storefront_uses_it(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/footer', [
            'description' => 'Custom footer description.',
            'social_links' => [
                ['label' => 'TikTok', 'icon' => 'fab fa-tiktok', 'url' => 'https://tiktok.com/@juvo'],
            ],
            'newsletter_enabled' => '1',
            'newsletter_label' => 'Weekly deals',
            'newsletter_placeholder' => 'Email address',
            'newsletter_button' => 'Join list',
            'columns' => [
                [
                    'title' => 'Custom Help',
                    'links' => [
                        ['label' => 'Custom Link', 'url' => '/custom-page'],
                    ],
                ],
            ],
            'copyright' => '© {year} {site}. Custom rights.',
            'note' => 'Custom footer note',
        ])->assertRedirect();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Custom footer description.', $html);
        $this->assertStringContainsString('aria-label="TikTok"', $html);
        $this->assertStringContainsString('Weekly deals', $html);
        $this->assertStringContainsString('Email address', $html);
        $this->assertStringContainsString('Join list', $html);
        $this->assertStringContainsString('Custom Help', $html);
        $this->assertStringContainsString('href="/custom-page"', $html);
        $this->assertStringContainsString('Custom rights.', $html);
        $this->assertStringContainsString('Custom footer note', $html);
    }

    public function test_newsletter_and_social_links_can_be_hidden(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/footer', [
            'description' => 'No socials here.',
            'social_links' => [],
            'newsletter_enabled' => '0',
            'columns' => [],
            'copyright' => '© {year} {site}',
            'note' => '',
        ])->assertRedirect();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="newsletterForm"', $html);
        $this->assertStringNotContainsString('jv-social-ico', $html);
        $this->assertStringContainsString('No socials here.', $html);
    }
}
