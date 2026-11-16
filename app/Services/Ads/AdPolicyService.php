<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdPolicyReview;
use Illuminate\Support\Facades\Auth;

/**
 * Advertising content policy. Records an immutable decision for every review
 * and runs a deterministic pre-check so obvious violations are flagged before
 * an admin spends time on them.
 */
class AdPolicyService
{
    /** Reason codes surfaced to reviewers and vendors. */
    public const REASONS = [
        'approved' => 'Meets advertising policy.',
        'prohibited_content' => 'Creative contains prohibited content.',
        'misleading_claim' => 'Creative contains a potentially misleading claim.',
        'broken_destination' => 'Destination URL is missing or invalid.',
        'asset_required' => 'Creative needs a headline and image.',
        'policy_manual' => 'Flagged for manual policy review.',
    ];

    /** Substrings that trigger a manual/blocked review. */
    protected array $prohibited = [
        'guaranteed cure', 'miracle cure', '100% free money', 'get rich quick',
        'counterfeit', 'replica', 'fake ', 'illegal', 'unregulated loan',
    ];

    protected array $misleading = ['guaranteed', 'no risk', 'instant results', 'cure'];

    public function review(AdCampaign $campaign, string $decision, ?string $reasonCode = null, ?string $note = null, ?int $reviewerId = null, ?int $creativeId = null): AdPolicyReview
    {
        $review = AdPolicyReview::create([
            'campaign_id' => $campaign->id,
            'creative_id' => $creativeId,
            'reviewer_id' => $reviewerId ?: Auth::id(),
            'decision' => $decision,
            'reason_code' => $reasonCode,
            'note' => $note,
            'created_at' => now(),
        ]);

        app(AdAuditService::class)->log('policy_review', [
            'campaign_id' => $campaign->id,
            'subject_type' => AdPolicyReview::class,
            'subject_id' => $review->id,
            'after' => ['decision' => $decision, 'reason_code' => $reasonCode],
            'reason' => $note,
        ]);

        return $review;
    }

    /**
     * Deterministic policy pre-check of a campaign's creative copy.
     *
     * @return array{ok: bool, reason_code: ?string, violations: array<int,string>}
     */
    public function screen(AdCampaign $campaign): array
    {
        $text = mb_strtolower(trim(($campaign->creative_headline ?? $campaign->title).' '.($campaign->creative_body ?? '')));

        if (! $campaign->target_url && ! $campaign->product_id) {
            return $this->flag($campaign, 'broken_destination');
        }

        foreach ($this->prohibited as $needle) {
            if (str_contains($text, $needle)) {
                return $this->flag($campaign, 'prohibited_content');
            }
        }

        foreach ($this->misleading as $needle) {
            if (str_contains($text, $needle)) {
                return $this->flag($campaign, 'misleading_claim');
            }
        }

        return ['ok' => true, 'reasons' => [], 'reason_code' => null];
    }

    protected function flag(AdCampaign $campaign, string $reasonCode): array
    {
        $this->review($campaign, 'changes_requested', $reasonCode, self::REASONS[$reasonCode] ?? null);

        return ['ok' => false, 'reasons' => [$reasonCode], 'reason_code' => $reasonCode];
    }
}
