<?php

use App\Models\Page;
use App\Services\Pages\PageBlocks;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $slugs = ['about-us', 'terms-conditions', 'privacy-policy', 'return-policy'];

        Page::with('translations')
            ->whereIn('slug', $slugs)
            ->where('is_system', false)
            ->get()
            ->each(function (Page $page) {
                if (! empty($page->blocks)) {
                    return;
                }

                $translation = $page->translations->firstWhere('language_code', config('app.locale'))
                    ?? $page->translations->first();

                $heading = PageBlocks::make('heading');
                $heading['settings'] = array_merge($heading['settings'], [
                    'text' => $page->title(),
                    'level' => 'h1',
                    'align' => 'left',
                ]);

                $blocks = [$heading];

                if ($translation?->image_url) {
                    $image = PageBlocks::make('image');
                    $image['settings'] = array_merge($image['settings'], [
                        'image' => $translation->image_url,
                        'alt' => $page->title(),
                        'align' => 'center',
                    ]);
                    $blocks[] = $image;
                }

                if ($translation?->content) {
                    $text = PageBlocks::make('text');
                    $text['settings'] = array_merge($text['settings'], [
                        'heading' => null,
                        'body' => $translation->content,
                        'align' => 'left',
                        'max_width' => 'md',
                    ]);
                    $blocks[] = $text;
                }

                $page->update([
                    'template' => 'default',
                    'blocks' => $blocks,
                ]);
            });
    }

    public function down(): void
    {
        //
    }
};
