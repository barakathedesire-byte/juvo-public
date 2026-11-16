<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class AdminEnginesRenderTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    public static function tabProvider(): array
    {
        return [
            'algorithms' => ['admin.algorithms.index'],
            'automation' => ['admin.automation.index'],
            'automation runs' => ['admin.automation.runs'],
            'workflows' => ['admin.workflows.index'],
            'features' => ['admin.features.index'],
            'marketplace intelligence' => ['admin.intelligence.index'],
            'anomalies' => ['admin.intelligence.anomalies'],
            'search' => ['admin.search.index'],
            'pricing' => ['admin.pricing.index'],
            'approvals' => ['admin.approvals.index'],
        ];
    }

    /** @dataProvider tabProvider */
    public function test_admin_engine_tab_renders(string $route): void
    {
        $response = $this->actingAs($this->admin())->get(route($route));

        $response->assertOk();
    }
}
