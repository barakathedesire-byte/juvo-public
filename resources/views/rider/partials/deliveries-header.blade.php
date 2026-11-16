@php
    $bellUnread = (int) ($unreadCount ?? 0);
@endphp

<header class="deliveries-header">
    <div class="deliveries-header-inner">
        <div class="deliveries-heading">
            @if (! empty($backUrl))
                <a href="{{ $backUrl }}" class="deliveries-back" aria-label="Back to deliveries">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </a>
            @endif

            <div class="deliveries-heading-copy">
                @if (! empty($eyebrow))
                    <span class="deliveries-eyebrow">{{ $eyebrow }}</span>
                @endif
                <h1>{{ $title }}</h1>
                @if (! empty($subtitle))
                    <p>{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        <div class="deliveries-header-actions">
            @if (! empty($showRefresh))
                <button type="button" class="deliveries-icon-btn" id="refreshDeliveries" aria-label="Refresh deliveries">
                    <i class="fas fa-rotate-right" aria-hidden="true"></i>
                </button>
            @endif

            <a href="{{ route('rider.dashboard') }}#notifications" class="deliveries-icon-btn" aria-label="Notifications{{ $bellUnread > 0 ? ' ('.$bellUnread.' unread)' : '' }}">
                <i class="far fa-bell" aria-hidden="true"></i>
                @if ($bellUnread > 0)
                    <span class="deliveries-unread-dot" aria-hidden="true"></span>
                @endif
            </a>
        </div>
    </div>
</header>
