<?php

namespace App\Repositories\Admin\Category;

use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Support\Str;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function all()
    {
        return Category::all();
    }

    public function find($id)
    {
        return Category::findOrFail($id);
    }

    public function store($data)
    {
        $slug = \Str::slug($data['name']);

        $category = $this->create([
            'slug' => $slug,
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'] ?? true,
            'parent_category_id' => $data['parent_category_id'] ?? null,
        ]);

        return $category;
    }

    public function update($id, array $data)
    {
        $category = $this->find($id);
        $slug = \Str::slug($data['name']);

        $category->update([
            'slug' => $slug,
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'] ?? true,
            'parent_category_id' => $data['parent_category_id'] ?? null,
        ]);

        return $category;
    }

    public function destroy($id)
    {
        $category = $this->find($id);

        foreach ($category->translations as $translation) {
            if ($translation->image_url) {
                \Storage::disk('public')->delete($translation->image_url);
            }
        }

        return $category->delete();
    }

    public function storeWithTranslations(array $translations, array $meta = [])
    {
        $slug = $this->uniqueSlug($meta['slug'] ?? null, $translations['en']['name'] ?? '');

        $category = Category::create([
            'slug' => $slug,
            'parent_category_id' => $meta['parent_category_id'] ?? null,
            'status' => $meta['status'] ?? true,
            'icon' => $meta['icon'] ?? null,
        ]);

        foreach ($translations as $languageCode => $translation) {
            $imagePath = null;

            if (isset($translation['image']) && $translation['image'] instanceof \Illuminate\Http\UploadedFile) {
                $imagePath = store_optimized_image($translation['image'], 'categories');
            }

            CategoryTranslation::create([
                'category_id' => $category->id,
                'language_code' => $languageCode,
                'name' => $translation['name'],
                'description' => $translation['description'] ?? null,
                'image_url' => $imagePath,
            ]);
        }

        return $category;
    }

    public function updateWithTranslations(Category $category, array $translations, array $meta = [])
    {
        $category->update([
            'slug' => $this->uniqueSlug($meta['slug'] ?? null, $translations['en']['name'] ?? $category->slug, $category->id),
            'parent_category_id' => $meta['parent_category_id'] ?? null,
            'status' => $meta['status'] ?? $category->status,
            'icon' => array_key_exists('icon', $meta) ? ($meta['icon'] ?: null) : $category->icon,
        ]);

        foreach ($translations as $languageCode => $translation) {
            $imagePath = $category->translations()->where('language_code', $languageCode)->value('image_url');

            if (isset($translation['image']) && $translation['image'] instanceof \Illuminate\Http\UploadedFile) {
                $imagePath = store_optimized_image($translation['image'], 'categories');
            }

            $category->translations()->updateOrCreate(
                ['language_code' => $languageCode],
                [
                    'name' => $translation['name'],
                    'description' => $translation['description'] ?? null,
                    'image_url' => $imagePath,
                ]
            );
        }

        return $category;
    }

    /**
     * Build a unique slug, optionally excluding the current record.
     */
    protected function uniqueSlug(?string $slug, string $fallbackName, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $fallbackName);

        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;
        $i = 2;

        while (
            Category::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
