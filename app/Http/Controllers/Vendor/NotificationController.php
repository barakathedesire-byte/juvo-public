<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $vendor = Auth::guard('vendor')->user();

        $notifications = $vendor->notifications()->paginate(20);

        return view('vendor.notifications.index', compact('notifications'));
    }

    public function markRead(string $id)
    {
        $vendor = Auth::guard('vendor')->user();
        $notification = $vendor->notifications()->whereKey($id)->first();
        $notification?->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead()
    {
        Auth::guard('vendor')->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
