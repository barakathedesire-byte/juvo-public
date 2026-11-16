@extends('rider.layouts.app')

@section('title', 'Deliveries')
@section('body_class', 'rider-deliveries')

@php
    $filterLinks = [
        'all' => 'All',
        'new' => 'New',
        'assigned' => 'Assigned',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
    ];
@endphp

@section('hero')
    @include('rider.partials.deliveries-header', [
        'title' => 'Deliveries',
        'subtitle' => 'Assigned routes, live progress and completed work.',
        'eyebrow' => 'Juvo Rider',
        'unreadCount' => $unreadCount,
        'showRefresh' => true,
    ])
@endsection

@section('content')
    <section class="deliveries-page"
             data-deliveries-root
             data-endpoint="{{ route('rider.orders') }}"
             aria-label="Deliveries">
        <form class="deliveries-search-form" method="GET" action="{{ route('rider.orders') }}" role="search" data-search-form>
            <input type="hidden" name="filter" value="{{ $filter }}">
            <label for="deliveriesSearch" class="visually-hidden">Search deliveries</label>
            <i class="fas fa-magnifying-glass deliveries-search-icon" aria-hidden="true"></i>
            <input type="search"
                   id="deliveriesSearch"
                   name="q"
                   value="{{ $search }}"
                   class="deliveries-search-input"
                   placeholder="Search order ID, customer or phone"
                   autocomplete="off"
                   enterkeyhint="search">
            <button type="button" class="deliveries-search-clear" data-search-clear aria-label="Clear search" @if ($search === '') hidden @endif>
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </form>

        <div class="delivery-filters" role="tablist" aria-label="Filter deliveries">
            @foreach ($filterLinks as $key => $label)
                <a href="{{ route('rider.orders', array_filter(['filter' => $key, 'q' => $search !== '' ? $search : null])) }}"
                   class="delivery-filter {{ $filter === $key ? 'is-active' : '' }}"
                   data-filter="{{ $key }}"
                   role="tab"
                   aria-selected="{{ $filter === $key ? 'true' : 'false' }}"
                   tabindex="{{ $filter === $key ? '0' : '-1' }}">
                    {{ $label }}
                    <span class="delivery-filter-count" data-count-for="{{ $key }}">{{ $summary[$key] > 99 ? '99+' : $summary[$key] }}</span>
                </a>
            @endforeach
        </div>

        <p class="visually-hidden" id="deliveriesFeedback" role="status" aria-live="polite"></p>

        <div class="deliveries-list" id="deliveriesList" data-deliveries-list>
            @include('rider.partials.deliveries-list', ['orders' => $orders, 'search' => $search])
        </div>
    </section>

    @include('rider.partials.accept-delivery-modal')
@endsection

