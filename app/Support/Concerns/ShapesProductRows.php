<?php

namespace App\Support\Concerns;

use Illuminate\Support\Collection as BaseCollection;

trait ShapesProductRows
{
    /**
     * Expand a multi-row product section into render-ready rows.
     *
     * Hidden rows are dropped; a row with no eligible products is kept only
     * when it declares an empty-state message so the section can explain
     * itself instead of fabricating inventory. The resolver callback supplies
     * the (already scoped) product collection for each row.
     */
    protected function buildProductRows(array $settings, callable $resolve): array
    {
        $rows = is_array($settings['rows'] ?? null) ? $settings['rows'] : [];
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (array_key_exists('visible', $row) && ! $row['visible']) {
                continue;
            }

            $products = $resolve($row);
            $emptyState = trim((string) ($row['empty_state'] ?? ''));

            if ($products->isEmpty() && $emptyState === '') {
                continue;
            }

            $out[] = [
                'title' => $row['title'] ?? '',
                'layout' => ($row['layout'] ?? 'carousel') === 'grid' ? 'grid' : 'carousel',
                'products' => $products,
                'columns' => [
                    'desktop' => max(1, (int) ($row['columns_desktop'] ?? 5)),
                    'tablet' => max(1, (int) ($row['columns_tablet'] ?? 3)),
                    'mobile' => max(1, (int) ($row['columns_mobile'] ?? 2)),
                ],
                'arrows' => (bool) ($row['arrows'] ?? true),
                'see_all' => (bool) ($row['see_all'] ?? false),
                'see_all_url' => $row['see_all_url'] ?? null,
                'empty_state' => $emptyState ?: null,
            ];
        }

        return $out;
    }

    /**
     * Normalise an admin-picked product id list (comma string or array) into a
     * de-duplicated, ordered collection of integers.
     */
    protected function manualProductIds($raw): BaseCollection
    {
        return collect(is_string($raw) ? explode(',', $raw) : (array) $raw)
            ->map(fn ($id) => (int) trim((string) $id))
            ->filter()
            ->unique()
            ->values();
    }
}
