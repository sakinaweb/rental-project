<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PricingRuleRequest;
use App\Http\Resources\PricingRuleResource;
use App\Models\PricingRule;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PricingRuleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PricingRuleResource::collection(PricingRule::latest('id')->paginate());
    }

    public function store(PricingRuleRequest $request): PricingRuleResource
    {
        return new PricingRuleResource(PricingRule::create($request->validated())->refresh());
    }
}
