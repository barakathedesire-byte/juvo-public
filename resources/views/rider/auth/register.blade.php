<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Juvo Rider | Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('vendor/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <style>
        :root { --juvo-orange: #f68b1e; --juvo-orange-dark: #e67e1a; --juvo-orange-light: #fff3e0; --juvo-dark: #1a1a2e; }
        body { background: linear-gradient(135deg, #fff8f0 0%, #ffe8cc 100%); min-height: 100vh; font-family: 'Inter', -apple-system, sans-serif; }
        .register-card { border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(246, 139, 30, 0.15); overflow: hidden; }
        .brand-header { background: linear-gradient(135deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 100%); padding: 2.5rem 2rem; text-align: center; }
        .brand-header .logo { width: 72px; height: 72px; background: white; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
        .brand-header .logo i { font-size: 2.25rem; color: var(--juvo-orange); }
        .brand-header h1 { color: white; font-weight: 700; font-size: 1.6rem; margin: 0; }
        .brand-header p { color: rgba(255,255,255,0.9); margin: 0; font-size: 0.95rem; }
        .form-control, .form-select { border-radius: 10px; border: 2px solid #eee; padding: 0.7rem 0.9rem; }
        .form-control:focus, .form-select:focus { border-color: var(--juvo-orange); box-shadow: 0 0 0 4px var(--juvo-orange-light); }
        .form-label { font-weight: 600; color: var(--juvo-dark); font-size: 0.9rem; margin-bottom: 0.35rem; }
        .btn-juvo { background: linear-gradient(135deg, var(--juvo-orange) 0%, var(--juvo-orange-dark) 100%); border: none; border-radius: 12px; padding: 1rem; font-weight: 600; color: white; width: 100%; }
        .btn-juvo:hover { color: white; opacity: 0.92; }
        .section-title { font-weight: 700; color: var(--juvo-dark); border-left: 4px solid var(--juvo-orange); padding-left: 0.75rem; margin: 2rem 0 1rem; font-size: 1.05rem; }
        .link-juvo { color: var(--juvo-orange); font-weight: 600; text-decoration: none; }
        .link-juvo:hover { color: var(--juvo-orange-dark); text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card register-card">
                    <div class="brand-header">
                        <div class="logo"><i class="fas fa-user-plus"></i></div>
                        <h1>Become a Juvo Rider</h1>
                        <p>Complete your profile and KYC documents to start delivering</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        @if ($errors->any())
                            <div class="alert alert-danger rounded-3">
                                <strong><i class="fas fa-exclamation-circle me-2"></i>Please fix the following:</strong>
                                <ul class="mb-0 mt-2">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('rider.register.post') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="section-title"><i class="fas fa-user me-2"></i>Personal Information</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="first_name">First Name</label>
                                    <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="last_name">Last Name</label>
                                    <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="email">Email Address</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="phone">Phone Number</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" required>
                                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="national_id">National ID Number</label>
                                    <input type="text" class="form-control @error('national_id') is-invalid @enderror" id="national_id" name="national_id" value="{{ old('national_id') }}" required>
                                    @error('national_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="date_of_birth">Date of Birth</label>
                                    <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" required>
                                    @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="gender">Gender</label>
                                    <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                                        <option value="">Select gender</option>
                                        @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('gender') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="city">City</label>
                                    <input type="text" class="form-control @error('city') is-invalid @enderror" id="city" name="city" value="{{ old('city') }}" required>
                                    @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="state">State / Region</label>
                                    <input type="text" class="form-control @error('state') is-invalid @enderror" id="state" name="state" value="{{ old('state') }}" required>
                                    @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="postal_code">Postal Code <span class="text-muted">(optional)</span></label>
                                    <input type="text" class="form-control @error('postal_code') is-invalid @enderror" id="postal_code" name="postal_code" value="{{ old('postal_code') }}">
                                    @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="address">Residential Address</label>
                                    <input type="text" class="form-control @error('address') is-invalid @enderror" id="address" name="address" value="{{ old('address') }}" required>
                                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="section-title"><i class="fas fa-lock me-2"></i>Account Security</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="password">Password</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
                                    <div class="form-text">Min 8 characters with upper/lowercase, a number and a symbol.</div>
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                                </div>
                            </div>

                            <div class="section-title"><i class="fas fa-motorcycle me-2"></i>Vehicle Information</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="vehicle_type">Vehicle Type</label>
                                    <select class="form-select @error('vehicle_type') is-invalid @enderror" id="vehicle_type" name="vehicle_type" required>
                                        <option value="">Select vehicle type</option>
                                        @foreach (['motorcycle' => 'Motorcycle', 'bicycle' => 'Bicycle', 'van' => 'Van', 'truck' => 'Truck'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('vehicle_type') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('vehicle_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="license_plate">License Plate</label>
                                    <input type="text" class="form-control @error('license_plate') is-invalid @enderror" id="license_plate" name="license_plate" value="{{ old('license_plate') }}" required>
                                    @error('license_plate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="vehicle_make">Vehicle Make</label>
                                    <input type="text" class="form-control @error('vehicle_make') is-invalid @enderror" id="vehicle_make" name="vehicle_make" value="{{ old('vehicle_make') }}" required>
                                    @error('vehicle_make')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="vehicle_model">Vehicle Model</label>
                                    <input type="text" class="form-control @error('vehicle_model') is-invalid @enderror" id="vehicle_model" name="vehicle_model" value="{{ old('vehicle_model') }}" required>
                                    @error('vehicle_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="vehicle_year">Vehicle Year</label>
                                    <input type="number" class="form-control @error('vehicle_year') is-invalid @enderror" id="vehicle_year" name="vehicle_year" min="2000" max="{{ date('Y') + 1 }}" value="{{ old('vehicle_year') }}" required>
                                    @error('vehicle_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="vehicle_color">Vehicle Color</label>
                                    <input type="text" class="form-control @error('vehicle_color') is-invalid @enderror" id="vehicle_color" name="vehicle_color" value="{{ old('vehicle_color') }}" required>
                                    @error('vehicle_color')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="license_number">Driving License Number</label>
                                    <input type="text" class="form-control @error('license_number') is-invalid @enderror" id="license_number" name="license_number" value="{{ old('license_number') }}" required>
                                    @error('license_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="license_expiry">License Expiry Date</label>
                                    <input type="date" class="form-control @error('license_expiry') is-invalid @enderror" id="license_expiry" name="license_expiry" value="{{ old('license_expiry') }}" required>
                                    @error('license_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="section-title"><i class="fas fa-university me-2"></i>Banking Details</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="bank_name">Bank Name</label>
                                    <input type="text" class="form-control @error('bank_name') is-invalid @enderror" id="bank_name" name="bank_name" value="{{ old('bank_name') }}" required>
                                    @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="bank_account_name">Account Name</label>
                                    <input type="text" class="form-control @error('bank_account_name') is-invalid @enderror" id="bank_account_name" name="bank_account_name" value="{{ old('bank_account_name') }}" required>
                                    @error('bank_account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="bank_account_number">Account Number</label>
                                    <input type="text" class="form-control @error('bank_account_number') is-invalid @enderror" id="bank_account_number" name="bank_account_number" value="{{ old('bank_account_number') }}" required>
                                    @error('bank_account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="bank_branch">Bank Branch</label>
                                    <input type="text" class="form-control @error('bank_branch') is-invalid @enderror" id="bank_branch" name="bank_branch" value="{{ old('bank_branch') }}" required>
                                    @error('bank_branch')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="section-title"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="emergency_contact_name">Contact Name</label>
                                    <input type="text" class="form-control @error('emergency_contact_name') is-invalid @enderror" id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" required>
                                    @error('emergency_contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="emergency_contact_phone">Contact Phone</label>
                                    <input type="text" class="form-control @error('emergency_contact_phone') is-invalid @enderror" id="emergency_contact_phone" name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" required>
                                    @error('emergency_contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="emergency_contact_relationship">Relationship</label>
                                    <input type="text" class="form-control @error('emergency_contact_relationship') is-invalid @enderror" id="emergency_contact_relationship" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship') }}" required>
                                    @error('emergency_contact_relationship')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="section-title"><i class="fas fa-id-card me-2"></i>KYC Documents</div>
                            <div class="row g-3">
                                @php
                                    $documents = [
                                        'national_id_front' => ['National ID (Front)', true],
                                        'national_id_back' => ['National ID (Back)', true],
                                        'driving_license' => ['Driving License', true],
                                        'vehicle_registration' => ['Vehicle Registration', true],
                                        'vehicle_insurance' => ['Vehicle Insurance', true],
                                        'passport_photo' => ['Passport Photo', true],
                                        'proof_of_address' => ['Proof of Address', true],
                                        'bank_statement' => ['Bank Statement', true],
                                        'background_check' => ['Background Check (optional)', false],
                                    ];
                                @endphp
                                @foreach ($documents as $key => [$label, $required])
                                    <div class="col-md-6">
                                        <label class="form-label" for="doc_{{ $key }}">{{ $label }} @unless ($required)<span class="text-muted">(optional)</span>@endunless</label>
                                        <input type="file" class="form-control @error('kyc_documents.'.$key) is-invalid @enderror" id="doc_{{ $key }}" name="kyc_documents[{{ $key }}]" accept=".jpg,.jpeg,.png,.pdf" @if ($required) required @endif>
                                        @error('kyc_documents.'.$key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                @endforeach
                            </div>

                            <button type="submit" class="btn btn-juvo mt-4"><i class="fas fa-check-circle me-2"></i>Submit Registration</button>
                            <p class="text-center text-muted small mt-3 mb-0">Your documents are stored privately and reviewed by our logistics team.</p>
                        </form>

                        <hr class="my-4">
                        <div class="text-center text-muted small">
                            Already registered? <a href="{{ route('rider.login') }}" class="link-juvo">Sign in</a> &bull;
                            <a href="{{ url('/') }}" class="link-juvo">Back to Marketplace</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
