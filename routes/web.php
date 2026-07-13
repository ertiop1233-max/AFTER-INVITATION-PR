<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\StorageController as AdminStorageController;
use App\Http\Controllers\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\DownloadController as ClientDownloadController;
use App\Http\Controllers\Client\MediaController as ClientMediaController;
use App\Http\Controllers\Client\SubmissionController as ClientSubmissionController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\UploadPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('/memories/{slug}/{token}', [UploadPageController::class, 'show'])
    ->name('upload.page');

Route::get('/health', [HealthController::class, 'check']);

Route::prefix('admin')->group(function () {
    Route::get('login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('login', [AdminAuthController::class, 'login'])->middleware('throttle:login');
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::resource('events', AdminEventController::class)->names('admin.events');
        Route::get('events/{event}/delete', [AdminEventController::class, 'confirmDelete'])->name('admin.events.confirm-delete');
        Route::post('events/{event}/close', [AdminEventController::class, 'close'])->name('admin.events.close');
        Route::post('events/{event}/reopen', [AdminEventController::class, 'reopen'])->name('admin.events.reopen');
        Route::get('events/{event}/credentials', [AdminEventController::class, 'credentials'])->name('admin.events.credentials');
        Route::post('events/{event}/credentials/reset', [AdminEventController::class, 'resetPassword'])->name('admin.events.credentials.reset');
        Route::get('events/{event}/qr', [AdminEventController::class, 'downloadQr'])->name('admin.events.qr');
        Route::post('events/{event}/qr/regenerate', [AdminEventController::class, 'regenerateQr'])->name('admin.events.qr.regenerate');
        Route::get('storage', [AdminStorageController::class, 'index'])->name('admin.storage');
        Route::get('health', [HealthController::class, 'check'])->name('admin.health');
    });
});

Route::prefix('client')->group(function () {
    Route::get('login', [ClientAuthController::class, 'showLogin'])->name('client.login');
    Route::post('login', [ClientAuthController::class, 'login'])->middleware('throttle:login');
    Route::post('logout', [ClientAuthController::class, 'logout'])->name('client.logout');

    Route::middleware('client.auth')->group(function () {
        Route::get('dashboard', [ClientDashboardController::class, 'index'])->name('client.dashboard');
        Route::get('gallery', [ClientDashboardController::class, 'gallery'])->name('client.gallery');
        Route::get('submissions', [ClientSubmissionController::class, 'index'])->name('client.submissions');
        Route::get('submissions/{submission}', [ClientSubmissionController::class, 'show'])->name('client.submissions.show');
        Route::delete('submissions/{submission}', [ClientSubmissionController::class, 'destroy'])->name('client.submissions.destroy');
        Route::get('submissions/{submission}/voice', [ClientSubmissionController::class, 'voice'])->name('client.submissions.voice');
        Route::get('media/{media}/view', [ClientMediaController::class, 'view'])->name('client.media.view');
        Route::get('media/{media}/thumbnail', [ClientMediaController::class, 'thumbnail'])->name('client.media.thumbnail');
        Route::delete('media/{media}', [ClientMediaController::class, 'destroy'])->name('client.media.destroy');
        Route::get('download/submission/{submission}', [ClientDownloadController::class, 'submission'])->name('client.download.submission');
        Route::get('download/event', [ClientDownloadController::class, 'event'])->name('client.download.event');
        Route::get('search', [ClientSubmissionController::class, 'search'])->name('client.search');
    });
});
