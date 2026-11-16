@extends('corporate.layouts.app')

@section('title', 'Juvo — Commerce, connected.')
@section('description', 'Juvo connects vendors, customers and riders on one platform — a marketplace with logistics and vendor growth built in.')

@section('meta')
    <meta property="og:description" content="A marketplace, a delivery network and vendor tools — built for local commerce in Tanzania.">
@endsection

@section('content')
    <section class="jvco-hero" id="top">
        <div class="jvco-hero-bg" aria-hidden="true">
            <span class="jvco-orb jvco-orb-a"></span>
            <span class="jvco-orb jvco-orb-b"></span>
            <span class="jvco-grid-lines"></span>
        </div>

        <div class="jvco-shell jvco-hero-grid">
            <div class="jvco-hero-copy">
                <span class="jvco-eyebrow jvco-reveal"><span class="jvco-pulse"></span> Juvo Group · Tanzania</span>
                <h1 class="jvco-reveal">Commerce that <span>moves</span> with Tanzania.</h1>
                <p class="jvco-hero-lede jvco-reveal">Juvo brings the storefront, the delivery network and the tools vendors need into one connected platform. One place to shop. One place to sell. One place to grow.</p>

                <div class="jvco-hero-actions jvco-reveal">
                    <a href="{{ route('xylo.home') }}" class="jvco-btn jvco-btn-primary jvco-btn-lg">
                        Explore the marketplace <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('join.sell') }}" class="jvco-btn jvco-btn-light jvco-btn-lg">
                        Partner with Juvo <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="jvco-hero-proof jvco-reveal">
                    <div class="jvco-proof-avatars" aria-hidden="true">
                        <span><i class="fa-solid fa-store"></i></span>
                        <span><i class="fa-solid fa-bag-shopping"></i></span>
                        <span><i class="fa-solid fa-motorcycle"></i></span>
                    </div>
                    <div>
                        <strong>Built for every side of commerce</strong>
                        <span>Vendors, customers and riders — connected.</span>
                    </div>
                </div>
            </div>

            <div class="jvco-hero-visual jvco-reveal" data-tilt>
                <div class="jvco-console">
                    <div class="jvco-console-top">
                        <span class="jvco-console-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span>Juvo operations</span>
                        <span class="jvco-live"><span></span> Live</span>
                    </div>
                    <div class="jvco-console-body">
                        <div class="jvco-console-head">
                            <div>
                                <small>Today’s dispatch</small>
                                <strong>Orders in motion</strong>
                            </div>
                            <span class="jvco-trend"><i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i> On track</span>
                        </div>

                        <div class="jvco-map" aria-hidden="true">
                            <svg viewBox="0 0 430 220" role="presentation">
                                <path class="jvco-map-road" d="M20 180 C90 150 80 70 160 76 S285 150 410 52" />
                                <path class="jvco-map-route" d="M20 180 C90 150 80 70 160 76 S285 150 410 52" />
                            </svg>
                            <span class="jvco-map-pin jvco-pin-a"><i class="fa-solid fa-store"></i></span>
                            <span class="jvco-map-pin jvco-pin-b"><i class="fa-solid fa-location-dot"></i></span>
                            <span class="jvco-map-pin jvco-pin-c"><i class="fa-solid fa-house"></i></span>
                            <span class="jvco-rider"><i class="fa-solid fa-motorcycle"></i></span>
                        </div>

                        <div class="jvco-order-card">
                            <span class="jvco-order-icon"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></span>
                            <div>
                                <strong>Order #JV-2481</strong>
                                <small>Vendor pickup · Dar es Salaam</small>
                            </div>
                            <span class="jvco-status">On the way</span>
                        </div>

                        <div class="jvco-console-foot">
                            <div><span>Pickup</span><strong>Ready</strong></div>
                            <div><span>Rider</span><strong>Assigned</strong></div>
                            <div><span>Delivery</span><strong>12 min</strong></div>
                        </div>
                    </div>
                </div>

                <div class="jvco-float jvco-float-a">
                    <span class="jvco-float-icon"><i class="fa-solid fa-motorcycle" aria-hidden="true"></i></span>
                    <div><strong>Rider assigned</strong><small>Pickup in 4 minutes</small></div>
                </div>
                <div class="jvco-float jvco-float-b">
                    <span class="jvco-float-icon jvco-float-icon-green"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                    <div><strong>Vendor payout</strong><small>Settled automatically</small></div>
                </div>
                <span class="jvco-ring jvco-ring-a" aria-hidden="true"></span>
                <span class="jvco-ring jvco-ring-b" aria-hidden="true"></span>
            </div>
        </div>

        <div class="jvco-marquee" aria-label="Juvo platform capabilities">
            <div class="jvco-marquee-track">
                <span>Local vendors</span><i></i>
                <span>Same-city dispatch</span><i></i>
                <span>Live order tracking</span><i></i>
                <span>Vendor tools</span><i></i>
                <span>Secure checkout</span><i></i>
                <span>Rider network</span><i></i>
                <span>Local vendors</span><i></i>
                <span>Same-city dispatch</span><i></i>
                <span>Live order tracking</span><i></i>
                <span>Vendor tools</span><i></i>
                <span>Secure checkout</span><i></i>
                <span>Rider network</span><i></i>
            </div>
        </div>
    </section>

    <section class="jvco-section jvco-what" id="what-we-do">
        <div class="jvco-shell">
            <div class="jvco-section-head jvco-reveal">
                <span class="jvco-kicker">What we do</span>
                <div>
                    <h2>Three engines. One connected experience.</h2>
                    <p>Commerce works best when the storefront, the delivery and the back office speak the same language. Juvo connects all three.</p>
                </div>
            </div>

            <div class="jvco-what-grid">
                <article class="jvco-what-card jvco-reveal" id="marketplace">
                    <span class="jvco-card-number">01</span>
                    <span class="jvco-card-icon"><i class="fa-solid fa-store" aria-hidden="true"></i></span>
                    <h3>Juvo Marketplace</h3>
                    <p>A local storefront where trusted vendors reach more customers, and customers find what they need nearby.</p>
                    <ul>
                        <li>Multi-vendor catalog</li>
                        <li>Secure checkout</li>
                        <li>Local discovery</li>
                    </ul>
                    <a href="{{ route('xylo.home') }}" class="jvco-text-link">Shop the marketplace <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>

                <article class="jvco-what-card jvco-reveal" id="logistics">
                    <span class="jvco-card-number">02</span>
                    <span class="jvco-card-icon"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i></span>
                    <h3>Juvo Logistics</h3>
                    <p>Dispatch, delivery and tracking in one flow — so orders move quickly and every handover stays visible.</p>
                    <ul>
                        <li>Rider assignment</li>
                        <li>Live delivery status</li>
                        <li>Cash-on-delivery support</li>
                    </ul>
                    <a href="{{ route('join.deliver') }}" class="jvco-text-link">Join the rider network <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>

                <article class="jvco-what-card jvco-reveal" id="growth">
                    <span class="jvco-card-number">03</span>
                    <span class="jvco-card-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                    <h3>Juvo Growth</h3>
                    <p>Practical tools that help vendors manage stock, understand demand and grow a more resilient business.</p>
                    <ul>
                        <li>Storefront controls</li>
                        <li>Order management</li>
                        <li>Performance insights</li>
                    </ul>
                    <a href="{{ route('join.sell') }}" class="jvco-text-link">Start selling <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>
            </div>
        </div>
    </section>

    <section class="jvco-section jvco-platform" id="platform">
        <div class="jvco-shell">
            <div class="jvco-platform-head jvco-reveal">
                <span class="jvco-kicker">The platform</span>
                <h2>One system, built around the way commerce actually happens.</h2>
            </div>

            <div class="jvco-tabs jvco-reveal">
                <div class="jvco-tab-list" role="tablist" aria-label="Juvo platform">
                    <button class="jvco-tab is-active" type="button" role="tab" aria-selected="true" aria-controls="panel-marketplace" id="tab-marketplace" data-tab="marketplace">
                        <span>01</span>
                        <strong>Marketplace</strong>
                        <small>Shop and discover</small>
                    </button>
                    <button class="jvco-tab" type="button" role="tab" aria-selected="false" aria-controls="panel-logistics" id="tab-logistics" data-tab="logistics" tabindex="-1">
                        <span>02</span>
                        <strong>Logistics</strong>
                        <small>Move and track</small>
                    </button>
                    <button class="jvco-tab" type="button" role="tab" aria-selected="false" aria-controls="panel-growth" id="tab-growth" data-tab="growth" tabindex="-1">
                        <span>03</span>
                        <strong>Growth</strong>
                        <small>Manage and scale</small>
                    </button>
                </div>

                <div class="jvco-tab-panels">
                    <div class="jvco-tab-panel is-active" id="panel-marketplace" role="tabpanel" aria-labelledby="tab-marketplace" data-panel="marketplace">
                        <div class="jvco-panel-copy">
                            <span class="jvco-pill">For customers</span>
                            <h3>Everything local, one tap away.</h3>
                            <p>Browse vendors across the city, compare what is available and check out with confidence. Juvo keeps the experience simple from discovery to doorstep.</p>
                            <ul class="jvco-check-list">
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i> A curated multi-vendor catalog</li>
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i> Clear pricing and secure checkout</li>
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i> Delivery updates from dispatch to door</li>
                            </ul>
                            <a href="{{ route('xylo.home') }}" class="jvco-btn jvco-btn-dark">Start shopping <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </div>
                        <div class="jvco-panel-visual">
                            <div class="jvco-ui-card jvco-ui-shop">
                                <div class="jvco-ui-top"><span>Juvo marketplace</span><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></div>
                                <div class="jvco-ui-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search nearby vendors</div>
                                <div class="jvco-ui-products">
                                    <span><i class="fa-solid fa-shirt" aria-hidden="true"></i><small>Fashion</small></span>
                                    <span><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i><small>Electronics</small></span>
                                    <span><i class="fa-solid fa-basket-shopping" aria-hidden="true"></i><small>Groceries</small></span>
                                </div>
                                <div class="jvco-ui-banner">
                                    <div><small>Local pick</small><strong>Fresh from nearby vendors</strong></div>
                                    <span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="jvco-tab-panel" id="panel-logistics" role="tabpanel" aria-labelledby="tab-logistics" data-panel="logistics" hidden>
                        <div class="jvco-panel-copy">
                            <span class="jvco-pill">For riders</span>
                            <h3>A delivery flow that keeps everyone in sync.</h3>
                            <p>Orders are matched with riders, routes stay visible and every status update is shared. Less guessing, more moving.</p>
                            <ul class="jvco-check-list">
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i> Smart rider assignment</li>
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i> Live route and delivery status</li>
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i> Clear pickup and drop-off details</li>
                            </ul>
                            <a href="{{ route('join.deliver') }}" class="jvco-btn jvco-btn-dark">Deliver with Juvo <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </div>
                        <div class="jvco-panel-visual">
                            <div class="jvco-ui-card jvco-ui-route">
                                <div class="jvco-ui-top"><span>Delivery #JV-2481</span><span class="jvco-live"><span></span> Live</span></div>
                                <div class="jvco-ui-map">
                                    <svg viewBox="0 0 360 190" role="presentation" aria-hidden="true">
                                        <path class="jvco-map-road" d="M24 154 C82 98 128 172 184 112 S278 44 340 70" />
                                        <path class="jvco-map-route" d="M24 154 C82 98 128 172 184 112 S278 44 340 70" />
                                    </svg>
                                    <span class="jvco-ui-pin jvco-ui-pin-a"><i class="fa-solid fa-store" aria-hidden="true"></i></span>
                                    <span class="jvco-ui-pin jvco-ui-pin-b"><i class="fa-solid fa-motorcycle" aria-hidden="true"></i></span>
                                    <span class="jvco-ui-pin jvco-ui-pin-c"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                                </div>
                                <div class="jvco-ui-route-list">
                                    <div><span class="jvco-dot"></span><div><small>Pickup</small><strong>Mlimani City vendor</strong></div><span>Done</span></div>
                                    <div><span class="jvco-dot is-active"></span><div><small>Current</small><strong>Rider on the way</strong></div><span>12 min</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="jvco-tab-panel" id="panel-growth" role="tabpanel" aria-labelledby="tab-growth" data-panel="growth" hidden>
                        <div class="jvco-panel-copy">
                            <span class="jvco-pill">For vendors</span>
                            <h3>The back office your business deserves.</h3>
                            <p>From the first product to the hundredth order, Juvo gives vendors a clearer view of stock, sales and customer demand.</p>
                            <ul class="jvco-check-list">
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i> Simple product and stock controls</li>
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i> Orders and payouts in one view</li>
                                <li><i class="fa-solid fa-check" aria-hidden="true"></i> Insights that support smarter decisions</li>
                            </ul>
                            <a href="{{ route('join.sell') }}" class="jvco-btn jvco-btn-dark">Open your store <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </div>
                        <div class="jvco-panel-visual">
                            <div class="jvco-ui-card jvco-ui-growth">
                                <div class="jvco-ui-top"><span>Vendor dashboard</span><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
                                <div class="jvco-ui-kpis">
                                    <div><small>Orders</small><strong>128</strong><span>+18%</span></div>
                                    <div><small>Revenue</small><strong>TZS 4.2m</strong><span>+12%</span></div>
                                </div>
                                <div class="jvco-ui-chart" aria-hidden="true">
                                    <span style="height: 38%"></span>
                                    <span style="height: 58%"></span>
                                    <span style="height: 46%"></span>
                                    <span style="height: 72%"></span>
                                    <span style="height: 64%"></span>
                                    <span style="height: 88%"></span>
                                    <span style="height: 78%"></span>
                                    <span style="height: 96%"></span>
                                </div>
                                <div class="jvco-ui-note"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Payouts reconciled automatically</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="jvco-section jvco-audiences" id="about">
        <div class="jvco-shell">
            <div class="jvco-split-head jvco-reveal">
                <div>
                    <span class="jvco-kicker">Built for every side</span>
                    <h2>Local commerce works when everyone moves forward.</h2>
                </div>
                <p>Juvo is designed as a shared system — not a single storefront. Each experience supports the others, so growth on one side creates opportunity on the next.</p>
            </div>

            <div class="jvco-audience-grid">
                <article class="jvco-audience-card jvco-reveal">
                    <span class="jvco-audience-icon"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></span>
                    <span class="jvco-audience-label">Customers</span>
                    <h3>Shop nearby. Get it delivered.</h3>
                    <p>Discover trusted local vendors, pay securely and follow your order all the way to your door.</p>
                    <a href="{{ route('xylo.home') }}">Explore the marketplace <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>

                <article class="jvco-audience-card jvco-audience-card-orange jvco-reveal">
                    <span class="jvco-audience-icon"><i class="fa-solid fa-store" aria-hidden="true"></i></span>
                    <span class="jvco-audience-label">Vendors</span>
                    <h3>Your business, online and organised.</h3>
                    <p>Open a storefront, manage orders and reach more customers without losing control of your day-to-day.</p>
                    <a href="{{ route('join.sell') }}">Sell on Juvo <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>

                <article class="jvco-audience-card jvco-audience-card-dark jvco-reveal">
                    <span class="jvco-audience-icon"><i class="fa-solid fa-motorcycle" aria-hidden="true"></i></span>
                    <span class="jvco-audience-label">Riders</span>
                    <h3>Earn on your schedule.</h3>
                    <p>Pick up nearby deliveries, follow clear routes and get paid for the trips you choose to take.</p>
                    <a href="{{ route('join.deliver') }}">Join the rider network <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>
            </div>
        </div>
    </section>

    <section class="jvco-stats">
        <div class="jvco-shell">
            <div class="jvco-stats-head jvco-reveal">
                <span class="jvco-kicker jvco-kicker-light">Our focus</span>
                <h2>Built around the people who move commerce.</h2>
            </div>
            <div class="jvco-stat-grid">
                <div class="jvco-stat jvco-reveal">
                    <strong data-count="1">0</strong>
                    <span>connected platform</span>
                </div>
                <div class="jvco-stat jvco-reveal">
                    <strong data-count="3">0</strong>
                    <span>experiences: shop, sell, deliver</span>
                </div>
                <div class="jvco-stat jvco-reveal">
                    <strong data-count="100" data-suffix="%">0</strong>
                    <span>Tanzania-focused</span>
                </div>
                <div class="jvco-stat jvco-reveal">
                    <strong data-count="24" data-suffix="/7">0</strong>
                    <span>always-on marketplace</span>
                </div>
            </div>
        </div>
    </section>

    <section class="jvco-section jvco-how">
        <div class="jvco-shell">
            <div class="jvco-section-head jvco-reveal">
                <span class="jvco-kicker">How it works</span>
                <div>
                    <h2>From storefront to doorstep, in one flow.</h2>
                    <p>A simple path for vendors and customers — with Juvo handling the connective tissue in between.</p>
                </div>
            </div>

            <div class="jvco-steps">
                <article class="jvco-step jvco-reveal">
                    <span class="jvco-step-number">01</span>
                    <span class="jvco-step-icon"><i class="fa-solid fa-upload" aria-hidden="true"></i></span>
                    <h3>Vendor lists</h3>
                    <p>A seller opens a store, adds products and sets availability.</p>
                </article>
                <article class="jvco-step jvco-reveal">
                    <span class="jvco-step-number">02</span>
                    <span class="jvco-step-icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></span>
                    <h3>Customer orders</h3>
                    <p>A customer discovers the store and checks out securely.</p>
                </article>
                <article class="jvco-step jvco-reveal">
                    <span class="jvco-step-number">03</span>
                    <span class="jvco-step-icon"><i class="fa-solid fa-route" aria-hidden="true"></i></span>
                    <h3>Juvo dispatches</h3>
                    <p>The order is matched with a rider and a clear delivery route.</p>
                </article>
                <article class="jvco-step jvco-reveal">
                    <span class="jvco-step-number">04</span>
                    <span class="jvco-step-icon"><i class="fa-solid fa-house-circle-check" aria-hidden="true"></i></span>
                    <h3>Rider delivers</h3>
                    <p>The customer follows the journey and receives the order.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="jvco-section jvco-commitment" id="impact">
        <div class="jvco-shell">
            <div class="jvco-commitment-grid">
                <div class="jvco-commitment-copy jvco-reveal">
                    <span class="jvco-kicker">Our commitment</span>
                    <h2>Growth that is shared, not extracted.</h2>
                    <p>Juvo is built on a simple belief: local commerce gets stronger when vendors keep more control, riders have clearer opportunities and customers can trust the experience.</p>
                    <div class="jvco-commitment-points">
                        <div><i class="fa-solid fa-store" aria-hidden="true"></i><span><strong>Vendors first</strong><small>Tools that respect the realities of small business.</small></span></div>
                        <div><i class="fa-solid fa-motorcycle" aria-hidden="true"></i><span><strong>Riders matter</strong><small>Clear trips, visible earnings and safer handovers.</small></span></div>
                        <div><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span><strong>Trust by default</strong><small>Transparent status, secure checkout and accountable delivery.</small></span></div>
                    </div>
                </div>

                <div class="jvco-commitment-card jvco-reveal">
                    <span class="jvco-quote-mark" aria-hidden="true">“</span>
                    <blockquote>Commerce should feel local: familiar, accountable and within reach.</blockquote>
                    <div class="jvco-quote-source">
                        <span class="jvco-quote-avatar">J</span>
                        <div><strong>The Juvo team</strong><small>Dar es Salaam, Tanzania</small></div>
                    </div>
                    <div class="jvco-commitment-tags">
                        <span>Local by design</span>
                        <span>Built for trust</span>
                        <span>Shared growth</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="jvco-section jvco-news" id="newsroom">
        <div class="jvco-shell">
            <div class="jvco-section-head jvco-reveal">
                <span class="jvco-kicker">The Juvo brief</span>
                <div>
                    <h2>Ideas, updates and the work behind the platform.</h2>
                    <p>Notes from the team building connected commerce for Tanzania.</p>
                </div>
            </div>

            <div class="jvco-news-grid">
                <a href="#platform" class="jvco-news-card jvco-reveal">
                    <span class="jvco-news-art jvco-news-art-orange"><i class="fa-solid fa-route" aria-hidden="true"></i></span>
                    <span class="jvco-news-meta"><span>Logistics</span><span>4 min read</span></span>
                    <h3>Why local commerce needs local logistics.</h3>
                    <p>The last mile is not a detail. It is the moment a marketplace earns trust.</p>
                    <span class="jvco-text-link">Read the brief <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </a>

                <a href="#platform" class="jvco-news-card jvco-reveal">
                    <span class="jvco-news-art jvco-news-art-dark"><i class="fa-solid fa-store" aria-hidden="true"></i></span>
                    <span class="jvco-news-meta"><span>Product</span><span>3 min read</span></span>
                    <h3>The vendor operating system.</h3>
                    <p>What small sellers actually need from the tools behind their storefront.</p>
                    <span class="jvco-text-link">Read the brief <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </a>

                <a href="#platform" class="jvco-news-card jvco-reveal">
                    <span class="jvco-news-art jvco-news-art-cream"><i class="fa-solid fa-handshake-angle" aria-hidden="true"></i></span>
                    <span class="jvco-news-meta"><span>Community</span><span>3 min read</span></span>
                    <h3>Delivering trust, one order at a time.</h3>
                    <p>How clear handovers and visible status make commerce feel human.</p>
                    <span class="jvco-text-link">Read the brief <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </a>
            </div>
        </div>
    </section>

    <section class="jvco-cta">
        <div class="jvco-shell">
            <div class="jvco-cta-card jvco-reveal">
                <div class="jvco-cta-glow" aria-hidden="true"></div>
                <div class="jvco-cta-copy">
                    <span class="jvco-kicker jvco-kicker-light">Build with Juvo</span>
                    <h2>Let’s build the next chapter of local commerce.</h2>
                    <p>Whether you sell, deliver or shop — there is a place for you on Juvo.</p>
                </div>
                <div class="jvco-cta-actions">
                    <a href="{{ route('join.sell') }}" class="jvco-btn jvco-btn-primary jvco-btn-lg">Sell on Juvo <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    <a href="{{ route('page.show', 'contact-us') }}" class="jvco-btn jvco-btn-light jvco-btn-lg">Partner with us <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                </div>
                <div class="jvco-cta-foot">
                    <span><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Shop the marketplace</span>
                    <span><i class="fa-solid fa-motorcycle" aria-hidden="true"></i> Deliver with Juvo</span>
                    <span><i class="fa-solid fa-headset" aria-hidden="true"></i> Talk to our team</span>
                </div>
            </div>
        </div>
    </section>
@endsection
