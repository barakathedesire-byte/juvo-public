<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Rider\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Services\Kyc\KycVerificationService;

class AuthController extends Controller
{
    /**
     * Show rider login form.
     */
    public function showLoginForm()
    {
        return view('rider.auth.login');
    }

    /**
     * Handle rider login.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->only('email'));
        }

        $credentials = $request->only('email', 'password');

        if (Auth::guard('rider')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $rider = Auth::guard('rider')->user();

            if (! $rider->is_active) {
                Auth::guard('rider')->logout();

                return back()->withErrors([
                    'email' => 'Your account has been deactivated. Please contact support.',
                ]);
            }

            if (! $rider->isKycApproved()) {
                return redirect()->route('rider.kyc.status')
                    ->with('warning', 'Please complete your KYC verification to access the dashboard.');
            }

            $rider->update([
                'is_online' => true,
                'last_online_at' => now(),
            ]);

            return redirect()->intended(route('rider.dashboard'))
                ->with('success', 'Welcome back, '.$rider->first_name.'!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('email'));
    }

    /**
     * Show rider registration form.
     */
    public function showRegistrationForm()
    {
        return view('rider.auth.register');
    }

    /**
     * Handle rider registration with KYC.
     */
    public function register(Request $request, KycVerificationService $kycService)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:riders',
            'phone' => 'required|string|max:20|unique:riders',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'national_id' => 'required|string|max:20|unique:riders',
            'date_of_birth' => 'required|date|before:-18 years',
            'gender' => 'required|in:male,female,other',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:10',

            // Vehicle
            'vehicle_type' => 'required|in:motorcycle,bicycle,van,truck',
            'vehicle_make' => 'required|string|max:100',
            'vehicle_model' => 'required|string|max:100',
            'vehicle_year' => 'required|integer|min:2000|max:'.(date('Y') + 1),
            'vehicle_color' => 'required|string|max:50',
            'license_plate' => 'required|string|max:20|unique:riders',

            // License
            'license_number' => 'required|string|max:50|unique:riders',
            'license_expiry' => 'required|date|after:today',

            // Banking
            'bank_name' => 'required|string|max:100',
            'bank_account_name' => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:30',
            'bank_branch' => 'required|string|max:100',

            // Emergency contact
            'emergency_contact_name' => 'required|string|max:100',
            'emergency_contact_phone' => 'required|string|max:20',
            'emergency_contact_relationship' => 'required|string|max:50',

            // KYC Documents
            'kyc_documents.national_id_front' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'kyc_documents.national_id_back' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'kyc_documents.driving_license' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'kyc_documents.vehicle_registration' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'kyc_documents.vehicle_insurance' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'kyc_documents.passport_photo' => 'required|file|mimes:jpg,jpeg,png|max:2048',
            'kyc_documents.proof_of_address' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'kyc_documents.bank_statement' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'kyc_documents.background_check' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'date_of_birth.before' => 'You must be at least 18 years old to register as a rider.',
            'license_expiry.after' => 'Your driving license must not be expired.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store KYC documents
        $kycDocuments = [];
        $documentTypes = [
            'national_id_front', 'national_id_back', 'driving_license',
            'vehicle_registration', 'vehicle_insurance', 'passport_photo',
            'proof_of_address', 'bank_statement', 'background_check',
        ];

        foreach ($documentTypes as $type) {
            if ($request->hasFile("kyc_documents.$type")) {
                $path = $request->file("kyc_documents.$type")->store("riders/kyc/{$request->national_id}", 'private');
                $kycDocuments[$type] = $path;
            }
        }

        $rider = Rider::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'national_id' => $request->national_id,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'vehicle_type' => $request->vehicle_type,
            'vehicle_make' => $request->vehicle_make,
            'vehicle_model' => $request->vehicle_model,
            'vehicle_year' => $request->vehicle_year,
            'vehicle_color' => $request->vehicle_color,
            'license_plate' => $request->license_plate,
            'license_number' => $request->license_number,
            'license_expiry' => $request->license_expiry,
            'kyc_status' => 'under_review',
            'kyc_documents' => $kycDocuments,
            'kyc_submitted_at' => now(),
            'payout_method' => $request->payout_method,
            'bank_name' => $request->bank_name,
            'bank_account_name' => $request->bank_account_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_branch' => $request->bank_branch,
            'mobile_money_provider' => $request->mobile_money_provider,
            'mobile_money_number' => $request->mobile_money_number,
            'mobile_money_account_name' => $request->mobile_money_account_name,
            'emergency_contact_name' => $request->emergency_contact_name,
            'emergency_contact_phone' => $request->emergency_contact_phone,
            'emergency_contact_relationship' => $request->emergency_contact_relationship,
            'background_check_status' => 'pending',
            'is_active' => true,
        ]);

        $kycService->verifyRider($rider);

        Auth::guard('rider')->login($rider);

        return redirect()->route('rider.kyc.status')
            ->with('success', 'Registration successful! Your KYC documents are under review. You will be notified once approved.');
    }

    /**
     * Show KYC status page.
     */
    public function kycStatus()
    {
        $rider = Auth::guard('rider')->user();

        return view('rider.auth.kyc-status', compact('rider'));
    }

    /**
     * Show password reset form.
     */
    public function showForgotPasswordForm()
    {
        return view('rider.auth.forgot-password');
    }

    /**
     * Send password reset link.
     */
    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:riders,email',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        // TODO: Implement password reset email
        return back()->with('success', 'Password reset link sent to your email.');
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        $rider = Auth::guard('rider')->user();
        if ($rider) {
            $rider->update([
                'is_online' => false,
                'last_online_at' => now(),
            ]);
        }

        Auth::guard('rider')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('rider.login')
            ->with('success', 'You have been logged out.');
    }
}
