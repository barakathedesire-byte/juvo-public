@extends('themes.xylo.layouts.master')

@section('title', 'Sell on Juvo — open your store on Tanzania\'s marketplace')
@section('meta')
    <meta name="description" content="Open a store on Juvo and sell to shoppers across Tanzania. Manage products, orders and payouts in one dashboard, with delivery handled by Juvo Logistics.">
    <link rel="canonical" href="{{ route('join.sell') }}">
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/join.css') }}?v={{ @filemtime(public_path('css/join.css')) }}">
@endsection

@section('content')
<div class="jvj-page">
    <section class="jvj-hero">
        <div class="container">
            <div class="jvj-hero-grid">
                <div>
                    <span class="jvj-eyebrow"><i class="fa-solid fa-store"></i> For sellers</span>
                    <h1>Sell on Juvo.<br>Reach shoppers across Tanzania.</h1>
                    <p class="jvj-lead">
                        Open your own storefront, list what you sell, and let Juvo handle the
                        discovery, checkout and delivery. You keep your prices and your brand.
                    </p>
                    <div class="jvj-actions">
                        <a href="{{ $registerUrl }}" class="jvj-btn jvj-btn-primary">
                            Start selling <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <a href="{{ $loginUrl }}" class="jvj-btn jvj-btn-ghost">Vendor sign in</a>
                    </div>
                    <p class="jvj-hero-note">
                        <i class="fa-solid fa-shield-halved"></i>
                        Every store is reviewed by our team before it goes live.
                    </p>

                    <div class="jvj-stats">
                        <div class="jvj-stat">
                            <strong>Own storefront</strong>
                            <span>A public shop page and product links</span>
                        </div>
                        <div class="jvj-stat">
                            <strong>1 dashboard</strong>
                            <span>Products, stock, orders and payouts</span>
                        </div>
                        <div class="jvj-stat">
                            <strong>5 wallets</strong>
                            <span>Mobile money payout options</span>
                        </div>
                    </div>
                </div>

                <div class="jvj-scene" aria-hidden="true">
                    <div class="jvj-phone">
                        <div class="jvj-phone-screen">
                            <div class="jvj-phone-bar"></div>
                            <div class="jvj-phone-hero">
                                <strong>Your store</strong>
                                Fresh stock, ready to ship
                            </div>
                            <div class="jvj-phone-grid">
                                <div class="jvj-phone-tile"><i class="fa-solid fa-shirt"></i></div>
                                <div class="jvj-phone-tile"><i class="fa-solid fa-mobile-screen"></i></div>
                                <div class="jvj-phone-tile"><i class="fa-solid fa-headphones"></i></div>
                                <div class="jvj-phone-tile"><i class="fa-solid fa-shoe-prints"></i></div>
                            </div>
                            <div class="jvj-phone-list">
                                <div class="jvj-phone-row"></div>
                                <div class="jvj-phone-row short"></div>
                            </div>
                        </div>
                    </div>

                    <div class="jvj-float jvj-float-a">
                        <i class="fa-solid fa-bag-shopping"></i>
                        <span>New order<small>Paid at checkout</small></span>
                    </div>
                    <div class="jvj-float jvj-float-b">
                        <i class="fa-solid fa-wallet"></i>
                        <span>Payout sent<small>Straight to mobile money</small></span>
                    </div>
                    <div class="jvj-float jvj-float-c">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Store approved<small>You're live on Juvo</small></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section">
        <div class="container">
            <div class="jvj-section-head jvj-reveal">
                <span class="jvj-eyebrow">Why Juvo</span>
                <h2>Everything you need to sell online</h2>
                <p>No separate website, hosting or payment integration to set up. Your store, orders and money live in one place.</p>
            </div>

            <div class="jvj-cards">
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-store"></i></div>
                    <h3>Your own storefront</h3>
                    <p>A branded shop page with your products, ratings and a shareable link you can post anywhere.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                    <h3>Simple product tools</h3>
                    <p>Add products with variants, images, prices and stock. Bulk import and export when your catalogue grows.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-receipt"></i></div>
                    <h3>Orders in one queue</h3>
                    <p>See every order, confirm it, and hand it to a rider without chasing messages on your phone.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-truck-fast"></i></div>
                    <h3>Delivery built in</h3>
                    <p>Juvo Logistics picks up from you and delivers to your customer, with tracking on every parcel.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-chart-line"></i></div>
                    <h3>Numbers that matter</h3>
                    <p>Sales, best sellers and customer feedback so you know what to restock and what to drop.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-bullhorn"></i></div>
                    <h3>Get discovered</h3>
                    <p>Appear in categories, collections and campaigns, and promote your products when you want more reach.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section" style="background:#fafbfc;">
        <div class="container">
            <div class="jvj-section-head jvj-reveal">
                <span class="jvj-eyebrow">How it works</span>
                <h2>From application to first sale</h2>
                <p>Most sellers finish setup in a single sitting. Approval happens before your store is visible to shoppers.</p>
            </div>

            <div class="jvj-steps">
                <div class="jvj-step jvj-reveal">
                    <h3>Apply with your business details</h3>
                    <p>Tell us your store name, what you sell and how to reach you.</p>
                </div>
                <div class="jvj-step jvj-reveal">
                    <h3>Get approved</h3>
                    <p>Our team reviews new stores to keep the marketplace trustworthy. You'll be notified once you're in.</p>
                </div>
                <div class="jvj-step jvj-reveal">
                    <h3>Add your products</h3>
                    <p>Upload photos, set prices and stock, then publish. Drafts stay hidden until you're ready.</p>
                </div>
                <div class="jvj-step jvj-reveal">
                    <h3>Fulfil and get paid</h3>
                    <p>Confirm orders, hand them to a rider, and track payouts from your finance page.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section">
        <div class="container">
            <div class="jvj-split">
                <div class="jvj-reveal">
                    <span class="jvj-eyebrow">You stay in control</span>
                    <h2 class="mt-2" style="font-size:clamp(1.35rem,3vw,2rem);font-weight:800;letter-spacing:-.02em;">Your prices. Your stock. Your call.</h2>
                    <p class="text-muted mt-2 mb-3" style="line-height:1.65;">
                        Juvo gives you the reach; you keep the decisions. Commission and payout terms are
                        agreed with you and visible in your dashboard — never a surprise deduction.
                    </p>
                    <ul class="jvj-check">
                        <li><i class="fa-solid fa-circle-check"></i> Set your own prices and run your own discounts</li>
                        <li><i class="fa-solid fa-circle-check"></i> Decide what to publish and when</li>
                        <li><i class="fa-solid fa-circle-check"></i> Choose Juvo Logistics for delivery, with tracking included</li>
                        <li><i class="fa-solid fa-circle-check"></i> Reach support from inside your vendor dashboard</li>
                    </ul>
                </div>

                <div class="jvj-panel jvj-reveal">
                    <h3 style="font-size:1rem;font-weight:700;margin-bottom:.35rem;">Payouts to the wallet you use</h3>
                    <p class="text-muted mb-0" style="font-size:.9rem;line-height:1.55;">
                        Withdraw your balance to mobile money or a bank account from the finance page.
                    </p>
                    <div class="jvj-pay">
                        <img src="{{ asset('images/mobile-money/mpesa.svg') }}" alt="M-Pesa">
                        <img src="{{ asset('images/mobile-money/airtel.svg') }}" alt="Airtel Money">
                        <img src="{{ asset('images/mobile-money/mixx.svg') }}" alt="Mixx by Yas">
                        <img src="{{ asset('images/mobile-money/halopesa.svg') }}" alt="HaloPesa">
                        <img src="{{ asset('images/mobile-money/tpesa.svg') }}" alt="T-Pesa">
                    </div>
                    <hr class="my-3">
                    <h3 style="font-size:1rem;font-weight:700;margin-bottom:.6rem;">What to prepare</h3>
                    <ul class="jvj-check">
                        <li><i class="fa-solid fa-check"></i> Store name and what you sell</li>
                        <li><i class="fa-solid fa-check"></i> A phone number and email you check often</li>
                        <li><i class="fa-solid fa-check"></i> Product photos and prices</li>
                        <li><i class="fa-solid fa-check"></i> Payout wallet or bank details</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section" style="background:#fafbfc;">
        <div class="container">
            <div class="jvj-section-head jvj-reveal">
                <span class="jvj-eyebrow">Questions</span>
                <h2>Before you apply</h2>
            </div>

            <div class="accordion jvj-faq jvj-reveal" id="vendorFaq">
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#vfaq1" aria-expanded="false" aria-controls="vfaq1">
                            What happens after I register?
                        </button>
                    </h3>
                    <div id="vfaq1" class="accordion-collapse collapse" data-bs-parent="#vendorFaq">
                        <div class="accordion-body">
                            Your application is queued for review. You can sign in, but your store stays
                            inactive until an administrator approves it. You'll be able to start adding
                            products as soon as you're approved.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#vfaq2" aria-expanded="false" aria-controls="vfaq2">
                            What does it cost to sell?
                        </button>
                    </h3>
                    <div id="vfaq2" class="accordion-collapse collapse" data-bs-parent="#vendorFaq">
                        <div class="accordion-body">
                            There is no charge to apply. Commission terms are agreed with you and shown in
                            your dashboard, so you always see what you earn on an order before you fulfil it.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#vfaq3" aria-expanded="false" aria-controls="vfaq3">
                            Do I deliver orders myself?
                        </button>
                    </h3>
                    <div id="vfaq3" class="accordion-collapse collapse" data-bs-parent="#vendorFaq">
                        <div class="accordion-body">
                            Not if you don't want to. Juvo Logistics can collect from your shop and deliver
                            to the customer, and both of you get tracking updates.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#vfaq4" aria-expanded="false" aria-controls="vfaq4">
                            When do I get paid?
                        </button>
                    </h3>
                    <div id="vfaq4" class="accordion-collapse collapse" data-bs-parent="#vendorFaq">
                        <div class="accordion-body">
                            Completed orders appear in your finance page, where you can see your balance and
                            withdraw to mobile money or a bank account.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section">
        <div class="container">
            <div class="jvj-cta jvj-reveal">
                <h2>Ready to open your store?</h2>
                <p>Create your seller account now. Our team reviews every application before your shop goes live.</p>
                <div class="jvj-actions">
                    <a href="{{ $registerUrl }}" class="jvj-btn jvj-btn-primary">
                        Start selling <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <a href="{{ $loginUrl }}" class="jvj-btn jvj-btn-ghost">I already have an account</a>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var items = document.querySelectorAll('.jvj-reveal');
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        items.forEach(function (el) { el.classList.add('is-in'); });
        return;
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-in');
                io.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
    items.forEach(function (el) { io.observe(el); });
});
</script>
@endsection
