<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\RecordingController;

// Define your API routes here
Route::post('/save-screen-record', [ApiController::class, 'saveConvert']);
Route::post('/recording/start', [RecordingController::class, 'startRecording']);
Route::post('/recording/stop', [RecordingController::class, 'stopRecording']);