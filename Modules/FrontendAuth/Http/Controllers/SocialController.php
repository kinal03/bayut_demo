<?php

namespace Modules\FrontendAuth\Http\Controllers;

use App\Models\User;
use Modules\FrontendAuth\Model\FrontendUser;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use Modules\FrontendAuth\Mail\MagicLinkMail;
use Modules\FrontendAuth\Mail\ForgotPasswordOtpMail;
use Modules\FrontendAuth\Mail\RegisterOtpMail;
use Modules\Location\Models\Countries;
use Modules\FrontendAuth\Model\EmailVerification;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;


class SocialController extends Controller
{
    public function socialLogin(Request $request)
    {
        // 🔑 Extract Bearer token or token param
        $token = $request->bearerToken()
            ?: $request->input('token')
            ?: $request->input('access_token')
            ?: $request->input('id_token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token missing'
            ], 401);
        }

        // Quick debug: check token format (do not return token itself)

        $supabaseUrl = config('services.supabase.url');
        if (!$supabaseUrl) {
            return response()->json([
                'success' => false,
                'message' => 'Supabase URL is not configured'
            ], 500);
        }

        // 🔥 Supabase JWKS endpoint
        $url = rtrim($supabaseUrl, '/') . '/auth/v1/.well-known/jwks.json';

        // 🔥 Fetch JWKS
        $response = Http::timeout(5)->get($url);

        $jwks = $response->json();

        //echo "JWKS Response: " . json_encode($jwks) . "\n";

        if (!$response->successful() || !isset($jwks['keys'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid JWKS response',
                'debug' => $response->body()
            ], 500);
        }

        // 🔐 Convert JWKS to keys
        $keys = JWK::parseKeySet($jwks);

        try {
            // 🔐 Decode JWT using parsed JWK keys (library handles alg checks)
            $decoded = JWT::decode($token, $keys);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
                'debug' => $e->getMessage()
            ], 401);
        }

        // 📧 Get email
        $email = $decoded->email ?? null;

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found'
            ], 401);
        }

        $metadata = $decoded->user_metadata ?? null;
        if (is_array($metadata)) {
            $metadata = (object) $metadata;
        }

        $appMetadata = $decoded->app_metadata ?? null;
        if (is_array($appMetadata)) {
            $appMetadata = (object) $appMetadata;
        }

        $provider = $appMetadata->provider ?? null;
        $providerId = $decoded->sub ?? null;

        // 👤 Create / update user
        $user = FrontendUser::where('email', $email)->first();

        if (!$user) {
            $user = FrontendUser::create([
                'name' => $metadata->full_name
                    ?? $metadata->name
                    ?? 'User',
                'email' => $email,
                'password' => bcrypt(Str::random(32)),
                'provider' => $provider,
                'provider_id' => $providerId,
                'avatar' => $metadata->avatar_url ?? null,
                'email_verified_at' => now(),
            ]);
        } else {
            $user->update([
                'name' => $metadata->full_name
                    ?? $metadata->name
                    ?? 'User',
                'provider' => $provider,
                'provider_id' => $providerId,
                'avatar' => $metadata->avatar_url ?? null,
                'email_verified_at' => now(),
            ]);
        }

        // 🧹 Remove old tokens (Sanctum)
        $user->tokens()->delete();

        // 🔑 Create new token
        $authToken = $user->createToken('frontend_users')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $authToken,
            'user' => $user
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:frontend_user,email',
            'password' => 'required|min:6',
            'country_code' => 'required',
            'phone_number' => 'required'
        ]);

        /*
        |--------------------------------------------------------------------------
        | DELETE OLD RECORDS
        |--------------------------------------------------------------------------
        */

        EmailVerification::where(
            'email',
            $request->email
        )->where(
            'type',
            'register'
        )->delete();

        /*
        |--------------------------------------------------------------------------
        | GENERATE OTP
        |--------------------------------------------------------------------------
        */

        $otp = rand(1000, 9999);

        /*
        |--------------------------------------------------------------------------
        | STORE TEMP DATA
        |--------------------------------------------------------------------------
        */

        EmailVerification::create([
            'email' => $request->email,
            'otp' => $otp,
            'type' => 'register',
            'payload' => [
                'name' => $request->name,
                'password' => bcrypt(
                    $request->password
                ),
                'country_code' => $request->country_code,
                'phone_number' => $request->phone_number
            ],

            'expires_at' => now()
                ->addMinutes(10)
        ]);

        /*
        |--------------------------------------------------------------------------
        | SEND MAIL
        |--------------------------------------------------------------------------
        */

        Mail::to($request->email)
            ->queue(new RegisterOtpMail($otp));

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY REGISTER OTP
    |--------------------------------------------------------------------------
    */

    public function verifyRegisterOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required'
        ]);

        $verification = EmailVerification::where([
            'email' => $request->email,
            'otp' => $request->otp,
            'type' => 'register'
        ])->first();

        /*
        |--------------------------------------------------------------------------
        | CHECK OTP
        |--------------------------------------------------------------------------
        */

        if (
            !$verification ||
            now()->gt($verification->expires_at)
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ], 400);
        }

        DB::beginTransaction();

        try {

            $payload = $verification->payload;

            /*
            |--------------------------------------------------------------------------
            | CREATE USER
            |--------------------------------------------------------------------------
            */
            $user = FrontendUser::create([
                'name' => $payload['name'],
                'email' => $verification->email,
                'password' => $payload['password'],
                'country_code' => $payload['country_code'],
                'phone_number' => $payload['phone_number'],
                'email_verified_at' => now()
            ]);

            /*
            |--------------------------------------------------------------------------
            | DELETE OTP RECORD
            |--------------------------------------------------------------------------
            */

            $verification->delete();

            /*
            |--------------------------------------------------------------------------
            | CREATE TOKEN
            |--------------------------------------------------------------------------
            */

            $token = $user
                ->createToken('frontend_users')
                ->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'token' => $token,
                'user' => $user
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEND MAGIC REGISTER LINK
    |--------------------------------------------------------------------------
    */

    public function sendMagicRegisterLink(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:frontend_user,email',
            'country_code' => 'required',
            'phone_number' => 'required'
        ]);

        /*
        |--------------------------------------------------------------------------
        | DELETE OLD LINKS
        |--------------------------------------------------------------------------
        */

        EmailVerification::where(
            'email',
            $request->email
        )->where(
            'type',
            'magic_register'
        )->delete();

        /*
        |--------------------------------------------------------------------------
        | GENERATE TOKEN
        |--------------------------------------------------------------------------
        */

        $plainToken = Str::random(64);

        $hashedToken = hash(
            'sha256',
            $plainToken
        );

        /*
        |--------------------------------------------------------------------------
        | STORE TEMP DATA
        |--------------------------------------------------------------------------
        */

        EmailVerification::create([
            'email' => $request->email,
            'token' => $hashedToken,
            'type' => 'magic_register',
            'payload' => [
                'name' => $request->name,
                'country_code' => $request->country_code,
                'phone_number' => $request->phone_number
            ],

            'expires_at' => now()
                ->addMinutes(15)
        ]);

        /*
        |--------------------------------------------------------------------------
        | GENERATE URL
        |--------------------------------------------------------------------------
        */

        $url = config('app.api_frontend_url')
            .'magic-login/'
            .$plainToken;

        /*
        |--------------------------------------------------------------------------
        | SEND MAIL
        |--------------------------------------------------------------------------
        */

        Mail::to($request->email)
            ->queue(new MagicLinkMail($url));

        return response()->json([
            'success' => true,
            'message' => 'Magic register link sent'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SEND MAGIC LOGIN LINK
    |--------------------------------------------------------------------------
    */

    public function sendMagicLoginLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        /*
        |--------------------------------------------------------------------------
        | CHECK USER
        |--------------------------------------------------------------------------
        */

        $user = FrontendUser::where(
            'email',
            $request->email
        )->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | DELETE OLD LINKS
        |--------------------------------------------------------------------------
        */

        EmailVerification::where(
            'email',
            $request->email
        )->where(
            'type',
            'magic_login'
        )->delete();

        /*
        |--------------------------------------------------------------------------
        | GENERATE TOKEN
        |--------------------------------------------------------------------------
        */

        $plainToken = Str::random(64);

        $hashedToken = hash(
            'sha256',
            $plainToken
        );

        /*
        |--------------------------------------------------------------------------
        | STORE LOGIN TOKEN
        |--------------------------------------------------------------------------
        */

        EmailVerification::create([
            'email' => $request->email,
            'token' => $hashedToken,
            'type' => 'magic_login',
            'expires_at' => now()
                ->addMinutes(15)
        ]);

        /*
        |--------------------------------------------------------------------------
        | GENERATE URL
        |--------------------------------------------------------------------------
        */

        $url = config('app.api_frontend_url')
            .'magic-login/'
            .$plainToken;

        /*
        |--------------------------------------------------------------------------
        | SEND MAIL
        |--------------------------------------------------------------------------
        */

        Mail::to($request->email)
            ->queue(new MagicLinkMail($url));

        return response()->json([
            'success' => true,
            'message' => 'Magic login link sent'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MAGIC LOGIN
    |--------------------------------------------------------------------------
    */

    public function magicLogin($token)
    {
        $hashedToken = hash(
            'sha256',
            $token
        );

        $verification = EmailVerification::where(
            'token',
            $hashedToken
        )->first();

        /*
        |--------------------------------------------------------------------------
        | CHECK TOKEN
        |--------------------------------------------------------------------------
        */

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid link'
            ], 400);
        }

        if (now()->gt($verification->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Expired link'
            ], 400);
        }
        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | MAGIC REGISTER
            |--------------------------------------------------------------------------
            */

            if (
                $verification->type
                == 'magic_register'
            ) {

                $payload = $verification->payload;

                $user = FrontendUser::create([
                    'name' => $payload['name'],
                    'email' => $verification->email,
                    'password' => bcrypt(
                        Str::random(16)
                    ),
                    'country_code' => $payload['country_code'],
                    'phone_number' => $payload['phone_number'],
                    'email_verified_at' => now()
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | MAGIC LOGIN
            |--------------------------------------------------------------------------
            */

            else {

                $user = FrontendUser::where(
                    'email',
                    $verification->email
                )->first();
            }

            /*
            |--------------------------------------------------------------------------
            | DELETE TOKEN RECORD
            |--------------------------------------------------------------------------
            */

            $verification->delete();

            /*
            |--------------------------------------------------------------------------
            | CREATE SANCTUM TOKEN
            |--------------------------------------------------------------------------
            */

            $token = $user
                ->createToken('auth')
                ->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN WITH PASSWORD
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = FrontendUser::where(
            'email',
            $request->email
        )->first();

        if (
            !$user ||
            !Hash::check(
                $request->password,
                $user->password
            )
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE TOKEN
        |--------------------------------------------------------------------------
        */

        $token = $user
            ->createToken('auth')
            ->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    public function checkEmailValidation(Request $request){
         $request->validate([
            'email' => 'required|email'
        ]);

        $user = FrontendUser::where('email',$request->email)->first();

        if($user){
            return response()->json([
                'success' => false,
                'message' => 'Email address Already Exit!'
            ], 401);
        }else{
            return response()->json([
                'success' => true,
                'message' => 'Email address available'
            ], 200);
        }
    }

    public function getCountries(){
        return Countries::select('id', 'name', 'phone_code', 'currency', 'currency_symbol', 'flag')->where('status', 'active')->get();
    }

    public function sendResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        $user = FrontendUser::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        EmailVerification::where('email', $request->email)
            ->where('type', 'password_reset')
            ->delete();

        $otp = random_int(1000, 9999);

        EmailVerification::create([
            'email' => $request->email,
            'otp' => $otp,
            'type' => 'password_reset',
            'payload' => [
                'password' => bcrypt($request->password)
            ],
            'expires_at' => now()->addMinutes(10)
        ]);

        Mail::to($request->email)
            ->queue(new ForgotPasswordOtpMail(
                $user,
                $otp
            ));

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully'
        ]);
    }

    public function verifyResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:4'
        ]);

        $verification = EmailVerification::where('email', $request->email)
            ->where('type', 'password_reset')
            ->first();

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP'
            ], 400);
        }

        if ($verification->otp != $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP'
            ], 400);
        }

        if (now()->gt($verification->expires_at)) {

            $verification->delete();

            return response()->json([
                'success' => false,
                'message' => 'OTP expired'
            ], 400);
        }

        $user = FrontendUser::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->update([
            'password' => $verification->payload['password']
        ]);

        $verification->delete();

        $token = $user->createToken('auth_token')
            ->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful',
            'token' => $token,
            'user' => $user
        ]);
    }

    public function editProfile(Request $request){
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => [
                'required',
                'string',
                Rule::unique('frontend_user', 'phone_number')->ignore($user->id),
            ],
            'whatsapp_number' => [
                'nullable',
                'string',
                Rule::unique('frontend_user', 'whatsapp_number')->ignore($user->id),
            ]
        ]);

        $user->update([
            'name' => $validated['name'],
            'country_code' => $request->country_code,
            'phone_number' => $validated['phone_number'],
            'whatsapp_number' => $validated['whatsapp_number'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $user,
        ]);
    }
}