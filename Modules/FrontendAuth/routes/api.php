<?php

use Illuminate\Support\Facades\Route;
use Modules\FrontendAuth\Http\Controllers\SocialController;

Route::prefix('auth')->group(function () {
    Route::post('/check-email-validation',[SocialController::class, 'checkEmailValidation']);
    Route::post('/social-login',[SocialController::class, 'socialLogin']);
    Route::post('/register',[SocialController::class, 'register']);
    Route::post('/verify-register-otp',[SocialController::class, 'verifyRegisterOtp']);
    Route::post('/send-magic-register-link',[SocialController::class, 'sendMagicRegisterLink']);
    Route::post('/send-magic-login-link',[SocialController::class, 'sendMagicLoginLink']);
    Route::get('/magic-login/{token}',[SocialController::class, 'magicLogin']);
    Route::get('/get-countries',[SocialController::class, 'getCountries']);
    Route::post('/login',[SocialController::class, 'login']);
    Route::post('/send-reset-otp', [SocialController::class, 'sendResetOtp']);
    Route::post('/verify-reset-otp',[SocialController::class, 'verifyResetOtp']);

    Route::middleware('auth:frontend_api')->group(function () {
        Route::post('/logout',[SocialController::class, 'logout']);
        Route::post('/edit-profile',[SocialController::class, 'editProfile']);
    });
});
