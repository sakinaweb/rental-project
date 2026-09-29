<?php

// India country pack. Keys follow MD §3 country_packs: age limits, licence rules, tax rules ref, feature flags.
// Placeholder values: confirm per market in Phase 8 (MD §7.8). Admin overrides live in country_packs.config_json.
return [
    'min_age' => ['bike' => 18, 'car' => 21],
    'licence' => [
        'min_years_held' => 1,
        'international_permit_accepted' => true,
    ],
    'kyc_document_types' => ['driving_licence', 'aadhaar', 'passport'],
    'tax_rules' => 'GST', // tax_rules.name for this country
    // Refundable deposit per vehicle type, in minor units of the country currency. Held at confirmation.
    'deposit' => ['bike' => 200000, 'car' => 500000],
    'features' => [
        'partner' => true,
        'investor' => false, // off until explicitly tested for this pack (MD §7.11)
    ],
];
