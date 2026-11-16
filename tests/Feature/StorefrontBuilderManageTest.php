<?php

namespace Tests\Feature;

use App\Models\StorefrontTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class StorefrontBuilderManageTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    private function template(): StorefrontTemplate
    {
        return StorefrontTemplate::create([
            'name' => 'Draft Template',
            'scope' => 'global',
            'vendor_id' => null,
            'blocks' => [],
            'status' => 0,
            'created_by' => $this->admin()->id,
        ]);
    }

    public function test_index_renders(): void
    {
        $this->actingAs($this->admin())->get(route('admin.storefront.index'))->assertOk();
    }

    public function test_template_can_be_created(): void
    {
        $this->actingAs($this->admin())->post(route('admin.storefront.store'), [
            'name' => 'Summer Layout',
            'scope' => 'global',
        ])->assertRedirect(route('admin.storefront.edit', StorefrontTemplate::where('name', 'Summer Layout')->firstOrFail()->id));

        $this->assertEmpty(StorefrontTemplate::where('name', 'Summer Layout')->firstOrFail()->status);
    }

    public function test_draft_save_persists_multiple_rows(): void
    {
        $template = $this->template();
        $admin = $this->admin();

        $layout = [
            ['id' => 'a1', 'type' => 'heading', 'hidden' => false, 'settings' => ['text' => 'Row One']],
            ['id' => 'a2', 'type' => 'heading', 'hidden' => false, 'settings' => ['text' => 'Row Two']],
            ['id' => 'a3', 'type' => 'heading', 'hidden' => true, 'settings' => ['text' => 'Row Three']],
        ];

        $response = $this->actingAs($admin)->postJson(route('admin.storefront.save', $template->id), [
            'layout' => json_encode($layout),
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true, 'count' => 3]);

        $template->refresh();
        $this->assertCount(3, $template->blocks['home']);
        $this->assertSame('Row Two', $template->blocks['home'][1]['settings']['text']);
        $this->assertTrue((bool) $template->blocks['home'][2]['hidden']);
        $this->assertSame(0, (int) $template->status);
    }

    public function test_publish_persists_layout_and_marks_published(): void
    {
        $template = $this->template();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('admin.storefront.publish', $template->id), [
            'layout' => json_encode([
                ['id' => 'b1', 'type' => 'product_grid', 'hidden' => false, 'settings' => ['columns' => 4]],
            ]),
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);

        $template->refresh();
        $this->assertSame(1, (int) $template->status);
        $this->assertNotNull($template->published_at);
        $this->assertSame(4, (int) $template->blocks['home'][0]['settings']['columns']);
    }

    public function test_edit_page_renders_with_builder(): void
    {
        $template = $this->template();

        $this->actingAs($this->admin())
            ->get(route('admin.storefront.edit', $template->id))
            ->assertOk()
            ->assertSee('blockList')
            ->assertSee('saveDraftBtn');
    }

    public function test_read_only_user_cannot_save(): void
    {
        $template = $this->template();

        $user = \App\Models\User::factory()->create(['role' => 'viewer']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole(\Spatie\Permission\Models\Role::where('name', 'read_only_analyst')->firstOrFail());

        $this->actingAs($user)
            ->postJson(route('admin.storefront.save', $template->id), ['layout' => '[]'])
            ->assertStatus(403);

        $this->assertSame([], $template->refresh()->toArray()['blocks']);
    }

    public function test_invalid_layout_is_sanitised_not_stored_verbatim(): void
    {
        $template = $this->template();

        $this->actingAs($this->admin())->postJson(route('admin.storefront.save', $template->id), [
            'layout' => json_encode([
                ['id' => 'c1', 'type' => 'unknown_type', 'settings' => ['x' => 'y']],
                ['not_a_block' => true],
            ]),
        ])->assertStatus(200);

        $template->refresh();
        $this->assertNotContains('unknown_type', array_column($template->blocks['home'], 'type'));
    }
}
