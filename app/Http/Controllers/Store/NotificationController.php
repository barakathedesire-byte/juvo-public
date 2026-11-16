<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request)
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notifications = $this->notifications->getRecentForCustomer($customer, 30);
        $unreadCount = $this->notifications->getUnreadCount($customer);

        return response()->json([
            'notifications' => $notifications->map(fn ($n) => $this->format($n)),
            'unread_count' => $unreadCount,
        ]);
    }

    public function unreadCount(Request $request)
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            return response()->json(['count' => 0]);
        }

        return response()->json(['count' => $this->notifications->getUnreadCount($customer)]);
    }

    public function markAsRead(Request $request)
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $ids = $request->input('ids', []);

        if (empty($ids)) {
            $count = $this->notifications->markAllAsReadForCustomer($customer);

            return response()->json(['marked' => $count]);
        }

        $notifications = Notification::forUser($customer->id, 'customer')
            ->whereIn('id', $ids)
            ->get();

        $count = $this->notifications->markAsRead($notifications);

        return response()->json(['marked' => $count]);
    }

    protected function format(Notification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->data['title'] ?? '',
            'message' => $n->data['message'] ?? '',
            'url' => $n->data['url'] ?? null,
            'read_at' => $n->read_at?->toISOString(),
            'created_at' => $n->created_at?->toISOString(),
        ];
    }
}
