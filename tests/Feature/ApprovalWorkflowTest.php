<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorVerificationDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function product(string $approvalStatus = 'pending', int $status = 0): Product
    {
        $vendor = Vendor::firstOrFail();

        $product = Product::create([
            'shop_id' => \App\Models\Shop::query()->value('id'),
            'vendor_id' => $vendor->id,
            'slug' => 'approval-'.uniqid(),
            'category_id' => Category::firstOrFail()->id,
            'product_type' => 'variable',
            'status' => $status,
            'approval_status' => $approvalStatus,
            'visibility' => 'public',
            'submitted_at' => now(),
        ]);

        $product->translations()->create([
            'language_code' => 'en',
            'name' => 'Approval Test Widget',
            'description' => 'Widget for approval tests.',
        ]);

        foreach (range(1, 3) as $i) {
            $product->images()->create([
                'name' => "approval-image-{$i}.jpg",
                'image_url' => "products/approval-image-{$i}.jpg",
                'type' => 'thumb',
            ]);
        }

        return $product;
    }

    protected function vendor(array $attrs = []): Vendor
    {
        return Vendor::create(array_merge([
            'name' => 'Approve Me',
            'shop_name' => 'Approve Shop',
            'email' => 'approve-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'inactive',
            'is_approved' => 0,
            'verification_status' => 'pending',
        ], $attrs));
    }

    public function test_admin_can_approve_a_pending_product(): void
    {
        $admin = $this->admin();
        $product = $this->product('pending', 0);

        $this->actingAs($admin)
            ->post(route('admin.products.approval.approve', $product->id))
            ->assertRedirect();

        $product->refresh();
        $this->assertSame('approved', $product->approval_status);
        $this->assertSame(1, (int) $product->status);
        $this->assertNotNull($product->approved_at);
        $this->assertSame($admin->id, (int) $product->approved_by);
        $this->assertTrue(Product::visible()->whereKey($product->id)->exists());
    }

    public function test_admin_can_reject_a_product_with_a_reason(): void
    {
        $admin = $this->admin();
        $product = $this->product('pending', 0);

        $this->actingAs($admin)
            ->post(route('admin.products.approval.reject', $product->id), ['reason' => 'Blurry images'])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame('rejected', $product->approval_status);
        $this->assertSame('Blurry images', $product->rejection_reason);
        $this->assertSame(0, (int) $product->status);
        $this->assertFalse(Product::visible()->whereKey($product->id)->exists());
    }

    public function test_rejecting_a_product_requires_a_reason(): void
    {
        $admin = $this->admin();
        $product = $this->product('pending', 0);

        $this->actingAs($admin)
            ->post(route('admin.products.approval.reject', $product->id), [])
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending', $product->fresh()->approval_status);
    }

    public function test_admin_verifies_a_vendor_and_activates_the_store(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();

        $this->actingAs($admin)
            ->post(route('admin.vendors.verification.approve', $vendor->id))
            ->assertRedirect();

        $vendor->refresh();
        $this->assertSame('verified', $vendor->verification_status);
        $this->assertTrue((bool) $vendor->is_approved);
        $this->assertSame('active', $vendor->status);
        $this->assertNotNull($vendor->verified_at);
        $this->assertSame($admin->id, (int) $vendor->verified_by);
    }

    public function test_admin_rejects_vendor_verification_and_keeps_store_inactive(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $document = VendorVerificationDocument::create([
            'vendor_id' => $vendor->id,
            'type' => 'business_license',
            'title' => 'License',
            'file_path' => 'vendor_verification/'.$vendor->id.'/license.pdf',
            'disk' => 'local',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.vendors.verification.reject', $vendor->id), ['reason' => 'Illegible scan'])
            ->assertRedirect();

        $vendor->refresh();
        $this->assertSame('rejected', $vendor->verification_status);
        $this->assertFalse((bool) $vendor->is_approved);
        $this->assertSame('inactive', $vendor->status);
        $this->assertSame('Illegible scan', $document->fresh()->rejection_reason);
    }

    public function test_admin_can_review_an_individual_document(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor();
        $document = VendorVerificationDocument::create([
            'vendor_id' => $vendor->id,
            'type' => 'id_card',
            'title' => 'National ID',
            'file_path' => 'vendor_verification/'.$vendor->id.'/id.jpg',
            'disk' => 'local',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.vendors.documents.approve', $document->id))
            ->assertRedirect();

        $document->refresh();
        $this->assertSame('approved', $document->status);
        $this->assertSame($admin->id, (int) $document->reviewed_by);
        $this->assertNotNull($document->reviewed_at);

        $this->actingAs($admin)
            ->post(route('admin.vendors.documents.reject', $document->id), ['reason' => 'Expired'])
            ->assertRedirect();

        $this->assertSame('rejected', $document->fresh()->status);
        $this->assertSame('Expired', $document->fresh()->rejection_reason);
    }

    public function test_admin_can_suspend_and_reinstate_a_vendor(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor(['status' => 'active', 'is_approved' => 1, 'verification_status' => 'verified']);

        $this->actingAs($admin)
            ->post(route('admin.vendors.suspend', $vendor->id), ['reason' => 'Policy violations'])
            ->assertRedirect();

        $vendor->refresh();
        $this->assertSame('suspended', $vendor->status);
        $this->assertNotNull($vendor->suspended_at);
        $this->assertSame('Policy violations', $vendor->suspension_reason);

        $this->actingAs($admin)
            ->post(route('admin.vendors.unsuspend', $vendor->id))
            ->assertRedirect();

        $vendor->refresh();
        $this->assertSame('active', $vendor->status);
        $this->assertNull($vendor->suspended_at);
        $this->assertNull($vendor->suspension_reason);
    }
}
