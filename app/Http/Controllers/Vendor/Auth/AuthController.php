<?php

namespace App\Http\Controllers\Vendor\Auth;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorVerificationDocument;
use App\Services\Auth\VerificationCodeService;
use App\Services\Intelligence\WorkflowDispatcher;
use App\Services\Kyc\KycVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('vendor.auth.login');
    }

    public function showRegisterForm()
    {
        return view('vendor.auth.register');
    }

    public function register(Request $request, VerificationCodeService $verificationService, KycVerificationService $kycService)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'shop_name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:vendors',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'tin_number' => 'required|string|max:64',
            'registration_number' => 'required|string|max:64',
            'business_documents' => 'required|array|min:1',
            'business_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
            'password' => 'required|string|confirmed|min:8',
        ]);

        $documents = [];
        if ($request->hasFile('business_documents')) {
            foreach ($request->file('business_documents') as $file) {
                $documents[] = $file->store('vendor_documents', 'local');
            }
        }

        $vendor = Vendor::create([
            'name' => $request->name,
            'shop_name' => $request->shop_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'tin_number' => $request->tin_number,
            'registration_number' => $request->registration_number,
            'business_documents' => $documents,
            'password' => Hash::make($request->password),
            'status' => 'inactive',
            'is_approved' => false,
        ]);

        foreach ($documents as $index => $path) {
            $document = VendorVerificationDocument::create([
                'vendor_id' => $vendor->id,
                'type' => 'business_document',
                'title' => basename($path),
                'file_path' => $path,
                'disk' => 'local',
                'status' => 'pending',
            ]);

            $kycService->verifyVendorDocument($document);
        }

        $verificationService->generateCodeFor($vendor);

        app(WorkflowDispatcher::class)->dispatchTrigger('vendor_signup', [
            'vendor_id' => $vendor->id,
        ], $vendor->id);

        return redirect()->route('vendor.verification.show')
            ->with('verify_email', $vendor->email)
            ->with('success', 'Application received. Verify your email address to continue.');
    }

    public function pending()
    {
        return view('vendor.auth.pending');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:5',
        ]);

        if (! Auth::guard('vendor')->attempt($request->only('email', 'password'))) {
            return back()->with('error', 'Invalid credentials');
        }

        /** @var Vendor $vendor */
        $vendor = Auth::guard('vendor')->user();

        if (! $vendor->email_verified_at && $vendor->verification_code) {
            Auth::guard('vendor')->logout();

            return redirect()->route('vendor.verification.show')
                ->with('verify_email', $vendor->email)
                ->with('error', 'Please verify your business email before signing in.');
        }

        if (! $vendor->isApproved()) {
            Auth::guard('vendor')->logout();

            return back()->with('error', 'Your store is pending admin approval. You will be able to sign in once it is approved.');
        }

        return redirect()->route('vendor.dashboard');
    }

    public function logout()
    {
        Auth::guard('vendor')->logout();

        return redirect()->route('vendor.login');
    }

    public function dashboard()
    {
        return view('vendor.dashboard');
    }
}
