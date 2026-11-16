<?php

use App\Http\Controllers\Agent\CommissionController;
use App\Http\Controllers\Agent\DealController;
use App\Http\Controllers\Agent\ShopController;
use App\Http\Controllers\Agent\LeadController;
use App\Http\Controllers\Agent\OrderController;
use App\Http\Controllers\Agent\PerformanceController;
use App\Http\Controllers\Agent\TeamController;
use App\Http\Controllers\Agent\CustomerController;
use App\Http\Controllers\Agent\DashboardController;
use App\Http\Controllers\Agent\PayoutController;
use App\Http\Controllers\Agent\ProfileController;
use App\Http\Controllers\Agent\ReferralController;
use App\Http\Controllers\Agent\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent Workspace
|--------------------------------------------------------------------------
*/

Route::prefix('agent')->name('agent.')->middleware(['agent.active'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::post('customers/{customer}/notes', [CustomerController::class, 'storeNote'])->name('customers.notes.store');
    Route::post('customers/{customer}/follow-ups', [CustomerController::class, 'storeFollowUp'])->name('customers.followups.store');
    Route::post('follow-ups/{followUp}/complete', [CustomerController::class, 'completeFollowUp'])->name('customers.followups.complete');
    Route::get('customers/{customer}/place-order', [CustomerController::class, 'placeOrder'])->name('customers.place-order');
    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('leads/create', [LeadController::class, 'create'])->name('leads.create');
    Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('leads/follow-ups', [LeadController::class, 'followUps'])->name('leads.followups');
    Route::get('leads/analytics', [LeadController::class, 'analytics'])->name('leads.analytics');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::get('leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
    Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('leads/{lead}/status', [LeadController::class, 'changeStatus'])->name('leads.status');
    Route::post('leads/{lead}/notes', [LeadController::class, 'storeNote'])->name('leads.notes.store');
    Route::post('leads/{lead}/follow-ups', [LeadController::class, 'storeFollowUp'])->name('leads.followups.store');
    Route::post('lead-follow-ups/{followUp}/complete', [LeadController::class, 'completeFollowUp'])->name('leads.followups.complete');
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
    Route::get('shop', [ShopController::class, 'index'])->name('shop.index');
    Route::get('shop/saved', [ShopController::class, 'saved'])->name('shop.saved');
    Route::get('shop/compare', [ShopController::class, 'compare'])->name('shop.compare');
    Route::get('shop/{product}', [ShopController::class, 'show'])->name('shop.show');
    Route::post('shop/{product}/save', [ShopController::class, 'save'])->name('shop.save');
    Route::delete('shop/{product}/save', [ShopController::class, 'unsave'])->name('shop.unsave');
    Route::post('shop/{product}/compare', [ShopController::class, 'addToCompare'])->name('shop.compare.add');
    Route::delete('shop/{product}/compare', [ShopController::class, 'removeFromCompare'])->name('shop.compare.remove');
    Route::get('shop/{product}/add-to-order', [ShopController::class, 'addToOrder'])->name('shop.add-to-order');
    Route::get('place-order', [WorkspaceController::class, 'placeOrder'])->name('place-order.index');
    Route::post('place-order', [WorkspaceController::class, 'placeOrderStore'])->name('place-order.store');
    Route::get('performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/issues', [OrderController::class, 'reportIssue'])->name('orders.issues.store');
    Route::post('orders/{order}/returns', [OrderController::class, 'requestReturn'])->name('orders.returns.store');
    Route::post('orders/{order}/cancellations', [OrderController::class, 'requestCancellation'])->name('orders.cancellations.store');


    Route::get('commissions', [CommissionController::class, 'index'])->name('commissions.index');
    Route::post('commissions/disputes', [CommissionController::class, 'dispute'])->name('commissions.disputes.store');

    Route::get('referrals', [TeamController::class, 'index'])->name('referrals.index');
    Route::post('team/invitations', [TeamController::class, 'storeInvitation'])->name('team.invitations.store');
    Route::post('referrals/{attribution}/confirm', [ReferralController::class, 'confirm'])->name('referrals.confirm');
    Route::post('referrals/{attribution}/dispute', [ReferralController::class, 'dispute'])->name('referrals.dispute');

    Route::get('deals', [DealController::class, 'index'])->name('deals.index');
    Route::get('deals/{promotion}', [DealController::class, 'show'])->name('deals.show');
    Route::post('deals/{promotion}/share', [DealController::class, 'share'])->name('deals.share');
    Route::get('academy', [WorkspaceController::class, 'academy'])->name('academy.index');
    Route::get('community', [WorkspaceController::class, 'community'])->name('community.index');
    Route::get('support', [WorkspaceController::class, 'support'])->name('support.index');

    Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    // Legacy payout endpoint retained for existing commission payouts.
    Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
    Route::post('payouts', [PayoutController::class, 'store'])->name('payouts.store');
});
