<?php

namespace Modules\RealEstate\Http\Controllers;


use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Modules\RealEstate\Models\Features,Modules\RealEstate\Models\Properties,Modules\RealEstate\Models\PropertiesImages,Modules\RealEstate\Models\PropertyFeatures,Modules\UserManagement\App\Models\User;
use Modules\RealEstate\Services\ImageService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class PropertiesApiController extends Controller
{

    #[OA\Get(
            path: '/api/properties',
            tags: ['Properties'],
            summary: 'Get properties',
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Success'
                )
            ]
        )]
    public function index(Request $request)
    {
        $user = User::find($request->id);
        setTenantConnection($user);

        $limit = $request->limit ?? 10;

        $allowedSorts = [
            'id',
            'title',
            'added_on',
            'status',
            'moderation_status',
            'total_bedroom',
            'total_bathroom',
            'area_size'
        ];

        $sort = in_array($request->sort, $allowedSorts)
            ? $request->sort
            : 'created_at';

        $dir = strtolower($request->dir) === 'asc' ? 'asc' : 'desc';

        $query = Properties::select(
            'id',
            'title',
            'price',
            'added_on',
            'location',
            'created_by',
            'currency',
            'status',
            'moderation_status',
            'total_bedroom',
            'total_bathroom',
            'area_size'
        )->with('images:properties_id,image_path');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                ->orWhere('location', 'like', '%' . $request->search . '%')
                ->orWhere('price', 'like', '%' . $request->search . '%')
                ->orWhere('total_bedroom', 'like', '%' . $request->search . '%')
                ->orWhere('total_bedroom', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('moderation_status') && !empty($request->moderation_status)) {
            $query->where('moderation_status', $request->moderation_status);
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

        $properties = $query->orderBy($sort, $dir)->paginate($limit);

        return response()->json([
            'success' => true,
            'approved_properties_count' => Properties::where('moderation_status', 'approved')->count(),
            'rejected_properties_count' => Properties::where('moderation_status', 'rejected')->count(),
            'pending_properties_count' => Properties::where('moderation_status', 'pending')->count(),
            'data' => $properties,
        ]);
    }

    public function store(Request $request, ImageService $imageService)
    {
        $user = $request->user();
        setTenantConnection($user);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'permalink' => 'nullable|string|unique:re_properties,permalink',
            'purpose' => 'required|in:sale,rent',
            'price' => 'nullable|numeric',
            'type' => 'nullable|string',
            'completion_status' => 'nullable|string',
            'furnishing_status' => 'nullable|string',
            'reference_no' => 'nullable|string',
            'trucheck_on' => 'nullable|date',
            'added_on' => 'nullable|date',
            'neighborhood' => 'nullable|string',
            'area_size' => 'nullable|numeric',
            'total_bedroom' => 'nullable|string',
            'total_bathroom' => 'nullable|string',
            'balcony_size' => 'nullable|numeric',
            'usage' => 'nullable|string',
            'ownership' => 'nullable|string',
            'parking_availability' => 'nullable|boolean',
            'description' => 'nullable|string',
            'project_name' => 'nullable|string',
            'developer' => 'nullable|string',
            'project_status' => 'nullable|string',
            'last_inspected' => 'nullable|date',
            'handover_year' => 'nullable|string',
            'handover_quarter' => 'nullable|string',
            'building_name' => 'nullable|string',
            'parking_spaces' => 'nullable|string',
            'building_floors' => 'nullable|integer',
            'building_area' => 'nullable|integer',
            'swimming_pools' => 'nullable|integer',
            'elevators' => 'nullable|integer',
            'permit_number' => 'nullable|string',
            'zone_name' => 'nullable|string',
            'registered_agency' => 'nullable|string',
            'rera_orn' => 'nullable|string',
            'agent_brn' => 'nullable|string',
            'location' => 'nullable|string',
            'currency' => 'nullable|string',
            'status' => 'nullable|in:published,unplished',
            'moderation_status' => 'nullable|in:pending,approved,rejected',
            'reject_reason' => 'nullable|string',

            'images' => 'nullable|array',
            'images.*' => 'string',
        ]);

        DB::beginTransaction();

        try {
            // Add system-controlled fields
            $validated['created_by'] = $user->id;
            $validated['user_type'] = $user->user_type;
            $validated['tenant_id'] = $user->tenancy_id;

            $property = Properties::create($validated);
            
            $property->features()->sync($request->features ?? []);

            // Move temp images and save
            if (!empty($validated['images'])) {

                $folder = "storage/uploads/tenant_" . $user->tenancy_id . "/properties/images";

                $images = collect($validated['images'])
                    ->map(function ($tempImage) use ($imageService, $folder) {
                        $path = $imageService->moveFromTemp($tempImage, $folder);

                        return $path ? ['image_path' => $path] : null;
                    })
                    ->filter()
                    ->values()
                    ->all();

                if ($images) {
                    $property->images()->createMany($images);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property created successfully.',
                'data' => $property,
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadTempImage(Request $request, ImageService $imageService)
    {
        $user = $request->user();
        setTenantConnection($user);

        // tenancy()->initialize($user->tenant_id);

        $request->validate([
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        try {
            // If type is property, require at least one of `image` or `images`
            if ($request->type === 'property' && !$request->hasFile('images')) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least one image is required for property (single `image` or multiple `images`).'
                ], 422);
            }
            /*
            |--------------------------------------------------------------------------
            | ✅ Temp Folder By Type
            |--------------------------------------------------------------------------
            */
            $uploadPath = 'uploads/tenant_' . $user->tenancy_id . '/temp_properties';

            /*
            |--------------------------------------------------------------------------
            | ✅ Create Folder If Not Exists
            |--------------------------------------------------------------------------
            */
            Storage::disk('public')->makeDirectory($uploadPath);

            /*
            |--------------------------------------------------------------------------
            | ✅ Upload Image(s)
            |--------------------------------------------------------------------------
            */
            $uploaded = [];

            // Multiple files (property)
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $uploaded[] = $imageService->uploadTemp($file, $uploadPath);
                }
            }

            // Normalize response: include both `temp_path` for single and `temp_paths` for multiple
            $responseData = [
                'temp_paths' => $uploaded,
            ];

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, ImageService $imageService, $id)
    {
        $user = $request->user();
        setTenantConnection($user);

        $property = Properties::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'permalink' => [
                'nullable',
                'string',
                Rule::unique('re_properties', 'permalink')->ignore($property->id),
            ],
            'purpose' => 'required|in:sale,rent',
            'price' => 'nullable|numeric',
            'type' => 'nullable|string',
            'completion_status' => 'nullable|string',
            'furnishing_status' => 'nullable|string',
            'reference_no' => 'nullable|string',
            'trucheck_on' => 'nullable|date',
            'added_on' => 'nullable|date',
            'neighborhood' => 'nullable|string',
            'area_size' => 'nullable|numeric',
            'total_bedroom' => 'nullable|string',
            'total_bathroom' => 'nullable|string',
            'balcony_size' => 'nullable|numeric',
            'usage' => 'nullable|string',
            'ownership' => 'nullable|string',
            'parking_availability' => 'nullable|boolean',
            'description' => 'nullable|string',
            'project_name' => 'nullable|string',
            'developer' => 'nullable|string',
            'project_status' => 'nullable|string',
            'last_inspected' => 'nullable|date',
            'handover_year' => 'nullable|string',
            'handover_quarter' => 'nullable|string',
            'building_name' => 'nullable|string',
            'parking_spaces' => 'nullable|string',
            'building_floors' => 'nullable|integer',
            'building_area' => 'nullable|integer',
            'swimming_pools' => 'nullable|integer',
            'elevators' => 'nullable|integer',
            'permit_number' => 'nullable|string',
            'zone_name' => 'nullable|string',
            'registered_agency' => 'nullable|string',
            'rera_orn' => 'nullable|string',
            'agent_brn' => 'nullable|string',
            'location' => 'nullable|string',
            'currency' => 'nullable|string',
            'status' => 'nullable|in:published,unplished',
            'moderation_status' => 'nullable|in:pending,approved,rejected',
            'reject_reason' => 'nullable|string',

            'features' => 'nullable|array',
            'images' => 'nullable|array',
            'images.*' => 'string',
        ]);

        DB::beginTransaction();

        try {

            // Update property
            $property->update($validated);

            // Sync features (only if features() relationship exists)
            if ($request->has('features') && method_exists($property, 'features')) {
                $property->features()->sync($request->features ?? []);
            }

            // Add new images
            if (!empty($validated['images'])) {

                $folder = "storage/uploads/tenant_{$user->tenancy_id}/properties/images";

                $images = [];

                foreach ($validated['images'] as $tempImage) {

                    $path = $imageService->moveFromTemp($tempImage, $folder);

                    if ($path) {
                        $images[] = [
                            'image_path' => $path,
                        ];
                    }
                }

                if (!empty($images)) {
                    $property->images()->createMany($images);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property updated successfully.',
                'data' => $property->load('images'),
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        if($user->user_type == 'super_admin'){
            $AuthUSer = User::find($request->agency_id);
            setTenantConnection($AuthUSer);
        }else{
            setTenantConnection($user);
        }

        $property = Properties::with([
            'features:id,name',
            'images:id,properties_id,image_path',
        ])->where('id', $id)->first();

        if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found'
            ], 404);
        }

        return response()->json($property);
    }

    public function approveProperties(Request $request){

        $request->validate([
            'agency_id' => 'required',
            'properties_id' => 'required',
            'moderation_status' => 'required|in:approved,rejected',
            'reject_reason' => 'required_if:moderation_status,rejected',
        ]);

        $user = User::find($request->agency_id);
        setTenantConnection($user);

        $properties = Properties::find($request->properties_id);

        if (!$properties) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found'
            ], 404);
        }

        $properties->moderation_status = $request->moderation_status;

        if($request->moderation_status == 'rejected'){
            $properties->reject_reason = $request->reject_reason;
        }
        $properties->save();

         return response()->json([
            'success' => true,
            'message' => 'Property '.ucfirst($request->moderation_status).' successfully.'
        ], 200);
    }
}