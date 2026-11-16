<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:190',
        ]);

        $subscriber = NewsletterSubscriber::updateOrCreate(
            ['email' => $data['email']],
            [
                'is_active' => true,
                'source' => $request->input('source', 'footer'),
                'subscribed_at' => now(),
            ]
        );

        $message = $subscriber->wasRecentlyCreated
            ? 'Thanks for subscribing!'
            : 'You are already subscribed.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
