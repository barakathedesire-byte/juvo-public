@extends('rider.layouts.app')

@section('title', 'Profile')
@section('body_class', 'rider-profile')

@php
    $riderName = $rider->full_name ?: 'Rider';
    $initials = Str::upper(Str::substr($rider->first_name ?? 'R', 0, 1).Str::substr($rider->last_name ?? '', 0, 1));
    $avatarUrl = $rider->profile_image
        ? (Str::startsWith($rider->profile_image, ['http://', 'https://']) ? $rider->profile_image : asset('storage/'.$rider->profile_image))
        : null;

    $kycStatus = $rider->kyc_status ?? 'pending';
    $kycMap = [
        'pending' => ['Pending submission', 'is-pending'],
        'under_review' => ['Under review', 'is-assigned'],
        'approved' => ['Verified', 'is-verified'],
        'rejected' => ['Rejected', 'is-issue'],
    ];
    [$kycLabel, $kycClass] = $kycMap[$kycStatus] ?? [Str::headline($kycStatus), 'is-pending'];

    $submittedDocuments = $rider->kyc_documents ?? [];
    $requiredDocuments = array_keys(\App\Models\Rider\Rider::KYC_DOCUMENT_TYPES);
    $documentProgress = count($requiredDocuments) > 0
        ? (int) round((count($submittedDocuments) / count($requiredDocuments)) * 100)
        : 0;

    $zone = trim(implode(', ', array_filter([$rider->city, $rider->state])));
    $supportEmail = $supportEmail ?: config('mail.from.address');
    $supportTel = $supportPhone ? preg_replace('/[^0-9+]/', '', $supportPhone) : null;
    $vehicleLine = trim(($rider->vehicle_make ?? '').' '.($rider->vehicle_model ?? ''));
@endphp

