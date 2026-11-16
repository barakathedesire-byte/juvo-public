<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Juvo Logistics Admin | Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('vendor/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <style>
        :root { --juvo-orange: #f68b1e; --juvo-orange-dark: #e67e1a; --juvo-orange-light: #fff3e0; --juvo-dark: #1a1a2e; }
        body { background: linear-gradient(135deg, #fff8f0 0%, #ffe8cc 100%); min-height: 100vh; font-family: 'Inter', -apple-system, sans-serif; }
        .register-card { border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(246, 139, 30, 0.15); overflow: hidden; }
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
        .password-strength { height: 4px; border-radius: 2px; margin-top: 0.5rem; background: #eee; overflow: hidden; }
        .password-strength-bar { height: 100%; transition: all 0.3s ease; border-radius: 2px; }
        .link-juvo { color: var(--juvo-orange); font-weight: 600; text-decoration: none; }
        .link-juvo:hover { color: var(--juvo-orange-dark); text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-7 col-xl-6">
                <div class="card register-card">
                    <div class="brand-header">
                        <div class="logo"><i class="fas fa-user-plus"></i></div>
                        <h1>Create Admin Account</h1>
                        <p>Join the Juvo Logistics team</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show rounded-3">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                        @endif

                        <form method="POST" action="{{ route('logistics.register') }}" id="registerForm">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label"><i class="fas fa-user me-2"></i>Full Name</label>
                                    <div class="input-group"><span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="John Doe" required>
                                    </div>
                                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i>Email Address</label>
                                    <div class="input-group"><span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="admin@example.com" required>
                                    </div>
                                    @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label"><i class="fas fa-phone me-2"></i>Phone Number</label>
                                    <div class="input-group"><span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+255 7XX XXX XXX">
                                    </div>
                                    @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label"><i class="fas fa-user-tag me-2"></i>Role</label>
                                    <select class="form-control @error('role') is-invalid @enderror" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Administrator</option>
                                        <option value="dispatcher" {{ old('role') == 'dispatcher' ? 'selected' : '' }}>Dispatcher</option>
                                        <option value="support" {{ old('role') == 'support' ? 'selected' : '' }}>Support Agent</option>
                                    </select>
                                    @error('role')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                                    <div class="input-group"><span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="Min 8 chars, upper, lower, number, symbol" required>
                                    </div>
                                    <div class="password-strength"><div class="password-strength-bar" id="passwordStrength" style="width: 0%;"></div></div>
                                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    <div class="form-text small">Use 8+ characters with uppercase, lowercase, number & symbol</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirmation" class="form-label"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                                    <div class="input-group"><span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" name="password_confirmation" placeholder="Confirm password" required>
                                    </div>
                                    @error('password_confirmation')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <button type="submit" class="btn btn-juvo mb-3"><i class="fas fa-user-plus me-2"></i>Create Account</button>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">Already have an account? <a href="{{ route('logistics.login') }}" class="link-juvo">Sign in</a></p>
                        </div>

                        <hr class="my-4">
                        <div class="text-center text-muted small">
                            <i class="fas fa-shield-alt me-1"></i> Secured by Juvo Logistics • 
                            <a href="{{ url('/') }}" class="link-juvo">Back to Marketplace</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('passwordStrength');
        passwordInput.addEventListener('input', function() {
            const val = this.value;
            let strength = 0;
            if (val.length >= 8) strength += 25;
            if (/[A-Z]/.test(val)) strength += 25;
            if (/[a-z]/.test(val)) strength += 25;
            if (/[0-9]/.test(val)) strength += 12.5;
            if (/[^A-Za-z0-9]/.test(val)) strength += 12.5;
            strength = Math.min(100, strength);
            strengthBar.style.width = strength + '%';
            if (strength < 50) strengthBar.style.background = '#ef4444';
            else if (strength < 75) strengthBar.style.background = '#f59e0b';
            else strengthBar.style.background = '#22c55e';
        });
    </script>
</body>
</html>
