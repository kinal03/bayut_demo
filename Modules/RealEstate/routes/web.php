<?php

use Illuminate\Support\Facades\Route;
use Modules\RealEstate\Http\Controllers\RealEstateController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('realestates', RealEstateController::class)->names('realestate');
});
