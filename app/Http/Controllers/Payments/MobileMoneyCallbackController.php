<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\MobileMoneyCallbackService;
use Illuminate\Http\Request;

/**
 * Receives the mobile money aggregator callback.
 *
 * The route is public (the aggregator is not a Juvo user) and excluded from
 * CSRF verification; the request is instead authenticated with the
 * aggregator's HMAC signature.
 */
class MobileMoneyCallbackController extends Controller
{
    public function __construct(protected MobileMoneyCallbackService $callbacks) {}

    public function handle(Request $request)
    {
        $header = (string) config('mobile_money.aggregator.callback_signature_header', 'X-Signature');

        $result = $this->callbacks->handle(
            $request->getContent(),
            $request->all(),
            $request->header($header)
        );

        if (! $result['ok']) {
            $status = $result['status'] === 'rejected' && str_contains($result['message'], 'signature')
                ? 403
                : 422;

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $status);
        }

        return response()->json([
            'success' => true,
            'status' => $result['status'],
            'message' => $result['message'],
            'order_ids' => $result['order_ids'],
            'campaign_id' => $result['campaign_id'],
        ]);
    }
}
