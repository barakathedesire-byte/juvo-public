<?php

namespace App\Support\Integrations;

use App\Services\Payments\MobileMoney;

/**
 * The integrations an administrator can add from the admin "Integrations"
 * screen.
 *
 * Every entry describes how the integration is presented (name, category,
 * icon or logo) and which credentials it needs. The mobile money networks and
 * the aggregator are generated from the mobile money configuration so the
 * catalogue and the checkout can never drift apart.
 */
class IntegrationCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return array_merge(
            self::payments(),
            self::mobileMoney(),
            self::messaging(),
            self::analytics(),
            self::authentication(),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function payments(): array
    {
        return [
            'stripe' => [
                'name' => 'Stripe',
                'category' => 'Payments',
                'icon' => 'fab fa-stripe-s',
                'description' => 'Accept cards worldwide.',
                'fields' => ['publishable_key' => 'Publishable key', 'secret_key' => 'Secret key'],
            ],
            'paypal' => [
                'name' => 'PayPal',
                'category' => 'Payments',
                'icon' => 'fab fa-paypal',
                'description' => 'PayPal checkout.',
                'fields' => ['client_id' => 'Client ID', 'client_secret' => 'Client secret'],
            ],
            'flutterwave' => [
                'name' => 'Flutterwave',
                'category' => 'Payments',
                'icon' => 'fas fa-credit-card',
                'description' => 'African payments & collections.',
                'fields' => ['public_key' => 'Public key', 'secret_key' => 'Secret key'],
            ],
        ];
    }

    /**
     * The Tanzanian mobile money networks and the aggregator that collects the
     * money on their behalf.
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function mobileMoney(): array
    {
        $catalog = [];

        foreach (config('mobile_money.providers', []) as $code => $definition) {
            $definition = is_array($definition) ? $definition : [];

            $catalog[$code] = [
                'name' => $definition['label'] ?? ucfirst(str_replace('_', ' ', $code)),
                'category' => 'Mobile Money',
                'icon' => 'fas fa-mobile-alt',
                'logo' => $definition['logo'] ?? null,
                'description' => 'Collect payments on the '.($definition['label'] ?? $code).' network.',
                'defaults' => [
                    'ussd' => $definition['ussd'] ?? null,
                    'aggregator_ref' => $definition['aggregator_ref'] ?? $code,
                ],
                'fields' => [
                    'merchant_number' => 'Collection number (money is paid here)',
                    'ussd' => 'USSD shortcode',
                    'aggregator_ref' => 'Aggregator network reference',
                ],
            ];
        }

        $catalog[MobileMoney::AGGREGATOR_INTEGRATION] = [
            'name' => 'Mobile Money Aggregator',
            'category' => 'Mobile Money',
            'icon' => 'fas fa-money-bill-transfer',
            'description' => 'Collect mobile money automatically through an aggregator (Selcom, Azampay, Beem, Flutterwave, DPO).',
            'fields' => [
                'base_url' => 'API base URL',
                'api_key' => 'API key',
                'api_secret' => 'API secret',
                'vendor_id' => 'Vendor / merchant id',
                'callback_url' => 'Callback URL',
                'callback_secret' => 'Callback secret (HMAC)',
            ],
        ];

        return $catalog;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function messaging(): array
    {
        return [
            'twilio' => [
                'name' => 'Twilio SMS',
                'category' => 'Messaging',
                'icon' => 'fas fa-sms',
                'description' => 'Transactional SMS.',
                'fields' => ['account_sid' => 'Account SID', 'auth_token' => 'Auth token', 'from_number' => 'From number'],
            ],
            'mailgun' => [
                'name' => 'Mailgun',
                'category' => 'Email',
                'icon' => 'fas fa-envelope',
                'description' => 'Transactional email delivery.',
                'fields' => ['domain' => 'Domain', 'api_key' => 'API key'],
            ],
            'whatsapp' => [
                'name' => 'WhatsApp Business',
                'category' => 'Messaging',
                'icon' => 'fab fa-whatsapp',
                'description' => 'Order updates over WhatsApp.',
                'fields' => ['phone_id' => 'Phone number ID', 'token' => 'Access token'],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function analytics(): array
    {
        return [
            'google_analytics' => [
                'name' => 'Google Analytics 4',
                'category' => 'Analytics',
                'icon' => 'fas fa-chart-line',
                'description' => 'Traffic & conversion tracking.',
                'fields' => ['measurement_id' => 'Measurement ID'],
            ],
            'facebook_pixel' => [
                'name' => 'Facebook Pixel',
                'category' => 'Marketing',
                'icon' => 'fab fa-facebook',
                'description' => 'Ads conversion tracking.',
                'fields' => ['pixel_id' => 'Pixel ID'],
            ],
            'fleetbase' => [
                'name' => 'Fleetbase Logistics',
                'category' => 'Shipping',
                'icon' => 'fas fa-truck',
                'description' => 'Pickup & last-mile delivery.',
                'fields' => ['base_url' => 'Base URL', 'api_key' => 'API key'],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function authentication(): array
    {
        return [
            'google' => [
                'name' => 'Google Sign-In (Gmail)',
                'category' => 'Authentication',
                'icon' => 'fab fa-google',
                'description' => 'Let shoppers sign in and create an account with their Gmail address.',
                'fields' => [
                    'client_id' => 'Client ID',
                    'client_secret' => 'Client secret',
                    'redirect_uri' => 'Redirect URI',
                ],
            ],
        ];
    }
}
