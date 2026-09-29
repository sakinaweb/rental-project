<?php

namespace Tests\Feature;

use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeographyTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_countries_lists_active_only_and_is_paginated(): void
    {
        Country::where('iso_code', 'DE')->update(['is_active' => false]);

        $this->getJson('/api/v1/countries')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissing(['iso_code' => 'DE'])
            ->assertJsonStructure(['data' => [['id', 'iso_code', 'name', 'currency_code']], 'links', 'meta']);
    }

    public function test_cities_filter_by_country_and_hide_inactive_countries(): void
    {
        $in = Country::where('iso_code', 'IN')->first();

        $this->getJson('/api/v1/cities?country_id='.$in->id)
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.country_id', $in->id)
            ->assertJsonStructure(['meta' => ['current_page', 'total']]);

        $in->update(['is_active' => false]);

        $this->getJson('/api/v1/cities?country_id='.$in->id)->assertOk()->assertJsonCount(0, 'data');
    }
}
