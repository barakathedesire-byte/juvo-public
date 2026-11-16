<?php

namespace App\Http\Controllers\Admin\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceAnomaly;
use App\Models\SearchQuery;
use App\Models\SearchSynonym;
use App\Services\Admin\AuditLogger;
use App\Services\Search\SearchIndexService;
use App\Services\Search\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SearchIntelligenceController extends Controller
{
    public function index(Request $request, SearchIndexService $index)
    {
        $days = (int) $request->input('days', 30);
        $from = now()->subDays($days);

        $base = SearchQuery::where('surface', SearchService::SURFACE_RESULTS)
            ->where('occurred_at', '>=', $from);

        $volume = (clone $base)->count();
        $zero = (clone $base)->where('is_zero_result', true)->count();

        $latencies = (clone $base)->orderBy('latency_ms')->pluck('latency_ms')->map(fn ($v) => (int) $v)->all();
        $p95 = 0;
        if ($latencies !== []) {
            $p95 = $latencies[(int) floor(0.95 * (count($latencies) - 1))];
        }

        $events = DB::table('recommendation_events')
            ->where('algorithm_key', SearchService::ALGORITHM_KEY)
            ->where('is_valid', true)
            ->where('occurred_at', '>=', $from);
        $impressions = (clone $events)->where('type', 'impression')->count();
        $clicks = (clone $events)->where('type', 'click')->count();

        $daily = SearchQuery::where('surface', SearchService::SURFACE_RESULTS)
            ->where('occurred_at', '>=', $from)
            ->selectRaw('DATE(occurred_at) as d, COUNT(*) as total, SUM(is_zero_result) as zero')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $topQueries = SearchQuery::where('surface', SearchService::SURFACE_RESULTS)
            ->where('occurred_at', '>=', $from)
            ->whereNotNull('normalized')
            ->selectRaw('normalized, COUNT(*) as total')
            ->groupBy('normalized')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $zeroQueries = SearchQuery::where('surface', SearchService::SURFACE_RESULTS)
            ->where('occurred_at', '>=', $from)
            ->where('is_zero_result', true)
            ->whereNotNull('normalized')
            ->selectRaw('normalized, COUNT(*) as total')
            ->groupBy('normalized')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        return view('admin.intelligence.search.index', [
            'days' => $days,
            'volume' => $volume,
            'zeroRate' => $volume > 0 ? round($zero / $volume * 100, 2) : 0.0,
            'ctr' => $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0,
            'p95' => $p95,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'daily' => $daily,
            'topQueries' => $topQueries,
            'zeroQueries' => $zeroQueries,
            'synonyms' => SearchSynonym::orderBy('term')->get(),
            'indexHealth' => [
                'documents' => $index->documentCount(),
                'stale' => $index->staleCount(),
                'last_rebuilt' => $index->lastRebuiltAt(),
            ],
            'anomalies' => MarketplaceAnomaly::where('metric', 'like', 'search_%')
                ->orderByDesc('detected_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function reindex(SearchIndexService $index)
    {
        $count = $index->rebuild();

        AuditLogger::record('search.reindexed', null, [], ['documents' => $count]);

        return back()->with('success', "Search index rebuilt: {$count} documents.");
    }

    public function storeSynonym(Request $request)
    {
        $data = Validator::make($request->all(), [
            'term' => 'required|string|max:120',
            'locale' => 'nullable|string|max:10',
            'synonyms' => 'required|string|max:1000',
        ])->validate();

        $synonyms = collect(explode(',', $data['synonyms']))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($synonyms === []) {
            return back()->withErrors(['synonyms' => 'Provide at least one synonym.']);
        }

        $synonym = SearchSynonym::updateOrCreate(
            ['term' => mb_strtolower(trim($data['term'])), 'locale' => $data['locale'] ?? null],
            ['synonyms' => $synonyms, 'is_active' => true, 'created_by' => $request->user()?->id]
        );

        AuditLogger::record('search.synonym.saved', $synonym, [], ['term' => $synonym->term, 'synonyms' => $synonyms]);

        $this->flushSynonymCache($synonym->locale);

        return back()->with('success', 'Synonym saved.');
    }

    public function toggleSynonym(SearchSynonym $synonym)
    {
        $synonym->update(['is_active' => ! $synonym->is_active]);

        AuditLogger::record('search.synonym.toggled', $synonym, [], ['is_active' => $synonym->is_active]);

        $this->flushSynonymCache($synonym->locale);

        return back()->with('success', $synonym->is_active ? 'Synonym enabled.' : 'Synonym disabled.');
    }

    public function destroySynonym(SearchSynonym $synonym)
    {
        AuditLogger::record('search.synonym.deleted', $synonym, [], ['term' => $synonym->term]);
        $locale = $synonym->locale;
        $synonym->delete();

        $this->flushSynonymCache($locale);

        return back()->with('success', 'Synonym removed.');
    }

    protected function flushSynonymCache(?string $locale): void
    {
        foreach (array_filter(['en', $locale]) as $key) {
            Cache::forget('search.synonyms.'.$key);
        }
    }

    public function preview(Request $request, SearchService $search)
    {
        $data = $request->validate([
            'q' => 'required|string|max:200',
            'sort' => 'nullable|string|max:30',
        ]);

        $result = $search->search($data['q'], [
            'sort' => $data['sort'] ?? 'relevance',
            'per_page' => 10,
            'include_sponsored' => false,
        ]);

        return response()->json([
            'query' => $result['query'],
            'intent' => $result['meta']['intent'],
            'total' => $result['meta']['total'],
            'index_used' => $result['meta']['index_used'],
            'did_you_mean' => $result['meta']['did_you_mean'],
            'latency_ms' => $result['meta']['latency_ms'],
            'items' => collect($result['products']->items())->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->translation->name ?? null,
                'score' => $p->search_score ?? null,
                'signals' => $p->search_signals ?? null,
            ])->values(),
        ]);
    }
}
