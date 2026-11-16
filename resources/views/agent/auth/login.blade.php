@extends('admin.layouts.login')

@section('title', 'JTeam Console — Sign In')

@section('css')
<style>
:root {
    --juvo-orange: #f68b1e;
    --juvo-orange-dark: #d9760c;
    --juvo-ink: #313133;
}
html, body { height: 100%; margin: 0; }
body {
    font-family: 'Nunito', system-ui, -apple-system, 'Segoe UI', sans-serif;
    background: #f4f5f7;
    color: var(--juvo-ink);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    position: relative;
    overflow: hidden;
}
body::before,
body::after {
    content: "";
    position: fixed;
    border-radius: 50%;
    filter: blur(90px);
    opacity: .55;
    pointer-events: none;
}
body::before {
    width: 520px; height: 520px;
    top: -220px; left: -160px;
    background: radial-gradient(circle, rgba(246,139,30,.35), rgba(246,139,30,0) 70%);
    animation: jt-drift 14s ease-in-out infinite;
}
body::after {
    width: 460px; height: 460px;
    bottom: -200px; right: -140px;
    background: radial-gradient(circle, rgba(217,118,12,.28), rgba(217,118,12,0) 70%);
    animation: jt-drift 18s ease-in-out infinite reverse;
}
.jt-login-shell {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 420px;
    padding: 20px;
}
.jt-login-card {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 28px 70px rgba(40,40,40,.16);
    animation: jt-pop .55s cubic-bezier(.22,.8,.28,1) both;
}
.jt-login-banner {
    position: relative;
    overflow: hidden;
    padding: 26px 28px;
    color: #fff;
    background: linear-gradient(135deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 60%, #b85f06 100%);
}
.jt-login-banner::after {
    content: "";
    position: absolute;
    width: 180px; height: 180px;
    right: -60px; top: -80px;
    border-radius: 50%;
    background: rgba(255,255,255,.14);
}
.jt-login-banner .jt-logo-row {
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    z-index: 1;
}
.jt-login-banner img { height: 36px; width: auto; }
.jt-login-banner strong { font-size: 20px; font-weight: 900; letter-spacing: -.02em; }
.jt-login-banner small { display: block; font-size: 10px; letter-spacing: .16em; text-transform: uppercase; opacity: .85; }
.jt-login-banner h1 { position: relative; z-index: 1; font-size: 23px; font-weight: 900; margin: 20px 0 4px; letter-spacing: -.02em; }
.jt-login-banner p { position: relative; z-index: 1; margin: 0; font-size: 13px; opacity: .9; }
.jt-login-body { padding: 26px 28px 30px; }
.form-label { font-weight: 800; font-size: 12px; color: var(--juvo-ink); text-transform: uppercase; letter-spacing: .04em; }
.form-control {
    height: 48px;
    border-radius: 10px;
    border: 1px solid #e2e2e6;
    font-size: 15px;
}
.form-control:focus { border-color: var(--juvo-orange); box-shadow: 0 0 0 .2rem rgba(246,139,30,.18); }
.jt-submit {
    height: 50px;
    width: 100%;
    border: none;
    border-radius: 10px;
    font-weight: 900;
    font-size: 15px;
    color: #fff;
    background: linear-gradient(135deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 100%);
    box-shadow: 0 10px 24px rgba(246,139,30,.35);
    transition: transform .15s ease, box-shadow .15s ease;
}
.jt-submit:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(246,139,30,.45); }
.jt-submit:active { transform: scale(.99); }
.jt-footnote { text-align: center; color: #75757a; font-size: 12px; margin: 18px 0 0; }
@keyframes jt-pop { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: none; } }
@keyframes jt-drift { 0%, 100% { transform: translate(0,0); } 50% { transform: translate(18px,-16px); } }
@media (prefers-reduced-motion: reduce) { body::before, body::after, .jt-login-card { animation: none !important; } }
@media (max-width: 575.98px) {
    body { overflow: auto; padding: 12px; }
    .jt-login-shell { padding: 8px; max-width: 100%; }
    .jt-login-card { border-radius: 16px; }
    .jt-login-banner { padding: 20px 18px; }
    .jt-login-banner h1 { font-size: 20px; }
    .jt-login-body { padding: 20px 18px 24px; }
    .form-control, .jt-submit { height: 46px; font-size: 16px; }
    body::before, body::after { display: none; }
}
</style>
@endsection

@section('content')
<div class="jt-login-shell">
    <div class="jt-login-card">
        <div class="jt-login-banner">
            <div class="jt-logo-row">
                <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" onerror="this.style.display='none';">
                <div>
                    <strong>JTeam</strong>
                    <small>Sales Console</small>
                </div>
            </div>
            <h1>Welcome back</h1>
            <p>Sign in to your independent JTeam workspace.</p>
        </div>
        <div class="jt-login-body">
            @error('password')
                <div class="alert alert-danger py-2" role="alert">{{ $message }}</div>
            @enderror
            @error('email')
                <div class="alert alert-danger py-2" role="alert">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('agent.login') }}" autocomplete="off">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email or username</label>
                    <input type="text" class="form-control @error('email') is-invalid @enderror"
                           name="email" value="{{ old('email') }}" id="email"
                           placeholder="you@example.com" required autofocus>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror"
                           name="password" id="password" placeholder="Your password" required>
                </div>
                <button type="submit" class="jt-submit">Sign in to JTeam</button>
            </form>

            <p class="jt-footnote">
                New to JTeam? <a href="{{ route('agent.register') }}" style="color:var(--juvo-orange);font-weight:800;">Self-register here</a>.
            </p>
        </div>
    </div>
</div>
@endsection
