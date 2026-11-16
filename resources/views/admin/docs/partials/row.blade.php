<tr>
    @if($showSection ?? false)
        <td class="text-break align-top">
            <span class="fw-semibold">{{ $item['section'] ?? '—' }}</span>
            @if(! empty($item['group']))<div class="small text-muted">{{ $item['group'] }}</div>@endif
        </td>
    @endif
    <td class="fw-semibold text-break">{{ $item['name'] }}</td>
    <td><span class="badge bg-light text-dark border">{{ $item['category'] }}</span></td>
    <td>
        @if(! empty($item['uri']))
            <span class="badge bg-dark">{{ $item['method'] ?: 'GET' }}</span>
            <code class="text-break">{{ $item['uri'] }}</code>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
    <td class="small">
        @php $hasMeta = ! empty($item['controller']) || ! empty($item['middleware']); @endphp
        @if(! empty($item['controller']))
            <div class="text-break"><i class="fas fa-code me-1 text-muted"></i>{{ $item['controller'] }}</div>
        @endif
        @if(! empty($item['middleware']))
            <div class="text-break mt-1"><i class="fas fa-shield-halved me-1 text-muted"></i>{{ implode(', ', (array) $item['middleware']) }}</div>
        @endif
        @unless($hasMeta)<span class="text-muted">—</span>@endunless
    </td>
    <td>
        <div>{{ $item['description'] }}</div>
        @if(! empty($item['instructions']))
            <small class="text-muted d-block mt-1"><i class="fas fa-circle-info me-1"></i>{{ $item['instructions'] }}</small>
        @endif
    </td>
</tr>
