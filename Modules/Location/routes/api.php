<?php

use Illuminate\Support\Facades\Route;
use Modules\Location\Http\Controllers\LocationController;

Route::middleware(\Modules\UserManagement\App\Http\Middleware\AuthenticateSanctumMultiTenant::class)->group(function () {
    Route::get('location/countries', [LocationController::class, 'getCountries']);

    Route::get('location/states', [LocationController::class, 'getStates']);
    Route::post('location/store-state', [LocationController::class, 'storeState']);
    Route::get('location/edit-state', [LocationController::class, 'editState']);
    Route::post('location/update-state', [LocationController::class, 'UpdateState']);
    Route::delete('location/delete-state', [LocationController::class, 'deleteState']);

    Route::get('location/cities', [LocationController::class, 'getCities']);
    Route::post('location/store-city', [LocationController::class, 'storeCity']);
    Route::get('location/edit-city', [LocationController::class, 'editCity']);
    Route::post('location/update-city', [LocationController::class, 'UpdateCity']);
    Route::delete('location/delete-city', [LocationController::class, 'deleteCity']);

    Route::post('location/update-status', [LocationController::class, 'updateStatus']);
});
