<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorStaff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index()
    {
        $vendor = Auth::guard('vendor')->user();

        $staff = VendorStaff::where('vendor_id', $vendor->id)
            ->orderBy('id')
            ->get();

        return view('vendor.staff.index', compact('staff'));
    }

    public function store(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:150', Rule::unique('vendor_staff', 'email')],
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:manager,staff',
        ]);

        VendorStaff::create([
            'vendor_id' => $vendor->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'status' => 'active',
        ]);

        return redirect()->route('vendor.staff.index')->with('success', 'Staff member added.');
    }

    public function update(Request $request, VendorStaff $staff)
    {
        $this->authorizeStaff($staff);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'role' => 'required|in:owner,manager,staff',
            'status' => 'required|in:active,inactive',
            'password' => 'nullable|string|min:8',
        ]);

        $update = [
            'name' => $data['name'],
            'role' => $data['role'],
            'status' => $data['status'],
        ];

        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        $staff->update($update);

        return back()->with('success', 'Staff member updated.');
    }

    public function destroy(VendorStaff $staff)
    {
        abort_if((int) $staff->vendor_id !== (int) Auth::guard('vendor')->id(), 404);

        if ($staff->role === 'owner') {
            return back()->with('error', 'The account owner cannot be removed.');
        }

        $staff->delete();

        return back()->with('success', 'Staff member removed.');
    }

    protected function authorizeStaff(VendorStaff $staff): void
    {
        abort_if((int) $staff->vendor_id !== (int) Auth::guard('vendor')->id(), 404);
    }
}
