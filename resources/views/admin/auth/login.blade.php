@extends('admin.layouts.login')

@section('title', 'Juvo Admin — Console')

@section('css')
<style>
:root {
    --juvo-orange: #f68b1e;
    --juvo-orange-dark: #d9760c;
    --juvo-ink: #313133;
    --juvo-dark: #282828;
}
html, body { height: 100%; margin: 0; }
body { display: block; font-family: 'Nunito', system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f4f5f7; color: var(--juvo-ink); }

.juvo-auth {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1.05fr 1fr;
}
.juvo-auth-brand {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 48px 56px;
    color: #fff;
    background:
        radial-gradient(120% 120% at 100% 0%, rgba(252,210,0,.22) 0%, rgba(252,210,0,0) 55%),
        linear-gradient(150deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 55%, #b85f06 100%);
    overflow: hidden;
}
.juvo-auth-brand::after {
    content: "";
    position: absolute;
    right: -120px;
    bottom: -120px;
    width: 340px;
    height: 340px;
    border-radius: 50%;
    background: rgba(255,255,255,.08);
}
.juvo-auth-brand::before {
    animation: juvo-float 12s ease-in-out infinite, juvo-aurora 8s ease-in-out infinite;
}
.juvo-auth-brand .juvo-aurora {
    position: absolute;
    inset: -20%;
    border-radius: 50%;
    background: conic-gradient(from 0deg, rgba(255,255,255,.18), rgba(252,210,0,.22), rgba(255,255,255,.06), rgba(246,139,30,.18), rgba(255,255,255,.18));
    filter: blur(28px);
    animation: juvo-aurora-spin 20s linear infinite;
    pointer-events: none;
}
.juvo-brand-logo { display: flex; align-items: center; gap: 12px; position: relative; z-index: 1; }
.juvo-brand-logo img { height: 54px; width: auto; }
.juvo-brand-name { font-size: 30px; font-weight: 900; letter-spacing: -.5px; line-height: 1; }
.juvo-brand-name span { color: #fff; }
.juvo-brand-copy { position: relative; z-index: 1; max-width: 420px; }
.juvo-brand-copy h1 { font-size: 34px; font-weight: 800; line-height: 1.15; margin: 0 0 14px; }
.juvo-brand-copy p { font-size: 16px; opacity: .92; margin: 0; }
.juvo-brand-foot { position: relative; z-index: 1; font-size: 13px; opacity: .85; }

.juvo-auth-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 24px;
}
.juvo-auth-card {
    width: 100%;
    max-width: 400px;
    background: #fff;
    border-radius: 16px;
    padding: 38px 34px;
    box-shadow: 0 18px 50px rgba(40,40,40,.12);
}
.juvo-auth-card h2 { font-size: 24px; font-weight: 800; margin: 0 0 4px; }
.juvo-auth-card .juvo-sub { color: #75757a; font-size: 14px; margin: 0 0 24px; }
.juvo-logo-sm { display: none; align-items: center; gap: 10px; margin-bottom: 20px; }
.juvo-logo-sm img { height: 42px; }
.juvo-logo-sm .juvo-brand-name { color: var(--juvo-ink); font-size: 24px; }

.form-label { font-weight: 700; font-size: 13px; color: var(--juvo-ink); }
.form-control {
    height: 48px;
    border-radius: 10px;
    border: 1px solid #e2e2e6;
    font-size: 15px;
}
.form-control:focus { border-color: var(--juvo-orange); box-shadow: 0 0 0 .2rem rgba(246,139,30,.18); }
.juvo-submit {
    height: 50px;
    width: 100%;
    border: none;
    border-radius: 10px;
    font-weight: 800;
    font-size: 15px;
    color: #fff;
    background: linear-gradient(135deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 100%);
    box-shadow: 0 10px 24px rgba(246,139,30,.35);
    transition: transform .15s ease, box-shadow .15s ease;
}
.juvo-submit:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(246,139,30,.45); }
.juvo-submit:active { transform: scale(.99); }
.juvo-back { display: inline-flex; align-items: center; gap: 6px; margin-top: 22px; font-size: 13px; color: #75757a; text-decoration: none; }
.juvo-back:hover { color: var(--juvo-orange); }

@keyframes juvo-gradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
@keyframes juvo-float { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(-24px, 26px) scale(1.08); } 100% { transform: translate(0, 0) scale(1); } }
@keyframes juvo-rise { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }
@keyframes juvo-pop { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: none; } }
@keyframes juvo-shine { 0% { transform: translateX(-140%) skewX(-18deg); } 100% { transform: translateX(240%) skewX(-18deg); } }
@keyframes juvo-aurora { 0%, 100% { transform: translate(0, 0) scale(1); opacity: .55; } 50% { transform: translate(18px, -14px) scale(1.12); opacity: .8; } }
@keyframes juvo-aurora-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.juvo-auth-brand { background-size: 220% 220%; animation: juvo-gradient 16s ease infinite; }
.juvo-auth-brand::before {
    content: "";
    position: absolute;
    top: -60px;
    left: -60px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255,255,255,.10);
    animation: juvo-float 12s ease-in-out infinite;
}
.juvo-brand-copy h1, .juvo-brand-copy p, .juvo-brand-logo, .juvo-brand-foot { animation: juvo-rise .7s ease both; }
.juvo-brand-copy h1 { animation-delay: .08s; }
.juvo-brand-copy p { animation-delay: .16s; }
.juvo-brand-foot { animation-delay: .24s; }

