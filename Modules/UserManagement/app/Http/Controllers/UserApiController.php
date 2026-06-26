<?php

namespace Modules\UserManagement\App\Http\Controllers;


use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\UserManagement\App\Models\User,Modules\UserManagement\App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class UserApiController extends Controller
{
    public function getAllAgency(Request $request)
    {
        $limit = $request->limit ?? 10;

        $allowedSorts = [
            'id',
            'first_name',
            'last_name',
            'email',
            'created_at'
        ];

        $sort = in_array($request->sort, $allowedSorts)
            ? $request->sort
            : 'created_at';

        $dir = strtolower($request->dir) === 'asc' ? 'asc' : 'desc';

        $query = User::select(
            'id',
            'first_name',
            'last_name',
            'email',
            'tenancy_id',
            'profile_picture',
            'user_type',
            'created_at'
        )->where('user_type', 'agency');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                ->orWhere('last_name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay(),
            ]);
        } else {
            if ($request->filled('from_date')) {
                $query->where('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
            }

            if ($request->filled('to_date')) {
                $query->where('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
            }
        }

        $agencies = $query->orderBy($sort, $dir)->paginate($limit);

        $agencies->getCollection()->transform(function ($agency) {
            setTenantConnection($agency);

            $agency->properties_count = \Modules\RealEstate\Models\Properties::where(['moderation_status'=>'approved','status'=>'published'])->count();
            $agency->agent_count = User::where('is_blocked',0)->count();

            return $agency;
        });

        return response()->json($agencies);
    }

    public function getAgents(Request $request){
        $user = $request->user();
        setTenantConnection($user);

        $limit = $request->limit ?? 10;

        $allowedSorts = [
            'id',
            'first_name',
            'last_name',
            'email',
            'created_at'
        ];

        $sort = in_array($request->sort, $allowedSorts)
            ? $request->sort
            : 'created_at';

        $dir = strtolower($request->dir) === 'asc' ? 'asc' : 'desc';

        $query = User::select(
            'id',
            'first_name',
            'last_name',
            'email',
            'tenancy_id',
            'profile_picture',
            'user_type',
            'created_at'
        );

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                ->orWhere('last_name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay(),
            ]);
        } else {
            if ($request->filled('from_date')) {
                $query->where('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
            }

            if ($request->filled('to_date')) {
                $query->where('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
            }
        }

        $agents = $query->orderBy($sort, $dir)->paginate($limit);

        return response()->json($agents);
    }

    public function getUserDetails(Request $request){
        $Auth = $request->user();

        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('tenant_id')) {
            $tenant = Tenant::find($request->tenant_id);

            if (!$tenant) {
                return response()->json(['message' => 'Tenant not found.'], 404);
            }

            // ✅ COMMON LOGIC
            setTenantConnection($Auth);

            // tenancy()->initialize($tenant);
            $user = User::find($request->id);

            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }

            tenancy()->end();
        }

        $user = User::find($request->id);

        return response()->json($user);
    }
}