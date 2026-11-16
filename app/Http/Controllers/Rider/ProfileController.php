<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Rider\Rider;
use App\Support\Rider\RiderBuilderContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function index()
    {
        $rider = Auth::guard('rider')->user();

        return view('rider.profile', [
            'rider' => $rider,
            'notifications' => $rider->notifications()->latest()->limit(8)->get(),
            'unreadCount' => $rider->unreadNotifications()->count(),
            'supportPhone' => RiderBuilderContent::settings()['support_phone'] ?: (getWebConfig('phone_number') ?: getWebConfig('contact_phone')),
            'supportEmail' => RiderBuilderContent::settings()['support_email'] ?: getWebConfig('email'),
        ]);
    }

    public function update(Request $request)
    {
        $rider = Auth::guard('rider')->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:riders,email,'.$rider->id],
            'phone' => ['required', 'string', 'max:20', 'unique:riders,phone,'.$rider->id],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
        ]);

        $rider->fill($validated)->save();

        return $this->respond($request, 'Profile updated successfully.', $rider);
    }

    public function photo(Request $request)
    {
        $rider = Auth::guard('rider')->user();

        $validated = $request->validate([
            'profile_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($rider->profile_image && Storage::disk('public')->exists($rider->profile_image)) {
            Storage::disk('public')->delete($rider->profile_image);
        }

        $path = $request->file('profile_image')->store('riders/profiles', 'public');
        $rider->profile_image = $path;
        $rider->save();

        return $this->respond($request, 'Profile photo updated.', $rider);
    }

    public function password(Request $request)
    {
        $rider = Auth::guard('rider')->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $rider->password)) {
            return $this->validationError($request, ['current_password' => ['The current password is incorrect.']]);
        }

        $rider->password = $validated['password'];
        $rider->save();

        return $this->respond($request, 'Password changed successfully.', $rider);
    }

    public function document(Request $request)
    {
        $rider = Auth::guard('rider')->user();

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:'.implode(',', Rider::KYC_DOCUMENT_TYPES ? array_keys(Rider::KYC_DOCUMENT_TYPES) : [])],
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $documentType = $validated['document_type'];
        $documents = $rider->kyc_documents ?? [];
        $directory = 'riders/kyc/'.($rider->national_id ?: $rider->id);

        if (isset($documents[$documentType]) && Storage::disk('private')->exists($documents[$documentType])) {
            Storage::disk('private')->delete($documents[$documentType]);
        }

        $path = $request->file('document')->store($directory, 'private');
        $documents[$documentType] = $path;

        $rider->kyc_documents = $documents;
        $rider->kyc_submitted_at = now();

        if ($rider->kyc_status === 'rejected') {
            $rider->kyc_status = 'under_review';
            $rider->kyc_rejection_reason = null;
        }

        $rider->save();

        return $this->respond($request, 'Document uploaded for review.', $rider);
    }

    public function toggleAvailability(Request $request)
    {
        $rider = Auth::guard('rider')->user();

        if ($reason = $rider->eligibilityReason()) {
            return response()->json([
                'success' => false,
                'is_online' => $rider->is_online,
                'message' => $reason,
            ], 403);
        }

        $rider->update([
            'is_online' => ! $rider->is_online,
            'last_online_at' => now(),
        ]);

        return $this->respond($request, $rider->is_online ? 'You are now online.' : 'You are now offline.', $rider);
    }

    private function respond(Request $request, string $message, Rider $rider)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'profile_image_url' => $rider->profile_image ? asset('storage/'.$rider->profile_image) : null,
                'is_online' => $rider->is_online,
            ]);
        }

        return back()->with('success', $message);
    }

    private function validationError(Request $request, array $errors)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'errors' => $errors,
            ], 422);
        }

        return back()->withErrors($errors)->withInput();
    }
}
