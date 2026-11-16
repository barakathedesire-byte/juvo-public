<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    protected const CATEGORIES = ['general', 'order', 'payment', 'return', 'account', 'other'];

    public function index()
    {
        $customer = Auth::guard('customer')->user();

        return view('themes.xylo.support.index', [
            'categories' => self::CATEGORIES,
            'customer' => $customer,
            'phone' => getWebConfig('phone_number'),
        ]);
    }

    public function help()
    {
        $customer = Auth::guard('customer')->user();

        $topics = [
            [
                'slug' => 'orders',
                'icon' => 'bi-box-seam',
                'title' => 'Orders & tracking',
                'desc' => 'Where is my parcel, change an address, cancel an order.',
                'links' => [
                    ['label' => 'Track an order', 'anchor' => '#track'],
                    ['label' => 'Shipping & delivery', 'route' => ['page.show', 'shipping-delivery']],
                    ['label' => 'Order help', 'anchor' => '#contact'],
                ],
            ],
            [
                'slug' => 'returns',
                'icon' => 'bi-arrow-counterclockwise',
                'title' => 'Returns & refunds',
                'desc' => 'Start a return, refund timelines and eligibility.',
                'links' => [
                    ['label' => 'Return policy', 'route' => ['page.show', 'return-policy']],
                    ['label' => 'Request a return', 'anchor' => '#contact'],
                ],
            ],
            [
                'slug' => 'payments',
                'icon' => 'bi-credit-card',
                'title' => 'Payments & pricing',
                'desc' => 'Payment methods, currency and invoices.',
                'links' => [
                    ['label' => 'Ask about a payment', 'anchor' => '#contact'],
                    ['label' => 'Terms & conditions', 'route' => ['page.show', 'terms-conditions']],
                ],
            ],
            [
                'slug' => 'account',
                'icon' => 'bi-person-circle',
                'title' => 'Account & security',
                'desc' => 'Sign in, password reset and account safety.',
                'links' => [
                    ['label' => 'Reset password', 'route' => ['customer.password.request']],
                    ['label' => 'Privacy policy', 'route' => ['page.show', 'privacy-policy']],
                ],
            ],
        ];

        $faqs = [
            [
                'q' => 'How do I track my order?',
                'a' => 'Enter your order number and the email you used at checkout below. You will see the live status and history.',
            ],
            [
                'q' => 'How long does delivery take?',
                'a' => 'Most orders arrive within 1–5 working days depending on your region and the vendor. Large items may take longer.',
            ],
            [
                'q' => 'What is the return window?',
                'a' => 'Most items can be returned within 7 days of delivery, unused and in their original packaging. See the return policy for exclusions.',
            ],
            [
                'q' => 'Which payment methods are accepted?',
                'a' => 'Mobile money, card and cash on delivery are supported depending on the vendor and your region.',
            ],
            [
                'q' => 'How do I sell on Juvo?',
                'a' => 'Create a vendor account, verify your business and start listing. Open “Sell on Juvo” to register.',
            ],
        ];

        return view('themes.xylo.help.index', [
            'customer' => $customer,
            'topics' => $topics,
            'faqs' => $faqs,
            'categories' => self::CATEGORIES,
            'phone' => getWebConfig('phone_number'),
            'email' => getWebConfig('email'),
        ]);
    }

    public function contact(Request $request)
    {
        $customer = Auth::guard('customer')->user();

        $data = $request->validate([
            'name' => [$customer ? 'nullable' : 'required', 'string', 'max:150'],
            'email' => [$customer ? 'nullable' : 'required', 'email', 'max:150'],
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', 'in:'.implode(',', self::CATEGORIES)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $ticket = SupportTicket::create([
            'ticket_number' => $this->uniqueTicketNumber(),
            'customer_id' => $customer?->id,
            'guest_name' => $customer ? null : $data['name'],
            'guest_email' => $customer ? null : $data['email'],
            'subject' => $data['subject'],
            'category' => $data['category'],
            'priority' => 'normal',
            'status' => 'open',
            'last_reply_at' => now(),
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => $customer ? 'customer' : 'guest',
            'sender_id' => $customer?->id,
            'body' => $data['message'],
            'is_internal' => false,
        ]);

        return back()->with('success', 'Thanks! Your message was received. Reference: '.$ticket->ticket_number);
    }

    public function track(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'email' => ['required', 'email', 'max:150'],
        ]);

        $order = Order::with(['details.product', 'statusHistories', 'customer'])
            ->where('id', $data['order_id'])
            ->where(function ($query) use ($data) {
                $query->where('guest_email', $data['email'])
                    ->orWhereHas('customer', fn ($q) => $q->where('email', $data['email']));
            })
            ->first();

        if (! $order) {
            return back()
                ->withErrors(['order_id' => 'No order found matching that order number and email.'])
                ->withInput();
        }

        return view('themes.xylo.support.track', compact('order'));
    }

    protected function uniqueTicketNumber(): string
    {
        do {
            $number = 'TKT-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (SupportTicket::where('ticket_number', $number)->exists());

        return $number;
    }
}
