# Mobile money

Juvo supports mobile-money payments for shopper checkout and for vendor ad
campaigns. The shopper chooses their network, and the payment aggregator pushes
a USSD/STK prompt to their phone. **The PIN is entered on the handset — Juvo
never sees or stores it.**

## What is in place

- A provider catalogue (`config/mobile_money.php`) with the Tanzanian networks:
  M-Pesa (Vodacom), Mixx by Yas (Tigo), Airtel Money, HaloPesa, T-Pesa.
- A reusable selector partial (`resources/views/partials/mobile-money-selector.blade.php`)
  rendered on:
  - storefront checkout — `resources/views/themes/xylo/checkout.blade.php`
  - vendor ad campaigns — `resources/views/vendor/ads/index.blade.php`
- Server-side validation and the aggregator hand-off in
  `app/Services/Payments/MobileMoney.php`.
- A `mobilemoney` row in `payment_gateways` (created by a migration), so the
  method is a first-class gateway and not just a special case in the checkout.
- A callback endpoint (`POST /payments/mobile-money/callback`) that verifies the
  aggregator signature and settles the order or campaign.

## When the aggregator is not configured

`MobileMoney::isConfigured()` is false until `MOBILE_MONEY_BASE_URL` and
`MOBILE_MONEY_API_KEY` are set:

- **Checkout** does not offer mobile money at all, and a request that slips
  through anyway is rejected before an order is created. Nothing is ever marked
  paid without a collection.
- **Ad campaigns** keep the mobile money option, because there the vendor
  declares how the campaign will be paid and an admin verifies the transfer
  manually (the campaign stays `awaiting_payment` until then).

## Provider list

Edit `config/mobile_money.php` to add or remove a network:

```php
'mpesa' => [
    'label' => 'M-Pesa (Vodacom)',
    'msisdn_prefixes' => ['74', '75', '76'],   // used to validate the number
    'aggregator_ref' => env('MOBILE_MONEY_REF_MPESA', 'M-PESA'),
    'enabled' => true,
],
```

Set `enabled` to `false` to hide a network without deleting it.

## Connecting an aggregator

Juvo talks to one aggregator (Selcom, Azampay, Beem, Flutterwave, DPO, ...),
not to each network directly. Add these to the production `.env`:

```
MOBILE_MONEY_DRIVER=generic
MOBILE_MONEY_BASE_URL=https://api.your-aggregator.example
MOBILE_MONEY_API_KEY=your-api-key
MOBILE_MONEY_API_SECRET=your-api-secret
MOBILE_MONEY_VENDOR_ID=your-vendor-id
MOBILE_MONEY_CALLBACK_URL=${APP_URL}/payments/mobile-money/callback
MOBILE_MONEY_CALLBACK_SECRET=your-callback-secret
MOBILE_MONEY_SIGNATURE_HEADER=X-Signature

MOBILE_MONEY_REF_MPESA=M-PESA
MOBILE_MONEY_REF_MIXX=TIGO-PESA
MOBILE_MONEY_REF_AIRTEL=AIRTEL
MOBILE_MONEY_REF_HALOPESA=HALOPESA
MOBILE_MONEY_REF_TPESA=T-PESA
```

Then run `php artisan config:clear` (or your normal deploy, which does
`optimize:clear`).

The request payload posted to `<base_url>/collections` is:

```json
{
  "vendor": "<MOBILE_MONEY_VENDOR_ID>",
  "amount": 25000,
  "currency": "TZS",
  "msisdn": "0754123456",
  "network": "M-PESA",
  "reference": "ORDER-1234",
  "callback_url": "${APP_URL}/payments/mobile-money/callback"
}
```

Adjust the field names in `MobileMoney::requestPayment()` if your aggregator
uses different ones (for example `phone` instead of `msisdn`, or `Bearer`
auth instead of a query key).

## The checkout flow

1. The shopper picks a network and types their number. Both are validated
   server-side against the network's prefixes (`+2557…`, `2557…` and `07…` are
   all accepted and stored as `07…`).
2. The order is created first, so a charge can never be taken without an
   order behind it.
3. The charge is handed to the aggregator with `reference = ORDER-<order id>`.
   The order total of *all* vendors in the cart is charged in one collection.
4. If the aggregator rejects the request, the order is canceled (which
   restocks the items and reverses the earnings) and the payment is marked
   failed — the shopper is asked to pick another method.
5. The details of the hand-off (network, number, aggregator reference, status)
   are stored on the payment row in `payments.meta.mobile_money`.

## Ad campaigns

A vendor never declares a campaign as paid. The campaign is created with
`payment_method = mobilemoney`, the network and number are stored in
`ad_campaigns.payment_meta`, and the campaign stays `awaiting_payment`:

- With a live aggregator the collection is started right away with
  `reference = AD-<campaign id>`; the callback settles the campaign and it is
  approved automatically.
- Without an aggregator the details are kept for an admin to reconcile, and
  the admin verifies the payment from
  *Ad Manager → Payments* (`Admin\AdManager\PaymentController::verify`).

## The callback

The aggregator notifies Juvo of the final result (paid / failed / cancelled)
at `MOBILE_MONEY_CALLBACK_URL`.

The request is authenticated with an HMAC-SHA256 signature of the raw body,
sent in the `MOBILE_MONEY_SIGNATURE_HEADER` header (hex or base64, with an
optional `sha256=` prefix). The secret is `MOBILE_MONEY_CALLBACK_SECRET`, or
`MOBILE_MONEY_API_SECRET` when that is not set. A request without a valid
signature is rejected with `403`; the callback route is excluded from CSRF
verification.

The expected payload is:

```json
{
  "status": "success",
  "merchant_reference": "ORDER-1234",
  "transaction_id": "TX-1",
  "amount": 25000,
  "currency": "TZS",
  "msisdn": "0754123456"
}
```

`merchant_reference` is the reference Juvo sent. If your aggregator echoes it
under a different name, add that name to `REFERENCE_KEYS` in
`app/Services/Payments/MobileMoneyCallbackService.php`. `status` accepts
`success`/`completed`/`paid`/`settled`/`confirmed` for a paid collection and
`failed`/`rejected`/`declined`/`cancelled`/`expired` for a failed one.

The callback is idempotent: a repeated callback never settles a collection
twice.

## Testing locally

Set the aggregator variables in your local `.env`, or leave them empty and the
selector validates the network/phone number but declines to initiate a charge.
The number is validated as a Tanzanian MSISDN and checked against the chosen
network's prefixes; `+2557…`, `2557…` and `07…` are all accepted and stored as
`07…`.

`tests/Feature/MobileMoneyTest.php` covers the flow end to end with a faked
aggregator, including the failure path and the callback.
