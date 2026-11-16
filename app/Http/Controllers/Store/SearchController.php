<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Search\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SearchController extends Controller
{
    public function __construct(
        protected SearchService $search,
    ) {}

    /**
     * Autocomplete: products, categories, brands and predictive queries.
     * With an empty term it returns trending searches instead.
     */
    public function suggestions(Request $request)
    {
        $locale = (string) $request->input('locale', App::getLocale());
        $q = (string) $request->input('q', '');
        $limit = (int) $request->input('limit', 8);

        if (trim($q) === '') {
            return response()->json([
                'products' => [],
                'categories' => [],
                'brands' => [],
                'queries' => [],
                'did_you_mean' => null,
                'trending' => $this->search->trending($locale, 6),
            ]);
        }

        $result = $this->search->autocomplete($q, [
            'locale' => $locale,
            'limit' => $limit,
        ]);

        return response()->json($result + ['trending' => []]);
    }

    public function searchResults(Request $request)
    {
        $options = [
            'locale' => $request->input('locale', App::getLocale()),
            'category' => (array) $request->input('category', []),
            'brand' => (array) $request->input('brand', []),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'sort' => $request->input('sort'),
            'page' => (int) $request->input('page', 1),
            'include_sponsored' => true,
            'query' => $request->input('q'),
            'ip' => $request->ip(),
        ];

        $result = $this->search->search((string) $request->input('q'), $options);

        return view('themes.xylo.search-results', [
            'query' => $result['query'],
            'products' => $result['products'],
            'facets' => $result['facets'],
            'selected' => $result['selected'],
            'sponsored' => $result['sponsored'],
            'meta' => $result['meta'],
        ]);
    }
}
