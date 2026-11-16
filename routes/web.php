<?php

use App\Http\Controllers\Admin\AdManager\AnalyticsController as AdAnalyticsController;
use App\Http\Controllers\Admin\AdManager\AuditController as AdAuditController;
use App\Http\Controllers\Admin\AdManager\CampaignController as AdManagerCampaignController;
use App\Http\Controllers\Admin\AdManager\EngineController as AdEngineController;
use App\Http\Controllers\Admin\AdManager\OverviewController as AdOverviewController;
use App\Http\Controllers\Admin\AdManager\PackageController as AdPackageController;
use App\Http\Controllers\Admin\AdManager\PaymentController as AdPaymentController;
use App\Http\Controllers\Admin\AdManager\PlacementController as AdPlacementController;
use App\Http\Controllers\Admin\AdManager\SettingController as AdSettingController;
use App\Http\Controllers\Admin\AdManager\SubmissionController as AdSubmissionController;
use App\Http\Controllers\Admin\AdManager\VendorAccountController as AdVendorAccountController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\BusinessRuleController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CommandCenterController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\Homepage\CollectionController as HomepageCollectionController;
use App\Http\Controllers\Admin\Homepage\HomepageController as HomepageBuilderController;
use App\Http\Controllers\Admin\Homepage\LayoutController as HomepageLayoutController;
use App\Http\Controllers\Admin\Homepage\SectionController as HomepageSectionController;
use App\Http\Controllers\Admin\Homepage\SlideController as HomepageSlideController;
use App\Http\Controllers\Admin\Homepage\TileController as HomepageTileController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\IntegrityController;
use App\Http\Controllers\Admin\Intelligence\AlgorithmController as IntelligenceAlgorithmController;
use App\Http\Controllers\Admin\Intelligence\AutomationController as IntelligenceAutomationController;
use App\Http\Controllers\Admin\Intelligence\MarketplaceIntelligenceController;
use App\Http\Controllers\Admin\Intelligence\SearchIntelligenceController;
use App\Http\Controllers\Admin\Intelligence\UnifiedCommandCenterController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\NewsletterSubscriberController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\ProductApprovalController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductReviewController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ShippingRateController;
use App\Http\Controllers\Admin\SocialMediaLinkController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SupportCannedResponseController;
use App\Http\Controllers\Admin\SupportSlaController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\TaxRateController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\Payments\MobileMoneyCallbackController;
use App\Http\Controllers\SiteSettingsController;
use App\Http\Controllers\Store\CheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

/* require base_path('routes/store.php'); */

