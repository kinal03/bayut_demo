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
      
        if ($user->user_type == 'super_admin') {
            $authUser = User::find($request->id);
            setTenantConnection($authUser);
        } else {
            setTenantConnection($user);
        }

        $limit = $request->limit ?? 10;

        $allowedSorts = [
            'id',
            'first_name',
            'last_name',
            'email',
            'is_blocked',
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
            'is_blocked',
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

        $totalAgents = User::where('user_type', 'agent')->count();

        $activeAgents = User::where('user_type', 'agent')
            ->where('is_blocked', 0)
            ->count();

        $blockedAgents = User::where('user_type', 'agent')
            ->where('is_blocked', 1)
            ->count();

        return response()->json([
            'agents' => $agents,
            'total_agents' => $totalAgents,
            'active_agents' => $activeAgents,
            'blocked_agents' => $blockedAgents
        ]);
    }

    public function getUserDetails(Request $request){
    
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('tenant_id')) {
            //die();
            $Auth = User::where('tenancy_id',$request->tenant_id)->first();
            setTenantConnection($Auth);

            // ✅ COMMON LOGIC

            // tenancy()->initialize($tenant);
            $user = User::find($request->id);

            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }

            return response()->json($user);
        }else{
            $Auth = $request->user();
            setTenantConnection($Auth);

            $user = User::find($request->id);

            return response()->json($user);
        }
    }

    public function blockAgency(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'is_blocked' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($request->id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->is_blocked = $request->is_blocked;
        $user->save();

        setTenantConnection($user);

        $agents = User::where('tenancy_id', $user->tenancy_id)->update(['is_blocked' => $request->is_blocked]);

        return response()->json(['message' => 'Agency blocked status updated.']);
    }
}