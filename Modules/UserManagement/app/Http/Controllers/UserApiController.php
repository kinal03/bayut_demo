<?php

namespace Modules\UserManagement\App\Http\Controllers;


use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\UserManagement\App\Models\User;

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

        return response()->json($agencies);
    }
}