<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;margin:0;padding:0;background:#f4f4f4;">
    <div style="max-width:560px;margin:24px auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #ececec;">
        <div style="background:#f68b1e;padding:18px 28px;color:#fff;font-size:22px;font-weight:800;">Juvo</div>
        <div style="padding:28px;">
            <h2 style="margin-top:0;color:#222;">Hello{{ $name ? " $name" : '' }},</h2>
            <p>Welcome to <strong>Juvo</strong>! Use the verification code below to confirm your email address.</p>
            <div style="background:#fff7ef;border:2px dashed #f68b1e;border-radius:10px;padding:24px;text-align:center;font-size:34px;letter-spacing:10px;font-weight:800;color:#f68b1e;">{{ $code }}</div>
            <p style="color:#888;font-size:13px;">This code expires after 30 minutes. If you did not create an account, you can ignore this email.</p>
        </div>
        <div style="padding:14px 28px;background:#fafafa;color:#aaa;font-size:12px;">&copy; {{ date('Y') }} {{ config('app.name', 'Juvo') }}</div>
    </div>
</body>
</html>