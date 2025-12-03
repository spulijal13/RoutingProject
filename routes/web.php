<?php

use App\Http\Controllers\MapsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MapsController::class, 'index'])->name('maps.index');
Route::get('/maps', [MapsController::class, 'index'])->name('maps.home');
Route::post('/maps/route', [MapsController::class, 'getRoute'])->name('maps.route');
Route::get('/maps/search', [MapsController::class, 'searchLocation'])->name('maps.search');

Route::middleware('auth')->group(function () {
    Route::post('/maps/locations', [MapsController::class, 'saveLocation'])->name('maps.locations.store');
    Route::post('/maps/routes', [MapsController::class, 'saveRoute'])->name('maps.routes.store');
});

Route::get('/test-search', function() {
    $response = Http::get('https://nominatim.openstreetmap.org/search', [
        'q' => 'Los Angeles',
        'format' => 'json',
        'limit' => 1,
    ]);
    
    return [
        'status' => $response->status(),
        'body' => $response->json(),
    ];
});