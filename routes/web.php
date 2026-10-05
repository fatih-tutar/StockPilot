<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CustomerVisitController;
use App\Http\Controllers\CustomOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FactoryController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WorkTaskController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile/documents/{medium}', [ProfileController::class, 'downloadDocument'])
        ->name('profile.documents.download');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('categories', CategoryController::class)->only([
        'index', 'store', 'update', 'destroy',
    ]);

    Route::resource('products', ProductController::class)->except(['show']);
    Route::post('products/{product}/adjust-stock', [ProductController::class, 'adjust'])
        ->name('products.adjust-stock');

    Route::resource('clients', ClientController::class)->except(['show']);

    Route::resource('factories', FactoryController::class)->except(['show']);

    Route::get('vehicles/{vehicle}/documents/{medium}', [VehicleController::class, 'downloadDocument'])
        ->name('vehicles.documents.download');
    Route::resource('vehicles', VehicleController::class)->except(['show']);

    Route::post('custom-orders/{custom_order}/close', [CustomOrderController::class, 'close'])
        ->name('custom-orders.close');
    Route::post('custom-orders/{custom_order}/reopen', [CustomOrderController::class, 'reopen'])
        ->name('custom-orders.reopen');
    Route::resource('custom-orders', CustomOrderController::class)->except(['show']);

    Route::post('customer-visits/categories', [CustomerVisitController::class, 'storeCategory'])
        ->name('customer-visits.categories.store');
    Route::put('customer-visits/categories/{customer_visit_category}', [CustomerVisitController::class, 'updateCategory'])
        ->name('customer-visits.categories.update');
    Route::delete('customer-visits/categories/{customer_visit_category}', [CustomerVisitController::class, 'destroyCategory'])
        ->name('customer-visits.categories.destroy');
    Route::resource('customer-visits', CustomerVisitController::class)->except(['show']);

    Route::resource('work-tasks', WorkTaskController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('staff/{staff}/documents/{medium}', [StaffController::class, 'downloadDocument'])
        ->name('staff.documents.download');
    Route::resource('staff', StaffController::class)->except(['show']);

    Route::get('organization/{organization_member}/photo', [OrganizationController::class, 'photo'])
        ->name('organization.photo');
    Route::get('organization', [OrganizationController::class, 'index'])->name('organization.index');
    Route::post('organization', [OrganizationController::class, 'update'])->name('organization.update');

    Route::resource('quotes', QuoteController::class)->except(['show']);

    Route::resource('shipments', ShipmentController::class)->except(['show']);
});

require __DIR__.'/auth.php';
