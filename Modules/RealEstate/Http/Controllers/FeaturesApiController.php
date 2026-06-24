<?php

namespace Modules\RealEstate\Http\Controllers;


use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Modules\RealEstate\Models\Features;

class FeaturesApiController extends Controller
{
    public function index(Request $req)
    {
        $user = $req->user();
        setTenantConnection($user);

        if ($req->select == true) {
            return Features::select('id', 'name','arabic_name', 'icon')
                ->where('status', 'published')
                ->get();
        }

            $limit = $req->limit ?? 10;
            $sort  = $req->sort ?? 'created_at';
            $dir   = $req->dir ?? 'desc';

            $query = Features::select('id', 'name', 'arabic_name', 'icon', 'status','created_at');

            if ($req->has('search') && !empty($req->search)) {
                $query->where('name', 'like', '%' . $req->search . '%');
            }

            if ($req->has('status') && !empty($req->status)) {
                $query->where('status', $req->status);
            }

            if ($req->filled('from_date') && $req->filled('to_date')) {
                $query->whereBetween('created_at', [
                    Carbon::parse($req->from_date)->startOfDay(),
                    Carbon::parse($req->to_date)->endOfDay(),
                ]);
            } else {
                if ($req->filled('from_date')) {
                    $query->where(
                        'created_at',
                        '>=',
                        Carbon::parse($req->from_date)->startOfDay()
                    );
                }
                if ($req->filled('to_date')) {
                    $query->where(
                        'created_at',
                        '<=',
                        Carbon::parse($req->to_date)->endOfDay()
                    );
                }
            }

            $Features = $query->orderBy($sort, $dir)->paginate($limit);

        return response()->json($Features);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'arabic_name' => 'string|max:255',
            'status' => 'required|string'
        ]);

        $user = $request->user();
        setTenantConnection($user);

        // tenancy()->initialize($user->tenant_id);

        $field = Features::create([
            'name' => $request->name,
            'arabic_name' => $request->arabic_name,
            'icon' => $request->icon,
            'status' => $request->status,
            'created_at' => now()
        ]);

        return response()->json([
            "success" => true,
            'message' => 'Features created successfully',
        ]);
    }

    public function show(Request $request,$id)
    {
        $user = $request->user();
        setTenantConnection($user);

        // tenancy()->initialize($user->tenant_id);

        $field = Features::findOrFail($id);
        return response()->json($field);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        setTenantConnection($user);

        // tenancy()->initialize($user->tenant_id);

        $field = Features::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'arabic_name' => 'string|max:255',
            'status' => 'required|string'
        ]);

        $field->update([
            'name' => $request->name,
            'arabic_name' => $request->arabic_name,
            'icon' => $request->icon,
            'status' => $request->status,
            'updated_at' => now()
        ]);

        return response()->json([
            "success" => true,
            'message' => 'Features updated successfully'
        ]);
    }

    public function destroy(Request $request,$id)
    {
        $user = $request->user();
        setTenantConnection($user);

        // tenancy()->initialize($user->tenant_id);

        $field = Features::findOrFail($id);
        $field->delete();

        return response()->json([
            "success" => true,
            'message' => 'Features deleted successfully'
        ]);
    }

    public function featureStatusUpdate(Request $request)
    {
        $user = $request->user();
        setTenantConnection($user);

        $request->validate([
            'id' => 'required',
            'status' => 'required|string'
        ]);

        Features::where('id', $request->id)->update(['status' => $request->status]);

        return response()->json([
            "success" => true,
            'message' => 'Features status updated successfully'
        ]);
    }
}