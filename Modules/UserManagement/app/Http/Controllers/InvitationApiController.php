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
use Illuminate\Support\Facades\Artisan;
use Modules\UserManagement\App\Models\TenantPersonalAccessToken,Modules\UserManagement\App\Models\CentralTenantTelations,Modules\UserManagement\App\Models\Tenant,Modules\UserManagement\App\Models\TenantUserInvitations,Modules\UserManagement\App\Models\UserInvitations;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Container\Attributes\Log;
use Modules\UserManagement\App\Mail\UserInvitationMail;
use Modules\UserManagement\App\Jobs\SendInvitationMailJob;


class InvitationApiController extends Controller
{
    public function sendInvite(Request $request)
    {
        $user = $request->user();

        if ($user->user_type === 'agency') {

            setTenantConnection($user);

            $invitationTable = 'invitation_users';
            $invitationModel = TenantUserInvitations::class;
            $user_type = 'agent';

        } else {

            $invitationTable = 'super_admin_invitations';
            $invitationModel = UserInvitations::class;
            $user_type = 'agency';
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => [
                'required',
                'email',
                'max:255',
                Rule::unique('mysql.users', 'email'),
                Rule::unique($invitationTable, 'email'),
                Rule::unique('mysql.central_tenant_relations', 'email'),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $invitation = $invitationModel::create([
            'first_name'       => $request->first_name,
            'last_name'        => $request->last_name,
            'email'            => $request->email,
            'mobile'           => $request->mobile,
            'whatsapp'         => $request->whatsapp,
            'landline'         => $request->landline,
            'gender'           => $request->gender,
            'nationality'      => $request->nationality,
            'experience'       => $request->experience,
            'languages'        => $request->languages,
            'specialities'     => $request->specialities,
            'speciality_areas' => $request->speciality_areas,
            'facebook'         => $request->socials['facebook'] ?? null,
            'instagram'        => $request->socials['instagram'] ?? null,
            'linkedin'         => $request->socials['linkedin'] ?? null,
            'twitter'          => $request->socials['twitter'] ?? null,
            'youtube'          => $request->socials['youtube'] ?? null,
            'token'            => Str::random(64),
            'user_type'        => $user_type,
            'status'           => 'pending',
            'tenant_id'        => $user->tenancy_id,
            'expires_at'       => now()->addDays(5),
            'created_by'       => $user->id,
        ]);

        $payload = [
            'id'         => $invitation->id,
            'token'      => $invitation->token,
            'email'      => $invitation->email,
            'first_name' => $invitation->first_name,
            'last_name'  => $invitation->last_name,
            'tenant_id'  => $invitation->tenant_id,
            'user_type'  => $user_type,
        ];

        $encrypted = Crypt::encryptString(
            json_encode($payload)
        );

        $frontendUrl = rtrim(config('app.frontend_url'), '/')
            . '/accept-invitation?data='
            . urlencode($encrypted);

        DB::setDefaultConnection('mysql');

        SendInvitationMailJob::dispatch(
            $invitation->email,
            [
                'first_name' => $invitation->first_name,
                'last_name' => $invitation->last_name,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at,
            ],
            [
                'name' => trim(
                    ($user->first_name ?? '') . ' ' .
                    ($user->last_name ?? '')
                ),
                'email' => $user->email,
            ],
            $frontendUrl
        )->onConnection('database');

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully.'
        ]);
    }

