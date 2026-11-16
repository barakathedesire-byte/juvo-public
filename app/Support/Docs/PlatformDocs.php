<?php

namespace App\Support\Docs;

use Illuminate\Support\Facades\Route;

class PlatformDocs
{
    /**
     * Curated, human-written documentation for platform features.
     * Add an entry here whenever a feature ships so it is searchable in the panel.
     */
    public static function sections(): array
    {
        return [
            [
                'key' => 'storefront',
                'title' => 'Storefront Builder',
                'icon' => 'fas fa-store',
                'group' => 'Marketing & Content',
                'description' => 'Design the customer-facing store layout. Global templates apply to every vendor; vendor-specific templates override them.',
                'items' => [
                    [
                        'name' => 'Create a template',
                        'category' => 'Action',
                        'method' => 'GET',
                        'uri' => '/admin/storefront/create',
                        'description' => 'Opens the dedicated creation form (no modal required). Choose Global or Vendor-specific scope.',
                        'instructions' => 'Storefront Builder → New Template → fill Name and Scope → Create & Edit.',
                    ],
                    [
                        'name' => 'Save a draft',
                        'category' => 'Action',
                        'method' => 'POST',
                        'uri' => '/admin/storefront/{template}/save',
                        'description' => 'Persists the current region drafts without publishing them to customers.',
                        'instructions' => 'Open a template → edit a region tab → Save Draft.',
                    ],
                    [
                        'name' => 'Publish a template',
                        'category' => 'Action',
                        'method' => 'POST',
                        'uri' => '/admin/storefront/{template}/publish',
                        'description' => 'Makes the draft live for the selected region.',
                        'instructions' => 'Open a template → Publish.',
                    ],
                ],
            ],
            [
                'key' => 'regions',
                'title' => 'Regions & Locations',
                'icon' => 'fas fa-map-marker-alt',
                'group' => 'System',
                'description' => 'Group shipping destinations into regions and add the cities/areas that belong to each region.',
                'items' => [
                    [
                        'name' => 'Add a region',
                        'category' => 'Action',
                        'method' => 'POST',
                        'uri' => '/admin/regions',
                        'description' => 'Creates a new region (name, country, sort order, active flag).',
                        'instructions' => 'Regions & Locations → Add Region → fill the form → Save.',
                    ],
                    [
                        'name' => 'Add a location',
                        'category' => 'Action',
                        'method' => 'POST',
                        'uri' => '/admin/regions/{region}/locations',
                        'description' => 'Adds a city/area to an existing region.',
                        'instructions' => 'Regions & Locations → expand a region → Add Location.',
                    ],
                ],
            ],
            [
                'key' => 'products',
                'title' => 'Products, Labels & Categories',
                'icon' => 'fas fa-box',
                'group' => 'Catalogue',
                'description' => 'Manage the product catalogue, promotional labels/badges and the category tree.',
                'items' => [
                    [
                        'name' => 'Assign a product label',
                        'category' => 'Field',
                        'method' => null,
                        'uri' => null,
                        'description' => 'Set a short marketing label (e.g. "Clearance") and a colour style. It renders as a badge on product cards and replaces the default text on the product page.',
                        'instructions' => 'Products → Add/Edit → "Label" and "Label Style". Leave empty to fall back to the automatic badge.',
                    ],
                    [
                        'name' => 'Choose a subcategory',
                        'category' => 'Field',
                        'method' => null,
                        'uri' => null,
                        'description' => 'Category pickers list parents and their children in grouped, indented options.',
                        'instructions' => 'Products / Homepage sections → Category field → pick a parent or child.',
                    ],
                ],
            ],
            [
                'key' => 'homepage',
                'title' => 'Homepage Sections',
                'icon' => 'fas fa-layer-group',
                'group' => 'Marketing & Content',
                'description' => 'Compose the homepage from reusable section blocks including multi-row product rows.',
                'items' => [
                    [
                        'name' => 'Add a product row',
                        'category' => 'Action',
                        'method' => 'POST',
                        'uri' => '/admin/homepage/sections',
                        'description' => 'Adds a section. With the "rows" layout you can add multiple rows, each with its own source, category and limit.',
                        'instructions' => 'Homepage → Sections → Add Section → layout "Rows" → add rows.',
                    ],
                ],
            ],
            [
                'key' => 'hr',
                'title' => 'Human Resources',
                'icon' => 'fas fa-users-gear',
                'group' => 'Operations',
                'description' => 'Employee records and HR operations: departments, positions, attendance, leave, recruitment, compensation, bank details and documents.',
                'items' => [
                    [
                        'name' => 'HR hub',
                        'category' => 'Section',
                        'method' => 'GET',
                        'uri' => '/admin/hr',
                        'description' => 'Entry point for all HR modules.',
                        'instructions' => 'Sidebar → Human Resources → Overview.',
                    ],
                ],
            ],
            [
                'key' => 'automation',
                'title' => 'Automation & Workflows',
                'icon' => 'fas fa-diagram-project',
                'group' => 'System',
                'description' => 'Event-driven workflows and scheduled automations that run actions such as notifications, emails and status changes.',
                'items' => [
                    [
                        'name' => 'Scheduler',
                        'category' => 'Operation',
                        'method' => null,
                        'uri' => null,
                        'description' => 'Scheduled automations are evaluated every minute by the "schedule:run" cron entry.',
                        'instructions' => 'Ensure the cron entry "php artisan schedule:run" runs every minute on the server.',
                    ],
                ],
            ],
            [
                'key' => 'access',
                'title' => 'Access Control',
                'icon' => 'fas fa-shield-halved',
                'group' => 'System',
                'description' => 'Role-based visibility. Sidebar tabs and write actions are gated by permissions resolved through the admin tab registry.',
                'items' => [
                    [
                        'name' => 'Permissions',
                        'category' => 'Concept',
                        'method' => null,
                        'uri' => null,
                        'description' => 'Users see a tab if they hold any "see" permission and can act if they hold any "write" permission for that tab.',
                        'instructions' => 'Assign permissions to roles under Users → Roles.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Auto-generated endpoint index derived from the live route table so that
     * newly added admin routes are documented automatically.
     */
    public static function endpoints(): array
    {
        $items = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! str_starts_with($name, 'admin.')) {
                continue;
            }

            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $method = $methods[0] ?? 'GET';

            $middleware = collect($route->gatherMiddleware())
                ->reject(fn ($m) => in_array($m, ['web', 'api'], true))
                ->map(function ($m) {
                    if ($m instanceof \Closure) {
                        return 'Closure';
                    }

                    return is_object($m) ? get_class($m) : (string) $m;
                })
                ->values()
                ->all();

            $items[] = [
                'name' => $name,
                'category' => 'Endpoint',
                'method' => $method,
                'uri' => '/'.ltrim($route->uri(), '/'),
                'controller' => $route->getActionName(),
                'middleware' => $middleware,
                'description' => 'Auto-generated from the route table.',
                'instructions' => count($methods) > 1 ? 'Accepts: '.implode(', ', $methods) : null,
            ];
        }

        usort($items, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $items;
    }

    /**
     * Flat list of all documented items with their section attached.
     */
    public static function allItems(): array
    {
        $items = [];

        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                $items[] = $item + ['section' => $section['title'], 'section_key' => $section['key'], 'group' => $section['group']];
            }
        }

        foreach (self::endpoints() as $item) {
            $items[] = $item + ['section' => 'API Endpoints', 'section_key' => 'endpoints', 'group' => 'Reference'];
        }

        return $items;
    }

    public static function categories(): array
    {
        $categories = array_values(array_unique(array_column(self::allItems(), 'category')));
        sort($categories);

        return $categories;
    }
}
