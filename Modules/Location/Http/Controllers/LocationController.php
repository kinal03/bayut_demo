<?php

namespace Modules\Location\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Location\Models\Countries;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Modules\Location\Models\States;
use Modules\Location\Models\Cities;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function getCountries(Request $req)
    {

        if ($req->select == true) {
            return Countries::select('id', 'name', 'phone_code', 'currency', 'currency_symbol', 'flag')->where('status', 'active')->get();
        }

        $limit = $req->limit ?? 10;

        $allowedSorts = [
            'id',
            'name',
            'phone_code',
            'currency',
            'status',
            'iso_code',
        ];
        $sort = in_array($req->sort, $allowedSorts)
            ? $req->sort
            : 'id';

        $dir = strtolower($req->dir) === 'asc' ? 'asc' : 'desc';

        $query = Countries::select('id', 'name', 'iso_code', 'phone_code', 'min_length', 'max_length', 'currency', 'currency_symbol', 'timezones', 'flag', 'status');

        if ($req->filled('search')) {
            $query->where('name', 'like', '%' . $req->search . '%');
        }

        if ($req->filled('status')) {
            $query->where('status', $req->status);
        }

        $countries = $query->orderBy($sort, $dir)->paginate($limit);


        return response()->json($countries);
    }

    public function storeState(Request $request)
    {
        $user = $request->user();

        // ✅ SET CONNECTION (COMMON FUNCTION)
        setTenantConnection($user);

        /*
        |-------------------------------------------------
        | VALIDATION
        |-------------------------------------------------
        */
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        /*
        |-------------------------------------------------
        | DUPLICATE CHECK (IMPORTANT)
        |-------------------------------------------------
        */
        $exists = States::where('name', $request->name)->where('country_id', $request->country_id)->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'State already exists for this country'
            ], 409);
        }

        /*
        |-------------------------------------------------
        | CREATE STATE
        |-------------------------------------------------
        */
        $state = States::create([
            'name'       => $request->name,
            'country_id' => $request->country_id,
            'status'     => 'active',
            'created_at' => now()
        ]);

        /*
        |-------------------------------------------------
        | CLEAR CACHE (IMPORTANT 🔥)
        |-------------------------------------------------
        */
        Cache::tags(['locations'])->flush();

        return response()->json([
            'status'  => true,
            'message' => 'State added successfully',
            'data'    => $state
        ]);
    }

    public function editState(Request $request)
    {
        $user = $request->user();

        // ✅ SET TENANT / CENTRAL CONNECTION
        setTenantConnection($user);

        $id = $request->id;

        /*
        |-------------------------------------------------
        | FIND STATE WITH COUNTRY
        |-------------------------------------------------
        */
        $state = States::select(
                'states.id',
                'states.name',
                'states.country_id',
            )
            ->where('states.id', $id)
            ->first();

        if (!$state) {
            return response()->json([
                'status'  => false,
                'message' => 'State not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $state
        ]);
    }

    public function updateState(Request $request)
    {
        $user = $request->user();

        // ✅ SET CONNECTION (COMMON FUNCTION)
        setTenantConnection($user);

        $id = $request->id;

        /*
        |-------------------------------------------------
        | FIND STATE
        |-------------------------------------------------
        */
        $state = States::find($id);

        /*
        |-------------------------------------------------
        | VALIDATION
        |-------------------------------------------------
        */
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        /*
        |-------------------------------------------------
        | DUPLICATE CHECK (EXCLUDE CURRENT ID)
        |-------------------------------------------------
        */
        $exists = States::where('name', $request->name)->where('country_id', $request->country_id)->where('id', '!=', $id)->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'State already exists for this country'
            ], 409);
        }

        /*
        |-------------------------------------------------
        | UPDATE STATE
        |-------------------------------------------------
        */
        $state->update([
            'name'       => $request->name,
            'country_id' => $request->country_id,
            'status' => $request->status,
            'updated_at' => now()
        ]);

        /*
        |-------------------------------------------------
        | CLEAR CACHE
        |-------------------------------------------------
        */
        Cache::tags(['locations'])->flush();

        return response()->json([
            'status'  => true,
            'message' => 'State updated successfully',
            'data'    => $state
        ]);
    }

    public function getStates(Request $req)
    {
        // Dropdown
        if ($req->select == true) {
            $query = States::select('id', 'name')->where('status', 'active');

            if ($req->filled('country_id')) {
                $query->where('country_id', $req->country_id);
            }

            return $query->get();
        }

        $limit = $req->limit ?? 10;

        $dir = strtolower($req->dir) === 'asc' ? 'asc' : 'desc';

        $query = States::select(
                'states.id',
                'states.name',
                'states.status',
                'countries.name as country_name'
            )
            ->join('countries', 'states.country_id', '=', 'countries.id');

        // Search
        if ($req->filled('search')) {
            $query->where(function ($q) use ($req) {
                $q->where('states.name', 'like', '%' . $req->search . '%')
                ->orWhere('countries.name', 'like', '%' . $req->search . '%');
            });
        }

        // Status
        if ($req->filled('status')) {
            $query->where('states.status', $req->status);
        }

        // Country
        if ($req->filled('country_id')) {
            $query->where('states.country_id', $req->country_id);
        }

        if($req->sort == 'country_name'){
            $sort = 'countries.name';
        } else {
            $sort  = 'states.'.$req->sort ?? 'states.name';
        }

        $states = $query->orderBy($sort, $dir)->paginate($limit)->appends($req->all()); // ✅ optional but good

        return response()->json($states);
    }

    public function deleteState(Request $request)
    {
        $user = $request->user();

        // ✅ SET CONNECTION (COMMON FUNCTION)
        setTenantConnection($user);

        $id = $request->id;

        /*
        |-------------------------------------------------
        | FIND STATE
        |-------------------------------------------------
        */
        $state = States::find($id);

        if (!$state) {
            return response()->json([
                'status'  => false,
                'message' => 'State not found'
            ], 404);
        }

        /*
        |-------------------------------------------------
        | CHECK DEPENDENCY (IMPORTANT 🔥)
        |-------------------------------------------------
        */
        $hasCities = Cities::where('state_id', $id)->exists();

        if ($hasCities) {
            return response()->json([
                'status'  => false,
                'message' => 'Cannot delete state. Cities exist under this state.'
            ], 400);
        }

        /*
        |-------------------------------------------------
        | DELETE STATE
        |-------------------------------------------------
        */
        $state->delete(); // soft delete if enabled

        /*
        |-------------------------------------------------
        | CLEAR CACHE
        |-------------------------------------------------
        */
        Cache::tags(['locations'])->flush();

        return response()->json([
            'status'  => true,
            'message' => 'State deleted successfully'
        ]);
    }

    public function getCities(Request $req)
    {
        // 🔹 Dropdown
        if ($req->select == true) {
            $query = Cities::select('id', 'name')->where('status', 'active');

            if ($req->filled('state_id')) {
                $query->where('state_id', $req->state_id);
            }

            return $query->get();
        }

        $limit = $req->limit ?? 10;
        $dir = strtolower($req->dir) === 'asc' ? 'asc' : 'desc';

        $query = Cities::select(
                'cities.id',
                'cities.name',
                'cities.status',
                'states.name as state_name',
                'countries.name as country_name'
            )
            ->join('states', 'cities.state_id', '=', 'states.id')
            ->join('countries', 'cities.country_id', '=', 'countries.id');

        // 🔹 Search
        if ($req->filled('search')) {
            $query->where(function ($q) use ($req) {
                $q->where('cities.name', 'like', '%' . $req->search . '%')
                ->orWhere('states.name', 'like', '%' . $req->search . '%')
                ->orWhere('countries.name', 'like', '%' . $req->search . '%');
            });
        }

        // 🔹 Filters
        if ($req->filled('status')) {
            $query->where('cities.status', $req->status);
        }

        if ($req->filled('country_id')) {
            $query->where('cities.country_id', $req->country_id);
        }

        if ($req->filled('state_id')) {
            $query->where('cities.state_id', $req->state_id);
        }

        if($req->sort == 'country_name'){
            $sort = 'countries.name';
        } else if($req->sort == 'state_name'){
            $sort = 'states.name';
        } else {
            $sort  = 'cities.'.$req->sort ?? 'cities.name';
        }

        $cities = $query->orderBy($sort, $dir)->paginate($limit)->appends($req->all()); // ✅ pagination links fix

        return response()->json($cities);
    }

    public function storeCity(Request $request)
    {
        $user = $request->user();

        // 🔹 Connection set
        setTenantConnection($user);

        /*
        |-----------------------------------------
        | VALIDATION
        |-----------------------------------------
        */
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'state_id'   => 'required|exists:states,id',
            'country_id' => 'required|exists:countries,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        /*
        |-----------------------------------------
        | DUPLICATE CHECK
        |-----------------------------------------
        */
        $exists = Cities::where('name', $request->name)
            ->where('state_id', $request->state_id)
            ->where('country_id', $request->country_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'City already exists'
            ], 409);
        }

        /*
        |-----------------------------------------
        | CREATE CITY
        |-----------------------------------------
        */
        $city = Cities::create([
            'name'       => $request->name,
            'state_id'   => $request->state_id,
            'country_id' => $request->country_id,
            'status'     => 'active',
        ]);

        /*
        |-----------------------------------------
        | CACHE CLEAR (IMPORTANT)
        |-----------------------------------------
        */
        Cache::tags(['locations'])->flush();

        return response()->json([
            'status'  => true,
            'message' => 'City created successfully',
            'data'    => $city
        ], 201);
    }

    public function editCity(Request $request)
    {
        $user = $request->user();

        setTenantConnection($user);

        $city = Cities::with(['state:id,name', 'country:id,name'])->find($request->id);

        if (!$city) {
            return response()->json([
                'status' => false,
                'message' => 'City not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $city
        ]);
    }

    public function updateCity(Request $request)
    {
        $user = $request->user();

        setTenantConnection($user);

        /*
        |-----------------------------------------
        | VALIDATION
        |-----------------------------------------
        */
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'state_id'   => 'required|exists:states,id',
            'country_id' => 'required|exists:countries,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        $city = Cities::find($request->id);

        if (!$city) {
            return response()->json([
                'status' => false,
                'message' => 'City not found'
            ], 404);
        }

        /*
        |-----------------------------------------
        | DUPLICATE CHECK (ignore current id)
        |-----------------------------------------
        */
        $exists = Cities::where('name', $request->name)
            ->where('state_id', $request->state_id)
            ->where('country_id', $request->country_id)
            ->where('id', '!=', $request->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'City already exists'
            ], 409);
        }

        /*
        |-----------------------------------------
        | UPDATE
        |-----------------------------------------
        */
        $city->update([
            'name'       => $request->name,
            'state_id'   => $request->state_id,
            'country_id' => $request->country_id,
          	'status' => $request->status,
        ]);

        /*
        |-----------------------------------------
        | CACHE CLEAR
        |-----------------------------------------
        */
        Cache::tags(['locations'])->flush();

        return response()->json([
            'status'  => true,
            'message' => 'City updated successfully',
            'data'    => $city
        ]);
    }

    public function deleteCity(Request $request)
    {

        $city = Cities::find($request->id);

        if (!$city) {
            return response()->json([
                'status' => false,
                'message' => 'City not found'
            ], 404);
        }

        /*
        |-----------------------------------------
        | OPTIONAL:
        |-----------------------------------------
        */
        // Example:
        // if ($city->properties()->count() > 0) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'City is in use, cannot delete'
        //     ], 400);
        // }

        $city->delete();

        return response()->json([
            'status'  => true,
            'message' => 'City deleted successfully'
        ]);
    }

    public function updateStatus(Request $req)
    {
        $req->validate([
            'type'   => 'required|in:country,state,city',
            'id'     => 'required|integer',
            'status' => 'required|in:active,inactive'
        ]);

        switch ($req->type) {
            case 'country':
                $model = Countries::find($req->id);

                if (!$model) {
                    return response()->json(['message' => 'Country not found'], 404);
                }

                States::where('country_id', $model->id)->update(['status' => $req->status]);

                Cities::where('country_id', $model->id)->update(['status' => $req->status]);

                break;

            case 'state':
                $model = States::find($req->id);

                if (!$model) {
                    return response()->json(['message' => 'State not found'], 404);
                }

                Cities::where('state_id', $model->id)->update(['status' => $req->status]);

                break;

            case 'city':
                $model = Cities::find($req->id);

                if (!$model) {
                    return response()->json(['message' => 'City not found'], 404);
                }

                break;

            default:
                return response()->json(['message' => 'Invalid type'], 400);
        }

        $model->status = $req->status;
        $model->save();

        return response()->json([
            'message' => ucfirst($req->type) . ' status updated successfully'
        ]);
    }
}