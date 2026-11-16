<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;

class NewsletterSubscriberController extends Controller
{
    public function index()
    {
        $subscribers = NewsletterSubscriber::orderByDesc('id')->paginate(50);

        return view('admin.newsletter.index', [
            'subscribers' => $subscribers,
            'total' => NewsletterSubscriber::count(),
            'activeCount' => NewsletterSubscriber::where('is_active', true)->count(),
        ]);
    }

    public function toggle(NewsletterSubscriber $subscriber)
    {
        $subscriber->update(['is_active' => ! $subscriber->is_active]);

        return back()->with('success', 'Subscriber updated.');
    }

    public function destroy(NewsletterSubscriber $subscriber)
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber removed.');
    }

    public function export()
    {
        $rows = NewsletterSubscriber::where('is_active', true)->orderBy('id')->get(['email', 'source', 'subscribed_at']);

        $csv = "email,source,subscribed_at\n";
        foreach ($rows as $row) {
            $csv .= sprintf("%s,%s,%s\n", $row->email, $row->source, optional($row->subscribed_at)->toDateTimeString());
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="newsletter-subscribers.csv"',
        ]);
    }
}
