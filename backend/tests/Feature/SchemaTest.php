<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Phase 1 DoD: migrations exist for every core table in MD §3 (including ledger tables).
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_md_core_tables_exist(): void
    {
        $tables = [
            'users', 'roles', 'kyc_documents',
            'countries', 'country_packs', 'cities', 'locations', 'branches',
            'vehicle_categories', 'brands', 'vehicle_models', 'vehicles', 'vehicle_documents',
            'pricing_rules', 'tax_rules',
            'bookings', 'booking_addons', 'addons', 'coupons', 'inspections', 'damages',
            'payments', 'wallet_transactions', 'deposits',
            'partners', 'partner_vehicles', 'revenue_share_agreements',
            'investors', 'investment_plans', 'investments', 'investment_vehicle_allocations', 'compliance_disclosures',
            'ledger_accounts', 'earnings_ledger', 'payout_requests', 'payout_batches', 'statements',
            'tickets', 'ticket_messages', 'notifications', 'reviews', 'settings', 'translations', 'audit_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing table: $table");
        }
    }
}
