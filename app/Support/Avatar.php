<?php

namespace App\Support;

class Avatar
{
    public static function initials(string $name, string $background = '0d6efd', int $size = 40): string
    {
        $letters = collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        if ($letters === '') {
            $letters = '?';
        }

        $background = ltrim($background, '#');
        $radius = (int) round($size / 2);
        $fontSize = (int) round($size * 0.42);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}" role="img" aria-label="{$letters}">
  <rect width="{$size}" height="{$size}" rx="{$radius}" fill="#{$background}"/>
  <text x="50%" y="50%" dy=".1em" text-anchor="middle" fill="#fff" font-family="system-ui,sans-serif" font-size="{$fontSize}" font-weight="600">{$letters}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
