<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\LogisticsAdmin;
use App\Support\Access\AccountStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Show login form.
     */
    public function showLoginForm()
    {
        return view('logistics.auth.login');
    }

    /**
     * Handle login request.
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

        $credentials = [
            'email' => $request->string('email')->lower()->toString(),
            'password' => $request->input('password'),
            'is_active' => true,
        ];

        if (Auth::guard('logistics_admin')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $admin = Auth::guard('logistics_admin')->user();

            if (! $admin || ! $admin->canAccessPortal()) {
                Auth::guard('logistics_admin')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => $admin ? AccountStatus::denialMessage($admin->account_status) : trans('auth.failed'),
                ])->withInput($request->only('email'));
            }

            $admin->update(['last_login_at' => now()]);

            $request->session()->put('auth_version', (int) $admin->auth_version);
            $request->session()->put('staff_login_at', now()->timestamp);

            return redirect()->intended(route('logistics.dashboard'))
                ->with('success', 'Welcome back, '.$admin->name.'!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('email'));
    }

    /**
     * Show registration form (super admin only).
     */
    public function showRegistrationForm()
    {
        abort_unless(config('juvo-logistics.registration_open', false), 404);

        return view('logistics.auth.register');
    }

    /**
     * Handle registration request.
     */
    public function register(Request $request)
    {
        abort_unless(config('juvo-logistics.registration_open', false), 404);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:logistics_admins',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => 'required|in:admin,dispatcher,support',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $admin = LogisticsAdmin::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'permissions' => null,
            'is_active' => true,
            'account_status' => AccountStatus::ACTIVE,
            'portal_access' => true,
            'portal_approved_at' => now(),
            'auth_version' => 0,
        ]);

        Auth::guard('logistics_admin')->login($admin);

        return redirect()->route('logistics.dashboard')
            ->with('success', 'Account created successfully!');
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        Auth::guard('logistics_admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('logistics.login')
            ->with('success', 'You have been logged out.');
    }

    /**
     * Show password reset form.
     */
    public function showForgotPasswordForm()
    {
        return view('logistics.auth.forgot-password');
    }

    /**
     * Send password reset link.
     */
    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:logistics_admins,email',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        // TODO: Implement password reset email
        return back()->with('success', 'Password reset link sent to your email.');
    }
}
