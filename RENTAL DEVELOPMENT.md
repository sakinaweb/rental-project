# Development Plan — Bike & Car Rental Platform (Laravel + Flutter)

Technical companion to the business blueprint. This file is the implementation spec: architecture, stack, folder layout, database schema, API contract, ledger logic, and build order. Treat version numbers as "latest stable at build time" and confirm before locking `composer.json` / `pubspec.yaml`.

---

## 1. Tech stack

**Backend**
- PHP 8.3+, Laravel 11.x (or current LTS at build time)
- MySQL 8 (primary datastore) — avoid MySQL-specific features that block Postgres later if you want DB portability as a selling point
- Redis — cache, queues, rate limiting
- Laravel Sanctum — API auth (mobile + web SPA if any)
- Laravel Horizon — queue monitoring (optional, nice for admin)
- Spatie packages: `laravel-permission` (roles), `laravel-medialibrary` (documents/photos), `laravel-translatable` or custom translation tables
- Filament or Livewire+Blade for the admin panel (Filament is faster to build a data-heavy admin with)
- Laravel Excel — CSV/report export
- DomPDF or Snappy — invoices/statements as PDF

**Mobile**
- Flutter 3.x (latest stable), Dart null-safety
- State management: Riverpod (recommended — testable, less boilerplate than Bloc for CRUD-heavy screens)
- `dio` for HTTP, `retrofit` (optional) for typed API client
- `firebase_messaging` — push
- `google_maps_flutter` — maps
- `intl` — locale, currency, date formatting
- `flutter_localizations` + ARB files — translation, with RTL tested on Arabic

**Infra**
- Queue worker via cron (`schedule:run` every minute) — must work on shared hosting, not just Docker
- Object storage: local disk driver by default, S3 driver optional
- CI: GitHub Actions (lint, static analysis, tests) even though buyers won't see this — it protects your own build quality

---

## 2. Repository structure

```
rental-platform/
├── backend/                    # Laravel app
│   ├── app/
│   │   ├── Models/
│   │   ├── Http/Controllers/Api/V1/
│   │   ├── Http/Controllers/Admin/
│   │   ├── Http/Requests/
│   │   ├── Http/Resources/
│   │   ├── Services/           # PricingService, LedgerService, AvailabilityService...
│   │   ├── Actions/            # single-purpose action classes
│   │   ├── Policies/
│   │   ├── Jobs/
│   │   └── Support/CountryPack/
│   ├── database/
│   │   ├── migrations/
│   │   ├── seeders/            # demo data for CodeCanyon preview
│   │   └── factories/
│   ├── resources/lang/{en,ar,hi,es,de}/
│   ├── config/countrypacks/{in,ae,us,de}.php
│   ├── routes/api_v1.php
│   ├── tests/Feature/ Unit/
│   └── installer/              # web setup wizard
├── customer_app/                # Flutter
│   ├── lib/
│   │   ├── core/ (api client, theme, l10n, router)
│   │   ├── features/
│   │   │   ├── auth/ search/ booking/ wallet/
│   │   │   ├── partner/        # owner-partner dashboard
│   │   │   └── investor/       # investor dashboard (feature-flagged)
│   │   └── main.dart
│   └── pubspec.yaml
├── staff_app/                   # Flutter, handover/return
│   └── lib/...
└── docs/
    ├── installation.md
    ├── api-reference.md
    ├── country-pack-guide.md
    └── changelog.md
```

---

## 3. Database schema (core tables)

Only key columns shown; add timestamps, soft deletes, and indexes as normal.

