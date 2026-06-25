<?php

namespace Modules\UserManagement\App\Http\Controllers;


use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Modules\UserManagement\App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Modules\UserManagement\App\Models\TenantPersonalAccessToken,Modules\UserManagement\App\Models\CentralTenantTelations,Modules\UserManagement\App\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Modules\UserManagement\App\Mail\ForgotPasswordMail;


class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        tenancy()->end();

        $centralUser = User::select('id', 'first_name', 'last_name', 'email', 'password', 'email_verified_at', 'user_type','is_blocked','tenancy_id')->where('email', $request->email)->first();

        if ($centralUser) {
            if ($centralUser->is_blocked == true) {
                $contactRole = match($centralUser->user_type) {
                    'agent'     => 'Agency',
                    default    => 'Administrator',
                };

                return response()->json([
                    'success' => false,
                    'message' => "Your account has been blocked. Please contact {$contactRole} for assistance."
                ], 403);
            }
            if (Hash::check($request->password, $centralUser->password)) {
                return $this->generateTokens(
                    $centralUser,
                    'central',
                    $centralUser->tenancy_id,
                    60,
                    120
                );
            } else {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        }else {
            $relation = CentralTenantTelations::where([
                'email' => $request->email,
                'status' => 'active'
            ])->first();

            if (!$relation) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials.'
                ], 400);
            }

            $tenant = Tenant::find($relation->tenant_id);

            if (!$tenant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials.'
                ], 404);
            }

            // tenancy()->initialize($tenant);
            $conn = getTenantConnection($tenant);
            $tenantUser = (new User)->setConnection($conn)->where('email', $request->email)->first();

            if (!$tenantUser) {
                tenancy()->end();

                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Credentials.'
                ], 400);
            }

            // ❌ Wrong Password
            if (!Hash::check($request->password, $tenantUser->password)) {

                tenancy()->end();

                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Credentials.'
                ], 400);
            }

            if ($tenantUser->is_blocked == true) {
                $contactRole = match($tenantUser->user_type) {
                    'agent'   => 'Agency',
                    default    => 'Administrator',
                };

                return response()->json([
                    'success' => false,
                    'message' => "Your account has been blocked. Please contact {$contactRole} for assistance."
                ], 403);
            }

            return $this->generateTokens(
                    $tenantUser,
                    'tenant',
                    $tenantUser->tenancy_id,
                    60,
                    120
                );
        }
    }

    public function logout(Request $request)
    {
        $bearer = $request->bearerToken();
        $authUser = $request->user();

        if (!$bearer) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        // Try to find token in current context (whether central or tenant)
        $conn = $authUser ? $authUser->getConnectionName() : null;

        if ($conn && $conn !== 'mysql' && $conn !== config('database.default')) {
            $token = TenantPersonalAccessToken::findTokenOnConnection($bearer, $conn);
        } else {
            $token = PersonalAccessToken::findToken($bearer);
        }

        if ($token) {
            $token->delete();
            return response()->json(['message' => 'Logged out successfully']);
        }

        // If not found in current context, end tenancy and try central
        // tenancy()->end();

        $token = PersonalAccessToken::findToken($bearer);

        if ($token) {
            $token->delete();
            return response()->json(['message' => 'Logged out successfully']);
        }

        return response()->json(['message' => 'Token not found or invalid'], 401);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Check in central users table
        $user = User::where('email', $request->email)->first();
        $tenantConn = null;

        // If not found, check relation table (agent / tenant user)
        if (!$user) {
            $relation = CentralTenantTelations::where('email', $request->email)->first();

            if ($relation) {
                $tenant = Tenant::find($relation->tenant_id);
                if (!$tenant) {
                    return response()->json([
                        'message' => 'Tenant not found'
                    ], 404);
                }

                $tenantConn = getTenantConnection($tenant);
                // tenancy()->initialize($tenant);
                $user = (new User)->setConnection($tenantConn)->where('email', $request->email)->first();

                // tenancy()->end();
            }

            if (!$relation) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email not found.'
                ], 404);
            }
        }

        // Generate reset token
        $plainToken = Str::random(64);
        if ($tenantConn) {
            DB::connection($tenantConn)->table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'token' => Hash::make($plainToken),
                    'created_at' => now()
                ]
            );
        } else {
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'token' => Hash::make($plainToken),
                    'created_at' => now()
                ]
            );
        }

        // Create reset URL (frontend page)
        $resetUrl = config('app.frontend_url') .
            "reset-password?token={$plainToken}&email=" . urlencode($request->email);

        Mail::to($request->email)->queue(
            new ForgotPasswordMail($resetUrl)
        );

        return response()->json([
            'status' => true,
            'message' => 'Password reset link sent to your email.'
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $isTenantRecord = false;
        $tenantConn = null;
        $tenant = null;

        // Try to find token record in central database first
        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

         if (!$record) {
            $relation = CentralTenantTelations::where('email', $request->email)->first();

            if ($relation) {
                $tenant = Tenant::find($relation->tenant_id);

                if ($tenant) {
                    $tenantConn = getTenantConnection($tenant);
                    $record = DB::connection($tenantConn)->table('password_reset_tokens')->where('email', $request->email)->first();

                    if ($record) {
                        $isTenantRecord = true;
                    }
                }
            }
        }

        // Check token match
        if (!Hash::check($request->token, $record->token)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired token.'
            ], 400);
        }

        // Check token expiry (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            return response()->json([
                'status' => false,
                'message' => 'Token expired.'
            ], 400);
        }

        // Try updating central user first
        $user = User::where('email', $request->email)->first();

        if ($user && !$isTenantRecord) {
            $user->password = Hash::make($request->password);
            $user->setRememberToken(Str::random(60));
            $user->save();

            // Delete token after use
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        } else {
            // If not central, update tenant user
            $relation = CentralTenantTelations::where('email', $request->email)->first();
            if (!$relation) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            if (!$tenant) {
                $tenant = Tenant::find($relation->tenant_id);
            }

            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant not found'
                ], 404);
            }

            // tenancy()->initialize($relation->tenant_id);
            if (!$tenantConn) {
                $tenantConn = getTenantConnection($tenant);
            }

            $tenantUser = (new User)->setConnection($tenantConn)->where('email', $request->email)->first();
            if (!$tenantUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found in tenant.'
                ], 404);
            }

            $tenantUser->password = Hash::make($request->password);
            $tenantUser->setRememberToken(Str::random(60));
            $tenantUser->save();

            // tenancy()->end();

            // Delete token after use
            if ($isTenantRecord) {
                DB::connection($tenantConn)->table('password_reset_tokens')->where('email', $request->email)->delete();
            } else {
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Password has been reset successfully.'
        ]);
    }

    private function generateTokens($user, $userType, $tenantId = null, $tokenExpireMinutes, $refreshExpireMinutes)
    {
        // ✅ FIX: Delete only old access tokens first, then old refresh tokens separately.
        // Previously `->tokens()->delete()` was deleting ALL tokens including valid refresh tokens.
        $user->tokens()->where('token_type', 'access')->delete();
        $user->tokens()->where('token_type', 'refresh')->delete();

        // Access Token
        $access = $user->createToken('auth-token', ['*'], now()->addMinutes($tokenExpireMinutes));
        $accessModel = $access->accessToken;
        $accessModel->token_type = 'access';
        $accessModel->mfa_verified = true;
        $accessModel->tenant_id = $tenantId;
        $accessModel->expires_at = now()->addMinutes($tokenExpireMinutes);
        $accessModel->save();

        // Refresh Token
        $refresh = $user->createToken('refresh_token', ['refresh'], now()->addMinutes($refreshExpireMinutes));
        $refreshToken = $refresh->accessToken;
        $refreshToken->token_type = 'refresh';
        $refreshToken->mfa_verified = true;
        $refreshToken->tenant_id = $tenantId;
        $refreshToken->expires_at = now()->addMinutes($refreshExpireMinutes);
        $refreshToken->save();

        return response()->json([
            'status' => true,
            'token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'user_type' => $userType,
            'tenant_id' => $tenantId
        ]);
    }

    public function refreshToken(Request $request)
    {
        $refreshToken = $request->refresh_token;
        if ($request->filled('tenant_id')) {
            $tenant = Tenant::find($request->tenant_id);
            if (!$tenant) {
                return response()->json(['message' => 'Tenant not found'], 404);
            }

            $conn = getTenantConnection($tenant);
            $token = TenantPersonalAccessToken::findTokenOnConnection($refreshToken, $conn);
        } else {
            tenancy()->end();
            $token = PersonalAccessToken::findToken($refreshToken);
        }

        if (!$token || $token->token_type !== 'refresh') {
            return response()->json(['message' => 'Invalid refresh token'], 401);
        }

        if ($token->expires_at < now()) {
            return response()->json(['message' => 'Refresh token expired'], 401);
        }

        $user = $token->tokenable;
        // Delete old access tokens
        $user->tokens()->where('token_type', 'access')->delete();

        // Create new access token
        $newAccessToken = $user->createToken(
            'auth-token',
            ['*'],
            now()->addMinutes(60),
            'access'
        );

        $accessModel = $newAccessToken->accessToken;
        $accessModel->token_type = 'access';
        $accessModel->mfa_verified = true;
        $accessModel->tenant_id = $token->tenant_id;
        $accessModel->expires_at = now()->addMinutes(60);
        $accessModel->save();

        return response()->json([
            'token' => $newAccessToken->plainTextToken
        ]);
    }

    public function loginUserDetails(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $relation = CentralTenantTelations::on('mysql')->where('email', $authUser->email)->first();

        /*
        |--------------------------------------------------------------------------
        | Tenant User
        |--------------------------------------------------------------------------
        */
        if ($relation) {
            $tenant = Tenant::find($relation->tenant_id);

            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant not found'
                ], 404);
            }

            $conn = getTenantConnection($tenant);

            $tenantUser = (new User)->setConnection($conn)->where('email', $authUser->email)->first();

            return response()->json([
                'type' => 'tenant',
                'user' => $tenantUser
            ]);
        } else {
            $conn = 'mysql';
            tenancy()->end();
        }

        /*
        |--------------------------------------------------------------------------
        | Central User
        |--------------------------------------------------------------------------
        */

        $user = (new User)->setConnection($conn)
            ->find($authUser->id);

        return response()->json([
            'type' => 'central',
            'user' => $user
        ]);
    }

    public function editProfile(Request $request){
       
        $validator = Validator::make($request->all(), [
            'first_name'       => 'required|string|max:255',
            'last_name'        => 'required|string|max:255',
            'mobile'           => 'nullable|string|max:20',
            'whatsapp'         => 'nullable|string|max:20',
            'landline'         => 'nullable|string|max:20',
            'gender'           => 'nullable|in:Male,Female,Other',
            'nationality'      => 'nullable|string|max:100',
            'experience'       => 'nullable|integer|min:0',
            'languages'        => 'nullable|string|max:255',
            'specialities'     => 'nullable|string|max:255',
            'speciality_areas' => 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'profile_picture'  => 'nullable',

            'socials.facebook'  => 'nullable|url',
            'socials.instagram' => 'nullable|url',
            'socials.linkedin'  => 'nullable|url',
            'socials.twitter'   => 'nullable|url',
            'socials.youtube'   => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }
         $bearer = $request->bearerToken();
        $authUser = $request->user();

        if (!$bearer) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        // Try to find token in current context (whether central or tenant)
        $conn = $authUser ? $authUser->getConnectionName() : null;

        if ($conn && $conn !== 'mysql' && $conn !== config('database.default')) {
            $token = TenantPersonalAccessToken::findTokenOnConnection($bearer, $conn);
        } else {
            $token = PersonalAccessToken::findToken($bearer);
        }

        // If not found in current context, try central
        if (!$token) {
            tenancy()->end();
            $token = PersonalAccessToken::findToken($bearer);
        }

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($token->tenant_id && $conn !== 'mysql') {
            $tenant = Tenant::find($token->tenant_id);

            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant not found'
                ], 404);
            }

            $conn = getTenantConnection($tenant);
        } else {
            tenancy()->end();
            $conn = 'mysql';
        }

        $user = (new User)->setConnection($conn)->find($token->tokenable_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Upload profile image
       $uploadPath = $user->user_type === 'agent'
            ? 'uploads/' . $user->tenancy_id . '/profile'
            : 'uploads/profile';

        Storage::disk('public')->makeDirectory($uploadPath);

        // Delete old image
        if (!empty($user->profile_picture)) {
            $oldPath = str_replace('storage/', '', $user->profile_picture);

            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Case 1: File Upload
        if ($request->hasFile('profile_picture')) {

            $fileName = time() . '_' . Str::random(10) . '.' .
                $request->file('profile_picture')->getClientOriginalExtension();

            $filePath = $request->file('profile_picture')
                ->storeAs($uploadPath, $fileName, 'public');

            $user->profile_picture = 'storage/' . $filePath;
        }

        // Case 2: Base64 Image
        elseif (!empty($request->profile_picture) && is_string($request->profile_picture)) {

            $image = $request->profile_picture;

            if (preg_match('/^data:image\/(\w+);base64,/', $image, $matches)) {
                $extension = strtolower($matches[1]);
                $image = substr($image, strpos($image, ',') + 1);
            } else {
                $extension = 'png';
            }

            $imageData = base64_decode($image);

            if ($imageData === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid base64 image'
                ], 422);
            }

            $fileName = Str::uuid() . '.' . $extension;
            $filePath = $uploadPath . '/' . $fileName;

            Storage::disk('public')->put($filePath, $imageData);

            $user->profile_picture = 'storage/' . $filePath;
        }

        $user->update([
            'first_name'       => $request->first_name,
            'last_name'        => $request->last_name,
            'mobile'           => $request->mobile,
            'whatsapp'         => $request->whatsapp,
            'landline'         => $request->landline,
            'gender'           => $request->gender,
            'nationality'      => $request->nationality,
            'experience'       => $request->experience,
            'languages'        => $request->languages,
            'specialities'     => $request->specialities,
            'speciality_areas' => $request->speciality_areas,
            'description'      => $request->description,
            'facebook'         => $request->socials['facebook'] ?? null,
            'instagram'        => $request->socials['instagram'] ?? null,
            'linkedin'         => $request->socials['linkedin'] ?? null,
            'twitter'          => $request->socials['twitter'] ?? null,
            'youtube'          => $request->socials['youtube'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data'    => $user,
        ]);

    }

    public function changePassword(Request $request){
        
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $authUser = $request->user();

        setTenantConnection($authUser);

        if (!Hash::check($request->current_password, $authUser->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 400);
        }

        $authUser->password = Hash::make($request->new_password);
        $authUser->setRememberToken(Str::random(60));
        $authUser->save();

        return response()->json(['message' => 'Password changed successfully']);
    }
}