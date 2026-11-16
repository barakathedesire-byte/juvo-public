<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function makeCategory(string $name, ?int $parentId = null): Category
    {
        $category = Category::create([
            'slug' => \Illuminate\Support\Str::slug($name),
            'parent_category_id' => $parentId,
            'status' => 1,
        ]);

        $category->translations()->create([
            'language_code' => 'en',
            'name' => $name,
            'description' => $name.' description',
            'image_url' => null,
        ]);

        return $category;
    }

    public function test_admin_can_create_a_category_without_an_image_or_description(): void
    {
        $parent = $this->makeCategory('Parent Group');

        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), [
                'translations' => ['en' => ['name' => 'Electronics']],
                'parent_category_id' => $parent->id,
                'status' => '0',
                'slug' => 'electronics-category',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::where('slug', 'electronics-category')->first();
        $this->assertNotNull($category);
        $this->assertSame($parent->id, $category->parent_category_id);
        $this->assertSame(0, (int) $category->status);
        $this->assertSame('Electronics', $category->translations->firstWhere('language_code', 'en')->name);
    }

    public function test_slug_is_generated_when_not_provided_and_is_made_unique(): void
    {
        $this->makeCategory('Home');

        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), [
                'translations' => ['en' => ['name' => 'Home']],
            ])
            ->assertRedirect(route('admin.categories.index'));

        $slugs = Category::whereIn('slug', ['home', 'home-2'])->pluck('slug')->all();
        $this->assertNotEmpty($slugs);
        $this->assertContains('home', $slugs);
        $this->assertContains('home-2', $slugs);
    }

    public function test_admin_can_update_category_parent_and_status(): void
    {
        $parent = $this->makeCategory('Parent');
        $child = $this->makeCategory('Child');

        $this->actingAs($this->admin())
            ->put(route('admin.categories.update', $child->id), [
                'translations' => ['en' => ['name' => 'Child']],
                'parent_category_id' => $parent->id,
                'status' => '0',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $child->refresh();
        $this->assertSame($parent->id, $child->parent_category_id);
        $this->assertSame(0, (int) $child->status);
    }

    public function test_edit_form_excludes_the_category_and_its_descendants_as_parents(): void
    {
        $root = $this->makeCategory('Root');
        $child = $this->makeCategory('Child', $root->id);
        $other = $this->makeCategory('Other');

        $this->actingAs($this->admin())
            ->get(route('admin.categories.edit', $root->id))
            ->assertOk()
            ->assertSee('value="'.$other->id.'"', false)
            ->assertDontSee('value="'.$child->id.'"', false)
            ->assertDontSee('value="'.$root->id.'"', false);
    }

    public function test_category_with_children_cannot_be_deleted(): void
    {
        $root = $this->makeCategory('Locked');
        $child = $this->makeCategory('Sub', $root->id);

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.destroy', $root->id))
            ->assertStatus(422);

        $this->assertNotNull(Category::find($root->id));
        $this->assertNotNull(Category::find($child->id));
    }

    public function test_category_nav_renders_nested_levels_and_refreshes_after_create(): void
    {
        $root = $this->makeCategory('Nav Root Alpha');
        $child = $this->makeCategory('Nav Child Beta', $root->id);
        $grand = $this->makeCategory('Nav Grand Gamma', $child->id);

        cache()->forget(Category::NAV_CACHE_KEY);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Nav Child Beta')
            ->assertSee('Nav Grand Gamma');

        // Prime the navigation cache, then create another category.
        $this->get(route('shop.index'))->assertOk();
        $this->assertTrue(cache()->has(Category::NAV_CACHE_KEY));

        $new = $this->makeCategory('Nav Fresh Delta', $root->id);

        // Creating the category must bust the cached navigation on its own.
        $this->assertFalse(cache()->has(Category::NAV_CACHE_KEY));

        $response = $this->get(route('shop.index'));
        $response->assertOk()->assertSee('Nav Fresh Delta');

        $this->assertNotNull($new);
        $this->assertNotNull(Category::find($grand->id));
    }
}
