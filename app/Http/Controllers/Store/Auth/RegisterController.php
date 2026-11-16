<?php

namespace App\Http\Controllers\Store\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Auth\VerificationCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('themes.xylo.auth.register');
    }

    public function register(Request $request, VerificationCodeService $verificationService)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:customers',
            'phone' => 'required|string|max:30',
            'national_id' => 'required|string|max:50',
            'address' => 'required|string|max:1000',
            'next_of_kin_name' => 'required|string|max:255',
            'next_of_kin_phone' => 'required|string|max:30',
            'password' => 'required|string|confirmed|min:8',
        ]);

        $customer = Customer::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'national_id' => $request->national_id,
            'address' => $request->address,
            'next_of_kin_name' => $request->next_of_kin_name,
            'next_of_kin_phone' => $request->next_of_kin_phone,
        ]);

        $verificationService->generateCodeFor($customer);

        Auth::guard('customer')->login($customer);

        return redirect()->route('customer.verification.show')
            ->with('success', 'Account created. Verify your email to continue shopping.');
    }
}