    public function acceptInvite(Request $request)
    {
        $request->validate([
            'data' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        try {
            $decoded = urldecode($request->data);

            $decrypted = json_decode(
                Crypt::decryptString($decoded),
                true
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid or corrupted invitation link.'
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | AGENT INVITATION
        |--------------------------------------------------------------------------
        */

        if (
            !empty($decrypted['tenant_id']) &&
            ($decrypted['user_type'] ?? null) === 'agent'
        ) {

            $tenant = Tenant::on('mysql')->find($decrypted['tenant_id']);

            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant not found.'
                ], 404);
            }

            $conn = getTenantConnection($tenant);

            $invitation = (new TenantUserInvitations)
                ->setConnection($conn)
                ->where('token', $decrypted['token'])
                ->where('status', 'pending')
                ->first();

            if (!$invitation) {
                return response()->json([
                    'message' => 'Invitation not found or already used.'
                ], 404);
            }

            if ($invitation->expires_at->isPast()) {

                $invitation->update([
                    'status' => 'expired'
                ]);

                return response()->json([
                    'message' => 'Invitation expired.'
                ], 403);
            }

            if (
                (new User)
                    ->setConnection($conn)
                    ->where('email', $invitation->email)
                    ->exists()
            ) {
                return response()->json([
                    'message' => 'User already exists.'
                ], 422);
            }

            (new CentralTenantTelations)
                ->setConnection('mysql')
                ->create([
                    'tenant_id' => $tenant->id,
                    'email' => $invitation->email,
                    'status' => 'active',
                ]);

            (new User)
                ->setConnection($conn)
                ->create([
                    'first_name' => $invitation->first_name,
                    'last_name' => $invitation->last_name,
                    'email' => $invitation->email,
                    'user_type' => 'agent',
                    'tenancy_id' => $tenant->id,
                    'email_verified_at' => now(),
                    'password' => Hash::make($request->password),
                    'mobile'           => $invitation->mobile,
                    'whatsapp'         => $invitation->whatsapp,
                    'landline'         => $invitation->landline,
                    'gender'           => $invitation->gender,
                    'nationality'      => $invitation->nationality,
                    'experience'       => $invitation->experience,
                    'languages'        => $invitation->languages,
                    'specialities'     => $invitation->specialities,
                    'speciality_areas' => $invitation->speciality_areas,
                    'facebook'         => $invitation->facebook,
                    'instagram'        => $invitation->instagram,
                    'linkedin'         => $invitation->linkedin,
                    'twitter'          => $invitation->twitter,
                    'youtube'          => $invitation->youtube,
                ]);

            $invitation->update([
                'status' => 'accepted'
            ]);

            return response()->json([
                'message' => 'Account created successfully.'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | AGENCY INVITATION
        |--------------------------------------------------------------------------
        */
        $invitation = UserInvitations::where(
                'token',
                $decrypted['token']
            )
            ->where('status', 'pending')
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invitation not found or already used.'
            ], 404);
        }

        if ($invitation->expires_at->isPast()) {

            $invitation->update([
                'status' => 'expired'
            ]);

            return response()->json([
                'message' => 'Invitation expired.'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | PREVENT DOUBLE EXECUTION
        |--------------------------------------------------------------------------
        */
        $invitation->update([
            'status' => 'processing'
        ]);

        $connection = 'tenant';
        $tenantId = (string) Str::uuid();
        $dbName = 'tenant_' . Str::lower(Str::random(12));

        try {

            /*
            |--------------------------------------------------------------------------
            | CREATE DATABASE
            |--------------------------------------------------------------------------
            */
            DB::purge($connection);

            DB::connection($connection)
                ->statement("CREATE DATABASE `$dbName`");

            /*
            |--------------------------------------------------------------------------
            | CONNECT NEW DATABASE
            |--------------------------------------------------------------------------
            */
            config([
                "database.connections.$connection.database" => $dbName
            ]);

            DB::purge($connection);
            DB::reconnect($connection);

            DB::connection($connection)->getPdo();

            /*
            |--------------------------------------------------------------------------
            | RUN MIGRATIONS
            |--------------------------------------------------------------------------
            */
            Artisan::call('migrate', [
                '--database' => $connection,
                '--path' => 'Modules/UserManagement/database/migrations/tenant',
                '--force' => true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | CREATE TENANT RECORD
            |--------------------------------------------------------------------------
            */
            $tenant = Tenant::on('mysql')->create([
                'id' => $tenantId,
                'database' => $dbName,
            ]);

            /*
            |--------------------------------------------------------------------------
            | STORAGE FOLDER
            |--------------------------------------------------------------------------
            */
            Storage::disk('public')
                ->makeDirectory("uploads/tenant_{$tenant->id}");

            /*
            |--------------------------------------------------------------------------
            | CREATE AGENCY USER
            |--------------------------------------------------------------------------
            */
            User::on('mysql')->create([
                'first_name' => $invitation->first_name,
                'last_name' => $invitation->last_name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'user_type' => 'agency',
                'tenancy_id' => $tenant->id, // NOT tenancy_id
                'email_verified_at' => now(),
                'mobile'           => $invitation->mobile,
                'whatsapp'         => $invitation->whatsapp,
                'landline'         => $invitation->landline,
                'gender'           => $invitation->gender,
                'nationality'      => $invitation->nationality,
                'experience'       => $invitation->experience,
                'languages'        => $invitation->languages,
                'specialities'     => $invitation->specialities,
                'speciality_areas' => $invitation->speciality_areas,
                'facebook'         => $invitation->facebook,
                'instagram'        => $invitation->instagram,
                'linkedin'         => $invitation->linkedin,
                'twitter'          => $invitation->twitter,
                'youtube'          => $invitation->youtube,
            ]);

            /*
            |--------------------------------------------------------------------------
            | MARK INVITATION ACCEPTED
            |--------------------------------------------------------------------------
            */
            $invitation->update([
                'status' => 'accepted'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully.'
            ]);

        } catch (\Exception $e) {

            /*
            |--------------------------------------------------------------------------
            | CLEANUP DATABASE IF CREATED
            |--------------------------------------------------------------------------
            */
            try {
                DB::statement("DROP DATABASE IF EXISTS `$dbName`");
            } catch (\Exception $ex) {
            }

            /*
            |--------------------------------------------------------------------------
            | RESET INVITATION
            |--------------------------------------------------------------------------
            */
            $invitation->update([
                'status' => 'pending'
            ]);

            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function invitationList(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->user_type === 'agency') {

            setTenantConnection($user);

            $invitationTable = 'invitation_users';
            $invitationModel = TenantUserInvitations::class;

        } else {

            $invitationTable = 'super_admin_invitations';
            $invitationModel = UserInvitations::class;
        }

        $limit = $request->limit ?? 10;

        $allowedSorts = [
            'id',
            'first_name',
            'last_name',
            'email',
            'user_type',
            'status',
            'created_at'
        ];

        $sort = in_array($request->sort, $allowedSorts)
            ? $request->sort
            : 'created_at';

        $dir = strtolower($request->dir) === 'asc' ? 'asc' : 'desc';

        $query = $invitationModel::select(
            'id',
            'first_name',
            'last_name',
            'email',
            'user_type',
            'status',
            'created_at'
        );

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->user_type != null) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                    ->orWhere('last_name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
        }

        $inviate = $query->orderBy($sort, $dir)->paginate($limit);

        return response()->json([
            'message' => 'Invitation list retrieved successfully',
            'status' => true,
            'data' => $inviate
        ]);
    }

    public function cancelInvitation(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->user_type === 'agency') {

            setTenantConnection($user);

            $invitationTable = 'invitation_users';
            $invitationModel = TenantUserInvitations::class;

        } else {

            $invitationTable = 'super_admin_invitations';
            $invitationModel = UserInvitations::class;
        }

        $invitation = $invitationModel::find($request->id);

        if (!$invitation) {
            return response()->json([
                'message' => 'Invitation not found.'
            ], 404);
        }

        $invitation->update([
            'status' => 'rejected'
        ]);

        return response()->json([
            'message' => 'Invitation cancelled successfully.'
        ]);
    }

    public function resendInvite(Request $request)
    {
        $user = $request->user();

        if ($user->user_type === 'agency') {

            setTenantConnection($user);

            $invitationTable = 'invitation_users';
            $invitationModel = TenantUserInvitations::class;
            $user_type = 'agent';

        } else {

            $invitationTable = 'super_admin_invitations';
            $invitationModel = UserInvitations::class;
            $user_type = 'agency';
        }

        $invitation = $invitationModel::where('id', $request->id)
            ->whereIn('status', ['expired', 'rejected'])
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Only expired or rejected invitations can be resent.'
            ], 422);
        }

        $invitation->update([
            'status' => 'pending'
        ]);

        $payload = [
            'id'         => $invitation->id,
            'token'      => $invitation->token,
            'email'      => $invitation->email,
            'first_name' => $invitation->first_name,
            'last_name'  => $invitation->last_name,
            'tenant_id'  => $invitation->tenant_id,
            'user_type'  => $user_type,
        ];

        $encrypted = Crypt::encryptString(
            json_encode($payload)
        );

        $frontendUrl = rtrim(config('app.frontend_url'), '/')
            . '/accept-invitation?data='
            . urlencode($encrypted);

        DB::setDefaultConnection('mysql');

        SendInvitationMailJob::dispatch(
            $invitation->email,
            [
                'first_name' => $invitation->first_name,
                'last_name' => $invitation->last_name,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at,
            ],
            [
                'name' => trim(
                    ($user->first_name ?? '') . ' ' .
                    ($user->last_name ?? '')
                ),
                'email' => $user->email,
            ],
            $frontendUrl
        )->onConnection('database');

        return response()->json([
            'message' => 'Invitation resent successfully.'
        ]);
    }
}