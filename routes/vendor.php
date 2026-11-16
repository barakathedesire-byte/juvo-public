<?php

use App\Http\Controllers\Vendor\AdsController;
use App\Http\Controllers\Vendor\AnalyticsController;
use App\Http\Controllers\Vendor\Auth\AuthController;
use App\Http\Controllers\Vendor\Auth\ForgotPasswordController;
use App\Http\Controllers\Vendor\Auth\ResetPasswordController;
use App\Http\Controllers\Vendor\Auth\VerificationController;
use App\Http\Controllers\Vendor\CouponController;
use App\Http\Controllers\Vendor\DashboardController;
use App\Http\Controllers\Vendor\FinanceController;
use App\Http\Controllers\Vendor\OrderController;
use App\Http\Controllers\Vendor\ProductController;
use App\Http\Controllers\Vendor\ProductImportExportController;
use App\Http\Controllers\Vendor\ProductReviewController;
use App\Http\Controllers\Vendor\ProfileController;
use App\Http\Controllers\Vendor\PromotionController;
use App\Http\Controllers\Vendor\SettingsController;
use App\Http\Controllers\Vendor\StaffController;
use App\Http\Controllers\Vendor\StoreController;
use App\Http\Controllers\Vendor\SupportController;
use Illuminate\Support\Facades\Route;

