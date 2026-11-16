<?php

namespace App\Support\Integrations;

/**
 * Google (Gmail) sign-in for shoppers.
 *
 * The OAuth credentials can live in the environment or in the admin
 * "Integrations" screen, where an administrator adds "Google Sign-In (Gmail)".
 */
class GoogleSignIn
{
    public const INTEGRATION = 'google';

    /**
     * @return array{client_id:?string, client_secret:?string, redirect:?string}
     */
    public static function credentials(): array
    {
        $config = IntegrationSettings::config(self::INTEGRATION);

        $redirect = $config['redirect_uri'] ?? config('services.google.redirect');

        if ($redirect === '/customer/auth/google/callback') {
            $redirect = null;
        }

        return [
            'client_id' => $config['client_id'] ?? config('services.google.client_id'),
            'client_secret' => $config['client_secret'] ?? config('services.google.client_secret'),
            'redirect' => $redirect ?: config('services.google.redirect'),
        ];
    }

    /**
     * Whether the sign-in button should be shown: the integration has to be
     * enabled and usable.
     */
    public static function isEnabled(): bool
    {
        $credentials = self::credentials();

        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            return false;
        }

        if (IntegrationSettings::exists(self::INTEGRATION)) {
            return IntegrationSettings::isEnabled(self::INTEGRATION);
        }

        return true;
    }

    /**
     * Push the stored credentials into the Socialite config so the provider
     * picks them up.
     */
    public static function applyToSocialite(): void
    {
        $credentials = self::credentials();

        config([
            'services.google.client_id' => $credentials['client_id'],
            'services.google.client_secret' => $credentials['client_secret'],
            'services.google.redirect' => $credentials['redirect'],
        ]);
    }
}
