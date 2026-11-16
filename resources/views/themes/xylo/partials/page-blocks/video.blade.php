@php
    $s = $block['settings'];
    $url = $s['url'] ?? null;
    $ratio = str_replace(':', '-', $s['ratio'] ?? '16:9');
    $poster = $s['poster'] ?? null;
    $posterUrl = $poster ? (\Illuminate\Support\Str::startsWith($poster, ['http://', 'https://']) ? $poster : \Illuminate\Support\Facades\Storage::url($poster)) : null;
    $embed = null;
    if ($url && preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]+)#', $url, $m)) {
        $embed = 'https://www.youtube.com/embed/'.$m[1];
    } elseif ($url && preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
        $embed = 'https://player.vimeo.com/video/'.$m[1];
    }
@endphp
@if($url)
<section class="jv-section">
    @if(!empty($s['title']))<h2 class="jv-section-title">{{ $s['title'] }}</h2>@endif
    <div class="jv-blk-video jv-blk-video-{{ $ratio }}">
        @if($embed)
            <iframe src="{{ $embed }}" title="{{ $s['title'] ?? 'Video' }}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        @else
            <video controls preload="metadata" @if($posterUrl) poster="{{ $posterUrl }}" @endif>
                <source src="{{ $url }}">
            </video>
        @endif
    </div>
</section>
@endif
