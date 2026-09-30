<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleCategoryResource;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\AvailabilityService;
use App\Services\VehicleSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleController extends Controller
{
    public function index(Request $request, VehicleSearch $search): AnonymousResourceCollection
    {
        return VehicleResource::collection($search->query($request->validate(VehicleSearch::rules()))->paginate());
    }

    public function show(int $id): VehicleResource
    {
        return new VehicleResource(Vehicle::listed()->with(VehicleSearch::RELATIONS)->findOrFail($id));
    }

    public function availability(Request $request, int $id, AvailabilityService $availability): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
        ]);
        Vehicle::listed()->findOrFail($id);

        $from = VehicleSearch::toAppTime($data['from']);
        $to = VehicleSearch::toAppTime($data['to']);

        return response()->json(['data' => [
            'vehicle_id' => $id,
            'from' => $from,
            'to' => $to,
            'available' => $availability->isAvailable($id, $from, $to),
        ]]);
    }

    public function categories(): AnonymousResourceCollection
    {
        return VehicleCategoryResource::collection(VehicleCategory::orderBy('name')->paginate());
    }
}
