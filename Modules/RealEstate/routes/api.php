<?php

use Illuminate\Support\Facades\Route;
use Modules\RealEstate\Http\Controllers\FeaturesApiController;
use Modules\RealEstate\Http\Controllers\PropertiesApiController;

Route::middleware(\Modules\UserManagement\App\Http\Middleware\AuthenticateSanctumMultiTenant::class)->group(function () {

    Route::apiResource('features', FeaturesApiController::class);
    Route::post('/features/update-status',[FeaturesApiController::class, 'featureStatusUpdate']);

    Route::apiResource('properties', PropertiesApiController::class);
    Route::post('/upload-temp-image', [PropertiesApiController::class, 'uploadTempImage']);
    Route::post('/approve-properties', [PropertiesApiController::class, 'approveProperties']);

});