// Single centralized staff/admin login. Authenticates against the `web` guard
// (staff `users` table) and enforces portal eligibility. Customer and vendor
// portals keep their own separate guards and are unaffected. Self-service
// password reset is intentionally not exposed: a platform administrator resets
// a staff password and provides it directly.
Route::get('login', [\App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [\App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::post('logout', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

// A typed or bookmarked /logout must not fail: log the staff member out and
// send them back to the sign-in form.
Route::get('logout', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout.get');

// The staff portal is reachable from the storefront host as well as from
// admin.<domain>, so /admin and /admin/login resolve instead of 404.
Route::get('admin', function () {
    $user = auth()->user();

    if ($user instanceof \App\Models\User) {
        return redirect()->route(app(\App\Services\Access\AuthorizationService::class)->landingRoute($user));
    }

    return redirect()->route('login');
})->name('admin.home');
Route::get('admin/login', [\App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('admin.login');
Route::get('agent/login', [\App\Http\Controllers\Auth\AgentLoginController::class, 'showLoginForm'])->name('agent.login');
Route::post('agent/login', [\App\Http\Controllers\Auth\AgentLoginController::class, 'login']);
Route::post('agent/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('agent.login');
})->name('agent.logout');
Route::get('agent/register', [\App\Http\Controllers\Auth\AgentRegisterController::class, 'showRegistrationForm'])->name('agent.register');
Route::post('agent/register', [\App\Http\Controllers\Auth\AgentRegisterController::class, 'register'])->middleware('throttle:10,1')->name('agent.register.post');

/* Global language switcher (riders and other non-admin users) */
Route::post('/change-language', [\App\Http\Controllers\Admin\LanguageController::class, 'changeLanguage'])->name('change.language');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'panel', 'denied.audit'])->group(function () {

    /* Dashboard */
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    /* Categiries */
    Route::middleware('permission:categories.view|manage-products')->group(function () {
        Route::resource('categories', CategoryController::class);
    });
    Route::post('/categories/data', [CategoryController::class, 'getCategories'])->middleware('permission:categories.view|manage-products')->name('categories.data');
    Route::post('/categories/update-status', [CategoryController::class, 'updateCategoryStatus'])->middleware('permission:categories.edit|manage-products')->name('categories.updateStatus');

    /* Products */
    Route::get('products/approval', [ProductApprovalController::class, 'index'])->middleware('permission:products.view|products.approve')->name('products.approval.index');
    Route::post('products/{id}/approval/approve', [ProductApprovalController::class, 'approve'])->middleware('permission:products.approve')->name('products.approval.approve');
    Route::post('products/{id}/approval/reject', [ProductApprovalController::class, 'reject'])->middleware('permission:products.approve')->name('products.approval.reject');
    Route::middleware('permission:products.view|manage-products')->group(function () {
        Route::resource('products', ProductController::class)->except(['show']);
    });
    Route::post('products/data', [ProductController::class, 'getProducts'])->middleware('permission:products.view|manage-products')->name('products.data');
    Route::post('products/updateStatus', [ProductController::class, 'updateStatus'])->middleware('permission:products.edit|manage-products')->name('products.updateStatus');

    /* Brands */
    Route::middleware('permission:brands.view|manage-products')->group(function () {
        Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
        Route::get('brands/getdata', [BrandController::class, 'getData'])->name('brands.getData');
        Route::get('brands/{id}/edit', [BrandController::class, 'edit'])->name('brands.edit');
        Route::get('brands/create', [BrandController::class, 'create'])->name('brands.create');
    });
    Route::put('brands/{brand}', [BrandController::class, 'update'])->middleware('permission:brands.edit|manage-products')->name('brands.update');
    Route::post('brands', [BrandController::class, 'store'])->middleware('permission:brands.create|manage-products')->name('brands.store');
    Route::delete('brands/{id}', [BrandController::class, 'destroy'])->middleware('permission:brands.delete|manage-products')->name('brands.destroy');
    Route::post('brands/update-status', [BrandController::class, 'updateStatus'])->middleware('permission:brands.edit|manage-products')->name('brands.updateStatus');

    /* change Language */
    Route::post('/change-language', [LanguageController::class, 'changeLanguage'])->name('change.language');

    /* Menus */
    Route::middleware('permission:menus.view|page_builder.view|manage-content')->group(function () {
        Route::resource('menus', MenuController::class)->except(['show']);
        Route::resource('menus.items', MenuItemController::class)->shallow()->except(['show']);
        Route::get('menus-items', [MenuItemController::class, 'index'])->name('menus.item.index');
        Route::post('menus/data', [MenuController::class, 'getData'])->name('menus.data');
        Route::post('menus-items/getdata', [MenuItemController::class, 'getData'])->name('menus.item.getData');
    });

    /* Banners */
    Route::middleware('permission:banners.view|page_builder.view|manage-content')->group(function () {
        Route::resource('banners', BannerController::class)->except(['show']);
        Route::post('banners/data', [BannerController::class, 'getData'])->name('banners.data');
        Route::put('/banners/toggle-status/{id}', [BannerController::class, 'toggleStatus'])->name('banners.toggleStatus');
        Route::post('/banners/update-status', [BannerController::class, 'updateStatus'])->name('banners.updateStatus');
    });

    /* Social Media Links */
    Route::middleware('permission:social_links.view|page_builder.view|manage-content')->group(function () {
        Route::resource('social-media-links', SocialMediaLinkController::class)->except(['show']);
        Route::post('social-media-links/data', [SocialMediaLinkController::class, 'getData'])->name('social-media-links.data');
    });

    /* Orders */
    Route::get('orders', [OrderController::class, 'index'])->middleware('permission:orders.view|manage-orders')->name('orders.index');
    Route::delete('orders/{id}', [OrderController::class, 'destroy'])->middleware('permission:orders.delete|manage-orders')->name('orders.destroy');
    Route::post('orders/data', [OrderController::class, 'getData'])->middleware('permission:orders.view|manage-orders')->name('orders.data');

    /* Fleetbase shipping */
    Route::middleware('permission:orders.edit|manage-orders')->group(function () {});

    /* Product Variants */
    Route::middleware('permission:products.view|manage-products')->group(function () {
        Route::resource('product_variants', ProductVariantController::class)->except(['show']);
        Route::post('/product_variants/data', [ProductVariantController::class, 'getData'])->name('product_variants.data');
    });

    /* Customers */
    Route::middleware('permission:customers.view|manage-customers')->group(function () {
        Route::resource('customers', CustomerController::class)->except(['show']);
        Route::get('customers/data', [CustomerController::class, 'getCustomerData'])->name('customers.data');
    });

    /* Reviews */
    Route::middleware('permission:product_reviews.view|manage-reviews')->group(function () {
        Route::get('/reviews/data', [ProductReviewController::class, 'getData'])->name('reviews.data');
        Route::post('reviews/{review}/toggle', [ProductReviewController::class, 'toggleApproval'])->name('reviews.toggle');
        Route::resource('reviews', ProductReviewController::class)->except(['create', 'store']);
    });

    /* Attributes */
    Route::middleware('permission:attributes.view|manage-products')->group(function () {
        Route::resource('attributes', AttributeController::class)->except(['show']);
        Route::post('attributes/data', [AttributeController::class, 'getAttributesData'])->name('attributes.data');
    });

    /* Vendors */
    Route::middleware('permission:manage-vendors')->group(function () {
        Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::get('vendors-verification', [VendorController::class, 'verificationQueue'])->name('vendors.verification');
        Route::get('verification-documents/{id}/download', [VendorController::class, 'downloadDocument'])->name('vendors.documents.download');
        Route::post('verification-documents/{id}/approve', [VendorController::class, 'approveDocument'])->name('vendors.documents.approve');
        Route::post('verification-documents/{id}/reject', [VendorController::class, 'rejectDocument'])->name('vendors.documents.reject');
        Route::get('vendors/data', [VendorController::class, 'getVendorData'])->name('vendors.data');
        Route::delete('vendors/{id}', [VendorController::class, 'destroy'])->name('vendors.destroy');
        Route::get('vendors/create', [VendorController::class, 'create'])->name('vendors.create');
        Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::post('vendors/{id}/approve', [VendorController::class, 'approve'])->name('vendors.approve');
        Route::post('vendors/{id}/reject', [VendorController::class, 'reject'])->name('vendors.reject');
        Route::post('vendors/{id}/verification/approve', [VendorController::class, 'approveVerification'])->name('vendors.verification.approve');
        Route::post('vendors/{id}/verification/reject', [VendorController::class, 'rejectVerification'])->name('vendors.verification.reject');
        Route::post('vendors/{id}/suspend', [VendorController::class, 'suspend'])->name('vendors.suspend');
        Route::post('vendors/{id}/unsuspend', [VendorController::class, 'unsuspend'])->name('vendors.unsuspend');
        Route::post('vendors/{id}/restrict', [VendorController::class, 'restrict'])->name('vendors.restrict');
        Route::post('vendors/{id}/unrestrict', [VendorController::class, 'unrestrict'])->name('vendors.unrestrict');
        Route::post('vendors/{id}/commission', [VendorController::class, 'updateCommission'])->name('vendors.commission');
        Route::post('vendors/{id}/adjust', [VendorController::class, 'adjustBalance'])->name('vendors.adjust');
        Route::get('vendors/{id}', [VendorController::class, 'show'])->name('vendors.show');
    });

    /* Pages & Page Builder */
    Route::middleware('permission:manage-content')->group(function () {
        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::post('pages/data', [PageController::class, 'data'])->name('pages.data');
        Route::get('pages/virtual-data', [PageController::class, 'virtualData'])->name('pages.virtualData');
        Route::get('pages/create', [PageController::class, 'create'])->name('pages.create');
        Route::post('pages', [PageController::class, 'store'])->name('pages.store');
        Route::post('pages/media', [PageController::class, 'uploadMedia'])->name('pages.media');
        Route::post('pages/update-status', [PageController::class, 'updatePageStatus'])->name('pages.updateStatus');
        Route::get('pages/{page}/editor', [PageController::class, 'editor'])->name('pages.editor');
        Route::get('pages/{page}/preview', [PageController::class, 'preview'])->name('pages.preview');
        Route::post('pages/{page}/save-blocks', [PageController::class, 'saveBlocks'])->name('pages.saveBlocks');
        Route::post('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
        Route::post('pages/{page}/unpublish', [PageController::class, 'unpublish'])->name('pages.unpublish');
        Route::post('pages/{page}/duplicate', [PageController::class, 'duplicate'])->name('pages.duplicate');
        Route::get('pages/{page}/revisions', [PageController::class, 'revisions'])->name('pages.revisions');
        Route::post('pages/{page}/revisions/{revision}/restore', [PageController::class, 'restore'])->name('pages.revisions.restore');
        Route::get('pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
    });

    /* payments */
    Route::middleware('permission:manage-finances')->group(function () {
        Route::get('payments/get-data', [PaymentController::class, 'getData'])->name('payments.getData');
        Route::resource('payments', PaymentController::class)->only(['index', 'destroy', 'show']);

        /* Refunds */
        Route::get('refunds', [RefundController::class, 'index'])->name('refunds.index');
        Route::get('refunds/data', [RefundController::class, 'getData'])->name('refunds.getData');
        Route::post('refunds/{refund}/approve', [RefundController::class, 'approve'])->name('refunds.approve');
        Route::post('refunds/{refund}/reject', [RefundController::class, 'reject'])->name('refunds.reject');
        Route::delete('refunds/{refund}', [RefundController::class, 'destroy'])->name('refunds.destroy');
        Route::get('refunds/{refund}', [RefundController::class, 'show'])->name('refunds.show');

        /* Payment Gateways */
        Route::get('payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
        Route::get('payment-gateways/create', [PaymentGatewayController::class, 'create'])->name('payment-gateways.create');
        Route::post('payment-gateways', [PaymentGatewayController::class, 'store'])->name('payment-gateways.store');
        Route::get('payment-gateways/data', [PaymentGatewayController::class, 'getData'])->name('payment-gateways.getData');
        Route::get('payment-gateways/{paymentGateway}/edit', [PaymentGatewayController::class, 'edit'])->name('payment-gateways.edit');
        Route::put('payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('payment-gateways.update');
        Route::delete('payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'destroy'])->name('payment-gateways.destroy');
    });

    /* Support tickets (customer/guest contact + guest conversations) */
    Route::prefix('support-tickets')->name('support-tickets.')->middleware('permission:support.view|manage-support')->group(function () {
        Route::get('/', [SupportTicketController::class, 'index'])->name('index');
        Route::get('data', [SupportTicketController::class, 'getData'])->name('getData');
        Route::get('{ticket}', [SupportTicketController::class, 'show'])->name('show');
        Route::post('{ticket}/reply', [SupportTicketController::class, 'reply'])->middleware('permission:support.reply|manage-support')->name('reply');
        Route::post('{ticket}/status', [SupportTicketController::class, 'status'])->middleware('permission:support.reply|support.close|support.reopen|manage-support')->name('status');
        Route::post('{ticket}/assign', [SupportTicketController::class, 'assign'])->middleware('permission:support.assign|manage-support')->name('assign');
        Route::post('{ticket}/escalate', [SupportTicketController::class, 'escalate'])->middleware('permission:support.escalate|manage-support')->name('escalate');
    });

    /* Support canned responses + SLA policy (support administration) */
    Route::prefix('support-canned')->name('support.canned.')->middleware('permission:support.manage_canned')->group(function () {
        Route::get('/', [SupportCannedResponseController::class, 'index'])->name('index');
        Route::post('/', [SupportCannedResponseController::class, 'store'])->name('store');
        Route::put('{canned}', [SupportCannedResponseController::class, 'update'])->name('update');
        Route::delete('{canned}', [SupportCannedResponseController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('support-sla')->name('support.sla.')->middleware('permission:support.manage_sla')->group(function () {
        Route::get('/', [SupportSlaController::class, 'index'])->name('index');
        Route::put('{priority}', [SupportSlaController::class, 'update'])->name('update');
    });

    /* Centralized feature registry */
    Route::prefix('features')->name('features.')->middleware('permission:features.view')->group(function () {
        Route::get('/', [FeatureController::class, 'index'])->name('index');
        Route::post('/', [FeatureController::class, 'store'])->middleware('permission:features.create')->name('store');
        Route::put('{feature}', [FeatureController::class, 'update'])->middleware('permission:features.edit')->name('update');
        Route::delete('{feature}', [FeatureController::class, 'destroy'])->middleware('permission:features.delete')->name('destroy');
        Route::post('{feature}/toggle', [FeatureController::class, 'toggle'])->middleware('permission:features.toggle|features_toggle.toggle')->name('toggle');
    });

    /* Currencies */
    Route::get('currencies', [CurrencyController::class, 'index'])->middleware('permission:currencies.view|settings.view|manage-settings')->name('currencies.index');
    Route::post('currencies', [CurrencyController::class, 'store'])->middleware('permission:currencies.edit|settings.edit|manage-settings')->name('currencies.store');
    Route::put('currencies/{id}', [CurrencyController::class, 'update'])->middleware('permission:currencies.edit|settings.edit|manage-settings')->name('currencies.update');
    Route::delete('currencies/{id}', [CurrencyController::class, 'destroy'])->middleware('permission:currencies.edit|settings.edit|manage-settings')->name('currencies.destroy');

    /* Integrations */
    Route::middleware('permission:manage-settings')->group(function () {
        Route::get('integrations', [IntegrationController::class, 'index'])->name('integrations.index');
        Route::put('integrations', [IntegrationController::class, 'update'])->name('integrations.update');
        Route::get('integrations/add', [IntegrationController::class, 'create'])->name('integrations.create');
        Route::post('integrations/add', [IntegrationController::class, 'store'])->name('integrations.store');
        Route::get('integrations/{integration}/configure', [IntegrationController::class, 'configure'])->name('integrations.configure');
        Route::put('integrations/{integration}/configure', [IntegrationController::class, 'saveConfig'])->name('integrations.configure.save');
        Route::delete('integrations/{integration}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
    });

    /* Ad Manager */
    Route::prefix('ad-manager')->name('ad-manager.')
        ->middleware('permission:ads.manage_campaigns|ads.review|ads.manage_placements|ads.manage_pricing|ads.manage_finances|ads.manage_optimization|ads.manage_policies|manage-ads')
        ->group(function () {
            Route::get('/', [AdOverviewController::class, 'index'])->name('overview');

            Route::get('submissions', [AdSubmissionController::class, 'index'])->name('submissions.index');
            Route::get('submissions/{campaign}', [AdSubmissionController::class, 'show'])->name('submissions.show');
            Route::post('submissions/{campaign}/review', [AdSubmissionController::class, 'startReview'])->name('submissions.review');
            Route::post('submissions/{campaign}/approve', [AdSubmissionController::class, 'approve'])->name('submissions.approve');
            Route::post('submissions/{campaign}/reject', [AdSubmissionController::class, 'reject'])->name('submissions.reject');
            Route::post('submissions/{campaign}/request-changes', [AdSubmissionController::class, 'requestChanges'])->name('submissions.changes');

            Route::get('campaigns', [AdManagerCampaignController::class, 'index'])->name('campaigns.index');
            Route::get('campaigns/{campaign}', [AdManagerCampaignController::class, 'show'])->name('campaigns.show');
            Route::post('campaigns/{campaign}/pause', [AdManagerCampaignController::class, 'pause'])->name('campaigns.pause');
            Route::post('campaigns/{campaign}/resume', [AdManagerCampaignController::class, 'resume'])->name('campaigns.resume');
            Route::post('campaigns/{campaign}/complete', [AdManagerCampaignController::class, 'complete'])->name('campaigns.complete');
            Route::post('campaigns/{campaign}/cancel', [AdManagerCampaignController::class, 'cancel'])->name('campaigns.cancel');
            Route::delete('campaigns/{campaign}', [AdManagerCampaignController::class, 'destroy'])->name('campaigns.destroy');

            Route::post('campaigns/{campaign}/creatives', [\App\Http\Controllers\Admin\AdManager\CreativeController::class, 'store'])->name('campaigns.creatives.store');
            Route::post('campaigns/{campaign}/creatives/{creative}/approve', [\App\Http\Controllers\Admin\AdManager\CreativeController::class, 'approve'])->name('campaigns.creatives.approve');

            Route::get('placements', [AdPlacementController::class, 'index'])->name('placements.index');
            Route::post('placements', [AdPlacementController::class, 'store'])->name('placements.store');
            Route::put('placements/{placement}', [AdPlacementController::class, 'update'])->name('placements.update');
            Route::post('placements/{placement}/toggle', [AdPlacementController::class, 'toggle'])->name('placements.toggle');
            Route::delete('placements/{placement}', [AdPlacementController::class, 'destroy'])->name('placements.destroy');

            Route::get('packages', [AdPackageController::class, 'index'])->name('packages.index');
            Route::post('packages', [AdPackageController::class, 'store'])->name('packages.store');
            Route::put('packages/{package}', [AdPackageController::class, 'update'])->name('packages.update');
            Route::post('packages/{package}/toggle', [AdPackageController::class, 'toggle'])->name('packages.toggle');
            Route::delete('packages/{package}', [AdPackageController::class, 'destroy'])->name('packages.destroy');

            Route::get('payments', [AdPaymentController::class, 'index'])->name('payments.index');
            Route::post('payments/{campaign}/verify', [AdPaymentController::class, 'verify'])->name('payments.verify');
            Route::get('payments/{campaign}/invoice', [AdPaymentController::class, 'invoice'])->name('payments.invoice');

            Route::get('analytics', [AdAnalyticsController::class, 'index'])->name('analytics.index');

            /* Juvo Ads engine: finances, auction decisions, policy, optimisation */
            Route::middleware('permission:ads.manage_finances|manage-ads')->group(function () {
                Route::get('finances', [AdEngineController::class, 'finances'])->name('finances');
                Route::post('accounts/{account}/credit', [AdEngineController::class, 'credit'])->name('accounts.credit');
            });
            Route::middleware('permission:ads.view_auctions|manage-ads')->group(function () {
                Route::get('auctions', [AdEngineController::class, 'auctions'])->name('auctions');
                Route::get('auctions/{decision}', [AdEngineController::class, 'showAuction'])->name('auctions.show');
            });
            Route::middleware('permission:ads.manage_policies|manage-ads')->group(function () {
                Route::get('policies', [AdEngineController::class, 'policies'])->name('policies');
                Route::post('campaigns/{campaign}/policy-review', [AdEngineController::class, 'runPolicyReview'])->name('policies.review');
            });
            Route::middleware('permission:ads.manage_optimization|manage-ads')->group(function () {
                Route::get('optimization', [AdEngineController::class, 'optimization'])->name('optimization');
                Route::post('campaigns/{campaign}/optimize', [AdEngineController::class, 'runOptimization'])->name('optimization.run');
            });

            Route::get('vendors', [AdVendorAccountController::class, 'index'])->name('vendors.index');
            Route::get('vendors/{vendor}', [AdVendorAccountController::class, 'show'])->name('vendors.show');
            Route::post('vendors/{vendor}/restrict', [AdVendorAccountController::class, 'restrict'])->name('vendors.restrict');
            Route::post('vendors/{vendor}/credit-limit', [AdVendorAccountController::class, 'creditLimit'])->name('vendors.credit-limit');

            Route::get('settings', [AdSettingController::class, 'index'])->name('settings.index');
            Route::post('settings', [AdSettingController::class, 'update'])->name('settings.update');

            Route::get('audit', [AdAuditController::class, 'index'])->name('audit.index');
        });

    /* Algorithm Engine */
    Route::prefix('algorithms')->name('algorithms.')->middleware('permission:algorithms.view')->group(function () {
        Route::get('/', [IntelligenceAlgorithmController::class, 'index'])->name('index');
        Route::get('{algorithm}', [IntelligenceAlgorithmController::class, 'show'])->name('show');
        Route::put('{algorithm}/config', [IntelligenceAlgorithmController::class, 'updateConfig'])->middleware('permission:algorithms.manage')->name('config.update');
        Route::post('{algorithm}/test', [IntelligenceAlgorithmController::class, 'test'])->middleware('permission:algorithms.test')->name('test');
        Route::post('{algorithm}/activate', [IntelligenceAlgorithmController::class, 'activate'])->middleware('permission:algorithms.activate')->name('activate');
        Route::post('{algorithm}/disable', [IntelligenceAlgorithmController::class, 'disable'])->middleware('permission:algorithms.activate')->name('disable');
        Route::post('{algorithm}/rollback/{version}', [IntelligenceAlgorithmController::class, 'rollback'])->middleware('permission:algorithms.rollback')->name('rollback');
    });

    /* Automation Engine */
    Route::prefix('automation')->name('automation.')->middleware('permission:automation.view')->group(function () {
        Route::get('/', [IntelligenceAutomationController::class, 'index'])->name('index');
        Route::get('runs', [IntelligenceAutomationController::class, 'runs'])->name('runs');
        Route::get('{workflow}', [IntelligenceAutomationController::class, 'show'])->name('show');
        Route::post('{workflow}/run', [IntelligenceAutomationController::class, 'run'])->middleware('permission:automation.run')->name('run');
        Route::post('{workflow}/toggle', [IntelligenceAutomationController::class, 'toggle'])->middleware('permission:automation.manage')->name('toggle');
        Route::post('runs/{run}/retry', [IntelligenceAutomationController::class, 'retry'])->middleware('permission:automation.run')->name('retry');
    });

    /* Marketplace Intelligence & Anomaly Detection */
    Route::prefix('intelligence')->name('intelligence.')->middleware('permission:intelligence.view')->group(function () {
        Route::get('/', [MarketplaceIntelligenceController::class, 'index'])->name('index');
        Route::post('associations/rebuild', [MarketplaceIntelligenceController::class, 'rebuildAssociations'])->middleware('permission:intelligence.manage')->name('associations.rebuild');
        Route::get('anomalies', [MarketplaceIntelligenceController::class, 'anomalies'])->middleware('permission:anomalies.view')->name('anomalies');
        Route::post('anomalies/{anomaly}/acknowledge', [MarketplaceIntelligenceController::class, 'acknowledge'])->middleware('permission:anomalies.acknowledge')->name('anomalies.acknowledge');
        Route::post('anomalies/{anomaly}/assign', [MarketplaceIntelligenceController::class, 'assign'])->middleware('permission:anomalies.acknowledge')->name('anomalies.assign');
        Route::post('anomalies/{anomaly}/resolve', [MarketplaceIntelligenceController::class, 'resolve'])->middleware('permission:anomalies.resolve')->name('anomalies.resolve');
        Route::post('anomalies/{anomaly}/false-positive', [MarketplaceIntelligenceController::class, 'falsePositive'])->middleware('permission:anomalies.resolve')->name('anomalies.false-positive');
    });

    /* Search Engine */
    Route::prefix('search')->name('search.')->middleware('permission:search.view')->group(function () {
        Route::get('/', [SearchIntelligenceController::class, 'index'])->name('index');
        Route::post('preview', [SearchIntelligenceController::class, 'preview'])->name('preview');
        Route::post('reindex', [SearchIntelligenceController::class, 'reindex'])->middleware('permission:search.reindex')->name('reindex');
        Route::post('synonyms', [SearchIntelligenceController::class, 'storeSynonym'])->middleware('permission:search.manage')->name('synonyms.store');
        Route::post('synonyms/{synonym}/toggle', [SearchIntelligenceController::class, 'toggleSynonym'])->middleware('permission:search.manage')->name('synonyms.toggle');
        Route::delete('synonyms/{synonym}', [SearchIntelligenceController::class, 'destroySynonym'])->middleware('permission:search.manage')->name('synonyms.destroy');
    });

    /* Pricing & Promotion Optimisation */
    Route::prefix('pricing')->name('pricing.')->middleware('permission:pricing.view')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Pricing\PricingController::class, 'index'])->name('index');
        Route::post('promotions', [\App\Http\Controllers\Admin\Pricing\PricingController::class, 'store'])->middleware('permission:pricing.manage')->name('promotions.store');
        Route::post('promotions/{promotion}/status', [\App\Http\Controllers\Admin\Pricing\PricingController::class, 'updateStatus'])->middleware('permission:pricing.manage')->name('promotions.status');
        Route::post('check', [\App\Http\Controllers\Admin\Pricing\PricingController::class, 'check'])->middleware('permission:pricing.view')->name('check');
    });

    /* Homepage Builder */
    Route::prefix('homepage')->name('homepage.')->middleware('permission:manage-homepage')->group(function () {
        Route::get('/', [HomepageBuilderController::class, 'index'])->name('index');
        Route::get('settings', [HomepageBuilderController::class, 'settings'])->name('settings');
        Route::post('settings', [HomepageBuilderController::class, 'updateSettings'])->name('settings.update');
        Route::get('preview', [HomepageBuilderController::class, 'preview'])->name('preview');

        Route::get('sidebar', [HomepageLayoutController::class, 'sidebar'])->name('sidebar');
        Route::post('sidebar', [HomepageLayoutController::class, 'updateSidebar'])->name('sidebar.update');
        Route::get('footer', [HomepageLayoutController::class, 'footer'])->name('footer');
        Route::post('footer', [HomepageLayoutController::class, 'updateFooter'])->name('footer.update');

        Route::get('collections', [HomepageCollectionController::class, 'index'])->name('collections.index');
        Route::post('collections', [HomepageCollectionController::class, 'store'])->name('collections.store');
        Route::put('collections/{collection}', [HomepageCollectionController::class, 'update'])->name('collections.update');
        Route::delete('collections/{collection}', [HomepageCollectionController::class, 'destroy'])->name('collections.destroy');
        Route::post('collections/{collection}/toggle', [HomepageCollectionController::class, 'toggle'])->name('collections.toggle');

        Route::get('slides', [HomepageSlideController::class, 'index'])->name('slides.index');
        Route::get('slides/create', [HomepageSlideController::class, 'create'])->name('slides.create');
        Route::post('slides', [HomepageSlideController::class, 'store'])->name('slides.store');
        Route::post('slides/reorder', [HomepageSlideController::class, 'reorder'])->name('slides.reorder');
        Route::get('slides/{slide}/edit', [HomepageSlideController::class, 'edit'])->name('slides.edit');
        Route::put('slides/{slide}', [HomepageSlideController::class, 'update'])->name('slides.update');
        Route::delete('slides/{slide}', [HomepageSlideController::class, 'destroy'])->name('slides.destroy');
        Route::post('slides/{slide}/toggle', [HomepageSlideController::class, 'toggle'])->name('slides.toggle');

        Route::get('tiles', [HomepageTileController::class, 'index'])->name('tiles.index');
        Route::post('tiles', [HomepageTileController::class, 'store'])->name('tiles.store');
        Route::post('tiles/reorder', [HomepageTileController::class, 'reorder'])->name('tiles.reorder');
        Route::put('tiles/{tile}', [HomepageTileController::class, 'update'])->name('tiles.update');
        Route::delete('tiles/{tile}', [HomepageTileController::class, 'destroy'])->name('tiles.destroy');
        Route::post('tiles/{tile}/toggle', [HomepageTileController::class, 'toggle'])->name('tiles.toggle');

        Route::get('sections', [HomepageSectionController::class, 'index'])->name('sections.index');
        Route::get('sections/create', [HomepageSectionController::class, 'create'])->name('sections.create');
        Route::post('sections', [HomepageSectionController::class, 'store'])->name('sections.store');
        Route::post('sections/reorder', [HomepageSectionController::class, 'reorder'])->name('sections.reorder');
        Route::get('sections/{section}/edit', [HomepageSectionController::class, 'edit'])->name('sections.edit');
        Route::put('sections/{section}', [HomepageSectionController::class, 'update'])->name('sections.update');
        Route::delete('sections/{section}', [HomepageSectionController::class, 'destroy'])->name('sections.destroy');
        Route::post('sections/{section}/toggle', [HomepageSectionController::class, 'toggle'])->name('sections.toggle');
    });

    /* Workflows */
    Route::middleware('permission:manage-settings')->group(function () {
        Route::get('workflows', [WorkflowController::class, 'index'])->name('workflows.index');
        Route::get('workflows/create', [WorkflowController::class, 'create'])->name('workflows.create');
        Route::post('workflows', [WorkflowController::class, 'store'])->name('workflows.store');
        Route::get('workflows/{workflow}/edit', [WorkflowController::class, 'edit'])->name('workflows.edit');
        Route::put('workflows/{workflow}', [WorkflowController::class, 'update'])->name('workflows.update');
        Route::delete('workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('workflows.destroy');
        Route::post('workflows/{workflow}/toggle', [WorkflowController::class, 'toggle'])->name('workflows.toggle');

        /* Business rules */
        Route::get('business-rules', [BusinessRuleController::class, 'index'])->name('business-rules.index');
        Route::get('business-rules/create', [BusinessRuleController::class, 'create'])->name('business-rules.create');
        Route::post('business-rules', [BusinessRuleController::class, 'store'])->name('business-rules.store');
        Route::post('business-rules/update', [BusinessRuleController::class, 'update'])->name('business-rules.update');
    });

    /* Admin portal self-management (appearance, tabs) */
    Route::prefix('admin-settings')->name('admin-settings.')->middleware('permission:admin_settings.view')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Settings\AdminSettingsController::class, 'index'])->name('index');
        Route::put('appearance', [\App\Http\Controllers\Admin\Settings\AdminSettingsController::class, 'updateAppearance'])->middleware('permission:admin_settings.manage')->name('appearance.update');
        Route::put('general', [\App\Http\Controllers\Admin\Settings\AdminSettingsController::class, 'updateGeneral'])->middleware('permission:admin_settings.manage')->name('general.update');
        Route::post('tabs', [\App\Http\Controllers\Admin\Settings\AdminSettingsController::class, 'storeTab'])->middleware('permission:admin_settings.manage')->name('tabs.store');
        Route::put('tabs/{tab}', [\App\Http\Controllers\Admin\Settings\AdminSettingsController::class, 'updateTab'])->middleware('permission:admin_settings.manage')->name('tabs.update');
        Route::post('tabs/{tab}/toggle', [\App\Http\Controllers\Admin\Settings\AdminSettingsController::class, 'toggleTab'])->middleware('permission:admin_settings.manage')->name('tabs.toggle');
        Route::delete('tabs/{tab}', [\App\Http\Controllers\Admin\Settings\AdminSettingsController::class, 'destroyTab'])->middleware('permission:admin_settings.manage')->name('tabs.destroy');
    });

    /* Commerce settings: coupons, tax, shipping, newsletter */
    Route::middleware('permission:manage-settings')->group(function () {
        Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::put('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::post('coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('coupons.toggle');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');

        Route::get('tax-rates', [TaxRateController::class, 'index'])->name('tax-rates.index');
        Route::post('tax-rates', [TaxRateController::class, 'store'])->name('tax-rates.store');
        Route::put('tax-rates/{taxRate}', [TaxRateController::class, 'update'])->name('tax-rates.update');
        Route::post('tax-rates/{taxRate}/toggle', [TaxRateController::class, 'toggle'])->name('tax-rates.toggle');
        Route::delete('tax-rates/{taxRate}', [TaxRateController::class, 'destroy'])->name('tax-rates.destroy');

        Route::get('shipping-rates', [ShippingRateController::class, 'index'])->name('shipping-rates.index');
        Route::post('shipping-rates', [ShippingRateController::class, 'store'])->name('shipping-rates.store');
        Route::put('shipping-rates/{shippingRate}', [ShippingRateController::class, 'update'])->name('shipping-rates.update');
        Route::post('shipping-rates/{shippingRate}/toggle', [ShippingRateController::class, 'toggle'])->name('shipping-rates.toggle');
        Route::delete('shipping-rates/{shippingRate}', [ShippingRateController::class, 'destroy'])->name('shipping-rates.destroy');

        Route::get('newsletter', [NewsletterSubscriberController::class, 'index'])->name('newsletter.index');
        Route::get('newsletter/export', [NewsletterSubscriberController::class, 'export'])->name('newsletter.export');
        Route::post('newsletter/{subscriber}/toggle', [NewsletterSubscriberController::class, 'toggle'])->name('newsletter.toggle');
        Route::delete('newsletter/{subscriber}', [NewsletterSubscriberController::class, 'destroy'])->name('newsletter.destroy');
    });

    /* Vendor payouts, commissions & reports */
    Route::middleware('permission:manage-finances')->group(function () {
        Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
        Route::post('payouts', [PayoutController::class, 'store'])->name('payouts.store');
        Route::post('payouts/{payout}/pay', [PayoutController::class, 'pay'])->name('payouts.pay');
        Route::post('payouts/{payout}/reject', [PayoutController::class, 'reject'])->name('payouts.reject');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    /* Finance Command Center */
    Route::prefix('finance')->name('finance.')->middleware('permission:finance.view')->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('index');
        Route::get('ledger', [FinanceController::class, 'ledger'])->name('ledger');
        Route::get('ledger/data', [FinanceController::class, 'ledgerData'])->name('ledger.data');
        Route::get('reconciliation', [FinanceController::class, 'reconciliation'])->name('reconciliation');
    });

    /* Unified Command Center — cross-module roll-up */
    Route::get('command-center', [CommandCenterController::class, 'index'])
        ->middleware('permission:analytics.view|dashboard.view|orders.view|finance.view|sales.view|support.view|manage-support|manage-finances|approvals.view|audit.view|features.view')
        ->name('command-center.index');

    /* Unified Intelligence & Executive Command Center */
    Route::prefix('command-center')->name('command-center.')->middleware('permission:analytics.view|dashboard.view|intelligence.view|orders.view|support.view|vendors.view|customers.view')->group(function () {
        Route::get('intelligence', [UnifiedCommandCenterController::class, 'index'])->name('intelligence');
        Route::get('marketplace', [UnifiedCommandCenterController::class, 'marketplace'])->name('marketplace');
        Route::get('logistics', [UnifiedCommandCenterController::class, 'logistics'])->name('logistics');
        Route::get('vendors', [UnifiedCommandCenterController::class, 'vendors'])->name('vendors');
        Route::get('customers', [UnifiedCommandCenterController::class, 'customers'])->name('customers');
        Route::get('geography', [UnifiedCommandCenterController::class, 'geography'])->name('geography');
        Route::get('risks', [UnifiedCommandCenterController::class, 'risks'])->name('risks');
        Route::get('reports', [UnifiedCommandCenterController::class, 'reports'])->name('reports');
        Route::get('reports/export', [UnifiedCommandCenterController::class, 'export'])->middleware('permission:analytics.export|orders.export|intelligence.export')->name('reports.export');
        Route::post('reports/saved', [UnifiedCommandCenterController::class, 'saveReport'])->name('reports.saved.store');
        Route::delete('reports/saved/{savedReport}', [UnifiedCommandCenterController::class, 'destroySavedReport'])->name('reports.saved.destroy');
        Route::post('assistant', [UnifiedCommandCenterController::class, 'assistant'])->name('assistant');
        Route::get('health', [UnifiedCommandCenterController::class, 'health'])->middleware('permission:audit.view|features.view|analytics.view')->name('health');
    });

    /* Profile */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    /* Roles, permissions & decision authority */
    Route::middleware('permission:roles.view')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
        Route::post('roles/{role}/duplicate', [RoleController::class, 'duplicate'])->middleware('permission:roles.create')->name('roles.duplicate');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
    });

    /* Unified approval center */
    Route::middleware('permission:approvals.view')->group(function () {
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('approvals/data', [ApprovalController::class, 'getData'])->name('approvals.data');
        Route::get('approvals/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
        Route::post('approvals/{approval}/approve', [ApprovalController::class, 'approve'])->middleware('permission:approvals.approve')->name('approvals.approve');
        Route::post('approvals/{approval}/reject', [ApprovalController::class, 'reject'])->middleware('permission:approvals.reject')->name('approvals.reject');
        Route::post('approvals/{approval}/request-changes', [ApprovalController::class, 'requestChanges'])->middleware('permission:approvals.approve')->name('approvals.requestChanges');
        Route::post('approvals/{approval}/reassign', [ApprovalController::class, 'reassign'])->middleware('permission:approvals.approve')->name('approvals.reassign');
        Route::post('approvals/{approval}/escalate', [ApprovalController::class, 'escalate'])->middleware('permission:approvals.approve')->name('approvals.escalate');
    });

    /* Audit log */
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/data', [AuditLogController::class, 'getData'])->name('audit-logs.data');
    });

    /* Integrity & reconciliation */
    Route::get('integrity', [IntegrityController::class, 'index'])
        ->middleware('permission:audit.view|finance.reconcile|finance.view')
        ->name('integrity.index');

    /* Staff & roles — super admins only */
    Route::middleware('permission:staff.view')->group(function () {
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('staff/create', [StaffController::class, 'create'])->middleware('permission:staff.create')->name('staff.create');
        Route::post('staff', [StaffController::class, 'store'])->middleware('permission:staff.create')->name('staff.store');
        Route::get('staff/{staff}', [StaffController::class, 'show'])->name('staff.show');
        Route::get('staff/{staff}/edit', [StaffController::class, 'edit'])->middleware('permission:staff.edit')->name('staff.edit');
        Route::put('staff/{staff}', [StaffController::class, 'update'])->middleware('permission:staff.edit')->name('staff.update');
        Route::post('staff/{staff}/toggle-status', [StaffController::class, 'toggleStatus'])->middleware('permission:staff.manage_access')->name('staff.toggleStatus');
        Route::post('staff/{staff}/approve-access', [StaffController::class, 'approveAccess'])->middleware('permission:staff.manage_access')->name('staff.approveAccess');
        Route::post('staff/{staff}/revoke-access', [StaffController::class, 'revokeAccess'])->middleware('permission:staff.manage_access')->name('staff.revokeAccess');
        Route::post('staff/{staff}/revoke-sessions', [StaffController::class, 'revokeSessions'])->middleware('permission:staff.manage_access')->name('staff.revokeSessions');
        Route::get('staff/{staff}/audit', [StaffController::class, 'audit'])->name('staff.audit');
        Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->middleware('permission:staff.delete')->name('staff.destroy');
    });

    /* Logistics staff & RBAC */
    Route::prefix('logistics-staff')->name('logistics-staff.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'index'])
            ->middleware('permission:staff.view')->name('index');
        Route::get('create', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'create'])
            ->middleware('permission:staff.create')->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'store'])
            ->middleware('permission:staff.create')->name('store');
        Route::get('{logisticsStaff}', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'show'])
            ->middleware('permission:staff.view')->name('show');
        Route::get('{logisticsStaff}/edit', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'edit'])
            ->middleware('permission:staff.edit')->name('edit');
        Route::put('{logisticsStaff}', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'update'])
            ->middleware('permission:staff.edit')->name('update');
        Route::post('{logisticsStaff}/toggle-status', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'toggleStatus'])
            ->middleware('permission:staff.manage_access')->name('toggleStatus');
        Route::post('{logisticsStaff}/approve-access', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'approveAccess'])
            ->middleware('permission:staff.manage_access')->name('approveAccess');
        Route::post('{logisticsStaff}/revoke-access', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'revokeAccess'])
            ->middleware('permission:staff.manage_access')->name('revokeAccess');
        Route::post('{logisticsStaff}/revoke-sessions', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'revokeSessions'])
            ->middleware('permission:staff.manage_access')->name('revokeSessions');
        Route::get('{logisticsStaff}/audit', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'audit'])
            ->middleware('permission:staff.view')->name('audit');
        Route::delete('{logisticsStaff}', [\App\Http\Controllers\Admin\LogisticsStaffController::class, 'destroy'])
            ->middleware('permission:staff.delete')->name('destroy');
    });

    /* HR Department */
    require base_path('routes/admin.php');

});

