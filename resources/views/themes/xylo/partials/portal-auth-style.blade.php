{{--
    Shared Juvo-branded portal auth styling (vendor/admin sign-in).
    Pulls the storefront brand palette: orange #f68b1e, ink #313133, dark #282828.
--}}
<style>
:root {
    --juvo-orange: #f68b1e;
    --juvo-orange-dark: #d9760c;
    --juvo-yellow: #fcd200;
    --juvo-ink: #313133;
    --juvo-dark: #282828;
}
html, body { height: 100%; margin: 0; }
.juvo-portal {
    font-family: 'Roboto', 'Poppins', system-ui, -apple-system, 'Segoe UI', sans-serif;
    color: var(--juvo-ink);
    background: #f4f5f7;
}
.juvo-portal-shell { min-height: 100vh; display: grid; grid-template-columns: 1.05fr 1fr; }

.juvo-portal-brand {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 26px;
    padding: 48px 56px;
    color: #fff;
    overflow: hidden;
    background:
        radial-gradient(120% 120% at 100% 0%, rgba(252,210,0,.22) 0%, rgba(252,210,0,0) 55%),
        linear-gradient(150deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 55%, #b85f06 100%);
}
.juvo-portal-brand::after {
    content: "";
    position: absolute;
    right: -120px; bottom: -120px;
    width: 340px; height: 340px;
    border-radius: 50%;
    background: rgba(255,255,255,.08);
}
.juvo-portal-logo { display: flex; align-items: center; gap: 12px; position: relative; z-index: 1; }
.juvo-portal-logo img { height: 54px; width: auto; }
.juvo-portal-word { font-size: 30px; font-weight: 900; letter-spacing: -.5px; line-height: 1; color: #fff; }
.juvo-portal-word span { color: #fff; }
.juvo-portal-smmark .juvo-portal-word { color: var(--juvo-ink); font-size: 24px; }
.juvo-portal-smmark .juvo-portal-word span { color: var(--juvo-orange); }

.juvo-portal-copy { position: relative; z-index: 1; max-width: 440px; }
.juvo-portal-tag {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,.18);
    padding: 6px 14px; border-radius: 20px;
    font-size: 13px; font-weight: 700; margin-bottom: 16px;
}
.juvo-portal-copy h1 { font-size: 34px; font-weight: 800; line-height: 1.15; margin: 0 0 14px; }
.juvo-portal-copy p { font-size: 16px; opacity: .92; margin: 0; }

.juvo-portal-points { list-style: none; padding: 0; margin: 0; position: relative; z-index: 1; }
.juvo-portal-points li { display: flex; align-items: center; gap: 10px; font-size: 14px; opacity: .95; margin-bottom: 10px; }
.juvo-portal-points i { color: var(--juvo-yellow); }
.juvo-portal-foot { position: relative; z-index: 1; font-size: 13px; opacity: .85; }

.juvo-portal-panel { display: flex; align-items: center; justify-content: center; padding: 40px 24px; }
.juvo-portal-card {
    width: 100%; max-width: 400px;
    background: #fff; border-radius: 16px;
    padding: 38px 34px;
    box-shadow: 0 18px 50px rgba(40,40,40,.12);
}
.juvo-portal-card h2 { font-size: 24px; font-weight: 800; margin: 0 0 4px; }
.juvo-portal-sub { color: #75757a; font-size: 14px; margin: 0 0 22px; }
.juvo-portal-smmark { display: none; align-items: center; gap: 10px; margin-bottom: 18px; }
.juvo-portal-smmark img { height: 42px; }

.juvo-portal-tabs { display: flex; gap: 8px; margin-bottom: 22px; background: #f1f1f2; border-radius: 10px; padding: 4px; }
.juvo-portal-tabs a {
    flex: 1; text-align: center; padding: 9px 0; border-radius: 8px;
    font-size: 14px; font-weight: 700; color: #75757a; text-decoration: none;
    transition: background .2s ease, color .2s ease;
}
.juvo-portal-tabs a.active { background: #fff; color: var(--juvo-orange); box-shadow: 0 2px 8px rgba(0,0,0,.06); }

.juvo-portal .form-label { font-weight: 700; font-size: 13px; }
.juvo-portal .form-control { height: 48px; border-radius: 10px; border: 1px solid #e2e2e6; font-size: 15px; }
.juvo-portal .form-control:focus { border-color: var(--juvo-orange); box-shadow: 0 0 0 .2rem rgba(246,139,30,.18); }

.juvo-portal-submit {
    height: 50px; width: 100%; border: none; border-radius: 10px;
    font-weight: 800; font-size: 15px; color: #fff;
    background: linear-gradient(135deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 100%);
    box-shadow: 0 10px 24px rgba(246,139,30,.35);
    transition: transform .15s ease, box-shadow .15s ease;
}
.juvo-portal-submit:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(246,139,30,.45); }
.juvo-portal-submit:active { transform: scale(.99); }
.juvo-portal-foot-links { margin: 22px 0 0; font-size: 13px; color: #75757a; line-height: 1.9; }
.juvo-portal-foot-links a { color: var(--juvo-orange); font-weight: 700; }

@media (max-width: 900px) {
    .juvo-portal-shell { grid-template-columns: 1fr; }
    .juvo-portal-brand { display: none; }
    .juvo-portal-smmark { display: flex; }
    .juvo-portal-panel { min-height: 100vh; background: linear-gradient(160deg, #fff 0%, #fff7ef 100%); }
}
</style>