Route::prefix('vendor')->group(function () {
    Route::middleware('guest:vendor')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('vendor.login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('vendor.login.submit');
        Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('vendor.register');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('vendor.register.submit');

        Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('vendor.password.request');
        Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:6,1')->name('vendor.password.email');
        Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('vendor.password.reset');
        Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('vendor.password.update');
    });

    Route::get('/pending', [AuthController::class, 'pending'])->name('vendor.pending');
    Route::get('/restricted', fn () => view('vendor.auth.pending'))->name('vendor.restricted');
    Route::get('/verify', [VerificationController::class, 'show'])->name('vendor.verification.show');
    Route::post('/verify', [VerificationController::class, 'verify'])->name('vendor.verification.verify');
    Route::post('/verify/resend', [VerificationController::class, 'resend'])->name('vendor.verification.resend');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.vendor')->name('vendor.logout');

    Route::middleware('auth.vendor')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('vendor.dashboard');
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('vendor.analytics.index');
        Route::get('analytics/export', [AnalyticsController::class, 'export'])->name('vendor.analytics.export');

        /** Product import / export (declared before the resource so paths win) */
        Route::get('products/export', [ProductImportExportController::class, 'export'])->name('vendor.products.export');
        Route::get('products/import', [ProductImportExportController::class, 'importForm'])->name('vendor.products.import');
        Route::post('products/import', [ProductImportExportController::class, 'import'])->name('vendor.products.import.store');
        Route::get('products/import/sample', [ProductImportExportController::class, 'sample'])->name('vendor.products.import.sample');

        Route::resource('products', ProductController::class)->names('vendor.products')->except(['show']);
        Route::post('products/data', [ProductController::class, 'getProducts'])->name('vendor.products.data');
        Route::post('products/updateStatus', [ProductController::class, 'updateStatus'])->name('vendor.products.updateStatus');
        Route::get('products/{id}/inventory', [ProductController::class, 'inventory'])->name('vendor.products.inventory');
        Route::post('products/{id}/inventory', [ProductController::class, 'adjustInventory'])->name('vendor.products.inventory.adjust');
        Route::post('products/{id}/duplicate', [ProductController::class, 'duplicate'])->name('vendor.products.duplicate');
        Route::post('products/{id}/archive', [ProductController::class, 'archive'])->name('vendor.products.archive');
        Route::post('products/{id}/restore', [ProductController::class, 'restore'])->name('vendor.products.restore');
        Route::post('products/{id}/visibility', [ProductController::class, 'toggleVisibility'])->name('vendor.products.visibility');
        Route::post('products/{id}/submit', [ProductController::class, 'submitForApproval'])->name('vendor.products.submit');
Route::post('products/images/{image}/remove-background', [ProductController::class, 'removeBackground'])->name('vendor.products.images.removeBackground');

        Route::get('reviews', [ProductReviewController::class, 'index'])->name('vendor.reviews.index');
        Route::get('reviews/data', [ProductReviewController::class, 'getData'])->name('vendor.reviews.data');
        Route::get('reviews/{review}', [ProductReviewController::class, 'show'])->name('vendor.reviews.show');
        Route::delete('reviews/{review}', [ProductReviewController::class, 'destroy'])->name('vendor.reviews.destroy'); // deprecated: forbidden; see controller.

        /** Orders */
        Route::get('orders', [OrderController::class, 'index'])->name('vendor.orders.index');
        Route::post('orders/data', [OrderController::class, 'getData'])->name('vendor.orders.data');
        Route::post('orders/bulk', [OrderController::class, 'bulkAction'])->name('vendor.orders.bulk');
        Route::get('orders/{id}', [OrderController::class, 'show'])->name('vendor.orders.show');
        Route::post('orders/{id}/status', [OrderController::class, 'updateStatus'])->name('vendor.orders.status');
        Route::post('orders/{id}/tracking', [OrderController::class, 'updateTracking'])->name('vendor.orders.tracking');
        Route::get('orders/{id}/document/{type?}', [OrderController::class, 'document'])->name('vendor.orders.document');
        Route::delete('orders/{id}', [OrderController::class, 'destroy'])->name('vendor.orders.destroy');

        /** Finance: earnings, transactions, payouts & statements */
        Route::get('finance', [FinanceController::class, 'index'])->name('vendor.finance.index');
        Route::get('finance/transactions', [FinanceController::class, 'transactions'])->name('vendor.finance.transactions');
        Route::post('finance/transactions/data', [FinanceController::class, 'transactionsData'])->name('vendor.finance.transactions.data');
        Route::get('finance/payouts', [FinanceController::class, 'payouts'])->name('vendor.finance.payouts');
        Route::post('finance/payouts', [FinanceController::class, 'requestPayout'])->name('vendor.finance.payouts.request');
        Route::get('finance/statement', [FinanceController::class, 'statement'])->name('vendor.finance.statement');

        /** Marketing: Ads (paid campaigns) */
        Route::get('ads', [AdsController::class, 'index'])->name('vendor.ads.index');
        Route::post('ads', [AdsController::class, 'store'])->name('vendor.ads.store');
        Route::post('ads/{campaign}/pause', [AdsController::class, 'pause'])->name('vendor.ads.pause');
        Route::post('ads/{campaign}/resume', [AdsController::class, 'resume'])->name('vendor.ads.resume');
        Route::delete('ads/{campaign}', [AdsController::class, 'destroy'])->name('vendor.ads.destroy');

        /** Notifications */
        Route::get('notifications', [\App\Http\Controllers\Vendor\NotificationController::class, 'index'])->name('vendor.notifications.index');
        Route::post('notifications/{id}/read', [\App\Http\Controllers\Vendor\NotificationController::class, 'markRead'])->name('vendor.notifications.read');
        Route::post('notifications/read-all', [\App\Http\Controllers\Vendor\NotificationController::class, 'markAllRead'])->name('vendor.notifications.readAll');

        /** Marketing: Promotions */
        Route::get('promotions', [PromotionController::class, 'index'])->name('vendor.promotions.index');
        Route::post('promotions', [PromotionController::class, 'store'])->name('vendor.promotions.store');
        Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy'])->name('vendor.promotions.destroy');

        /** Coupons */
        Route::get('coupons', [CouponController::class, 'index'])->name('vendor.coupons.index');
        Route::post('coupons', [CouponController::class, 'store'])->name('vendor.coupons.store');
        Route::put('coupons/{coupon}', [CouponController::class, 'update'])->name('vendor.coupons.update');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('vendor.coupons.destroy');

        /** My Store: storefront profile & sub-pages */
        Route::get('store', [StoreController::class, 'edit'])->name('vendor.store.edit');
        Route::patch('store', [StoreController::class, 'update'])->name('vendor.store.update');

        /** Settings: store info + payout methods */
        Route::get('settings', [SettingsController::class, 'index'])->name('vendor.settings.index');
        Route::patch('settings', [SettingsController::class, 'update'])->name('vendor.settings.update');
        Route::post('settings/payout', [SettingsController::class, 'storePayout'])->name('vendor.settings.payout.store');
        Route::delete('settings/payout/{id}', [SettingsController::class, 'destroyPayout'])->name('vendor.settings.payout.destroy');

        /** Verification documents */
        Route::post('settings/documents', [SettingsController::class, 'storeDocument'])->name('vendor.settings.documents.store');
        Route::delete('settings/documents/{id}', [SettingsController::class, 'destroyDocument'])->name('vendor.settings.documents.destroy');

        /** Profile */
        Route::get('support', [SupportController::class, 'index'])->name('vendor.support.index');
        Route::post('support/reviews/{review}/reply', [SupportController::class, 'replyToReview'])->name('vendor.support.reviews.reply');
        Route::get('staff', [StaffController::class, 'index'])->name('vendor.staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('vendor.staff.store');
        Route::put('staff/{staff}', [StaffController::class, 'update'])->name('vendor.staff.update');
        Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->name('vendor.staff.destroy');
        Route::get('profile/edit', [ProfileController::class, 'edit'])->name('vendor.profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('vendor.profile.update');
    });
});
