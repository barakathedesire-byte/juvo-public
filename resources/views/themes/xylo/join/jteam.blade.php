@extends('themes.xylo.layouts.master')

@section('title', 'Join JTeam — Juvo sales consultants')
@section('meta')
    <meta name="description" content="Become a JTeam sales consultant for Juvo: place orders for customers, earn commission on delivered orders, and track everything in the JTeam CRM.">
    <link rel="canonical" href="{{ route('join.jteam') }}">
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
                    <span class="jvj-eyebrow"><i class="fa-solid fa-users"></i> For sales consultants</span>
                    <h1>Join JTeam.<br>Earn from every order you help place.</h1>
                    <p class="jvj-lead">
                        JTeam is Juvo's network of sales consultants. You help customers find products,
                        place orders on their behalf, and earn commission once an order is delivered and paid.
                    </p>
                    <div class="jvj-actions">
                        <a href="{{ route('agent.register') }}" class="jvj-btn jvj-btn-primary">
                            Self-register for JTeam <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <a href="{{ $loginUrl }}" class="jvj-btn jvj-btn-ghost">Consultant sign in</a>
                    </div>
                    <p class="jvj-hero-note">
                        <i class="fa-solid fa-shield-halved"></i>
                        Self-registration is open. Accounts are reviewed and activated by Juvo. No stock to hold, no shop to run.
                    </p>

                    <div class="jvj-stats">
                        <div class="jvj-stat">
                            <strong>Free to join</strong>
                            <span>No registration fee or upfront capital</span>
                        </div>
                        <div class="jvj-stat">
                            <strong>Flexible hours</strong>
                            <span>Work from anywhere, at your own pace</span>
                        </div>
                        <div class="jvj-stat">
                            <strong>Twice-monthly payouts</strong>
                            <span>Commissions paid on a fixed schedule</span>
                        </div>
                    </div>
                </div>

                <div class="jvj-scene" aria-hidden="true">
                    <div class="jvj-phone">
                        <div class="jvj-phone-screen">
                            <div class="jvj-phone-bar"></div>
                            <div class="jvj-phone-hero">
                                <strong>JTeam CRM</strong>
                                Consultants, sales and payouts
                            </div>
                            <div class="jvj-phone-grid">
                                <div class="jvj-phone-tile"><i class="fa-solid fa-user-plus"></i></div>
                                <div class="jvj-phone-tile"><i class="fa-solid fa-bag-shopping"></i></div>
                                <div class="jvj-phone-tile"><i class="fa-solid fa-coins"></i></div>
                                <div class="jvj-phone-tile"><i class="fa-solid fa-wallet"></i></div>
                            </div>
                            <div class="jvj-phone-list">
                                <div class="jvj-phone-row"></div>
                                <div class="jvj-phone-row short"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section">
        <div class="container">
            <div class="jvj-section-head jvj-reveal">
                <span class="jvj-eyebrow">How it works</span>
                <h2>From application to first commission</h2>
                <p>Most consultants finish onboarding in a single sitting.</p>
            </div>

            <div class="jvj-steps">
                <div class="jvj-step jvj-reveal">
                    <h3>Self-register</h3>
                    <p>Complete the JTeam registration form with your phone, national ID, address and next of kin. Your application starts in "Applied" status.</p>
                </div>
                <div class="jvj-step jvj-reveal">
                    <h3>Get verified and approved</h3>
                    <p>The Juvo team reviews and verifies your account before you can transact.</p>
                </div>
                <div class="jvj-step jvj-reveal">
                    <h3>Start selling</h3>
                    <p>Share products, place orders for customers using your referral code, and track them in the CRM.</p>
                </div>
                <div class="jvj-step jvj-reveal">
                    <h3>Earn and get paid</h3>
                    <p>Commission becomes payable after delivery and payment, then pays out twice monthly.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section" style="background:#fafbfc;">
        <div class="container">
            <div class="jvj-section-head jvj-reveal">
                <span class="jvj-eyebrow">Commission</span>
                <h2>How JTeam consultants earn</h2>
                <p>Commission rates are set per product category in the active JTeam policy.</p>
            </div>

            <div class="jvj-cards">
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-bag-shopping"></i></div>
                    <h3>Direct commission</h3>
                    <p>Earn on every order you place or refer, based on the category rate and order value.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-users"></i></div>
                    <h3>Team commission</h3>
                    <p>Sponsors can earn an indirect commission on the sales of consultants they refer.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <h3>Delivered orders only</h3>
                    <p>Commission is only payable on orders that are delivered and fully paid.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-ban"></i></div>
                    <h3>No flash-sale commission</h3>
                    <p>Flash-sale items are excluded from commissionable sales.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section">
        <div class="container">
            <div class="jvj-section-head jvj-reveal">
                <span class="jvj-eyebrow">Training</span>
                <h2>Learn before you sell</h2>
                <p>Training modules are available inside the JTeam CRM for onboarding, selling, compliance and payouts.</p>
            </div>
            <div class="jvj-actions">
                <a href="{{ $loginUrl }}" class="jvj-btn jvj-btn-primary">
                    Open JTeam Console <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <section class="jvj-section" style="background:#fafbfc;">
        <div class="container">
            <div class="jvj-section-head jvj-reveal">
                <span class="jvj-eyebrow">Questions</span>
                <h2>Before you apply</h2>
            </div>

            <div class="accordion jvj-faq jvj-reveal" id="jteamFaq">
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#jfaq1" aria-expanded="false" aria-controls="jfaq1">
                            What is JTeam?
                        </button>
                    </h3>
                    <div id="jfaq1" class="accordion-collapse collapse" data-bs-parent="#jteamFaq">
                        <div class="accordion-body">
                            JTeam is Juvo's network of sales consultants who place orders on behalf of customers
                            and earn commission on delivered, paid orders.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#jfaq2" aria-expanded="false" aria-controls="jfaq2">
                            Is there a registration fee?
                        </button>
                    </h3>
                    <div id="jfaq2" class="accordion-collapse collapse" data-bs-parent="#jteamFaq">
                        <div class="accordion-body">
                            No. Joining JTeam is free. Juvo will never ask you for money to become a consultant.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#jfaq3" aria-expanded="false" aria-controls="jfaq3">
                            When do I get paid?
                        </button>
                    </h3>
                    <div id="jfaq3" class="accordion-collapse collapse" data-bs-parent="#jteamFaq">
                        <div class="accordion-body">
                            Commissions become payable after delivery and payment, and payouts are processed
                            twice monthly through the JTeam CRM.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