Route::middleware(['auth', 'permission:manage-settings'])->group(function () {
    Route::get('site-settings', [SiteSettingsController::class, 'index'])->name('site-settings.index');
    Route::get('site-settings/edit', [SiteSettingsController::class, 'edit'])->name('admin.site-settings.edit');
    Route::put('site-settings/update', [SiteSettingsController::class, 'update'])->name('admin.site-settings.update');
});

Route::middleware('auth.customer')->group(function () {
    Route::post('/checkout/process', [CheckoutController::class, 'process'])->name('checkout.process');
    // PayPal success callback
    Route::get('/checkout/paypal/success', [CheckoutController::class, 'paypalSuccess'])
        ->name('paypal.success');
    // PayPal cancel callback
    Route::get('/checkout/paypal/cancel', [CheckoutController::class, 'paypalCancel'])
        ->name('paypal.cancel');
    // Stripe hosted checkout callbacks
    Route::get('/checkout/stripe/success', [CheckoutController::class, 'stripeSuccess'])
        ->name('stripe.checkout.success');
    Route::get('/checkout/stripe/cancel', [CheckoutController::class, 'stripeCancel'])
        ->name('stripe.checkout.cancel');

    // Pesapal payment routes
    Route::get('/checkout/pesapal/{order}', [\App\Http\Controllers\Store\PaymentGateway\PesapalController::class, 'initiatePayment'])
        ->name('pesapal.initiate');
    Route::get('/checkout/pesapal/return/{order}', [\App\Http\Controllers\Store\PaymentGateway\PesapalController::class, 'return'])
        ->name('pesapal.return');
    Route::get('/checkout/pesapal/cancel/{order}', [\App\Http\Controllers\Store\PaymentGateway\PesapalController::class, 'cancel'])
        ->name('pesapal.cancel');

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\Store\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Store\NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/notifications/mark-read', [\App\Http\Controllers\Store\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');

});

