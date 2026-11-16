<div class="ic-head">
    <div>
        <div class="ic-eyebrow">Juvo Intelligence &amp; Command Center</div>
        <h1 class="ic-title">{{ $title }}</h1>
        <p class="ic-sub">{{ $subtitle }}</p>
    </div>
    <div>
        <form method="GET" class="ic-filter" id="icFilter">
            @isset($reportType)
                <input type="hidden" name="report_type" value="{{ $reportType }}">
            @endisset
            <label class="ic-period">
                <span class="ic-small ic-muted">Period</span>
                <input type="date" name="from" value="{{ $from->toDateString() }}" aria-label="From date">
                <span class="ic-muted">&ndash;</span>
                <input type="date" name="to" value="{{ $to->toDateString() }}" aria-label="To date">
            </label>
            <select name="vendor_id" aria-label="Vendor filter">
                <option value="">All vendors</option>
                @foreach ($filterOptions['vendors'] as $vendor)
                    <option value="{{ $vendor->id }}" @selected(($filters['vendor_id'] ?? null) == $vendor->id)>{{ $vendor->shop_name ?: $vendor->name }}</option>
                @endforeach
            </select>
            <select name="region_id" aria-label="Region filter">
                <option value="">All regions</option>
                @foreach ($filterOptions['regions'] as $region)
                    <option value="{{ $region->id }}" @selected(($filters['region_id'] ?? null) == $region->id)>{{ $region->name }}</option>
                @endforeach
            </select>
            <select name="category_id" aria-label="Category filter">
                <option value="">All categories</option>
                @foreach ($filterOptions['categories'] as $category)
                    <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->displayName() }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary btn-sm" type="submit">Apply</button>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route(Route::currentRouteName()) }}">Reset</a>
        </form>
        <div class="ic-small ic-muted mt-2 text-end">Updated {{ now()->format('d M Y H:i') }} &middot; {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</div>
    </div>
</div>
