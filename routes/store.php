<?php

use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Store\Auth\EmailVerificationController;
use App\Http\Controllers\Store\Auth\ForgotPasswordController;
use App\Http\Controllers\Store\Auth\GoogleController;
use App\Http\Controllers\Store\Auth\LoginController;
use App\Http\Controllers\Store\Auth\RegisterController;
use App\Http\Controllers\Store\Auth\ResetPasswordController;
use App\Http\Controllers\Store\CartController;
use App\Http\Controllers\Store\CategoryController;
use App\Http\Controllers\Store\ChatController;
use App\Http\Controllers\Store\CheckoutController;
use App\Http\Controllers\Store\CurrencyController;
use App\Http\Controllers\Store\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Store\Customer\ProfileController;
use App\Http\Controllers\Store\JoinController;
use App\Http\Controllers\Store\NewsletterController;
use App\Http\Controllers\Store\PageController;
use App\Http\Controllers\Store\PaymentGateway\StripeController;
use App\Http\Controllers\Store\ProductController;
use App\Http\Controllers\Store\ReferralController;
use App\Http\Controllers\Store\ReviewController;
use App\Http\Controllers\Store\SearchController;
use App\Http\Controllers\Store\ShopController;
use App\Http\Controllers\Store\SitemapController;
use App\Http\Controllers\Store\StorefrontController;
use App\Http\Controllers\Store\SupportController;
use App\Http\Controllers\Store\WishlistController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StoreController::class, 'index'])->name('xylo.home');
Route::view('/company', 'corporate.index')->name('company');
Route::get('/ref/{code}', [ReferralController::class, 'capture'])
    ->where('code', '[A-Za-z0-9_-]{2,64}')
    ->middleware('throttle:30,1')
    ->name('referral.capture');
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('product.show');
Route::post('/change-currency', [CurrencyController::class, 'changeCurrency'])->name('change.currency');

Route::post('/cart/add', [CartController::class, 'addToCart'])->name('cart.add');
Route::get('/cart', [CartController::class, 'viewCart'])->name('cart.view');
Route::post('/cart/update', [CartController::class, 'updateCart'])->name('cart.update');
Route::post('/cart/remove', [CartController::class, 'removeFromCart'])->name('cart.remove');
Route::post('/cart/save-later', [CartController::class, 'saveForLater'])->name('cart.saveLater');
Route::post('/cart/move-to-cart', [CartController::class, 'moveToCart'])->name('cart.moveToCart');
Route::post('/cart/clear', [CartController::class, 'clearCart'])->name('cart.clear');

Route::post('/change-store-language', [LanguageController::class, 'changeLanguage'])->name('change.store.language');

Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon'])->name('cart.applyCoupon');
Route::post('/cart/remove-coupon', [CartController::class, 'removeCoupon'])->name('cart.removeCoupon');

Route::get('/products', [ShopController::class, 'index'])->name('shop.index');

Route::get('/store/{slug}', [StorefrontController::class, 'show'])->name('store.show');
Route::get('/store/{slug}/products', [StorefrontController::class, 'products'])->name('store.products');
Route::get('/store/{slug}/collections', [StorefrontController::class, 'collections'])->name('store.collections');
Route::get('/store/{slug}/about', [StorefrontController::class, 'about'])->name('store.about');

Route::get('/search-suggestions', [SearchController::class, 'suggestions'])->middleware('throttle:120,1');
Route::get('/search', [SearchController::class, 'searchResults'])->middleware('throttle:60,1')->name('search');

Route::get('/get-variant-price', [ProductController::class, 'getVariantPrice'])->name('product.variant.price');

Route::middleware('auth.customer')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
});

Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:20,1')->name('newsletter.subscribe');

Route::middleware('auth.customer')->group(function () {
    Route::get('/customer/verify', [EmailVerificationController::class, 'show'])->name('customer.verification.show');
    Route::post('/customer/verify', [EmailVerificationController::class, 'verify'])->name('customer.verification.verify');
    Route::post('/customer/verify/resend', [EmailVerificationController::class, 'resend'])->name('customer.verification.resend');
});

Route::get('/thank-you', [CheckoutController::class, 'thankyou'])
    ->middleware('auth.customer')
    ->name('thankyou');

