<?php

namespace App\Http\Controllers\Admin\Homepage;

use App\Http\Controllers\Controller;
use App\Models\HomepageSetting;
use App\Support\Homepage\FooterContent;
use Illuminate\Http\Request;

class LayoutController extends Controller
{
    public function sidebar()
    {
        return view('admin.homepage.sidebar', [
            'sections' => HomepageSetting::json('sidebar_sections'),
        ]);
    }

    public function updateSidebar(Request $request)
    {
        $request->validate([
            'sections' => 'nullable|array',
            'sections.*.title' => 'nullable|string|max:80',
            'sections.*.icon' => 'nullable|string|max:80',
            'sections.*.links' => 'nullable|array',
            'sections.*.links.*.label' => 'nullable|string|max:80',
            'sections.*.links.*.url' => 'nullable|string|max:500',
            'sections.*.links.*.badge' => 'nullable|string|max:24',
        ]);

        HomepageSetting::put('sidebar_sections', json_encode($this->sections($request->input('sections', []))));

        return back()->with('success', 'Marketplace sidebar saved.');
    }

    public function footer()
    {
        return view('admin.homepage.footer', [
            'footer' => FooterContent::settings(),
            'defaults' => FooterContent::defaults(),
            'hasCustomColumns' => HomepageSetting::get('footer_columns') !== null,
        ]);
    }

    public function updateFooter(Request $request)
    {
        $request->validate([
            'description' => 'nullable|string|max:500',
            'social_links' => 'nullable|array',
            'social_links.*.label' => 'nullable|string|max:80',
            'social_links.*.icon' => 'nullable|string|max:80',
            'social_links.*.url' => 'nullable|string|max:500',
            'newsletter_enabled' => 'nullable|boolean',
            'newsletter_label' => 'nullable|string|max:120',
            'newsletter_placeholder' => 'nullable|string|max:80',
            'newsletter_button' => 'nullable|string|max:40',
            'columns' => 'nullable|array',
            'columns.*.title' => 'nullable|string|max:80',
            'columns.*.links' => 'nullable|array',
            'columns.*.links.*.label' => 'nullable|string|max:80',
            'columns.*.links.*.url' => 'nullable|string|max:500',
            'copyright' => 'nullable|string|max:160',
            'note' => 'nullable|string|max:160',
        ]);

        HomepageSetting::put('footer_description', trim((string) $request->input('description', '')));
        HomepageSetting::put('footer_social_links', json_encode($this->socialLinks($request->input('social_links', []))));
        HomepageSetting::put('footer_newsletter', json_encode([
            'enabled' => $request->boolean('newsletter_enabled'),
            'label' => trim((string) $request->input('newsletter_label', '')),
            'placeholder' => trim((string) $request->input('newsletter_placeholder', '')),
            'button' => trim((string) $request->input('newsletter_button', '')),
        ]));
        HomepageSetting::put('footer_columns', json_encode($this->columns($request->input('columns', []))));
        HomepageSetting::put('footer_copyright', trim((string) $request->input('copyright', '')));
        HomepageSetting::put('footer_note', trim((string) $request->input('note', '')));

        return back()->with('success', 'Footer saved.');
    }

    private function sections(array $input): array
    {
        $sections = [];

        foreach ($input as $section) {
            $title = trim((string) ($section['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $links = [];

            foreach (($section['links'] ?? []) as $link) {
                $label = trim((string) ($link['label'] ?? ''));
                $url = $this->url($link['url'] ?? '');

                if ($label === '' || $url === '') {
                    continue;
                }

                $links[] = [
                    'label' => $label,
                    'url' => $url,
                    'badge' => trim((string) ($link['badge'] ?? '')),
                ];
            }

            if ($links === []) {
                continue;
            }

            $sections[] = [
                'title' => $title,
                'icon' => trim((string) ($section['icon'] ?? '')),
                'links' => $links,
            ];
        }

        return $sections;
    }

    private function socialLinks(array $input): array
    {
        $links = [];

        foreach ($input as $link) {
            $label = trim((string) ($link['label'] ?? ''));
            $url = $this->url($link['url'] ?? '');

            if ($label === '' || $url === '') {
                continue;
            }

            $links[] = [
                'label' => $label,
                'icon' => trim((string) ($link['icon'] ?? '')),
                'url' => $url,
            ];
        }

        return $links;
    }

    private function columns(array $input): array
    {
        $columns = [];

        foreach ($input as $column) {
            $title = trim((string) ($column['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $links = [];

            foreach (($column['links'] ?? []) as $link) {
                $label = trim((string) ($link['label'] ?? ''));
                $url = $this->url($link['url'] ?? '');

                if ($label === '' || $url === '') {
                    continue;
                }

                $links[] = ['label' => $label, 'url' => $url];
            }

            if ($links === []) {
                continue;
            }

            $columns[] = ['title' => $title, 'links' => $links];
        }

        return $columns;
    }

    private function url($value): string
    {
        $url = trim((string) $value);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme !== '' && ! in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return '';
        }

        return $scheme === '' ? 'https://'.$url : $url;
    }
}
