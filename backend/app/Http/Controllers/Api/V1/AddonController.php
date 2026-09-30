<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonController extends Controller
{
    /** GET /addons (Phase 6, D2): what can be added to a quote/booking. Price in minor units of the booking currency. */
    public function index(): AnonymousResourceCollection
    {
        return JsonResource::collection(Addon::orderBy('name')->select(['id', 'name', 'price', 'type'])->paginate());
    }
}
