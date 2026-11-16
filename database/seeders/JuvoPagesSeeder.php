<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Seeder;

class JuvoPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'about-us' => [
                'title' => 'About Us',
                'content' => '<p>Juvo is a modern online marketplace connecting buyers with trusted vendors. We bring together thousands of products across electronics, fashion, home, and more — delivered fast to your doorstep.</p><p>Our mission is to make shopping simple, secure, and affordable for everyone.</p>',
            ],
            'terms-conditions' => [
                'title' => 'Terms & Conditions',
                'content' => '<p>By using Juvo you agree to these terms. Please read them carefully. Orders, payments, and deliveries are subject to the policies described here.</p><ul><li>Prices are displayed in the active store currency.</li><li>Orders require a verified account.</li><li>Vendors are responsible for the products they list.</li></ul>',
            ],
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'content' => '<p>We respect your privacy. This policy explains what data we collect, how we use it, and the choices you have.</p><p>We collect account details, order history, and usage data to operate and improve the marketplace. We never sell your personal data.</p>',
            ],
            'return-policy' => [
                'title' => 'Return & Refund Policy',
                'content' => '<p>Not satisfied with your purchase? You may request a return within the eligible window shown on the product page. Refunds are issued to the original payment method after inspection.</p>',
            ],
            'shipping-delivery' => [
                'title' => 'Shipping & Delivery',
                'content' => '<p>We deliver across Tanzania. Delivery times vary by region — typically 1 to 3 days.</p><p>Free delivery applies to eligible orders above the free-shipping threshold shown at checkout.</p>',
            ],
            'faq' => [
                'title' => 'Frequently Asked Questions',
                'content' => '<p><strong>How do I track my order?</strong> Open your account dashboard and view your orders.</p><p><strong>How do I become a vendor?</strong> Register a vendor account and complete verification.</p><p><strong>What payment methods are available?</strong> See the options shown at checkout.</p>',
            ],
            'contact-us' => [
                'title' => 'Contact Us',
                'content' => '<p>We would love to hear from you. Reach our support team any time and we will get back to you as soon as possible.</p>',
            ],
        ];

        foreach ($pages as $slug => $data) {
            $page = Page::updateOrCreate(
                ['slug' => $slug],
                ['status' => 1]
            );

            PageTranslation::updateOrCreate(
                ['page_id' => $page->id, 'language_code' => 'en'],
                ['title' => $data['title'], 'content' => $data['content']]
            );
        }
    }
}
