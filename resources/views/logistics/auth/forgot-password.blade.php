<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Juvo Logistics Admin | Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('vendor/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <style>
        :root { --juvo-orange: #f68b1e; --juvo-orange-dark: #e67e1a; --juvo-orange-light: #fff3e0; --juvo-dark: #1a1a2e; }
        body { background: linear-gradient(135deg, #fff8f0 0%, #ffe8cc 100%); min-height: 100vh; font-family: 'Inter', -apple-system, sans-serif; }
        .card { border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(246, 139, 30, 0.15); overflow: hidden; }
        .brand-header { background: linear-gradient(135deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 100%); padding: 3rem 2rem; text-align: center; }
        .brand-header .logo { width: 80px; height: 80px; background: white; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .brand-header .logo i { font-size: 2.5rem; color: var(--juvo-orange); }
        .brand-header h1 { color: white; font-weight: 700; font-size: 1.75rem; margin: 0; }
        .brand-header p { color: rgba(255,255,255,0.9); margin: 0; font-size: 0.95rem; }
        .form-control { border-radius: 12px; border: 2px solid #eee; padding: 0.875rem 1rem; font-size: 1rem; transition: all 0.3s ease; }
        .form-control:focus { border-color: var(--juvo-orange); box-shadow: 0 0 0 4px var(--juvo-orange-light); }
        .form-label { font-weight: 600; color: var(--juvo-dark); margin-bottom: 0.5rem; }
        .btn-juvo { background: linear-gradient(135deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 100%); border: none; border-radius: 12px; padding: 1rem; font-weight: 600; font-size: 1rem; color: white; transition: all 0.3s ease; width: 100%; }
        .btn-juvo:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(246, 139, 30, 0.4); color: white; }
        .input-group-text { background: var(--juvo-gray); border: 2px solid #eee; border-right: none; border-radius: 12px 0 0 12px; }
        .input-group .form-control { border-left: none; border-radius: 0 12px 12px 0; }
        .link-juvo { color: var(--juvo-orange); font-weight: 600; text-decoration: none; }
        .link-juvo:hover { color: var(--juvo-orange-dark); text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5 col-xl-4">
                <div class="card">
                    <div class="brand-header">
                        <div class="logo"><i class="fas fa-key"></i></div>
                        <h1>Forgot Password?</h1>
                        <p>Enter your email and we'll send you a reset link</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show rounded-3">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                        @endif
                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show rounded-3">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                        @endif

                        <form method="POST" action="{{ route('logistics.password.email') }}">
                            @csrf
                            <div class="mb-4">
                                <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i>Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="admin@example.com" required autofocus>
                                </div>
                                @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-juvo mb-3"><i class="fas fa-paper-plane me-2"></i>Send Reset Link</button>
                        </form>

                        <div class="text-center mt-4">
                            <a href="{{ route('logistics.login') }}" class="link-juvo"><i class="fas fa-arrow-left me-2"></i>Back to Login</a>
                        </div>

                        <hr class="my-4">
                        <div class="text-center text-muted small">
                            <i class="fas fa-shield-alt me-1"></i> Secured by Juvo Logistics
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
