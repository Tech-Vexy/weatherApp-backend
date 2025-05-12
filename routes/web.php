<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WeatherController;

Route::get('/', function () {
    return view('welcome');
});

// Weather API Routes - Only keeping the forecast by city route
Route::prefix('api/weather')->group(function () {
    Route::get('/forecast/city', [WeatherController::class, 'forecastByCity']);
});
