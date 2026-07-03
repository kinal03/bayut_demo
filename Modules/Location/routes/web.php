<?php

use Illuminate\Support\Facades\Route;
use Modules\Location\Http\Controllers\LocationController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('locations', LocationController::class)->names('location');
});
