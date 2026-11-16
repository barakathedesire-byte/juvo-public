<?php

namespace App\Services\Auth;

use App\Mail\VerificationMail;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VerificationCodeService
{
    public function generateCodeFor(Customer|Vendor $account): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $account->forceFill([
            'verification_code' => $code,
            'email_verified_at' => $account->email_verified_at,
        ])->save();

        $this->sendCode($account, $code);

        return $code;
    }

    public function sendCode(Customer|Vendor $account, string $code): bool
    {
        try {
            Mail::to($account->email)->send(new VerificationMail($code, $account->name ?? ''));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Verification email could not be sent: '.$e->getMessage());

            return false;
        }
    }

    public function verify(Customer|Vendor $account, string $code): bool
    {
        if (! $account->verification_code) {
            return false;
        }

        if (! hash_equals((string) $account->verification_code, trim($code))) {
            return false;
        }

        $account->forceFill([
            'email_verified_at' => now(),
            'verification_code' => null,
        ])->save();

        return true;
    }

    public function isVerified(Customer|Vendor $account): bool
    {
        return (bool) $account->email_verified_at;
    }
}
