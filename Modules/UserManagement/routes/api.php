<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\UserManagementController;
use Modules\UserManagement\App\Http\Controllers\AuthApiController;
use Modules\UserManagement\App\Http\Controllers\InvitationApiController;
use Modules\UserManagement\App\Http\Controllers\UserApiController;

Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthApiController::class, 'resetPassword']);
Route::post('/refresh', [AuthApiController::class, 'refreshToken']);
Route::post('accept-invite', [InvitationApiController::class, 'acceptInvite']);


Route::middleware(\Modules\UserManagement\App\Http\Middleware\AuthenticateSanctumMultiTenant::class)->group(function () {
    Route::post('logout', [AuthApiController::class, 'logout']);
    Route::get('me', [AuthApiController::class, 'loginUserDetails']);
    Route::post('edit-profile', [AuthApiController::class, 'editProfile']);
    Route::post('change-password', [AuthApiController::class, 'changePassword']);

    //send invitation
    Route::post('send-invite', [InvitationApiController::class, 'sendInvite']);
    Route::get('invitation-list', [InvitationApiController::class, 'invitationList']);

    //User Management
    Route::get('get-agency', [UserApiController::class, 'getAllAgency']);
    Route::get('get-agents', [UserApiController::class, 'getAgents']);
    Route::get('user-details', [UserApiController::class, 'getUserDetails']);
});