<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Juvo Rider | Login</title>
    <link rel="icon" type="image/png" href="{{ asset('images/juvo_logo_3d.png') }}">
    <link href="{{ asset('vendor/fonts/nunito.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('vendor/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <style>
        :root {
            --juvo-orange: #f68b1e;
            --juvo-orange-dark: #d9760c;
            --juvo-orange-light: #fff3e0;
            --juvo-ink: #313133;
            --juvo-dark: #282828;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Nunito", system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--juvo-ink);
            background:
                radial-gradient(120% 100% at 100% 0%, rgba(246, 139, 30, .18) 0%, rgba(246, 139, 30, 0) 52%),
                linear-gradient(160deg, #fffaf4 0%, #fff1df 100%);
            overflow-x: hidden;
        }

        .rider-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem 1rem;
        }

        .rider-card {
            width: 100%;
            max-width: 460px;
            background: rgba(255, 255, 255, .92);
            border: 1px solid rgba(246, 139, 30, .12);
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(40, 40, 40, .14);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .rider-brand {
            position: relative;
            padding: 2.25rem 2rem 1.75rem;
            text-align: center;
            color: #fff;
            background: linear-gradient(135deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 100%);
        }

        .rider-brand::after {
            content: "";
            position: absolute;
            inset: auto -40px -40px auto;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .12);
        }

        .rider-logo {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .rider-logo img {
            height: 52px;
            width: auto;
            filter: drop-shadow(0 6px 14px rgba(246, 139, 30, .35));
        }

        .rider-brand h1 {
            position: relative;
            z-index: 1;
            margin: 0;
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .rider-brand p {
            position: relative;
            z-index: 1;
            margin: .35rem 0 0;
            opacity: .92;
            font-weight: 600;
        }

        .rider-body {
            padding: 2rem;
        }

        .form-label {
            font-weight: 700;
            color: var(--juvo-dark);
            margin-bottom: .45rem;
        }

        .form-control {
            border: 2px solid #eee;
            border-radius: 12px;
            padding: .875rem 1rem;
            font-size: 1rem;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .form-control:focus {
            border-color: var(--juvo-orange);
            box-shadow: 0 0 0 4px var(--juvo-orange-light);
        }

        .input-group-text {
            background: #fafafa;
            border: 2px solid #eee;
            border-right: 0;
            border-radius: 12px 0 0 12px;
            color: var(--juvo-orange);
        }

        .input-group .form-control {
            border-left: 0;
            border-radius: 0 12px 12px 0;
        }

        .btn-juvo {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
            background: linear-gradient(135deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 100%);
            box-shadow: 0 12px 28px rgba(246, 139, 30, .32);
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .btn-juvo:hover {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(246, 139, 30, .42);
        }

        .link-juvo {
            color: var(--juvo-orange);
            font-weight: 700;
            text-decoration: none;
        }

        .link-juvo:hover {
            color: var(--juvo-orange-dark);
            text-decoration: underline;
        }

        .rider-footer {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid #eee;
            color: #75757a;
            font-size: .875rem;
        }

        /* Intro animation */
        html.ride-intro .login-stage {
            opacity: 0;
            transform: translateY(24px);
            pointer-events: none;
        }

        .login-stage {
            opacity: 1;
            transform: none;
            transition: opacity .55s ease, transform .55s ease;
        }

        .ride-overlay {
            position: fixed;
            inset: 0;
            z-index: 30;
            overflow: hidden;
            pointer-events: none;
            background: linear-gradient(160deg, #fffaf4 0%, #fff1df 100%);
        }

        .ride-bike {
            position: absolute;
            left: 0;
            bottom: 17vh;
            transform: translateX(-150vw);
            animation: rideAcross 2.15s cubic-bezier(.22, .72, .24, 1) forwards;
            filter: drop-shadow(0 18px 24px rgba(40, 40, 40, .18));
        }

        .ride-bike svg {
            width: 230px;
            height: auto;
            display: block;
        }

        .ride-bike .wheel {
            transform-origin: 50% 50%;
            animation: spin .42s linear infinite;
        }

        .ride-shadow {
            position: absolute;
            left: 50%;
            bottom: 14vh;
            width: 180px;
            height: 18px;
            margin-left: -90px;
            border-radius: 50%;
            background: radial-gradient(ellipse at center, rgba(40, 40, 40, .22) 0%, rgba(40, 40, 40, 0) 72%);
            transform: scaleX(.75);
            animation: shadowAcross 2.15s cubic-bezier(.22, .72, .24, 1) forwards;
        }

        .ride-dust {
            position: absolute;
            left: 50%;
            bottom: 16vh;
            width: 10px;
            height: 10px;
            margin-left: -5px;
            border-radius: 50%;
            background: rgba(246, 139, 30, .35);
            opacity: 0;
            animation: dustTrail 2.15s ease-out forwards;
        }

        @keyframes rideAcross {
            0% { transform: translateX(-150vw); }
            100% { transform: translateX(150vw); }
        }

        @keyframes shadowAcross {
            0% { transform: translateX(-150vw) scaleX(.75); opacity: .18; }
            100% { transform: translateX(150vw) scaleX(.75); opacity: .18; }
        }

        @keyframes dustTrail {
            0% { opacity: 0; transform: translate(-130vw, 0) scale(.4); }
            18% { opacity: .55; }
            100% { opacity: 0; transform: translate(130vw, -18px) scale(1.6); }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (prefers-reduced-motion: reduce) {
            .ride-overlay { display: none !important; }
            html.ride-intro .login-stage {
                opacity: 1;
                transform: none;
                pointer-events: auto;
            }
        }
    </style>
    <script>
        document.documentElement.classList.add('ride-intro');
    </script>
</head>
<body>
    <div class="ride-overlay" aria-hidden="true">
        <div class="ride-shadow"></div>
        <div class="ride-dust"></div>
        <div class="ride-bike">
            <svg viewBox="0 0 250 120" role="img" aria-label="Rider on a bicycle">
                <defs>
                    <linearGradient id="riderBody" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#f68b1e"/>
                        <stop offset="1" stop-color="#d9760c"/>
                    </linearGradient>
                </defs>

                <!-- rear wheel -->
                <g class="wheel">
                    <circle cx="58" cy="86" r="28" fill="none" stroke="#282828" stroke-width="8"/>
                    <circle cx="58" cy="86" r="4" fill="#282828"/>
                    <path d="M58 58v56M30 86h56M38 66l40 40M78 66l-40 40" stroke="#9aa0a6" stroke-width="2"/>
                </g>

                <!-- front wheel -->
                <g class="wheel">
                    <circle cx="198" cy="86" r="28" fill="none" stroke="#282828" stroke-width="8"/>
                    <circle cx="198" cy="86" r="4" fill="#282828"/>
                    <path d="M198 58v56M170 86h56M178 66l40 40M218 66l-40 40" stroke="#9aa0a6" stroke-width="2"/>
                </g>

                <!-- frame -->
                <path d="M58 86 L104 52 L150 52 L198 86" fill="none" stroke="#313133" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M104 52 L126 86 L150 52" fill="none" stroke="#313133" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M150 52 L178 40" fill="none" stroke="#313133" stroke-width="7" stroke-linecap="round"/>

                <!-- seat -->
                <rect x="92" y="44" width="34" height="8" rx="4" fill="#313133"/>

                <!-- handlebar -->
                <path d="M178 40 L190 30" fill="none" stroke="#313133" stroke-width="7" stroke-linecap="round"/>
                <path d="M186 24 L198 24" fill="none" stroke="#313133" stroke-width="7" stroke-linecap="round"/>

                <!-- rider -->
                <circle cx="122" cy="24" r="12" fill="url(#riderBody)"/>
                <path d="M122 36 L118 52 L104 52" fill="none" stroke="url(#riderBody)" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M118 52 L128 72 L150 72" fill="none" stroke="url(#riderBody)" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M118 52 L108 74 L92 82" fill="none" stroke="url(#riderBody)" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M150 72 L164 60 L178 42" fill="none" stroke="url(#riderBody)" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>

                <!-- motion accent -->
                <path d="M18 34 C34 24, 52 24, 68 34" fill="none" stroke="rgba(246,139,30,.35)" stroke-width="4" stroke-linecap="round"/>
            </svg>
        </div>
    </div>

    <div class="rider-shell">
        <div class="login-stage">
            <div class="rider-card">
                <div class="rider-brand">
                    <div class="rider-logo">
                        <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" onerror="this.style.display='none';">
                    </div>
                    <h1>Juvo Rider</h1>
                    <p>Sign in to your rider account</p>
                </div>

                <div class="rider-body">
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show rounded-3">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show rounded-3">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    @endif
                    @if (session('warning'))
                        <div class="alert alert-warning alert-dismissible fade show rounded-3">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    @endif

                    <form method="POST" action="{{ route('rider.login.post') }}">
                        @csrf
                        <div class="mb-4">
                            <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i>Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="rider@email.com" required autofocus>
                            </div>
                            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="Enter your password" required>
                            </div>
                            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4 d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label mb-0" for="remember">Remember me</label>
                            </div>
                            <a href="{{ route('rider.password.request') }}" class="link-juvo small">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn btn-juvo mb-3"><i class="fas fa-sign-in-alt me-2"></i>Sign In</button>
                    </form>

                    <div class="text-center mb-3">
                        <span class="text-muted">NEW RIDER?</span>
                    </div>

                    <div class="text-center">
                        <p class="text-muted mb-2">Ready to start earning?</p>
                        <a href="{{ route('rider.register') }}" class="btn btn-outline-warning w-100" style="border-radius: 12px; border-width: 2px;">
                            <i class="fas fa-user-plus me-2"></i>Register as Rider
                        </a>
                    </div>

                    <div class="rider-footer text-center">
                        <i class="fas fa-shield-alt me-1"></i> Powered by Juvo Logistics •
                        <a href="{{ url('/') }}" class="link-juvo">Back to Marketplace</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var overlay = document.querySelector('.ride-overlay');
            var bike = document.querySelector('.ride-bike');

            function revealForm() {
                document.documentElement.classList.remove('ride-intro');
                if (overlay) {
                    overlay.style.opacity = '0';
                    overlay.style.transition = 'opacity .35s ease';
                    window.setTimeout(function () {
                        overlay.style.display = 'none';
                    }, 360);
                }
            }

            if (reduceMotion) {
                revealForm();
                return;
            }

            if (bike) {
                bike.addEventListener('animationend', revealForm, { once: true });
            }

            window.setTimeout(revealForm, 2600);
        })();
    </script>
</body>
</html>