```sql
-- Identity & access
users(id, name, email, phone, password, role_id, country_id, locale, status, created_at)
roles(id, name)                          -- admin, staff, customer, partner, investor
kyc_documents(id, user_id, type, file_path, status, reviewed_by, reviewed_at)

-- Geography & country config
countries(id, iso_code, name, currency_code, is_active)
country_packs(id, country_id, config_json)   -- age limits, licence rules, tax rules ref, feature flags
cities(id, country_id, name)
locations(id, city_id, name, lat, lng, type)  -- branch / delivery-zone
branches(id, location_id, name, contact)

-- Fleet
vehicle_categories(id, name, type)        -- bike, car
brands(id, name)
vehicle_models(id, brand_id, name, category_id, seats, transmission, fuel_type)
vehicles(id, model_id, branch_id, owner_type, owner_id, plate_no, status, odometer)
   -- owner_type: 'company' | 'partner'   owner_id: nullable, references partners.id
vehicle_documents(id, vehicle_id, type, file_path, expiry_date, status)

-- Pricing & tax
pricing_rules(id, scope_type, scope_id, unit, base_rate, weekend_multiplier, monthly_discount_pct)
tax_rules(id, country_id, name, rate_pct, applies_to, inclusive)

-- Bookings
bookings(id, user_id, vehicle_id, pickup_location_id, dropoff_location_id,
         start_at, end_at, status, currency_code, exchange_rate,
         subtotal, tax_total, discount_total, deposit_amount, grand_total)
booking_addons(id, booking_id, addon_id, qty, price)
addons(id, name, price, type)
coupons(id, code, type, value, valid_from, valid_to, usage_limit)
inspections(id, booking_id, stage, photos_json, odometer, fuel_level, notes, inspected_by)
damages(id, booking_id, description, cost, evidence_json, status)

-- Payments & wallet
payments(id, booking_id, gateway, gateway_ref, amount, currency_code, status)
wallet_transactions(id, user_id, type, amount, balance_after, reference_type, reference_id)
deposits(id, booking_id, amount, status, refunded_amount, refunded_at)

-- Partners (v1.1)
partners(id, user_id, payout_details_json, status)
partner_vehicles(id, partner_id, vehicle_id, commission_pct, status)
revenue_share_agreements(id, partner_id, vehicle_id, terms_json, signed_at, document_path)

-- Investors (v1.2, feature-flagged)
investors(id, user_id, kyc_status, risk_ack_at)
investment_plans(id, name, vehicle_category_id, min_amount, term_months, revenue_share_pct, status)
investments(id, investor_id, plan_id, amount, status, started_at, ends_at)
investment_vehicle_allocations(id, investment_id, vehicle_id, allocated_amount)
compliance_disclosures(id, investor_id, version, acknowledged_at)

-- Ledger (build in v1.0, used from v1.1 onward)
ledger_accounts(id, owner_type, owner_id, currency_code, balance)
   -- owner_type: 'platform' | 'partner' | 'investor'
earnings_ledger(id, account_id, booking_id, entry_type, amount, running_balance, created_at)
   -- entry_type: 'credit_share' | 'debit_deduction' | 'payout' | 'reversal'
payout_requests(id, account_id, amount, status, requested_at, processed_at)
payout_batches(id, processed_by, total_amount, status, processed_at)
statements(id, account_id, period_start, period_end, file_path)

-- Support & misc
tickets(id, user_id, subject, status)
ticket_messages(id, ticket_id, sender_id, message)
notifications(id, user_id, type, payload_json, read_at)
reviews(id, booking_id, rating, comment)
settings(id, key, value_json)
translations(id, locale, group, key, value)
audit_logs(id, actor_id, action, subject_type, subject_id, meta_json)
```

---

## 4. API contract (v1) — representative endpoints

Base: `/api/v1`. Auth via Sanctum bearer token. All list endpoints paginated; all money fields returned as integer minor units + `currency_code`.

```
POST   /auth/register
POST   /auth/login
POST   /auth/otp/request
POST   /auth/otp/verify
GET    /me
POST   /kyc/documents
GET    /countries
GET    /cities?country_id=

GET    /vehicles?city_id=&category=&from=&to=&filters...
GET    /vehicles/{id}
GET    /vehicles/{id}/availability?from=&to=

POST   /bookings                # create draft booking (locks availability)
GET    /bookings/{id}
POST   /bookings/{id}/confirm   # after payment
POST   /bookings/{id}/cancel
POST   /bookings/{id}/extend
GET    /bookings                # my bookings

POST   /payments/intent
POST   /payments/webhook/{gateway}   # gateway-specific, signature verified

GET    /wallet
POST   /wallet/topup

-- Partner (v1.1)
POST   /partner/apply
POST   /partner/vehicles
GET    /partner/dashboard
GET    /partner/earnings
POST   /partner/payout-requests
POST   /partner/vehicles/{id}/blockout

-- Investor (v1.2, behind feature flag)
GET    /investment-plans
POST   /investments
GET    /investor/dashboard
GET    /investor/statements/{id}

-- Admin (separate guard/prefix, or Filament handles server-rendered)
GET/POST/PUT/DELETE /admin/vehicles
GET/POST            /admin/pricing-rules
GET                 /admin/bookings
POST                /admin/bookings/{id}/assign
GET                 /admin/reports/revenue
POST                /admin/payout-batches
```

