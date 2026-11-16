@php
    $map = [
        'pending' => 'text-bg-warning',
        'approved' => 'text-bg-success',
        'rejected' => 'text-bg-danger',
        'changes_requested' => 'text-bg-info',
        'escalated' => 'text-bg-dark',
        'canceled' => 'text-bg-secondary',
        'executed' => 'text-bg-primary',
    ];
@endphp
<span class="badge {{ $map[$status] ?? 'text-bg-secondary' }}">{{ str_replace('_', ' ', $status) }}</span>
