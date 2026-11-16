<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Models\Vendor;

/**
 * Hold a newly signed-up vendor pending until its paperwork is in, honouring
 * the workflow's `auto_approve` flag. Mirrors the admin approval path
 * (`is_approved` + `status = active`) so automation and humans agree.
 */
class VendorOnboardingApprovalAction implements AutomationAction
{
    public function key(): string
    {
        return 'vendor_onboarding_approval';
    }

    public function label(): string
    {
        return 'Vendor onboarding approval';
    }

    public function mutates(): bool
    {
        return true;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        $config = (array) $run->workflow?->config;

        $vendor = Vendor::find($input['vendor_id'] ?? null);
        if (! $vendor) {
            return ['ok' => false, 'reason' => 'vendor_not_found'];
        }

        $requireDocuments = (bool) ($input['require_documents'] ?? $config['require_documents'] ?? true);
        $autoApprove = (bool) ($input['auto_approve'] ?? $config['auto_approve'] ?? false);
        $documents = $this->documentCount($vendor);

        if ($requireDocuments && $documents === 0) {
            return [
                'ok' => true,
                'outcome' => 'held',
                'reason' => 'no_documents',
                'vendor_id' => $vendor->id,
                'documents' => 0,
            ];
        }

        if ($vendor->isApproved()) {
            return [
                'ok' => true,
                'outcome' => 'already_approved',
                'vendor_id' => $vendor->id,
                'documents' => $documents,
            ];
        }

        if (! $autoApprove) {
            return [
                'ok' => true,
                'outcome' => 'awaiting_review',
                'vendor_id' => $vendor->id,
                'documents' => $documents,
            ];
        }

        $vendor->update(['is_approved' => true, 'status' => 'active']);

        return [
            'ok' => true,
            'outcome' => 'approved',
            'vendor_id' => $vendor->id,
            'documents' => $documents,
        ];
    }

    protected function documentCount(Vendor $vendor): int
    {
        return count($this->documents($vendor))
            + (int) $vendor->verificationDocuments()->count();
    }

    /**
     * Older signups stored a JSON string inside an array-cast attribute, so
     * decode defensively rather than reporting a false "no documents".
     *
     * @return array<int, mixed>
     */
    protected function documents(Vendor $vendor): array
    {
        $stored = $vendor->business_documents;

        if (is_string($stored)) {
            $stored = json_decode($stored, true) ?: [];
        }

        return is_array($stored) ? array_values($stored) : [];
    }
}
