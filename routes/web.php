<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{ActiveSubscriberController, AllSubscriberController, AuthenticatedSessionController, DashboardController, ComboPurchaseController, SubscriberController, ReportController, SettingsController};

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/dashboard/live-stats', [DashboardController::class, 'live'])->name('dashboard.live');
    Route::get('/combo-purchases', [ComboPurchaseController::class, 'index'])->name('combo-purchases.index');
    Route::get('/combo-purchases/export/excel', [ComboPurchaseController::class, 'exportExcel'])->name('combo-purchases.export-excel');
    Route::get('/combo-purchases/{id}', [ComboPurchaseController::class, 'show'])->name('combo-purchases.show');
    Route::get('/subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
    Route::get('/subscribers/{id}', [SubscriberController::class, 'show'])->name('subscribers.show');
    Route::post('/subscribers/{purchaseId}/complete', [SubscriberController::class, 'complete'])->name('subscribers.complete');
    Route::get('/active-subscribers', [ActiveSubscriberController::class, 'index'])->name('active-subscribers.index');
    Route::post('/active-subscribers/{activeSubscriber}/complete', [ActiveSubscriberController::class, 'complete'])->name('active-subscribers.complete');
    Route::get('/all-subscribers', [AllSubscriberController::class, 'index'])->name('all-subscribers.index');
    Route::get('/reports', [ReportController::class, 'index'])->middleware('role:SUPERADMIN')->name('reports.index');
    Route::get('/reports/agent-performance/export/pdf', [ReportController::class, 'exportAgentPerformancePdf'])->middleware('role:SUPERADMIN')->name('reports.agent-performance.export-pdf');
    Route::middleware('role:SUPERADMIN')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/users', [SettingsController::class, 'storeUser'])->name('users.store');
        Route::patch('/users/{user}/status', [SettingsController::class, 'updateUserStatus'])->name('users.status');
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