Route::get('/page/{slug}', [PageController::class, 'show'])->name('page.show');

// SEO: XML sitemap + robots.txt
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Support: help hub, contact form, guest order tracking
Route::get('/support', [SupportController::class, 'index'])->name('support.index');
Route::post('/support/contact', [SupportController::class, 'contact'])->middleware('throttle:10,1')->name('support.contact');
Route::post('/support/track', [SupportController::class, 'track'])->middleware('throttle:20,1')->name('support.track');

// Help hub: guided self-service plus live chat entry point
Route::get('/help', [SupportController::class, 'help'])->name('help.index');

// Seller and rider recruitment pages (shown before sign-up)
Route::get('/sell-on-juvo', [JoinController::class, 'sell'])->name('join.sell');
Route::get('/deliver-with-juvo', [JoinController::class, 'deliver'])->name('join.deliver');
Route::get('/join-jteam', [JoinController::class, 'jteam'])->name('join.jteam');

// Offline fallback served by the service worker
Route::get('/offline', fn () => view('themes.xylo.offline'))->name('offline');

// Live chat (polling): one conversation per visitor/customer
Route::prefix('chat')->name('chat.')->middleware('throttle:120,1')->group(function () {
    Route::post('/start', [ChatController::class, 'start'])->name('start');
    Route::get('/thread', [ChatController::class, 'thread'])->name('thread');
    Route::post('/message', [ChatController::class, 'send'])->middleware('throttle:30,1')->name('message');
    Route::post('/poll', [ChatController::class, 'poll'])->name('poll');
    Route::post('/end', [ChatController::class, 'end'])->name('end');
});

// Category page
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('category.show');

Route::post('/product/review/store', [ReviewController::class, 'store'])
    ->middleware(['auth.customer', 'throttle:10,1'])
    ->name('review.store');

Route::prefix('customer')->name('customer.')->group(function () {

    // Guest routes
    Route::middleware('guest:customer')->group(function () {
        Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->middleware('throttle:6,1');

        Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
        Route::post('register', [RegisterController::class, 'register'])->middleware('throttle:10,1');

        Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
        Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:6,1')->name('password.email');

        Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
        Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

        // Social sign-in (Google)
        Route::get('auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
        Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
    });

    // Authenticated routes
    Route::middleware('auth.customer')->group(function () {
        Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');

        //  Customer Profile Routes
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

        // Orders & returns
        Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/invoice', [CustomerOrderController::class, 'invoice'])->name('orders.invoice');
        Route::post('/orders/{order}/return', [CustomerOrderController::class, 'returnRequest'])->name('orders.return');
    });
});

Route::get('/stripe/checkout', [StripeController::class, 'checkout'])
    ->middleware(['auth.customer', 'throttle:10,1'])
    ->name('stripe.checkout.process');

/* Ad delivery tracking (public pixels, rate-limited) */
Route::middleware('throttle:120,1')->group(function () {
    Route::get('ads/i/{campaign}', [\App\Http\Controllers\Ads\AdTrackingController::class, 'impression'])->name('ads.impression');
    Route::get('ads/c/{campaign}', [\App\Http\Controllers\Ads\AdTrackingController::class, 'click'])->name('ads.click');
    Route::post('ads/conv/{campaign}', [\App\Http\Controllers\Ads\AdTrackingController::class, 'conversion'])
        ->middleware('auth:customer')
        ->name('ads.conversion');
});

/* Recommendation / discovery behaviour events (public, rate-limited) */
Route::middleware('throttle:180,1')->group(function () {
    Route::post('recommendations/event', [\App\Http\Controllers\RecommendationTrackingController::class, 'store'])
        ->name('recommendations.event');
});

Route::get('/api/regions/{region}/locations', [\App\Http\Controllers\Store\LocationController::class, 'byRegion'])->name('api.regions.locations');
Route::post('/api/locations/remember', [\App\Http\Controllers\Store\LocationController::class, 'remember'])->name('api.locations.remember');

Route::post('/product/{slug}/report', [\App\Http\Controllers\Store\ProductReportController::class, 'store'])->middleware('throttle:20,1')->name('product.report');

Route::get('/api/delivery/quote', [\App\Http\Controllers\Store\DeliveryApiController::class, 'quote'])->name('api.delivery.quote');
