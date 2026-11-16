@php
    $user = auth()->user();
    $workspaces = $user instanceof \App\Models\User
        ? \App\Support\Access\NavigationRegistry::workspaces($user)
        : [];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access denied</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:720px;">
    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">
            <h1 class="h4 mb-3">Access denied</h1>
            <p class="text-muted mb-4">
                You are signed in, but you do not have permission to view this page or perform this action.
                If you believe this is a mistake, contact an administrator.
            </p>

            @if (! empty($workspaces))
                <h2 class="h6 text-uppercase text-muted">Your workspaces</h2>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    @foreach ($workspaces as $workspace)
                        <a href="{{ route($workspace['route']) }}" class="btn btn-sm btn-outline-primary">
                            {{ $workspace['label'] }}
                        </a>
                    @endforeach
                </div>
            @else
                <div class="alert alert-warning mb-4">
                    You have no assigned workspace. Please ask an administrator to grant you access.
                </div>
            @endif

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-outline-secondary btn-sm" type="submit">Sign out</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
