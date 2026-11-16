<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JTeam | Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f5f7; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; }
        .card { border:none; border-radius:16px; box-shadow:0 18px 50px rgba(40,40,40,.10); }
        .btn-juvo { background:#f68b1e; border-color:#f68b1e; color:#fff; }
        .btn-juvo:hover { background:#d9760c; border-color:#d9760c; color:#fff; }
    </style>
</head>
<body class="py-4">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card p-4 p-md-5">
                <h1 class="h3 mb-1">Join JTeam</h1>
                <p class="text-muted mb-4">Self-register as a Juvo sales consultant. Your account will be reviewed before activation.</p>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('agent.register.post') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email address</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone number</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">National ID</label>
                            <input type="text" name="national_id" class="form-control" value="{{ old('national_id') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2" required>{{ old('address') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Next of kin name</label>
                            <input type="text" name="next_of_kin_name" class="form-control" value="{{ old('next_of_kin_name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Next of kin phone</label>
                            <input type="text" name="next_of_kin_phone" class="form-control" value="{{ old('next_of_kin_phone') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm password</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="terms" value="1" id="terms" required>
                                <label class="form-check-label" for="terms">I accept the JTeam terms and conditions.</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-juvo mt-4 w-100">Submit Registration</button>
                </form>

                <div class="text-center mt-3">
                    <a href="{{ route('agent.login') }}" class="text-decoration-none">Already registered? Sign in</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
