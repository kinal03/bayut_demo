<?php

use Illuminate\Support\Facades\Route;
use Modules\RealEstate\Http\Controllers\RealEstateController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('realestates', RealEstateController::class)->names('realestate');
});