@section('content')
    <header class="profile-orange-header" aria-label="Profile header">
        <div class="profile-orange-top">
            <a href="{{ route('rider.dashboard') }}" class="profile-back-btn" aria-label="Back to dashboard">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
            </a>

            <button type="button" class="profile-bell-btn" data-bs-toggle="modal" data-bs-target="#profileNotificationsModal" aria-label="Notifications{{ $unreadCount > 0 ? ' ('.$unreadCount.' unread)' : '' }}">
                <i class="far fa-bell" aria-hidden="true"></i>
                @if ($unreadCount > 0)
                    <span class="profile-bell-dot" aria-hidden="true"></span>
                @endif
            </button>
        </div>

        <div class="profile-photo-wrap">
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $riderName }}" class="profile-photo">
            @else
                <span class="profile-photo-fallback">{{ $initials }}</span>
            @endif
            <button type="button" class="profile-photo-edit" data-bs-toggle="modal" data-bs-target="#photoModal" aria-label="Change profile photo">
                <i class="fas fa-camera" aria-hidden="true"></i>
            </button>
        </div>

        <h1 class="profile-name">{{ $riderName }}</h1>
        <div class="profile-id-row">
            <span>Rider ID #{{ $rider->id }}</span>
            <span class="profile-verification-pill {{ $kycClass }}">
                <i class="fas fa-circle-check" aria-hidden="true"></i> {{ $kycLabel }}
            </span>
        </div>

        <button type="button" class="profile-edit-btn" data-bs-toggle="modal" data-bs-target="#editProfileModal">
            <i class="fas fa-pen" aria-hidden="true"></i> Edit Profile
        </button>

        <svg class="profile-header-wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            <path d="M0,42 C220,82 420,86 640,52 C860,18 1080,16 1280,44 C1380,58 1410,64 1440,56 L1440,80 L0,80 Z" fill="#F5F7FA"/>
        </svg>
    </header>

    <section class="profile-page" aria-label="Rider profile">
        <article class="profile-card">
            <div class="profile-card-head">
                <div>
                    <span class="section-kicker">Personal information</span>
                    <h2>Contact and address</h2>
                </div>
            </div>

            <div class="profile-info-list">
                <div><dt>Name</dt><dd>{{ $rider->full_name ?: 'Not provided' }}</dd></div>
                <div><dt>Phone</dt><dd>{{ $rider->phone ?: 'Not provided' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $rider->email }}</dd></div>
                <div><dt>Address</dt><dd>{{ $rider->address ?: 'Not provided' }}</dd></div>
                <div><dt>City / state</dt><dd>{{ $zone !== '' ? $zone : 'Not provided' }}</dd></div>
                <div><dt>Postal code</dt><dd>{{ $rider->postal_code ?: 'Not provided' }}</dd></div>
            </div>
        </article>

        <article class="profile-card">
            <div class="profile-card-head">
                <div>
                    <span class="section-kicker">Rider verification</span>
                    <h2>Identity and documents</h2>
                </div>
                <span class="delivery-status {{ $kycClass }}">{{ $kycLabel }}</span>
            </div>

            <div class="profile-progress">
                <div class="profile-progress-bar" style="--progress: {{ $documentProgress }}%;"></div>
                <span>{{ count($submittedDocuments) }} of {{ count($requiredDocuments) }} documents submitted</span>
            </div>

            @if ($rider->kyc_rejection_reason)
                <div class="delivery-inline-note">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <span>{{ $rider->kyc_rejection_reason }}</span>
                </div>
            @endif

            <div class="profile-document-list">
                @foreach (\App\Models\Rider\Rider::KYC_DOCUMENT_TYPES as $type => $label)
                    @php $path = $submittedDocuments[$type] ?? null; @endphp
                    <div class="profile-document-row">
                        <div>
                            <strong>{{ $label }}</strong>
                            <span>{{ $path ? 'Submitted for review' : 'Not submitted' }}</span>
                        </div>
                        <span class="delivery-status {{ $path ? 'is-picked' : 'is-pending' }}">{{ $path ? 'Submitted' : 'Missing' }}</span>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('rider.profile.documents') }}" enctype="multipart/form-data" class="profile-upload-form">
                @csrf
                <div class="delivery-field">
                    <label for="document_type">Document type</label>
                    <select id="document_type" name="document_type" required>
                        <option value="">Choose a document</option>
                        @foreach (\App\Models\Rider\Rider::KYC_DOCUMENT_TYPES as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="delivery-field">
                    <label for="document">File <span>JPG, PNG or PDF, max 5 MB</span></label>
                    <input type="file" id="document" name="document" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
                <div class="delivery-form-error" data-form-error hidden></div>
                <button type="submit" class="delivery-btn delivery-btn-primary delivery-btn-block">
                    Update Documents <i class="fas fa-upload" aria-hidden="true"></i>
                </button>
            </form>

            <a href="{{ route('rider.kyc.status') }}" class="delivery-btn delivery-btn-ghost delivery-btn-block profile-kyc-link">
                View verification status <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
        </article>

        <article class="profile-card">
            <div class="profile-card-head">
                <div>
                    <span class="section-kicker">Vehicle information</span>
                    <h2>Registered vehicle</h2>
                </div>
                <span class="delivery-status {{ $rider->license_expiry && $rider->license_expiry->isFuture() ? 'is-done' : 'is-issue' }}">
                    {{ $rider->license_expiry && $rider->license_expiry->isFuture() ? 'Valid license' : 'License attention' }}
                </span>
            </div>

            <div class="profile-vehicle-row">
                <span class="profile-vehicle-icon"><i class="fas fa-motorcycle" aria-hidden="true"></i></span>
                <div>
                    <strong>{{ Str::headline($rider->vehicle_type ?: 'Not set') }}</strong>
                    <span>{{ $vehicleLine !== '' ? $vehicleLine : 'Vehicle details not provided' }}</span>
                </div>
            </div>

            <div class="profile-info-list">
                <div><dt>Registration</dt><dd>{{ $rider->license_plate ?: 'Not provided' }}</dd></div>
                <div><dt>License number</dt><dd>{{ $rider->license_number ?: 'Not provided' }}</dd></div>
                <div><dt>License expiry</dt><dd>{{ $rider->license_expiry ? $rider->license_expiry->format('d M Y') : 'Not provided' }}</dd></div>
                <div><dt>Color</dt><dd>{{ $rider->vehicle_color ?: 'Not provided' }}</dd></div>
            </div>
            <p class="profile-note">Vehicle details are set during registration and reviewed by Juvo. Contact support to request changes.</p>
        </article>

        <article class="profile-card">
            <div class="profile-card-head">
                <div>
                    <span class="section-kicker">Work information</span>
                    <h2>Operational details</h2>
                </div>
            </div>

            <div class="profile-info-list">
                <div><dt>Assigned zone</dt><dd>{{ $zone !== '' ? $zone : 'Not assigned' }}</dd></div>
                <div><dt>Account type</dt><dd>{{ $rider->is_active ? 'Active partner' : 'Inactive' }}</dd></div>
                <div><dt>Availability</dt><dd>{{ $rider->is_online ? 'Online and receiving requests' : 'Offline' }}</dd></div>
                <div><dt>Background check</dt><dd>{{ Str::headline($rider->background_check_status ?: 'pending') }}</dd></div>
                <div><dt>Last online</dt><dd>{{ $rider->last_online_at ? $rider->last_online_at->diffForHumans() : 'Never' }}</dd></div>
            </div>

            <form method="POST" action="{{ route('rider.profile.availability') }}" class="profile-availability-form">
                @csrf
                <div class="delivery-form-error" data-form-error hidden></div>
                <button type="submit" class="delivery-btn {{ $rider->is_online ? 'delivery-btn-danger-ghost' : 'delivery-btn-primary' }} delivery-btn-block">
                    {{ $rider->is_online ? 'Go offline' : 'Go online' }} <i class="fas fa-power-off" aria-hidden="true"></i>
                </button>
            </form>
        </article>

        <article class="profile-card">
            <div class="profile-card-head">
                <div>
                    <span class="section-kicker">Account & preferences</span>
                    <h2>Settings</h2>
                </div>
            </div>

            <div class="profile-menu-list">
                <a href="{{ route('rider.dashboard') }}#notifications" class="profile-menu-row">
                    <span class="profile-menu-icon"><i class="far fa-bell" aria-hidden="true"></i></span>
                    <span class="profile-menu-copy">
                        <strong>Notifications</strong>
                        <small>{{ $unreadCount }} unread</small>
                    </span>
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </a>

                <form method="POST" action="{{ url('/change-language') }}" class="profile-menu-row profile-menu-form">
                    @csrf
                    <span class="profile-menu-icon"><i class="fas fa-language" aria-hidden="true"></i></span>
                    <span class="profile-menu-copy">
                        <strong>Language</strong>
                        <small>Choose app language</small>
                    </span>
                    <select name="lang" onchange="this.form.submit()" aria-label="Language">
                        @foreach (['en' => 'English'] as $code => $label)
                            <option value="{{ $code }}" @selected(session('locale', 'en') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>

                <button type="button" class="profile-menu-row" data-bs-toggle="modal" data-bs-target="#passwordModal">
                    <span class="profile-menu-icon"><i class="fas fa-key" aria-hidden="true"></i></span>
                    <span class="profile-menu-copy">
                        <strong>Change Password</strong>
                        <small>Update your account password</small>
                    </span>
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </button>

                <button type="button" class="profile-menu-row" data-bs-toggle="modal" data-bs-target="#passwordModal">
                    <span class="profile-menu-icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>
                    <span class="profile-menu-copy">
                        <strong>Privacy & Security</strong>
                        <small>Session and account security</small>
                    </span>
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </button>

                @if ($supportPhone)
                    <a href="tel:{{ $supportTel }}" class="profile-menu-row">
                        <span class="profile-menu-icon"><i class="fas fa-headset" aria-hidden="true"></i></span>
                        <span class="profile-menu-copy">
                            <strong>Help & Support</strong>
                            <small>{{ $supportPhone }}</small>
                        </span>
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </a>
                @endif

                @if ($supportEmail)
                    <a href="mailto:{{ $supportEmail }}?subject=Juvo%20Rider%20problem%20report" class="profile-menu-row">
                        <span class="profile-menu-icon"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i></span>
                        <span class="profile-menu-copy">
                            <strong>Report a Problem</strong>
                            <small>Send details to Juvo support</small>
                        </span>
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </article>

        <form method="POST" action="{{ route('rider.logout') }}" id="logoutForm" class="profile-logout-row">
            @csrf
            <button type="submit" class="profile-logout-button">
                <span class="profile-menu-icon is-danger"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i></span>
                <span class="profile-menu-copy">
                    <strong>Log Out</strong>
                    <small>Sign out of this device</small>
                </span>
            </button>
        </form>

        @if ($supportEmail)
            <p class="delivery-support-line">
                Need more help? <a href="mailto:{{ $supportEmail }}">Contact Juvo support</a>
            </p>
        @endif
    </section>

    <div class="modal fade" id="profileNotificationsModal" tabindex="-1" aria-labelledby="profileNotificationsTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content deliveries-modal">
                <div class="modal-body">
                    <h2 id="profileNotificationsTitle">Notifications</h2>
                    <p class="deliveries-modal-copy">{{ $unreadCount }} unread notification{{ $unreadCount === 1 ? '' : 's' }}.</p>

                    <div class="profile-notification-list">
                        @forelse ($notifications as $notification)
                            @php $data = is_array($notification->data ?? null) ? $notification->data : []; @endphp
                            <div class="profile-notification-row">
                                <strong>{{ $data['title'] ?? 'Delivery update' }}</strong>
                                @if (! empty($data['message']))
                                    <span>{{ $data['message'] }}</span>
                                @endif
                                <small>{{ $notification->created_at?->diffForHumans() }}</small>
                            </div>
                        @empty
                            <div class="deliveries-empty">
                                <span class="deliveries-empty-icon"><i class="far fa-bell-slash" aria-hidden="true"></i></span>
                                <h3>You are all caught up</h3>
                                <p>New delivery updates will appear here.</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="deliveries-modal-actions">
                        <button type="button" class="delivery-btn delivery-btn-ghost" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="delivery-btn delivery-btn-primary" data-mark-notifications-read>Mark all read</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content deliveries-modal">
                <form method="POST" action="{{ route('rider.profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <h2 id="editProfileTitle">Edit Profile</h2>
                        <p class="deliveries-modal-copy">Update your permitted personal information. KYC and vehicle details require Juvo review.</p>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="delivery-field">
                                    <label for="first_name">First name</label>
                                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $rider->first_name) }}" required maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="delivery-field">
                                    <label for="last_name">Last name</label>
                                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $rider->last_name) }}" required maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="delivery-field">
                                    <label for="phone">Phone</label>
                                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $rider->phone) }}" required maxlength="20">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="delivery-field">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" value="{{ old('email', $rider->email) }}" required maxlength="255">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="delivery-field">
                                    <label for="address">Address</label>
                                    <textarea id="address" name="address" rows="2" maxlength="500">{{ old('address', $rider->address) }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="delivery-field">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" value="{{ old('city', $rider->city) }}" maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="delivery-field">
                                    <label for="state">State / region</label>
                                    <input type="text" id="state" name="state" value="{{ old('state', $rider->state) }}" maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="delivery-field">
                                    <label for="postal_code">Postal code</label>
                                    <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code', $rider->postal_code) }}" maxlength="20">
                                </div>
                            </div>
                        </div>

                        <div class="delivery-form-error" data-form-error hidden></div>

                        <div class="deliveries-modal-actions">
                            <button type="button" class="delivery-btn delivery-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="delivery-btn delivery-btn-primary">Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content deliveries-modal">
                <form method="POST" action="{{ route('rider.profile.photo') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <h2 id="photoTitle">Change Profile Photo</h2>
                        <p class="deliveries-modal-copy">Upload a clear JPG or PNG image up to 2 MB.</p>
                        <div class="delivery-field">
                            <label for="profile_image">Photo</label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/png,image/jpeg,image/webp" required>
                        </div>
                        <div class="delivery-form-error" data-form-error hidden></div>
                        <div class="deliveries-modal-actions">
                            <button type="button" class="delivery-btn delivery-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="delivery-btn delivery-btn-primary">Upload Photo</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content deliveries-modal">
                <form method="POST" action="{{ route('rider.profile.password') }}">
                    @csrf
                    <div class="modal-body">
                        <h2 id="passwordTitle">Change Password</h2>
                        <p class="deliveries-modal-copy">Enter your current password, then choose a new password.</p>
                        <div class="delivery-field">
                            <label for="current_password">Current password</label>
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="delivery-field">
                            <label for="password">New password</label>
                            <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
                        </div>
                        <div class="delivery-field">
                            <label for="password_confirmation">Confirm new password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" minlength="8">
                        </div>
                        <div class="delivery-form-error" data-form-error hidden></div>
                        <div class="deliveries-modal-actions">
                            <button type="button" class="delivery-btn delivery-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="delivery-btn delivery-btn-primary">Update Password</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    document.getElementById('logoutForm')?.addEventListener('submit', (event) => {
        if (!window.confirm('Are you sure you want to log out?')) {
            event.preventDefault();
        }
    });

    document.getElementById('refreshDeliveries')?.addEventListener('click', () => window.location.reload());
})();
</script>
@endpush
