<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Services\Rules\Decision;
use App\Services\Rules\DecisionPipeline;
use App\Services\Rules\RuleBlockedException;
use App\Services\Rules\RuleEngine;

/**
 * Single eligibility gate for taking a product into the public storefront.
 *
 * Eligibility is a hard stage in the platform decision order: a product may
 * only be published when its vendor is approved and active, and (at the
 * approval transition for a new submission) when it carries the configured
 * minimum number of images. Algorithms, promotions and configured rules can
 * never resurrect a product this gate has denied.
 */
class ProductEligibilityService
{
    public function __construct(
        protected RuleEngine $rules,
        protected DecisionPipeline $pipeline,
    ) {}

    public function minimumImages(): int
    {
        return max(1, (int) $this->rules->number('product_min_images', 3));
    }

    public function imageCount(Product $product): int
    {
        return (int) $product->images()->count();
    }

    /**
     * @param  bool  $requireImages  Enforce the configured minimum image count
     *                               (used at the approval transition).
     */
    public function forPublish(Product $product, bool $requireImages = false): Decision
    {
        return $this->pipeline->evaluate([
            'legal_policy' => fn () => $product->archived_at
                ? Decision::deny('legal_policy', 'Archived products cannot be published.')
                : Decision::allow('legal_policy'),

            'eligibility' => function () use ($product, $requireImages) {
                $vendor = $product->vendor;

                if (! $vendor || ! (bool) $vendor->is_approved || $vendor->status !== 'active') {
                    return Decision::deny('eligibility', 'The vendor is not approved and active.');
                }

                if ($requireImages) {
                    $minimum = $this->minimumImages();

                    if ($this->imageCount($product) < $minimum) {
                        return Decision::deny('eligibility', "A product needs at least {$minimum} images before it can be published.");
                    }
                }

                return Decision::allow('eligibility');
            },
        ]);
    }

    /**
     * @throws RuleBlockedException
     */
    public function assertPublishable(Product $product, bool $requireImages = false): void
    {
        $decision = $this->forPublish($product, $requireImages);

        if (! $decision->isAllowed()) {
            throw new RuleBlockedException($decision->reason);
        }
    }
}
