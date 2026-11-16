<?php

namespace Tests\Feature;

use App\Models\BusinessRule;
use App\Services\Rules\Decision;
use App\Services\Rules\DecisionPipeline;
use App\Services\Rules\RuleEngine;
use App\Services\Rules\RuleRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessRuleEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected function engine(): RuleEngine
    {
        return app(RuleEngine::class);
    }

    public function test_registry_sync_records_governance_metadata(): void
    {
        app(RuleRegistry::class)->sync();

        $rule = BusinessRule::where('key', 'ad_enabled')->first();

        $this->assertTrue($rule->is_managed);
        $this->assertSame('advertising', $rule->category);
        $this->assertSame('Juvo Ads', $rule->owner);
        $this->assertNotNull($rule->validation_rules);
        $this->assertNotNull($rule->default_value);
    }

    public function test_active_valid_value_is_returned(): void
    {
        $engine = $this->engine();
        $engine->setValue('pricing.max_discount_percent', '25');

        $this->assertSame(25.0, $engine->number('pricing.max_discount_percent'));
    }

    public function test_inactive_managed_rule_resolves_to_safe_default(): void
    {
        $engine = $this->engine();
        $engine->setValue('pricing.max_discount_percent', '25');
        $engine->setActive('pricing.max_discount_percent', false);

        $this->assertSame(0.0, $engine->number('pricing.max_discount_percent'));
    }

    public function test_missing_managed_rule_resolves_to_safe_default(): void
    {
        BusinessRule::where('key', 'pricing.min_price')->delete();
        $engine = $this->engine();
        $engine->flush();

        $this->assertSame(0.0, $engine->number('pricing.min_price'));
    }

    public function test_invalid_stored_value_falls_back_to_safe_default(): void
    {
        BusinessRule::where('key', 'low_stock_threshold')->update(['value' => 'not-a-number']);
        $engine = $this->engine();
        $engine->flush();

        $this->assertSame(10.0, $engine->number('low_stock_threshold'));
    }

    public function test_set_value_rejects_value_outside_schema(): void
    {
        $this->expectException(ValidationException::class);

        $this->engine()->setValue('pricing.max_discount_percent', '150');
    }

    public function test_set_value_bumps_version_and_writes_revision(): void
    {
        $engine = $this->engine();

        $first = $engine->setValue('pricing.max_discount_percent', '30');
        $second = $engine->setValue('pricing.max_discount_percent', '40', null, 'test change');

        $this->assertSame($first->version + 1, $second->version);
        $this->assertDatabaseHas('business_rule_revisions', [
            'key' => 'pricing.max_discount_percent',
            'version' => $second->version,
            'value' => '40',
            'reason' => 'test change',
        ]);
    }

    public function test_revert_restores_previous_value_as_new_revision(): void
    {
        $engine = $this->engine();

        $v1 = $engine->setValue('pricing.max_discount_percent', '15');
        $v2 = $engine->setValue('pricing.max_discount_percent', '35');
        $reverted = $engine->revert('pricing.max_discount_percent', $v1->version);

        $this->assertSame('15', $reverted->value);
        $this->assertGreaterThan($v2->version, $reverted->version);
    }

    public function test_unregistered_rule_cannot_be_written(): void
    {
        $this->expectException(ValidationException::class);

        $this->engine()->setValue('totally.unknown.rule', '1');
    }

    public function test_legacy_unmanaged_rule_keeps_passthrough_behaviour(): void
    {
        BusinessRule::create([
            'key' => 'legacy_custom_flag',
            'name' => 'Legacy flag',
            'value_type' => 'number',
            'value' => '42',
            'is_active' => true,
            'is_managed' => false,
        ]);

        $engine = $this->engine();
        $engine->flush();

        $this->assertSame(42.0, $engine->number('legacy_custom_flag', 7));
    }

    public function test_boolean_coercion_follows_stored_value(): void
    {
        $engine = $this->engine();

        $engine->setValue('ad_auto_approve', '1');
        $this->assertTrue($engine->boolean('ad_auto_approve'));

        $engine->setValue('ad_auto_approve', '0');
        $this->assertFalse($engine->boolean('ad_auto_approve'));
    }

    public function test_decision_pipeline_short_circuits_in_priority_order(): void
    {
        $calls = [];
        $pipeline = new DecisionPipeline;

        $decision = $pipeline->evaluate([
            'algorithm' => function () use (&$calls) {
                $calls[] = 'algorithm';

                return Decision::allow('algorithm');
            },
            'auth' => function () use (&$calls) {
                $calls[] = 'auth';

                return Decision::deny('auth', 'not authenticated');
            },
            'eligibility' => function () use (&$calls) {
                $calls[] = 'eligibility';

                return Decision::allow('eligibility');
            },
        ]);

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('auth', $decision->blockedBy());
        $this->assertSame(['auth'], $calls);
    }

    public function test_algorithm_cannot_override_hard_eligibility_denial(): void
    {
        $pipeline = new DecisionPipeline;

        $decision = $pipeline->evaluate([
            'eligibility' => fn () => Decision::deny('eligibility', 'vendor not approved'),
            'algorithm' => fn () => Decision::allow('algorithm', 'high relevance score'),
            'ux' => fn () => Decision::allow('ux'),
        ]);

        $this->assertFalse($decision->isAllowed());
        $this->assertSame('eligibility', $decision->blockedBy());
    }

    public function test_decision_pipeline_allows_when_every_stage_passes(): void
    {
        $pipeline = new DecisionPipeline;

        $decision = $pipeline->evaluate([
            'auth' => fn () => Decision::allow('auth'),
            'configured_rules' => fn () => Decision::allow('configured_rules'),
            'algorithm' => fn () => Decision::allow('algorithm'),
        ]);

        $this->assertTrue($decision->isAllowed());
        $this->assertNull($decision->blockedBy());
    }
}
