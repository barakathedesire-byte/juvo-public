<?php

namespace App\Support\Homepage;

use App\Models\HomepageSetting;

class FooterContent
{
    /**
     * The footer as it ships with the storefront. Both the admin editor and the
     * public footer read from here so newly added links appear in both places.
     */
    public static function defaults(): array
    {
        return [
            'description' => 'Juvo is a multi-vendor marketplace connecting buyers with trusted sellers — fast delivery, secure payments, easy returns.',
            'social_links' => [
                ['label' => 'Facebook', 'icon' => 'fab fa-facebook-f', 'url' => '#'],
                ['label' => 'X', 'icon' => 'fab fa-x-twitter', 'url' => '#'],
                ['label' => 'Instagram', 'icon' => 'fab fa-instagram', 'url' => '#'],
                ['label' => 'LinkedIn', 'icon' => 'fab fa-linkedin-in', 'url' => '#'],
            ],
            'newsletter' => [
                'enabled' => true,
                'label' => 'Get deals in your inbox',
                'placeholder' => 'Your email',
                'button' => 'Subscribe',
            ],
            'columns' => [
                [
                    'title' => 'Shop',
                    'links' => [
                        ['label' => 'All Products', 'url' => route('shop.index')],
                        ['label' => 'Cart', 'url' => route('cart.view')],
                        ['label' => 'Wishlist', 'url' => route('customer.login')],
                    ],
                ],
                [
                    'title' => 'Account',
                    'links' => [
                        ['label' => 'Sign in', 'url' => route('customer.login')],
                        ['label' => 'Create account', 'url' => route('customer.register')],
                        ['label' => 'My profile', 'url' => route('customer.profile.edit')],
                    ],
                ],
                [
                    'title' => 'Vendors',
                    'links' => [
                        ['label' => 'Sell on Juvo', 'url' => route('join.sell')],
                        ['label' => 'Deliver with Juvo', 'url' => route('join.deliver')],
                        ['label' => 'Join JTeam', 'url' => route('join.jteam')],
                        ['label' => 'Vendor sign in', 'url' => route('vendor.login')],
                    ],
                ],
                [
                    'title' => 'Help',
                    'links' => [
                        ['label' => 'Help Center', 'url' => route('help.index')],
                        ['label' => 'Shipping', 'url' => route('page.show', 'shipping-delivery')],
                        ['label' => 'Returns', 'url' => route('page.show', 'return-policy')],
                        ['label' => 'Contact', 'url' => route('page.show', 'contact-us')],
                    ],
                ],
                [
                    'title' => 'Company',
                    'links' => [
                        ['label' => 'Our Company', 'url' => route('company')],
                        ['label' => 'About Us', 'url' => route('page.show', 'about-us')],
                        ['label' => 'Terms', 'url' => route('page.show', 'terms-conditions')],
                        ['label' => 'Privacy', 'url' => route('page.show', 'privacy-policy')],
                        ['label' => 'Contact', 'url' => route('page.show', 'contact-us')],
                    ],
                ],
            ],
            'note' => 'Powered by Juvo Marketplace',
            'copyright' => '© {year} {site}. All rights reserved.',
        ];
    }

    public static function settings(): array
    {
        $defaults = self::defaults();
        $savedNewsletter = HomepageSetting::json('footer_newsletter', $defaults['newsletter']);

        return [
            'description' => HomepageSetting::get('footer_description', $defaults['description']),
            'social_links' => HomepageSetting::json('footer_social_links', $defaults['social_links']),
            'newsletter' => array_merge($defaults['newsletter'], is_array($savedNewsletter) ? $savedNewsletter : []),
            'columns' => HomepageSetting::json('footer_columns', $defaults['columns']),
            'note' => HomepageSetting::get('footer_note', $defaults['note']),
            'copyright' => HomepageSetting::get('footer_copyright', $defaults['copyright']),
        ];
    }

    public static function forDisplay(): array
    {
        $defaults = self::defaults();
        $settings = self::settings();

        if (trim((string) $settings['copyright']) === '') {
            $settings['copyright'] = $defaults['copyright'];
        }

        $settings['copyright'] = str_replace(
            ['{year}', '{site}'],
            [date('Y'), config('app.name', 'Juvo')],
            $settings['copyright']
        );

        if (trim((string) $settings['note']) === '') {
            $settings['note'] = $defaults['note'];
        }

        return $settings;
    }
}
