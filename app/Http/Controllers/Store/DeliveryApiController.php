<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Delivery\DeliveryCalculator;
use Illuminate\Http\Request;

class DeliveryApiController extends Controller
{
    public function __construct(protected DeliveryCalculator $calculator) {}

    public function quote(Request $request)
    {
        $region = $request->input('region');
        $location = $request->input('location');
        $subtotal = (float) $request->input('subtotal', 0);

        if (!$region || !$location) {
            return response()->json(['error' => 'region and location required'], 422);
        }

        $door = $this->calculator->calculateDoorFee($region, $location, $subtotal);
        $pickup = $this->calculator->calculatePickupFee($region, $location, $subtotal);

        return response()->json([
            'door' => $door,
            'pickup' => $pickup,
        ]);
    }
}
