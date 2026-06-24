<?php

use Illuminate\Support\Facades\Route;
use Modules\RealEstate\Http\Controllers\FeaturesApiController;

Route::middleware(\Modules\UserManagement\App\Http\Middleware\AuthenticateSanctumMultiTenant::class)->group(function () {

    Route::apiResource('features', FeaturesApiController::class);
    Route::post('/features/update-status',[FeaturesApiController::class, 'featureStatusUpdate']);

});
