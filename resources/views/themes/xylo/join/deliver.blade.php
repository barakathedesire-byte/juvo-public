@extends('themes.xylo.layouts.master')

@section('title', 'Deliver with Juvo — earn on your own schedule')
@section('meta')
    <meta name="description" content="Become a Juvo delivery rider. Accept nearby deliveries, see the pay before you accept, and cash out from your rider dashboard.">
    <link rel="canonical" href="{{ route('join.deliver') }}">
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
                    <span class="jvj-eyebrow"><i class="fa-solid fa-motorcycle"></i> For riders</span>
                    <h1>Deliver with Juvo.<br>Earn on your own schedule.</h1>
                    <p class="jvj-lead">
                        Pick up parcels from Juvo sellers and drop them with happy customers.
                        Go online when it suits you, see the details before you accept, and track
                        your earnings in one place.
                    </p>
                    <div class="jvj-actions">
                        <a href="{{ $registerUrl }}" class="jvj-btn jvj-btn-primary">
                            Apply to ride <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <a href="{{ $loginUrl }}" class="jvj-btn jvj-btn-ghost">Rider sign in</a>
                    </div>
                    <p class="jvj-hero-note">
                        <i class="fa-solid fa-id-card"></i>
                        You must be 18+ and verified before your first delivery.
                    </p>

                    <div class="jvj-stats">
                        <div class="jvj-stat">
                            <strong>Choose your hours</strong>
                            <span>Ride when you want to ride</span>
                        </div>
                        <div class="jvj-stat">
                            <strong>Clear job details</strong>
                            <span>Pickup, drop-off and pay</span>
                        </div>
                        <div class="jvj-stat">
                            <strong>Earnings history</strong>
                            <span>Every delivery recorded</span>
                        </div>
                    </div>
                </div>

                <div class="jvj-scene" aria-hidden="true">
                    <div class="jvj-panel" style="width:min(320px,84vw);padding:1rem;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong style="font-size:.85rem;">Delivery in progress</strong>
                            <span class="badge" style="background:var(--jvj-soft);color:var(--jvj-accent-dark);font-size:.7rem;">On the way</span>
                        </div>
                        <div class="jvj-route">
                            <svg viewBox="0 0 300 190" preserveAspectRatio="none">
                                <path d="M42 130 C 90 130, 96 74, 148 74 S 214 46, 252 42"></path>
                            </svg>
                            <span class="jvj-route-dot start"></span>
                            <span class="jvj-route-dot end"></span>
                        </div>
                        <div class="jvj-earn mt-3">
                            <div class="jvj-earn-row"><span>Pickup</span><strong>Kariakoo</strong></div>
                            <div class="jvj-earn-row"><span>Drop-off</span><strong>Mikocheni</strong></div>
                            <div class="jvj-earn-total"><span>Delivery pay</span><strong>Shown up front</strong></div>
                        </div>
                    </div>

                    <div class="jvj-float jvj-float-a">
                        <i class="fa-solid fa-bolt"></i>
                        <span>New delivery<small>Nearby, ready to accept</small></span>
                    </div>
                    <div class="jvj-float jvj-float-b">
                        <i class="fa-solid fa-wallet"></i>
                        <span>Cash out<small>To your mobile wallet</small></span>
                    </div>
                    <div class="jvj-float jvj-float-c">
                        <i class="fa-solid fa-motorcycle"></i>
                        <span>You're online<small>Receiving job offers</small></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section">
        <div class="container">
            <div class="jvj-section-head jvj-reveal">
                <span class="jvj-eyebrow">Why ride with Juvo</span>
                <h2>Built around the rider</h2>
                <p>No guessing, no hidden deductions. You see the job, the route and the pay before you commit.</p>
            </div>

            <div class="jvj-cards">
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-clock"></i></div>
                    <h3>Your hours</h3>
                    <p>Go online when you're free. There's no shift roster to negotiate with.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-location-dot"></i></div>
                    <h3>Jobs near you</h3>
                    <p>Offers are based on where you are, so you spend less time riding empty.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                    <h3>Pay before you accept</h3>
                    <p>Each offer shows the delivery pay upfront. Accept only what makes sense for you.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-route"></i></div>
                    <h3>Full order details</h3>
                    <p>Pickup point, drop-off address and parcel notes are in the job card.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-chart-simple"></i></div>
                    <h3>Earnings you can check</h3>
                    <p>Your dashboard keeps a record of completed deliveries and what you earned.</p>
                </div>
                <div class="jvj-card jvj-reveal">
                    <div class="jvj-card-icon"><i class="fa-solid fa-headset"></i></div>
                    <h3>Support on the road</h3>
                    <p>Something wrong with a delivery? Reach the Juvo team without leaving the app.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section" style="background:#fafbfc;">
        <div class="container">
            <div class="jvj-section-head jvj-reveal">
                <span class="jvj-eyebrow">Getting started</span>
                <h2>Four steps to your first delivery</h2>
                <p>Registration asks for your details and documents up front, so verification is a single review.</p>
            </div>

            <div class="jvj-steps">
                <div class="jvj-step jvj-reveal">
                    <h3>Create your rider account</h3>
                    <p>Your name, contact details, date of birth and address.</p>
                </div>
                <div class="jvj-step jvj-reveal">
                    <h3>Add your vehicle and licence</h3>
                    <p>Vehicle type, plate, make and model, plus your driving licence details.</p>
                </div>
                <div class="jvj-step jvj-reveal">
                    <h3>Upload your documents</h3>
                    <p>ID, licence, vehicle registration and insurance, and the rest of the checklist below.</p>
                </div>
                <div class="jvj-step jvj-reveal">
                    <h3>Get verified and go online</h3>
                    <p>Once our team approves your KYC, your dashboard unlocks and offers start arriving.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section">
        <div class="container">
            <div class="jvj-split">
                <div class="jvj-reveal">
                    <span class="jvj-eyebrow">Be ready</span>
                    <h2 class="mt-2" style="font-size:clamp(1.35rem,3vw,2rem);font-weight:800;letter-spacing:-.02em;">Documents to have on your phone</h2>
                    <p class="text-muted mt-2 mb-3" style="line-height:1.65;">
                        Verification is manual, so incomplete uploads are the most common reason an
                        application waits. Have these ready as clear photos or PDFs before you start.
                    </p>
                    <ul class="jvj-check">
                        <li><i class="fa-solid fa-circle-check"></i> National ID — front and back</li>
                        <li><i class="fa-solid fa-circle-check"></i> Driving licence</li>
                        <li><i class="fa-solid fa-circle-check"></i> Vehicle registration and insurance</li>
                        <li><i class="fa-solid fa-circle-check"></i> Passport-size photo</li>
                        <li><i class="fa-solid fa-circle-check"></i> Proof of address and bank statement</li>
                    </ul>
                </div>

                <div class="jvj-panel jvj-reveal">
                    <h3 style="font-size:1rem;font-weight:700;margin-bottom:.35rem;">What happens after you apply</h3>
                    <p class="text-muted mb-3" style="font-size:.9rem;line-height:1.55;">
                        You can sign in immediately, but the dashboard stays locked until your KYC is approved.
                        You'll see your status on screen the whole time.
                    </p>
                    <div class="jvj-earn">
                        <div class="jvj-earn-row"><span>1. Application received</span><strong>Instant</strong></div>
                        <div class="jvj-earn-row"><span>2. Documents reviewed</span><strong>Under review</strong></div>
                        <div class="jvj-earn-row"><span>3. Account approved</span><strong>Offers start</strong></div>
                    </div>
                    <hr class="my-3">
                    <ul class="jvj-check">
                        <li><i class="fa-solid fa-check"></i> 18 years or older</li>
                        <li><i class="fa-solid fa-check"></i> Motorcycle, bicycle, van or truck</li>
                        <li><i class="fa-solid fa-check"></i> Valid licence that hasn't expired</li>
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

            <div class="accordion jvj-faq jvj-reveal" id="riderFaq">
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rfaq1" aria-expanded="false" aria-controls="rfaq1">
                            Do I need my own motorcycle?
                        </button>
                    </h3>
                    <div id="rfaq1" class="accordion-collapse collapse" data-bs-parent="#riderFaq">
                        <div class="accordion-body">
                            Yes. You register the vehicle you'll deliver with — motorcycle, bicycle, van or
                            truck — and upload its registration and insurance.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rfaq2" aria-expanded="false" aria-controls="rfaq2">
                            How long does verification take?
                        </button>
                    </h3>
                    <div id="rfaq2" class="accordion-collapse collapse" data-bs-parent="#riderFaq">
                        <div class="accordion-body">
                            Reviews are done by our team, so timing depends on volume. Uploading clear,
                            complete documents the first time is the fastest route to approval.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rfaq3" aria-expanded="false" aria-controls="rfaq3">
                            Can I deliver part-time?
                        </button>
                    </h3>
                    <div id="rfaq3" class="accordion-collapse collapse" data-bs-parent="#riderFaq">
                        <div class="accordion-body">
                            Yes. You decide when you go online, and you can decline offers that don't work
                            for you.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rfaq4" aria-expanded="false" aria-controls="rfaq4">
                            Why do you need bank details?
                        </button>
                    </h3>
                    <div id="rfaq4" class="accordion-collapse collapse" data-bs-parent="#riderFaq">
                        <div class="accordion-body">
                            Payouts are sent to your account, so we collect your bank name, account name,
                            number and branch during registration. These stay private to Juvo.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="jvj-section">
        <div class="container">
            <div class="jvj-cta jvj-reveal">
                <h2>Ready to start riding?</h2>
                <p>Apply once, get verified, then ride whenever you're free. Your first offer is one approval away.</p>
                <div class="jvj-actions">
                    <a href="{{ $registerUrl }}" class="jvj-btn jvj-btn-primary">
                        Apply to ride <i class="fa-solid fa-arrow-right"></i>
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
