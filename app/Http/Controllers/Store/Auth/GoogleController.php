<?php

namespace App\Http\Controllers\Store\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Integrations\GoogleSignIn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Send the buyer to Google's OAuth consent screen.
     */
    public function redirect()
    {
        if (! GoogleSignIn::isEnabled()) {
            return redirect()->route('customer.login')
                ->with('error', 'Google sign-in is not configured yet. Add it under Admin > Integrations.');
        }

        GoogleSignIn::applyToSocialite();

        try {
            return Socialite::driver('google')->redirect();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('customer.login')
                ->with('error', 'We could not reach Google. Please try again.');
        }
    }

    /**
     * Handle the callback from Google and sign the buyer in.
     */
    public function callback()
    {
        GoogleSignIn::applyToSocialite();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('customer.login')
                ->with('error', 'We could not complete Google sign-in. Please try again.');
        }

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        if (! $email) {
            return redirect()->route('customer.login')
                ->with('error', 'Google did not share an email address for this account.');
        }

        // Link by stable Google id first, then fall back to email so an
        // existing email/password buyer is reused rather than duplicated.
        $customer = $googleId ? Customer::where('google_id', $googleId)->first() : null;
        $customer = $customer ?: Customer::where('email', $email)->first();

        if ($customer) {
            if ($googleId && ! $customer->google_id) {
                $customer->update(['google_id' => $googleId]);
            }
        } else {
            $customer = Customer::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Juvo Customer',
                'email' => $email,
                'google_id' => $googleId,
                'password' => Hash::make(Str::random(32)),
                'status' => 'active',
            ]);
        }

        Auth::guard('customer')->login($customer, true);

        return redirect()->route('xylo.home')->with('success', 'Signed in with Google. Welcome to Juvo!');
    }
}
