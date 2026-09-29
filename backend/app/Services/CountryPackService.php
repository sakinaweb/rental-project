<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Setting;

/**
 * Single source of truth for per-country rules (MD §5).
 * Rules = config/countrypacks/{iso}.php, overridden by country_packs.config_json.
 * A feature is on only if both the pack and the global settings flag (features.{name}) enable it.
 */
class CountryPackService
{
    /** @return array<string, mixed> */
    public function rulesFor(int $countryId): array
    {
        $country = Country::with('pack')->findOrFail($countryId);

        $rules = array_replace_recursive(
            config('countrypacks.'.strtolower($country->iso_code), []),
            $country->pack->config_json ?? [],
        );

        foreach ($rules['features'] ?? [] as $feature => $enabled) {
            $rules['features'][$feature] = $enabled && Setting::valueOf("features.$feature", false);
        }

        return $rules;
    }
}
