# Changelog

## Unreleased

### Phase 2: Fleet & pricing (MD §7.2)

- Models for fleet, locations, pricing, tax, bookings, add-ons and coupons on the Phase 1 schema (no new migrations).
- `GET /vehicles`: paginated search of in-service vehicles in active countries. Filters: `city_id`, `category` (id), `type` (bike|car), `brand_id`, `transmission`, `fuel_type`, `seats` (minimum), and `from`/`to` to hide vehicles booked in that period. ISO-8601 offsets are accepted.
- `GET /vehicles/{id}`, `GET /vehicles/{id}/availability?from=&to=`, `GET /vehicle-categories`.
- Admin API (admin role only): `GET/POST/PUT/DELETE /admin/vehicles` (soft delete), `GET/POST /admin/pricing-rules`.
- `AvailabilityService::isAvailable()`: locks the vehicle row (SELECT … FOR UPDATE) and checks bookings in draft, pending_payment, confirmed or picked_up status, widened by a turnaround buffer (`settings.availability.buffer_minutes`, default 60).
- `PricingService::quote()`: most specific pricing rule scope wins (vehicle > model > category > branch > global); each hourly/daily rule in that scope prices the period and the customer gets the cheaper; weekend multiplier per unit starting Sat/Sun; `monthly_discount_pct` from 30 days; per-day and per-booking add-ons; fixed and percent (basis points) coupons within their validity window, capped at the amount; currency from the branch country.
- `TaxService::apply()`: country tax rules matching `all` or the vehicle type; inclusive rules extracted from the amount, exclusive rules added on the net; integer minor-unit rounding.
- Tests for availability (overlap, buffer, statuses, row lock), every pricing discount/tax/multiplier combination (48-case matrix + hand-calculated cases), tax, vehicle search and admin endpoints.

### Phase 1: Foundation (MD §7.1)

- Laravel 13 backend in `backend/` on PHP 8.3, MySQL 8, Redis (Docker dev stack: `docker-compose.yml`).
- Migrations for every core table in MD §3, including partner, investor and ledger tables (schema only for now). Money columns are integer minor units.
- Auth API under `/api/v1` with Sanctum bearer tokens: `POST /auth/register`, `/auth/login`, `/auth/otp/request`, `/auth/otp/verify`, `GET /me`.
- OTP login: 6-digit code, 5-minute expiry, single use, locks after 5 wrong attempts, max 3 sends per phone per 10 minutes, same response for unknown numbers. SMS goes through a swappable `SmsDriver` (log driver for now).
- Roles: `roles` table + `users.role_id` (admin, staff, customer, partner, investor); `role:` route middleware.
- Countries and cities: `GET /countries`, `GET /cities?country_id=` (paginated, active countries only). Seeded IN, AE, US, DE.
- Country packs: `config/countrypacks/{in,ae,us,de}.php` with admin overrides in `country_packs.config_json`; `CountryPackService::rulesFor()`. A feature (partner/investor) is on only when both the pack and the global `settings` flag enable it; both global flags default to off.
- Translations: `lang/{en,ar,hi,es,de}`, locale from the user's profile or `Accept-Language`, admin overrides from the `translations` table.
- Fully commented `.env.example`; GitHub Actions CI (Pint, Larastan, tests on MySQL 8).