@push('scripts')
<script>
(function () {
    const root = document.querySelector('[data-deliveries-root]');
    if (!root) return;

    const endpoint = root.dataset.endpoint;
    const list = document.getElementById('deliveriesList');
    const searchForm = root.querySelector('[data-search-form]');
    const searchInput = document.getElementById('deliveriesSearch');
    const clearButton = root.querySelector('[data-search-clear]');
    const feedback = document.getElementById('deliveriesFeedback');
    const refreshButton = document.getElementById('refreshDeliveries');
    const modalEl = document.getElementById('acceptDeliveryModal');
    const acceptModal = window.bootstrap && modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const acceptConfirm = modalEl?.querySelector('[data-accept-confirm]');
    const acceptError = modalEl?.querySelector('[data-accept-error]');
    const acceptRefresh = modalEl?.querySelector('[data-accept-refresh]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const filters = Array.from(root.querySelectorAll('[data-filter]'));

    let filter = @json($filter);
    let query = @json($search);
    let activeRequest = null;
    let pendingAcceptForm = null;
    let accepting = false;
    let searchTimer = null;

    const announce = (message) => {
        if (feedback) feedback.textContent = message;
    };

    const skeleton = () => `
        <div class="delivery-card delivery-skeleton" aria-hidden="true">
            <span class="skeleton-line is-short"></span>
            <span class="skeleton-line is-title"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line is-short"></span>
        </div>
        <div class="delivery-card delivery-skeleton" aria-hidden="true">
            <span class="skeleton-line is-short"></span>
            <span class="skeleton-line is-title"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line is-short"></span>
        </div>
        <div class="delivery-card delivery-skeleton" aria-hidden="true">
            <span class="skeleton-line is-short"></span>
            <span class="skeleton-line is-title"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line is-short"></span>
        </div>`;

    const errorState = () => `
        <div class="deliveries-error" role="alert">
            <span class="deliveries-empty-icon"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i></span>
            <h3>Could not load deliveries</h3>
            <p>Check your connection and try again. Your assigned work is not affected.</p>
            <button type="button" class="delivery-btn delivery-btn-primary" data-retry>
                <i class="fas fa-rotate-right" aria-hidden="true"></i> Try again
            </button>
        </div>`;

    const toggleClearButton = () => {
        if (clearButton) clearButton.hidden = searchInput.value.trim() === '';
    };

    const syncFilterUI = () => {
        filters.forEach((link) => {
            const isActive = link.dataset.filter === filter;
            link.classList.toggle('is-active', isActive);
            link.setAttribute('aria-selected', isActive ? 'true' : 'false');
            link.tabIndex = isActive ? 0 : -1;
        });
    };

    const syncUrl = () => {
        const url = new URL(window.location.href);
        if (filter === 'all') url.searchParams.delete('filter');
        else url.searchParams.set('filter', filter);

        if (query !== '') url.searchParams.set('q', query);
        else url.searchParams.delete('q');

        window.history.replaceState({}, '', url);
    };

    const updateCounts = (summary) => {
        if (!summary) return;
        root.querySelectorAll('[data-count-for]').forEach((badge) => {
            const value = Number(summary[badge.dataset.countFor] || 0);
            badge.textContent = value > 99 ? '99+' : String(value);
        });
    };

    const load = async (url = null) => {
        if (activeRequest) activeRequest.abort();

        const request = new AbortController();
        activeRequest = request;
        list.setAttribute('aria-busy', 'true');
        list.innerHTML = skeleton();
        announce('Loading deliveries');

        try {
            const target = url ? new URL(url, window.location.origin) : new URL(endpoint, window.location.origin);

            if (!url) {
                target.searchParams.set('filter', filter);
                if (query !== '') target.searchParams.set('q', query);
                else target.searchParams.delete('q');
                target.searchParams.delete('page');
            }

            const response = await fetch(target, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: request.signal,
            });

            if (!response.ok) throw new Error('Unable to load deliveries.');

            const data = await response.json();
            list.innerHTML = data.html;
            updateCounts(data.summary);

            if (url) window.history.replaceState({}, '', target);
            else syncUrl();

            announce(data.total === 0 ? 'No deliveries found' : data.total + ' deliveries loaded');
        } catch (error) {
            if (error.name === 'AbortError') return;

            list.innerHTML = errorState();
            announce('Could not load deliveries.');
        } finally {
            if (activeRequest === request) activeRequest = null;
            list.removeAttribute('aria-busy');
        }
    };

    const submitSearch = () => {
        query = searchInput.value.trim();
        toggleClearButton();
        load();
    };

    searchInput?.addEventListener('input', () => {
        toggleClearButton();
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(submitSearch, 350);
    });

    searchForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        window.clearTimeout(searchTimer);
        submitSearch();
    });

    clearButton?.addEventListener('click', () => {
        searchInput.value = '';
        query = '';
        toggleClearButton();
        load();
        searchInput.focus();
    });

    filters.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            filter = link.dataset.filter;
            syncFilterUI();
            load();
        });
    });

    refreshButton?.addEventListener('click', () => load());

    list.addEventListener('click', (event) => {
        if (event.target.closest('[data-retry]')) {
            load();
            return;
        }

        if (event.target.closest('[data-clear-search]')) {
            searchInput.value = '';
            query = '';
            toggleClearButton();
            load();
            return;
        }

        const paginationLink = event.target.closest('[data-deliveries-pagination] a');
        if (paginationLink) {
            event.preventDefault();
            load(paginationLink.href);
        }
    });

    const showAcceptError = (message) => {
        if (!acceptError) return;
        acceptError.textContent = message;
        acceptError.hidden = false;
    };

    const clearAcceptError = () => {
        if (!acceptError) return;
        acceptError.textContent = '';
        acceptError.hidden = true;
    };

    list.addEventListener('submit', (event) => {
        const form = event.target.closest('.delivery-accept-form');
        if (!form) return;

        event.preventDefault();
        pendingAcceptForm = form;
        clearAcceptError();

        if (!acceptModal) {
            if (window.confirm('Accept delivery #' + form.dataset.orderRef + '?')) form.submit();
            return;
        }

        modalEl.querySelector('[data-accept-ref]').textContent = form.dataset.orderRef || '—';
        modalEl.querySelector('[data-accept-route]').textContent = form.dataset.orderRoute || '';
        if (acceptRefresh) acceptRefresh.hidden = true;
        acceptModal.show();
    });

    acceptConfirm?.addEventListener('click', async () => {
        if (!pendingAcceptForm || accepting) return;

        accepting = true;
        acceptConfirm.disabled = true;
        acceptConfirm.classList.add('is-loading');
        clearAcceptError();
        if (acceptRefresh) acceptRefresh.hidden = true;

        try {
            const response = await fetch(pendingAcceptForm.action, {
                method: 'POST',
                body: new FormData(pendingAcceptForm),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                window.location = data.redirect || pendingAcceptForm.action;
                return;
            }

            const validationMessage = data.errors
                ? Object.values(data.errors).flat().filter(Boolean)[0]
                : null;

            let message = validationMessage || data.message || 'This delivery could not be accepted.';

            if (response.status === 409 || response.status === 422) {
                message += ' Refresh the list to see the latest assignment.';
            }

            showAcceptError(message);
            if (acceptRefresh) acceptRefresh.hidden = false;
        } catch (error) {
            showAcceptError('Network error. Check your connection and try again.');
            if (acceptRefresh) acceptRefresh.hidden = false;
        } finally {
            accepting = false;
            acceptConfirm.disabled = false;
            acceptConfirm.classList.remove('is-loading');
        }
    });

    acceptRefresh?.addEventListener('click', () => {
        acceptModal?.hide();
        load();
    });

    modalEl?.addEventListener('hidden.bs.modal', () => {
        pendingAcceptForm = null;
        clearAcceptError();
        if (acceptRefresh) acceptRefresh.hidden = true;
    });

    toggleClearButton();
})();
</script>
@endpush
