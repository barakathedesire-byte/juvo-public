<?php

namespace App\Http\Controllers\Store\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\VerificationCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController extends Controller
{
    public function show()
    {
        $customer = Auth::guard('customer')->user();

        if ($customer && $customer->email_verified_at) {
            return redirect()->route('xylo.home');
        }

        $debugCode = app()->environment('local', 'testing')
            ? $customer?->verification_code
            : null;

        return view('themes.xylo.verify-email', [
            'email' => $customer?->email,
            'debugCode' => $debugCode,
        ]);
    }

    public function verify(Request $request, VerificationCodeService $service)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $customer = Auth::guard('customer')->user();

        if ($service->verify($customer, $request->code)) {
            return redirect()->route('xylo.home')->with('success', 'Email verified successfully. Welcome!');
        }

        return back()->withErrors(['code' => 'That code is incorrect or has already been used.'])->withInput();
    }

    public function resend(VerificationCodeService $service)
    {
        $customer = Auth::guard('customer')->user();
        $service->generateCodeFor($customer);

        return back()->with('success', 'A new verification code has been sent to '.$customer->email.'.');
    }
}