// Logistics Fulfillment (Vendor/Admin)
Route::middleware('auth.vendor')->prefix('vendor/logistics')->name('vendor.logistics.')->group(function () {
    Route::post('/orders/{order}/fulfill', [\App\Http\Controllers\Api\Logistics\FulfillmentController::class, 'initiate'])->name('fulfill');
    Route::get('/orders/{order}/status', [\App\Http\Controllers\Api\Logistics\FulfillmentController::class, 'status'])->name('status');
    Route::post('/orders/{order}/cancel', [\App\Http\Controllers\Api\Logistics\FulfillmentController::class, 'cancel'])->name('cancel');
    Route::get('/orders/{order}/embed', [\App\Http\Controllers\Api\Logistics\FulfillmentController::class, 'embed'])->name('embed');
});

// Logistics Admin Portal
Route::middleware('guest:logistics_admin')->prefix('logistics')->name('logistics.')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Logistics\AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Logistics\AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('login.post');
    Route::get('/register', [\App\Http\Controllers\Logistics\AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [\App\Http\Controllers\Logistics\AuthController::class, 'register'])->name('register.post');
    Route::get('/password/reset', [\App\Http\Controllers\Logistics\AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/password/email', [\App\Http\Controllers\Logistics\AuthController::class, 'sendResetLink'])->name('password.email');
});

Route::middleware(['auth:logistics_admin', 'logistics.access'])->prefix('logistics')->name('logistics.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Logistics\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/orders', [\App\Http\Controllers\Logistics\DashboardController::class, 'orders'])->name('orders.index');
    Route::get('/orders/export', [\App\Http\Controllers\Logistics\DashboardController::class, 'exportOrders'])->name('orders.export');
    Route::get('/orders/{order}', [\App\Http\Controllers\Logistics\DeliveryController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/priority', [\App\Http\Controllers\Logistics\DeliveryController::class, 'updatePriority'])->name('orders.priority');
    Route::post('/orders/{order}/auto-assign', [\App\Http\Controllers\Logistics\DeliveryController::class, 'autoAssign'])->name('orders.auto-assign');
    Route::get('/orders/{order}/events', [\App\Http\Controllers\Logistics\DeliveryController::class, 'events'])->name('orders.events');
    Route::post('/orders/{order}/assign', [\App\Http\Controllers\Logistics\DeliveryController::class, 'assign'])->name('orders.assign');
    Route::post('/orders/{order}/unassign', [\App\Http\Controllers\Logistics\DeliveryController::class, 'unassign'])->name('orders.unassign');
    Route::post('/orders/{order}/pickup', [\App\Http\Controllers\Logistics\DeliveryController::class, 'pickup'])->name('orders.pickup');
    Route::post('/orders/{order}/transit', [\App\Http\Controllers\Logistics\DeliveryController::class, 'transit'])->name('orders.transit');
    Route::post('/orders/{order}/deliver', [\App\Http\Controllers\Logistics\DeliveryController::class, 'deliver'])->name('orders.deliver');
    Route::post('/orders/{order}/fail', [\App\Http\Controllers\Logistics\DeliveryController::class, 'fail'])->name('orders.fail');
    Route::post('/orders/{order}/reschedule', [\App\Http\Controllers\Logistics\DeliveryController::class, 'reschedule'])->name('orders.reschedule');
    Route::post('/orders/{order}/return', [\App\Http\Controllers\Logistics\DeliveryController::class, 'returnToSender'])->name('orders.return');
    Route::post('/orders/{order}/cancel', [\App\Http\Controllers\Logistics\DeliveryController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/note', [\App\Http\Controllers\Logistics\DeliveryController::class, 'note'])->name('orders.note');
    Route::post('/orders/{order}/cod/collect', [\App\Http\Controllers\Logistics\DeliveryController::class, 'collectCod'])->name('orders.cod.collect');
    Route::post('/orders/{order}/cod/receive', [\App\Http\Controllers\Logistics\DeliveryController::class, 'receiveCod'])->name('orders.cod.receive');
    Route::get('/riders', [\App\Http\Controllers\Logistics\DashboardController::class, 'riders'])->name('riders.index');
    Route::get('/riders/{rider}', [\App\Http\Controllers\Logistics\RiderController::class, 'show'])->name('riders.show');

    Route::get('/dispatch', [\App\Http\Controllers\Logistics\DispatchController::class, 'index'])->name('dispatch.index');
    Route::get('/dispatch/config', [\App\Http\Controllers\Logistics\DispatchController::class, 'config'])->name('dispatch.config');
    Route::post('/dispatch/config', [\App\Http\Controllers\Logistics\DispatchController::class, 'updateConfig'])->name('dispatch.config.update');
    Route::get('/dispatch/recommend/{order}', [\App\Http\Controllers\Logistics\DispatchController::class, 'recommend'])->name('dispatch.recommend');
    Route::get('/tracking', [\App\Http\Controllers\Logistics\DashboardController::class, 'tracking'])->name('tracking.index');
    Route::get('/tracking/data', [\App\Http\Controllers\Logistics\TrackingController::class, 'data'])->name('tracking.data');
    Route::get('/finance', [\App\Http\Controllers\Logistics\CodFinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/export', [\App\Http\Controllers\Logistics\CodFinanceController::class, 'export'])->name('finance.export');
    Route::get('/finance/riders/{rider}', [\App\Http\Controllers\Logistics\CodFinanceController::class, 'riderStatement'])->name('finance.rider');
    Route::post('/finance/handovers', [\App\Http\Controllers\Logistics\CodFinanceController::class, 'createHandover'])->name('finance.handovers.store');
    Route::post('/finance/handovers/{handover}/verify', [\App\Http\Controllers\Logistics\CodFinanceController::class, 'verifyHandover'])->name('finance.handovers.verify');
    Route::post('/finance/handovers/{handover}/reject', [\App\Http\Controllers\Logistics\CodFinanceController::class, 'rejectHandover'])->name('finance.handovers.reject');
    Route::post('/finance/adjustments', [\App\Http\Controllers\Logistics\CodFinanceController::class, 'createAdjustment'])->name('finance.adjustments.store');
    Route::post('/finance/adjustments/{adjustment}/approve', [\App\Http\Controllers\Logistics\CodFinanceController::class, 'approveAdjustment'])->name('finance.adjustments.approve');
    Route::post('/finance/adjustments/{adjustment}/reject', [\App\Http\Controllers\Logistics\CodFinanceController::class, 'rejectAdjustment'])->name('finance.adjustments.reject');

    Route::get('/payouts', [\App\Http\Controllers\Logistics\PayoutFinanceController::class, 'index'])->name('payouts.index');
    Route::get('/payouts/export', [\App\Http\Controllers\Logistics\PayoutFinanceController::class, 'export'])->name('payouts.export');
    Route::get('/payouts/riders/{rider}', [\App\Http\Controllers\Logistics\PayoutFinanceController::class, 'riderStatement'])->name('payouts.rider');
    Route::post('/payouts/riders/{rider}', [\App\Http\Controllers\Logistics\PayoutFinanceController::class, 'create'])->name('payouts.store');
    Route::get('/payouts/{payout}', [\App\Http\Controllers\Logistics\PayoutFinanceController::class, 'show'])->name('payouts.show');
    Route::post('/payouts/{payout}/approve', [\App\Http\Controllers\Logistics\PayoutFinanceController::class, 'approve'])->name('payouts.approve');
    Route::post('/payouts/{payout}/complete', [\App\Http\Controllers\Logistics\PayoutFinanceController::class, 'complete'])->name('payouts.complete');
    Route::post('/payouts/{payout}/reject', [\App\Http\Controllers\Logistics\PayoutFinanceController::class, 'reject'])->name('payouts.reject');
    Route::post('/payouts/{payout}/fail', [\App\Http\Controllers\Logistics\PayoutFinanceController::class, 'fail'])->name('payouts.fail');
    Route::post('/payouts/{payout}/reverse', [\App\Http\Controllers\Logistics\PayoutFinanceController::class, 'reverse'])->name('payouts.reverse');
    Route::get('/support', [\App\Http\Controllers\Logistics\SupportController::class, 'index'])->name('support.index');
    Route::get('/fraud', [\App\Http\Controllers\Logistics\FraudController::class, 'index'])->name('fraud.index');
    Route::post('/fraud/scan', [\App\Http\Controllers\Logistics\FraudController::class, 'scan'])->name('fraud.scan');
    Route::get('/fraud/{case}', [\App\Http\Controllers\Logistics\FraudController::class, 'show'])->name('fraud.show');
    Route::put('/fraud/{case}', [\App\Http\Controllers\Logistics\FraudController::class, 'update'])->name('fraud.update');
    Route::post('/fraud/{case}/assign', [\App\Http\Controllers\Logistics\FraudController::class, 'assign'])->name('fraud.assign');
    Route::post('/fraud/{case}/evidence', [\App\Http\Controllers\Logistics\FraudController::class, 'evidence'])->name('fraud.evidence');
    Route::post('/support', [\App\Http\Controllers\Logistics\SupportController::class, 'store'])->name('support.store');
    Route::get('/support/{ticket}', [\App\Http\Controllers\Logistics\SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/reply', [\App\Http\Controllers\Logistics\SupportController::class, 'reply'])->name('support.reply');
    Route::put('/support/{ticket}', [\App\Http\Controllers\Logistics\SupportController::class, 'update'])->name('support.update');
    Route::post('/support/{ticket}/assign', [\App\Http\Controllers\Logistics\SupportController::class, 'assign'])->name('support.assign');
    Route::post('/support/{ticket}/escalate', [\App\Http\Controllers\Logistics\SupportController::class, 'escalate'])->name('support.escalate');
    Route::get('/notifications', [\App\Http\Controllers\Logistics\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/integration', [\App\Http\Controllers\Logistics\IntegrationController::class, 'index'])->name('integration.index');
    Route::post('/integration/{event}/retry', [\App\Http\Controllers\Logistics\IntegrationController::class, 'retry'])->name('integration.retry');
    Route::get('/notifications/inbox', [\App\Http\Controllers\Logistics\NotificationController::class, 'inbox'])->name('notifications.inbox');
    Route::get('/notifications/channels', [\App\Http\Controllers\Logistics\NotificationController::class, 'channels'])->name('notifications.channels');
    Route::put('/notifications/channels/{channel}', [\App\Http\Controllers\Logistics\NotificationController::class, 'updateChannel'])->name('notifications.channels.update');
    Route::post('/notifications/channels/{channel}/test', [\App\Http\Controllers\Logistics\NotificationController::class, 'testChannel'])->name('notifications.channels.test');
    Route::get('/notifications/templates', [\App\Http\Controllers\Logistics\NotificationController::class, 'templates'])->name('notifications.templates');
    Route::post('/notifications/templates', [\App\Http\Controllers\Logistics\NotificationController::class, 'storeTemplate'])->name('notifications.templates.store');
    Route::get('/notifications/automation', [\App\Http\Controllers\Logistics\NotificationController::class, 'automation'])->name('notifications.automation');
    Route::post('/notifications/automation', [\App\Http\Controllers\Logistics\NotificationController::class, 'storeAutomation'])->name('notifications.automation.store');
    Route::post('/notifications/automation/{workflow}/toggle', [\App\Http\Controllers\Logistics\NotificationController::class, 'toggleAutomation'])->name('notifications.automation.toggle');
    Route::post('/notifications/automation/{workflow}/run', [\App\Http\Controllers\Logistics\NotificationController::class, 'runAutomation'])->name('notifications.automation.run');
    Route::get('/notifications/monitor', [\App\Http\Controllers\Logistics\NotificationController::class, 'monitor'])->name('notifications.monitor');
    Route::post('/notifications/monitor/{run}/retry', [\App\Http\Controllers\Logistics\NotificationController::class, 'retryRun'])->name('notifications.monitor.retry');
    Route::post('/riders/{rider}/approve', [\App\Http\Controllers\Logistics\RiderController::class, 'approve'])->name('riders.approve');
    Route::post('/riders/{rider}/reject', [\App\Http\Controllers\Logistics\RiderController::class, 'reject'])->name('riders.reject');
    Route::post('/riders/{rider}/toggle-active', [\App\Http\Controllers\Logistics\RiderController::class, 'toggleActive'])->name('riders.toggle-active');
    Route::get('/zones', [\App\Http\Controllers\Logistics\ZoneController::class, 'index'])->name('zones.index');
    Route::post('/zones/locations/{location}/settings', [\App\Http\Controllers\Logistics\ZoneController::class, 'updateLocationSettings'])->name('zones.locations.settings');
    Route::post('/zones', [\App\Http\Controllers\Logistics\ZoneController::class, 'store'])->name('zones.store');
    Route::put('/zones/{zone}', [\App\Http\Controllers\Logistics\ZoneController::class, 'update'])->name('zones.update');
    Route::delete('/zones/{zone}', [\App\Http\Controllers\Logistics\ZoneController::class, 'destroy'])->name('zones.destroy');

    Route::resource('hubs', \App\Http\Controllers\Logistics\HubController::class)->except(['show', 'create', 'edit']);
    Route::get('/pricing', [\App\Http\Controllers\Logistics\PricingController::class, 'index'])->name('pricing.index');
    Route::get('/pricing/simulate', [\App\Http\Controllers\Logistics\PricingController::class, 'simulate'])->name('pricing.simulate');
    Route::post('/pricing/simulate', [\App\Http\Controllers\Logistics\PricingController::class, 'simulate'])->name('pricing.simulate.run');
    Route::get('/pricing/revenue', [\App\Http\Controllers\Logistics\PricingController::class, 'revenue'])->name('pricing.revenue');
    Route::post('/pricing/{rule}/approve', [\App\Http\Controllers\Logistics\PricingController::class, 'approve'])->name('pricing.approve');
    Route::post('/pricing', [\App\Http\Controllers\Logistics\PricingController::class, 'store'])->name('pricing.store');
    Route::put('/pricing/{rule}', [\App\Http\Controllers\Logistics\PricingController::class, 'update'])->name('pricing.update');
    Route::delete('/pricing/{rule}', [\App\Http\Controllers\Logistics\PricingController::class, 'destroy'])->name('pricing.destroy');
    Route::get('/routes', [\App\Http\Controllers\Logistics\RouteController::class, 'index'])->name('routes.index');
    Route::get('/route-optimization', [\App\Http\Controllers\Logistics\RouteOptimizationController::class, 'index'])->name('route-optimization.index');
    Route::post('/route-optimization', [\App\Http\Controllers\Logistics\RouteOptimizationController::class, 'store'])->name('route-optimization.store');
    Route::get('/route-optimization/{plan}', [\App\Http\Controllers\Logistics\RouteOptimizationController::class, 'show'])->name('route-optimization.show');
    Route::post('/routes', [\App\Http\Controllers\Logistics\RouteController::class, 'store'])->name('routes.store');
    Route::delete('/routes/{route}', [\App\Http\Controllers\Logistics\RouteController::class, 'destroy'])->name('routes.destroy');
    Route::get('/reports', [\App\Http\Controllers\Logistics\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/generate', [\App\Http\Controllers\Logistics\ReportController::class, 'generate'])->name('reports.generate');
    Route::get('/analytics', [\App\Http\Controllers\Logistics\AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/predictive', [\App\Http\Controllers\Logistics\PredictiveIntelligenceController::class, 'index'])->name('predictive.index');
    Route::post('/predictive/alerts/{alert}/acknowledge', [\App\Http\Controllers\Logistics\PredictiveIntelligenceController::class, 'acknowledge'])->name('predictive.alerts.acknowledge');
    Route::post('/predictive/alerts/{alert}/resolve', [\App\Http\Controllers\Logistics\PredictiveIntelligenceController::class, 'resolve'])->name('predictive.alerts.resolve');
    Route::get('/settings', [\App\Http\Controllers\Logistics\SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [\App\Http\Controllers\Logistics\SettingsController::class, 'update'])->name('settings.update');
    Route::get('/rider-builder', [\App\Http\Controllers\Logistics\RiderBuilderController::class, 'index'])->name('rider-builder.index');
    Route::post('/rider-builder', [\App\Http\Controllers\Logistics\RiderBuilderController::class, 'update'])->name('rider-builder.update');
    Route::get('/profile', [\App\Http\Controllers\Logistics\DashboardController::class, 'profile'])->name('profile');
    Route::post('/logout', [\App\Http\Controllers\Logistics\AuthController::class, 'logout'])->name('logout');
});

// Rider Portal
Route::middleware('guest:rider')->prefix('rider')->name('rider.')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Rider\AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Rider\AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.post');
    Route::get('/register', [\App\Http\Controllers\Rider\AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [\App\Http\Controllers\Rider\AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.post');
    Route::get('/password/reset', [\App\Http\Controllers\Rider\AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/password/email', [\App\Http\Controllers\Rider\AuthController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
});

Route::middleware('auth:rider')->prefix('rider')->name('rider.')->group(function () {
    Route::get('/kyc/status', [\App\Http\Controllers\Rider\AuthController::class, 'kycStatus'])->name('kyc.status');
    Route::post('/logout', [\App\Http\Controllers\Rider\AuthController::class, 'logout'])->name('logout');

    Route::middleware('rider.eligible')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Rider\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/earnings', [\App\Http\Controllers\Rider\DashboardController::class, 'earnings'])->name('earnings');
        Route::get('/orders', [\App\Http\Controllers\Rider\DashboardController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [\App\Http\Controllers\Rider\DeliveryController::class, 'show'])->name('deliveries.show');
        Route::post('/orders/{order}/accept', [\App\Http\Controllers\Rider\DeliveryController::class, 'accept'])->name('deliveries.accept');
        Route::post('/orders/{order}/decline', [\App\Http\Controllers\Rider\DeliveryController::class, 'decline'])->name('deliveries.decline');
        Route::post('/orders/{order}/arrived-pickup', [\App\Http\Controllers\Rider\DeliveryController::class, 'arrivedPickup'])->name('deliveries.arrived-pickup');
        Route::post('/orders/{order}/pickup', [\App\Http\Controllers\Rider\DeliveryController::class, 'pickup'])->name('deliveries.pickup');
        Route::post('/orders/{order}/transit', [\App\Http\Controllers\Rider\DeliveryController::class, 'transit'])->name('deliveries.transit');
        Route::post('/orders/{order}/arrived-dropoff', [\App\Http\Controllers\Rider\DeliveryController::class, 'arrivedDropoff'])->name('deliveries.arrived-dropoff');
        Route::post('/orders/{order}/deliver', [\App\Http\Controllers\Rider\DeliveryController::class, 'deliver'])->name('deliveries.deliver');
        Route::post('/orders/{order}/fail', [\App\Http\Controllers\Rider\DeliveryController::class, 'fail'])->name('deliveries.fail');
        Route::get('/profile', [\App\Http\Controllers\Rider\ProfileController::class, 'index'])->name('profile');
        Route::post('/profile', [\App\Http\Controllers\Rider\ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/photo', [\App\Http\Controllers\Rider\ProfileController::class, 'photo'])->name('profile.photo');
        Route::post('/profile/password', [\App\Http\Controllers\Rider\ProfileController::class, 'password'])->name('profile.password');
        Route::post('/profile/documents', [\App\Http\Controllers\Rider\ProfileController::class, 'document'])->name('profile.documents');
        Route::post('/profile/availability', [\App\Http\Controllers\Rider\ProfileController::class, 'toggleAvailability'])->name('profile.availability');
        Route::post('/toggle-online', [\App\Http\Controllers\Rider\DashboardController::class, 'toggleOnline'])->name('toggle-online');
        Route::post('/update-location', [\App\Http\Controllers\Rider\DashboardController::class, 'updateLocation'])->name('update-location');
        Route::post('/notifications/read', [\App\Http\Controllers\Rider\DashboardController::class, 'markNotificationsRead'])->name('notifications.read');
    });
});

/*
|--------------------------------------------------------------------------
| Mobile money aggregator callback
|--------------------------------------------------------------------------
|
| The aggregator posts the outcome of a collection here. The request is
| authenticated with the aggregator's HMAC signature, not with a session.
|
*/
Route::post('/payments/mobile-money/callback', [MobileMoneyCallbackController::class, 'handle'])
    ->name('payments.mobile-money.callback');

// Pesapal IPN Webhook (CSRF exempt - handled in bootstrap/app.php)
Route::post('/payments/pesapal/webhook', [\App\Http\Controllers\Store\PaymentGateway\PesapalWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('pesapal.webhook');

// Juvo Logistics Webhook (CSRF exempt)
Route::post('/logistics/webhook', [\App\Http\Controllers\Api\Logistics\WebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('logistics.webhook');

// Public delivery tracking (token-protected link shared with customers)
Route::get('/track/{token}', [\App\Http\Controllers\Logistics\TrackingController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('logistics.track');

Route::get('/embed/track/{token}', [\App\Http\Controllers\Logistics\TrackingController::class, 'embed'])
    ->middleware('throttle:60,1')
    ->name('logistics.track.embed');