Document this fully with OpenAPI/Postman before Flutter work starts — the mobile team (even if that's future-you) should never guess a response shape.

---

## 5. Core service classes (backend)

- `AvailabilityService::isAvailable(vehicleId, from, to)` — row-locked check against `bookings` + buffer config.
- `PricingService::quote(vehicle, from, to, addons, coupon)` — returns full price breakdown before booking creation.
- `TaxService::apply(amount, countryId)` — inclusive/exclusive per country pack.
- `LedgerService::postBookingCompletion(booking)` — writes the full revenue waterfall as ledger entries (platform, partner/investor share) in one DB transaction.
- `PayoutService::requestPayout(account, amount)` / `::processBatch(batch)`.
- `CountryPackService::rulesFor(countryId)` — single source of truth for age limits, KYC doc types, feature toggles (partner/investor enabled?).
- `NotificationService` — wraps FCM + SMS + email drivers behind one interface.

Write these as plain PHP services with interfaces where a buyer might swap implementation (payment gateway, SMS provider, maps provider). This "driver" pattern is what makes the script feel professional in review.

---

## 6. Booking lifecycle (state machine)

```
draft → pending_payment → confirmed → picked_up → returned → completed
                                   ↘ cancelled
                                   ↘ no_show
completed → (damage hold window) → settled (ledger posted, deposit released)
```

Implement as an explicit state machine (e.g. `spatie/laravel-model-states` or a simple enum + guard methods) rather than free-text status strings — prevents illegal transitions and makes the admin UI trustworthy.

---

## 7. Build order (maps to the roadmap in the business blueprint)

1. **Foundation:** auth, roles, countries/country-packs skeleton, translations setup, migrations for all core tables (including ledger tables even though unused yet).
2. **Fleet & pricing:** vehicles, categories, pricing rules, tax rules, availability service.
3. **Booking engine:** booking creation, state machine, pricing quote endpoint, deposit handling.
4. **Payments:** gateway driver interface + Stripe/Razorpay first, webhooks, wallet.
5. **Admin panel (Filament):** CRUD for everything above, dashboard, reports.
6. **Customer website:** search, booking flow, account area — reuse the API.
7. **Customer Flutter app:** mirror the website flow; get one country pack fully working end to end before adding more.
8. **Country packs 2–4:** add India/UAE/US/EU configs, test currency/tax/RTL per pack.
9. **Partner module:** partner tables, onboarding, dashboard, `LedgerService` wired to real payouts.
10. **Staff app:** handover/return checklist screens.
11. **Investor module:** behind a global feature flag, off until explicitly tested per country pack.
12. **Hardening:** automated tests for pricing/ledger/availability (these three are where bugs cost real money), security pass, load test the booking-creation race condition specifically.
13. **CodeCanyon packaging:** installer wizard, seeders for demo data, docs, video, screenshots.

---

## 8. Testing priorities

Not everything needs equal coverage — focus automated tests where money or double-booking risk lives:

- Availability service: concurrent booking attempts on the same vehicle/time window (this is the #1 source of "double booked" support tickets).
- Pricing service: every discount/tax/multiplier combination.
- Ledger service: revenue waterfall math, rounding, reversals — assert debits+credits always balance.
- Payment webhook handlers: idempotency (gateways retry webhooks).
- Country pack switching: confirm feature toggles (partner/investor) actually gate the UI and API, not just hide a button.

---

## 9. Environment & config checklist

- `.env.example` fully documented (every key commented).
- Feature flags in `settings` table, editable from admin, not just `.env` (buyers shouldn't need SSH access to toggle investor mode).
- Queue driver: Redis preferred, database driver as a shared-hosting fallback.
- File storage: local by default, S3 config optional and clearly documented.
- Currency/exchange rate source: pluggable (manual entry vs API like exchangerate.host) — don't hard-depend on one paid API.

---

## 10. Definition of done (per module, before moving on)

- Feature works through the API, the admin panel, and at least one client (web or app).
- Validation + policy checks in place (no "trust the frontend").
- Money-related code has a passing automated test.
- Translated strings added to `en` (other locales can lag).
- Entry added to `docs/changelog.md`.
