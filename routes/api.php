<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\AppointmentEmailJobController;
use App\Http\Controllers\RecordingController;
use Illuminate\Support\Facades\Route;

// Define your API routes here
Route::post('/save-screen-record', [ApiController::class, 'saveConvert']);
Route::post('/recording/start', [RecordingController::class, 'startRecording']);
Route::post('/recording/stop', [RecordingController::class, 'stopRecording']);

Route::prefix('admin')->group(function () {
    Route::get('/appointment-email-jobs', [AppointmentEmailJobController::class, 'index']);
    Route::get('/appointment-email-jobs/summary', [AppointmentEmailJobController::class, 'summary']);
});