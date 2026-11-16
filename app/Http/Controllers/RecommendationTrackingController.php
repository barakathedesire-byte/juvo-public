<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Intelligence\RecommendationEventService;
use Illuminate\Http\Request;

/**
 * Public, rate-limited intake for the recommendation/behaviour event stream.
 * The customer subject is always derived server-side from the session — never
 * trusted from the request body.
 */
class RecommendationTrackingController extends Controller
{
    public function store(Request $request, RecommendationEventService $service)
    {
        $payload = $request->input('events');
        $events = is_array($payload) ? $payload : [$request->all()];

        $customer = auth('customer')->user();
        $stored = 0;

        foreach ($events as $event) {
            $data = validator($event, [
                'event_uuid' => 'nullable|string|max:36',
                'type' => 'required|in:view,impression,click,add_to_cart,purchase',
                'surface' => 'required|string|max:64',
                'product_id' => 'nullable|integer',
                'position' => 'nullable|integer|min:0',
                'algorithm_key' => 'nullable|string|max:64',
                'algorithm_version' => 'nullable|integer|min:0',
                'order_id' => 'nullable|integer',
                'session_id' => 'nullable|string|max:64',
                'value_minor' => 'nullable|integer|min:0',
                'meta' => 'nullable|array',
            ])->validate();

            if ($customer) {
                $data['subject_type'] = $customer->getMorphClass();
                $data['subject_id'] = $customer->getKey();
            }

            try {
                $service->record($data);
                $stored++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true, 'stored' => $stored]);
    }
}