.juvo-auth-card { animation: juvo-pop .55s cubic-bezier(.22,.8,.28,1) both; }
.juvo-auth-card .form-label, .juvo-auth-card .form-control, .juvo-auth-card .form-check, .juvo-auth-card .alert { animation: juvo-rise .5s ease both; }
.juvo-auth-card .form-label:nth-of-type(2) { animation-delay: .04s; }

.juvo-submit { position: relative; overflow: hidden; }
.juvo-submit::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 40%;
    height: 100%;
    background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.45) 50%, rgba(255,255,255,0) 100%);
    transform: translateX(-140%) skewX(-18deg);
    animation: juvo-shine 3.2s ease-in-out infinite;
    pointer-events: none;
}

@media (prefers-reduced-motion: reduce) {
    .juvo-auth-brand, .juvo-auth-brand::before, .juvo-auth-brand .juvo-aurora, .juvo-auth-brand *, .juvo-auth-card, .juvo-auth-card *, .juvo-submit::after {
        animation: none !important;
    }
}

@media (max-width: 900px) {
    .juvo-auth { grid-template-columns: 1fr; }
    .juvo-auth-brand { display: none; }
    .juvo-logo-sm { display: flex; }
    .juvo-auth-panel { min-height: 100vh; background: linear-gradient(160deg, #fff 0%, #fff7ef 100%); }
}
</style>
@endsection

@section('content')
<div class="juvo-auth">
    <div class="juvo-auth-brand">
        <div class="juvo-aurora" aria-hidden="true"></div>
        <div class="juvo-brand-logo">
            <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo"
                 onerror="this.style.display='none';">
            <span class="juvo-brand-name">Ju<span style="color:#fff;">vo</span></span>
        </div>

        <div class="juvo-brand-copy">
            <h1>Run the Juvo marketplace.</h1>
            <p>Sign in to manage vendors, products, orders, finance and the whole platform from one console.</p>
        </div>

        <div class="juvo-brand-foot">&copy; {{ date('Y') }} Juvo. All rights reserved.</div>
    </div>

    <div class="juvo-auth-panel">
        @include('admin.partials.login-network')
        <div class="juvo-auth-card">
            <div class="juvo-logo-sm">
                <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" onerror="this.style.display='none';">
                <span class="juvo-brand-name">Ju<span style="color:var(--juvo-orange);">vo</span></span>
            </div>

            <h2>{{ cms_translate('auth.login') }}</h2>
            <p class="juvo-sub">Welcome back — sign in to your console.</p>

            @error('password')
                <div class="alert alert-danger py-2" role="alert">{{ $message }}</div>
            @enderror
            @error('email')
                <div class="alert alert-danger py-2" role="alert">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('login') }}" autocomplete="off">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">{{ 'Email or username' }}</label>
                    <input type="text" class="form-control @error('email') is-invalid @enderror"
                           name="email" value="{{ old('email') }}" id="email"
                           placeholder="{{ 'Email or username' }}" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">{{ cms_translate('auth.password') }}</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror"
                           name="password" id="password" placeholder="{{ cms_translate('auth.password') }}" required>
                </div>
                <div class="mb-4">
                    <p class="juvo-sub mb-0" style="font-size:12.5px;">Password resets are issued by a platform administrator.</p>
                </div>
                <button type="submit" class="juvo-submit">{{ cms_translate('auth.login') }}</button>
            </form>

            
        </div>
    </div>
</div>
@endsection
