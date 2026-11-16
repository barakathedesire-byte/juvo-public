<?php

namespace App\Http\Controllers\Store\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PaymentGateway\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class StripeController extends Controller
{
    public function checkout(Request $request)
    {
        $currency = strtolower(getWebConfig('default_currency', 'TZS'));
        $subtotal = $this->cartSubtotal();

        if ($subtotal <= 0) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        // Amount is server-computed and expressed in the currency's minor unit.
        $amount = (int) round($subtotal * 100);

        $stripe = PaymentManager::make('stripe', 'sandbox');
        $intent = $stripe->createPaymentIntent($amount, $currency);

        return response()->json([
            'publicKey' => $stripe->getPublicKey(),
            'clientSecret' => $intent->client_secret,
        ]);
    }

    protected function cartSubtotal(): float
    {
        $subtotal = 0.0;

        foreach (Session::get('cart', []) as $item) {
            $product = Product::visible()->find($item['product_id'] ?? null);

            if (! $product) {
                continue;
            }

            $variant = ! empty($item['variant_id'])
                ? ProductVariant::where('product_id', $product->id)->find($item['variant_id'])
                : ProductVariant::where('product_id', $product->id)->where('is_primary', true)->first();

            if (! $variant) {
                continue;
            }

            $subtotal += (float) ($variant->discount_price ?? $variant->price) * max(1, (int) ($item['quantity'] ?? 1));
        }

        return $subtotal;
    }
}
