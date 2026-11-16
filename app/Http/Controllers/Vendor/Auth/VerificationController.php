<?php

namespace App\Http\Controllers\Vendor\Auth;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\Auth\VerificationCodeService;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function show(Request $request)
    {
        $email = $request->query('email', session('verify_email'));
        $vendor = $email ? Vendor::where('email', $email)->first() : null;
        $debugCode = null;

        if ($vendor && $vendor->email_verified_at) {
            return redirect()->route('vendor.login')
                ->with('success', 'Your email address is already verified. You can sign in.');
        }

        if (app()->environment('local', 'testing') && $vendor) {
            $debugCode = $vendor->verification_code;
        }

        return view('vendor.auth.verify', [
            'email' => $email,
            'sent' => session('verify_sent'),
            'debugCode' => $debugCode,
        ]);
    }

    public function verify(Request $request, VerificationCodeService $service)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $vendor = Vendor::where('email', $request->email)->first();

        if (! $vendor) {
            return back()->withErrors(['email' => 'No vendor account found for this email.'])->withInput();
        }

        if ($service->verify($vendor, $request->code)) {
            return redirect()->route('vendor.pending')->with('success', 'Email verified. Your application is now under review.');
        }

        return back()->withErrors(['code' => 'That code is incorrect or has already been used.'])->withInput();
    }

    public function resend(Request $request, VerificationCodeService $service)
    {
        $request->validate(['email' => 'required|email']);

        $vendor = Vendor::where('email', $request->email)->first();

        if (! $vendor) {
            return back()->withErrors(['email' => 'No vendor account found for this email.'])->withInput();
        }

        if ($vendor->email_verified_at) {
            return back()->with('success', 'Your email address is already verified. You can sign in.');
        }

        $service->generateCodeFor($vendor);

        return back()->with('success', 'A new verification code has been sent to '.$vendor->email.'.');
    }
}
