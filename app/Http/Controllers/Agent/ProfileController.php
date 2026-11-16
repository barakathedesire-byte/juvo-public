<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        return view('agent.profile', [
            'agent' => $request->attributes->get('agent'),
        ]);
    }

    public function update(Request $request)
    {
        $agent = $request->attributes->get('agent');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'payout_method' => ['nullable', 'string', 'max:50'],
            'payout_account_name' => ['nullable', 'string', 'max:150'],
            'payout_account_number' => ['nullable', 'string', 'max:150'],
        ]);

        $accountChanged = ! empty($data['payout_account_number'])
            && $data['payout_account_number'] !== $agent->payout_account_number;

        if (! $accountChanged) {
            unset($data['payout_account_number']);
        } elseif ($agent->payout_status === 'verified') {
            $data['payout_status'] = 'pending';
        }

        $agent->update($data);

        $message = $accountChanged
            ? 'Profile updated. Payout details changed and await re-verification.'
            : 'Profile updated.';

        return back()->with('success', $message);
    }
}
