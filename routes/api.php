<?php

use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('events/{token}', [UploadController::class, 'eventInfo'])
    ->middleware('throttle:30,1');

Route::middleware(['throttle:uploads', 'verify.upload.nonce'])->group(function () {
    Route::post('submissions/start', [SubmissionController::class, 'start'])->middleware('throttle:submissions');
    Route::post('submissions/finalize', [SubmissionController::class, 'finalize'])->middleware('throttle:submissions');

    Route::post('upload/init', [UploadController::class, 'init']);
    Route::post('upload/complete', [UploadController::class, 'complete']);
    Route::post('upload/thumbnail', [UploadController::class, 'thumbnail']);
    Route::post('upload/voice', [UploadController::class, 'voice']);
});

Route::post('upload/chunk', [UploadController::class, 'chunk'])
    ->middleware(['throttle:upload-chunks', 'verify.upload.nonce']);
