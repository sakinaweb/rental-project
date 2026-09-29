<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Http\Resources\CountryResource;
use App\Models\City;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GeographyController extends Controller
{
    public function countries(): AnonymousResourceCollection
    {
        return CountryResource::collection(Country::active()->orderBy('name')->paginate());
    }

    public function cities(Request $request): AnonymousResourceCollection
    {
        $request->validate(['country_id' => ['nullable', 'integer']]);

        $cities = City::query()
            ->whereHas('country', fn ($q) => $q->where('is_active', true))
            ->when($request->integer('country_id'), fn ($q, $id) => $q->where('country_id', $id))
            ->orderBy('name')
            ->paginate();

        return CityResource::collection($cities);
    }
}
