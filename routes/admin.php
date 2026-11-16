<?php

/* Regions & Locations */
Route::prefix('regions')->name('regions.')->middleware('permission:regions.view|settings.view|settings.edit|regions.create|regions.edit|regions.delete')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\RegionLocationController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\Admin\RegionLocationController::class, 'storeRegion'])->middleware('permission:regions.create|settings.edit')->name('store');
    Route::put('/{region}', [\App\Http\Controllers\Admin\RegionLocationController::class, 'updateRegion'])->middleware('permission:regions.edit|settings.edit')->name('update');
    Route::delete('/{region}', [\App\Http\Controllers\Admin\RegionLocationController::class, 'destroyRegion'])->middleware('permission:regions.delete|settings.edit')->name('destroy');
    Route::post('/{region}/locations', [\App\Http\Controllers\Admin\RegionLocationController::class, 'storeLocation'])->middleware('permission:regions.create|settings.edit')->name('locations.store');
});

Route::prefix('locations')->name('locations.')->group(function () {
    Route::put('/{location}', [\App\Http\Controllers\Admin\RegionLocationController::class, 'updateLocation'])->middleware('permission:regions.edit|settings.edit')->name('update');
    Route::delete('/{location}', [\App\Http\Controllers\Admin\RegionLocationController::class, 'destroyLocation'])->middleware('permission:regions.delete|settings.edit')->name('destroy');
});

/* Platform Documentation */
Route::get('/docs', [\App\Http\Controllers\Admin\PlatformDocumentationController::class, 'index'])->middleware('permission:settings.view|manage-settings|dashboard.view')->name('docs.index');

/* Product Reports */
Route::prefix('product-reports')->name('product-reports.')->middleware('permission:product_reports.view|manage-reviews')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\ProductReportAdminController::class, 'index'])->name('index');
    Route::get('/{productReport}', [\App\Http\Controllers\Admin\ProductReportAdminController::class, 'show'])->name('show');
    Route::put('/{productReport}', [\App\Http\Controllers\Admin\ProductReportAdminController::class, 'update'])->middleware('permission:product_reports.edit|manage-reviews')->name('update');
});

/* Storefront Builder (central vendor store templates) */
Route::prefix('storefront')->name('storefront.')->middleware('permission:page_builder.view|manage-content')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\StorefrontBuilderController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\Admin\StorefrontBuilderController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\Admin\StorefrontBuilderController::class, 'store'])->middleware('permission:page_builder.edit|manage-content')->name('store');
    Route::get('/{template}/edit', [\App\Http\Controllers\Admin\StorefrontBuilderController::class, 'edit'])->name('edit');
    Route::post('/{template}/save', [\App\Http\Controllers\Admin\StorefrontBuilderController::class, 'saveDraft'])->middleware('permission:page_builder.edit|manage-content')->name('save');
    Route::post('/{template}/publish', [\App\Http\Controllers\Admin\StorefrontBuilderController::class, 'publish'])->middleware('permission:page_builder.edit|manage-content')->name('publish');
    Route::post('/{template}/unpublish', [\App\Http\Controllers\Admin\StorefrontBuilderController::class, 'unpublish'])->middleware('permission:page_builder.edit|manage-content')->name('unpublish');
    Route::get('/{template}/preview', [\App\Http\Controllers\Admin\StorefrontBuilderController::class, 'preview'])->name('preview');
    Route::delete('/{template}', [\App\Http\Controllers\Admin\StorefrontBuilderController::class, 'destroy'])->middleware('permission:page_builder.edit|manage-content')->name('destroy');
});
