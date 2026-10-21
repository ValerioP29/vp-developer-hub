<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WorkController;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/health', function () {
    try {
        DB::table('activations')->limit(1)->get(['id', 'carried_remainder_seconds']);

        return response()->json(['status' => 'ok']);
    } catch (Throwable) {
        return response()->json(['status' => 'unavailable'], 503);
    }
})->middleware('throttle:30,1');
Route::middleware('auth')->group(function () {
    Route::get('/security', fn () => view('hub.security'))->name('security')->middleware('password.confirm');
});
Route::middleware(['auth', 'hub', 'throttle:hub'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/clienti/{slug}', function (string $slug) {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();

        return redirect('/dashboard');
    });
    Route::get('/manage/{resource}', [ResourceController::class, 'index'])->name('resource.index');
    Route::get('/manage/{resource}/create', [ResourceController::class, 'create'])->name('resource.create');
    Route::post('/manage/{resource}', [ResourceController::class, 'store']);
    Route::get('/manage/{resource}/{id}/edit', [ResourceController::class, 'edit']);
    Route::get('/manage/{resource}/{id}', [ResourceController::class, 'show']);
    Route::put('/manage/{resource}/{id}', [ResourceController::class, 'update']);
    Route::get('/interventions/{intervention}', [WorkController::class, 'show']);
    Route::post('/interventions/{intervention}/time', [WorkController::class, 'time']);
    Route::post('/interventions/{intervention}/timer', [WorkController::class, 'timer']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'comment']);
    Route::post('/tickets/{ticket}/status', [TicketController::class, 'status']);
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
    Route::get('/documents/{document}/logo', [DocumentController::class, 'logo']);
    Route::get('/billing', [BillingController::class, 'index']);
    Route::post('/billing/adjustments', [BillingController::class, 'adjustment']);
    Route::delete('/billing/adjustments/{adjustment}', [BillingController::class, 'removeAdjustment']);
    Route::post('/billing/activate', [BillingController::class, 'activate']);
    Route::post('/billing/finalize', [BillingController::class, 'finalize']);
    Route::put('/billing/{summary}/invoice', [BillingController::class, 'invoice']);
    Route::get('/approvals', [BillingController::class, 'approvals']);
    Route::post('/approvals', [BillingController::class, 'requestApproval']);
    Route::post('/approvals/{approval}/decide', [BillingController::class, 'decide']);
    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team', [TeamController::class, 'store']);
    Route::put('/team/{id}', [TeamController::class, 'update']);
    Route::post('/team/{id}/reset', [TeamController::class, 'resend'])->middleware('throttle:6,1');
    Route::get('/reports/{tenant}/{format}', [ReportController::class, 'export']);
    Route::get('/audit', [DashboardController::class, 'audit']);
});
