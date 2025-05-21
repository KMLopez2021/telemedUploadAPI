<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

// Define your API routes here
Route::post('/save-screen-record', [ApiController::class, 'saveConvert']);