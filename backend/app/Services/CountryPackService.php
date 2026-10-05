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

        $rules = $this->merge(config('countrypacks.'.strtolower($country->iso_code), []), $country->pack->config_json ?? []);

        foreach ($rules['features'] ?? [] as $feature => $enabled) {
            $rules['features'][$feature] = $enabled && Setting::valueOf("features.$feature", false);
        }

        return $rules;
    }

    /**
     * Admin overrides win. Keyed arrays merge key by key; a list (gateways, KYC document types) is replaced whole,
     * so an override can shorten or empty it.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $base[$key] = is_array($value) && is_array($base[$key] ?? null) && ! array_is_list($base[$key])
                ? $this->merge($base[$key], $value)
                : $value;
        }

        return $base;
    }
}
