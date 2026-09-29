<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VehicleController extends Controller
{
    private const RELATIONS = ['vehicleModel.brand', 'vehicleModel.category', 'branch.location'];

    public function index(): AnonymousResourceCollection
    {
        return VehicleResource::collection(Vehicle::with(self::RELATIONS)->latest('id')->paginate());
    }

    public function store(VehicleRequest $request): VehicleResource
    {
        return new VehicleResource(Vehicle::create($request->validated())->load(self::RELATIONS));
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        return new VehicleResource($vehicle->load(self::RELATIONS));
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        $vehicle->update($request->validated());

        return new VehicleResource($vehicle->load(self::RELATIONS));
    }

    public function destroy(Vehicle $vehicle): Response
    {
        $vehicle->delete();

        return response()->noContent();
    }
}
