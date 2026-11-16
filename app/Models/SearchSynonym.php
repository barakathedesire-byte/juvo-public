<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchSynonym extends Model
{
    protected $fillable = ['term', 'locale', 'synonyms', 'is_active', 'created_by'];

    protected $casts = [
        'synonyms' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Normalised synonym map for a locale: term => [expanded words].
     *
     * @return array<string, array<int, string>>
     */
    public static function mapFor(string $locale): array
    {
        return static::where('is_active', true)
            ->where(function ($q) use ($locale) {
                $q->whereIn('locale', [$locale, 'en'])->orWhereNull('locale');
            })
            ->get()
            ->mapWithKeys(function (self $row) {
                $term = static::normalize($row->term);
                $syns = array_values(array_filter(array_map(
                    [static::class, 'normalize'],
                    (array) $row->synonyms
                )));

                return $term !== '' ? [$term => $syns] : [];
            })
            ->all();
    }

    public static function normalize(string $value): string
    {
        return trim(mb_strtolower($value));
    }
}
