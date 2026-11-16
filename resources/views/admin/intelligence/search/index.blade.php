@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <h4 class="mb-0"><i class="fas fa-magnifying-glass-chart me-2" style="color:#f68b1e;"></i>Search Engine</h4>
            <p class="text-muted mb-0 small">Query quality, ranking behaviour and index health.</p>
        </div>
        <form method="GET" action="{{ route('admin.search.index') }}" class="d-flex gap-2">
            <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ([7, 30, 90] as $option)
                    <option value="{{ $option }}" @selected($days === $option)>{{ $option }} days</option>
                @endforeach
            </select>
        </form>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Searches</div>
            <div class="fs-4 fw-semibold">{{ number_format($volume) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Zero-result rate</div>
            <div class="fs-4 fw-semibold">{{ number_format($zeroRate, 2) }}%</div>
        </div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">CTR</div>
            <div class="fs-4 fw-semibold">{{ number_format($ctr, 2) }}%</div>
        </div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">p95 latency</div>
            <div class="fs-4 fw-semibold">{{ number_format($p95) }} ms</div>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Ranking preview</span>
                    <span class="text-muted small" id="preview-meta"></span>
                </div>
                <div class="card-body">
                    <form id="preview-form" class="d-flex gap-2 mb-3">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Try a live query…" required>
                        <button class="btn btn-sm btn-outline-primary" type="submit">Preview</button>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Product</th><th style="width:90px;">Score</th><th>Signals</th></tr></thead>
                            <tbody id="preview-body">
                                <tr><td colspan="3" class="text-muted small">Run a query to inspect ranking signals.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Daily volume</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Date</th><th>Searches</th><th>Zero-result</th></tr></thead>
                        <tbody>
                        @forelse ($daily as $row)
                            <tr><td>{{ $row->d }}</td><td>{{ number_format($row->total) }}</td><td>{{ number_format($row->zero) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted small">No search activity in this period.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Index health</span>
                    <form method="POST" action="{{ route('admin.search.reindex') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary">Rebuild</button>
                    </form>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between"><span>Indexed documents</span><strong>{{ number_format($indexHealth['documents']) }}</span></strong></li>
                    <li class="list-item d-none"></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Stale (&gt; 24h)</span><strong>{{ number_format($indexHealth['stale']) }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Last full rebuild</span><strong>{{ $indexHealth['last_rebuilt'] ? \Illuminate\Support\Carbon::parse($indexHealth['last_rebuilt'])->diffForHumans() : 'never' }}</strong></li>
                </ul>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Top queries</div>
                <ul class="list-group list-group-flush">
                    @forelse ($topQueries as $row)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $row->normalized }}</span>
                            <span class="badge text-bg-light">{{ number_format($row->total) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">No data yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Zero-result queries</div>
                <ul class="list-group list-group-flush">
                    @forelse ($zeroQueries as $row)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $row->normalized }}</span>
                            <span class="badge text-bg-warning">{{ number_format($row->total) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">None in range.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Synonym dictionary</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.search.synonyms.store') }}" class="row g-2 mb-3">
                        @csrf
                        <div class="col-md-3"><input type="text" name="term" class="form-control form-control-sm" placeholder="term" required></div>
                        <div class="col-md-2">
                            <select name="locale" class="form-select form-select-sm">
                                <option value="">any</option>
                                @foreach (['en', 'ar', 'fr', 'es', 'de'] as $loc)
                                    <option value="{{ $loc }}">{{ $loc }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5"><input type="text" name="synonyms" class="form-control form-control-sm" placeholder="comma, separated, synonyms" required></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit">Save</button></div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Term</th><th>Locale</th><th>Synonyms</th><th style="width:120px;"></th></tr></thead>
                            <tbody>
                            @forelse ($synonyms as $synonym)
                                <tr>
                                    <td>{{ $synonym->term }}</td>
                                    <td>{{ $synonym->locale ?: 'any' }}</td>
                                    <td class="small text-muted">{{ implode(', ', $synonym->synonyms ?? []) }}</td>
                                    <td class="d-flex gap-1">
                                        <form method="POST" action="{{ route('admin.search.synonyms.toggle', $synonym) }}">
                                            @csrf
                                            <button class="btn btn-sm {{ $synonym->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}" type="submit">
                                                {{ $synonym->is_active ? 'On' : 'Off' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.search.synonyms.destroy', $synonym) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted small">No synonyms defined.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Search anomalies</div>
                <ul class="list-group list-group-flush">
                    @forelse ($anomalies as $anomaly)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $anomaly->metric }}<div class="text-muted small">{{ $anomaly->detected_at }}</div></span>
                            <span class="badge text-bg-{{ $anomaly->severity === 'critical' ? 'danger' : 'warning' }}">{{ $anomaly->severity }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">No search anomalies detected.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('preview-form')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const form = e.target;
            const body = new FormData(form);
            fetch('{{ route('admin.search.preview') }}', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body,
            })
                .then((r) => r.json())
                .then((data) => {
                    document.getElementById('preview-meta').textContent =
                        `${data.total} results · intent ${data.intent} · ${data.latency_ms} ms` + (data.index_used ? '' : ' · LIKE fallback');
                    const rows = (data.items || []).map((item) => {
                        const signals = Object.entries(item.signals || {})
                            .map(([k, v]) => `${k}:${Number(v).toFixed(2)}`)
                            .join(' ');
                        return `<tr><td>${item.name ?? ('#' + item.id)}</td><td>${Number(item.score).toFixed(3)}</td><td class="small text-muted">${signals}</td></tr>`;
                    });
                    document.getElementById('preview-body').innerHTML = rows.join('') ||
                        '<tr><td colspan="3" class="text-muted small">No results.</td></tr>';
                });
        });
    </script>
@endsection
