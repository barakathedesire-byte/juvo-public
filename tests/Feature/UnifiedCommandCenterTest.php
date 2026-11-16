<?php

namespace Tests\Feature;

use App\Models\IntelligenceSavedReport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class UnifiedCommandCenterTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    public static function workspaceProvider(): array
    {
        return [
            'overview' => ['admin.command-center.intelligence'],
            'marketplace' => ['admin.command-center.marketplace'],
            'logistics' => ['admin.command-center.logistics'],
            'vendors' => ['admin.command-center.vendors'],
            'customers' => ['admin.command-center.customers'],
            'geography' => ['admin.command-center.geography'],
            'risks' => ['admin.command-center.risks'],
            'reports' => ['admin.command-center.reports'],
            'health' => ['admin.command-center.health'],
        ];
    }

    /** @dataProvider workspaceProvider */
    public function test_admin_can_open_intelligence_workspace(string $route): void
    {
        $this->actingAs($this->admin())
            ->get(route($route))
            ->assertOk()
            ->assertSee('Juvo Intelligence');
    }

    public function test_assistant_returns_period_and_evidence(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.command-center.assistant'), [
                'question' => 'Which products are frequently out of stock?',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['answer', 'bullets', 'method', 'link', 'period']);
    }

    public function test_report_export_streams_csv(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.command-center.reports.export', ['report_type' => 'marketplace']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_admin_can_save_and_remove_report_configuration(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.command-center.reports.saved.store'), [
            'name' => 'Weekly marketplace',
            'report_type' => 'marketplace',
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ])->assertRedirect();

        $saved = IntelligenceSavedReport::where('user_id', $admin->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('admin.command-center.reports.saved.destroy', $saved))
            ->assertRedirect();

        $this->assertDatabaseMissing('intelligence_saved_reports', ['id' => $saved->id]);
    }
}
