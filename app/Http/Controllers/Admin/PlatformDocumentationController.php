<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Docs\PlatformDocs;
use Illuminate\Http\Request;

class PlatformDocumentationController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', '');
        $sort = (string) $request->query('sort', 'section');
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $viewMode = $request->query('view') === 'flat' ? 'flat' : 'grouped';

        $items = PlatformDocs::allItems();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = array_values(array_filter($items, function ($item) use ($needle) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $item['name'] ?? '',
                    $item['description'] ?? '',
                    $item['instructions'] ?? '',
                    $item['uri'] ?? '',
                    $item['method'] ?? '',
                    $item['section'] ?? '',
                    $item['group'] ?? '',
                ])));

                return str_contains($haystack, $needle);
            }));
        }

        if ($category !== '') {
            $items = array_values(array_filter($items, fn ($item) => ($item['category'] ?? '') === $category));
        }

        $items = $this->sort($items, $sort, $dir);

        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['section']][] = $item;
        }

        return view('admin.docs.index', [
            'grouped' => $grouped,
            'items' => $items,
            'viewMode' => $viewMode,
            'total' => count($items),
            'categories' => PlatformDocs::categories(),
            'filters' => [
                'q' => $search,
                'category' => $category,
                'sort' => $sort,
                'dir' => $dir,
                'view' => $viewMode,
            ],
        ]);
    }

    protected function sort(array $items, string $sort, string $dir): array
    {
        $keyMap = [
            'section' => 'section',
            'name' => 'name',
            'method' => 'method',
            'category' => 'category',
            'uri' => 'uri',
        ];

        $key = $keyMap[$sort] ?? 'section';

        usort($items, fn ($a, $b) => strcmp((string) ($a[$key] ?? ''), (string) ($b[$key] ?? '')));

        if ($dir === 'desc') {
            $items = array_reverse($items);
        }

        return $items;
    }
}
