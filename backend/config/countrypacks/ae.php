<?php

// UAE country pack. Keys follow MD §3 country_packs: age limits, licence rules, tax rules ref, feature flags.
// Market defaults (Phase 8, owner decision): confirm with a local advisor before selling. Admin overrides live in
// country_packs.config_json. Tax rates are tax_rules rows (database/seeders/TaxRuleSeeder.php), editable in the admin.
return [
    'min_age' => ['bike' => 18, 'car' => 21],
    'licence' => [
        'min_years_held' => 1,
        'international_permit_accepted' => true,
    ],
    'kyc_document_types' => ['driving_licence', 'emirates_id', 'passport'],
    'tax_rules' => 'VAT', // tax_rules.name for this country
    // Refundable deposit per vehicle type, in minor units of the country currency. Held at confirmation.
    'deposit' => ['bike' => 50000, 'car' => 150000],
    // Card gateways offered for this country (Phase 8), if configured on the server. The wallet is always offered.
    'gateways' => ['stripe'],
    'features' => [
        'partner' => true,
        'investor' => false, // off until explicitly tested for this pack (MD §7.11)
    ],
];
