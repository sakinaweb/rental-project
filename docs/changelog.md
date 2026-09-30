# Changelog

## Unreleased

### Phase 4: Payments (MD §7.4)

- Gateway driver interface `PaymentGateway` (MD §5) with Stripe (PaymentIntents) and Razorpay (Orders) drivers on the official REST APIs via Laravel's HTTP client (no SDK packages). A gateway is offered only when its keys and webhook secret are configured (`.env.example` documents them and the webhook URLs).
- `POST /payments/intent` (`booking_id`, `gateway`: stripe | razorpay | wallet): collects what is still due (grand total + deposit − net paid). A draft moves to pending_payment. Gateway: creates a pending payment and returns the client data (Stripe client_secret, Razorpay order_id). Wallet: debits immediately and confirms the booking. Also pays the difference after an extension on a confirmed booking.
- `POST /payments/webhook/{gateway}`: signature verified by the driver (Stripe `Stripe-Signature` with 5-minute tolerance and rotated secrets; Razorpay `X-Razorpay-Signature`), 400 on a bad signature. Idempotent (MD §8): the payment row is locked and an already-succeeded payment is never processed again. Amount and currency must match the payment. Success confirms a fully paid pending_payment booking; a failed payment can still succeed later on the same intent.
- Wallet: `GET /wallet` (balance per currency + paginated transactions), `POST /wallet/topup` (gateway payment, credited by the webhook; currency defaults to the user's country). Append-only `wallet_transactions` with balance_after, serialized per user by a row lock. **Owner decision:** the wallet pays bookings and receives refunds.
- Refunds (**owner decision:** full refund to the wallet, no cancellation fee): cancelling refunds everything paid (rental + deposit); money arriving after cancellation, and overpayment (two intents completed), is refunded automatically; settlement credits the released deposit minus approved damages. "Paid" on a booking is now net of refunds.
- Payment timeout (**owner decision:** 30 minutes, `settings.payment.timeout_minutes`; the MD specifies none): `payments:expire` (every minute) cancels pending_payment bookings not paid in time and marks their open payments expired. Measured from entering pending_payment.
- Schema: `payments.user_id`, `payments.booking_id` nullable (top-ups have no booking), `wallet_transactions.currency_code` (one balance per currency, since refunds arrive in each booking's currency).
- **Testing without real keys (owner decision):** drivers tested with faked HTTP and self-signed webhooks; the live Docker test confirmed a real call reaches Stripe (401 with a dummy key) and drove the webhook, wallet, refund and timeout flows end to end.

### Phase 3: Booking engine (MD §7.3)

- Booking API (Sanctum): `POST /bookings` creates a draft that holds the vehicle, `GET /bookings` (mine, paginated), `GET /bookings/{id}`, `POST /bookings/{id}/confirm|cancel|extend`. Customers act on their own bookings only (BookingPolicy); admins on all. `GET /admin/bookings` (admin).
- Pricing quote endpoint: public `POST /bookings/quote` with the same input as creating a booking; returns the Phase 2 breakdown plus the deposit. **Implementation-chosen path:** MD §7.3 requires a "pricing quote endpoint" but §4 does not list one or give its path.
- State machine (MD §6) as the `BookingStatus` enum with allowed transitions and `Booking::transitionTo()` as the only way to change status: draft → pending_payment → confirmed → picked_up → returned → completed → settled; cancelled from draft, pending_payment or confirmed; no_show from confirmed. Illegal transitions are refused (422). **Approved deviation from the §6 diagram:** the diagram draws `cancelled` from `confirmed` only; allowing it from `draft` and `pending_payment` too (so customers can abandon an unpaid booking) was explicitly approved by the project owner for Phase 3.
- Creation runs in one transaction: vehicle row lock → availability (locking read of bookings) → coupon row lock and usage_limit check → price → insert booking and add-on lines (unit price snapshot). Verified live: 6 rounds of 10 simultaneous customers booking the same vehicle and period each produced exactly one booking.
- Drafts stop holding the vehicle after `settings.booking.draft_ttl_minutes` (default 15) and can then only be cancelled.
- Confirm requires pending_payment and succeeded payments (booking currency) covering grand total + deposit; payments are written by Phase 4.
- Extend (draft, confirmed, picked_up): availability re-checked for the added time excluding the booking itself; the booked period keeps its price and the booking is charged the difference between quotes for the new and old periods at current rates (coupon judged at booking time).
- Deposits: amount per vehicle type from the country pack (`deposit` in `config/countrypacks/{iso}.php`, admin-overridable); held at confirmation; released in full on cancellation; at settlement released minus approved damages. `bookings:settle` (hourly via the scheduler) settles completed bookings after `settings.booking.damage_hold_hours` (default 72); bookings with damage reports under review wait.
- Schema: `bookings.coupon_id`, `bookings.completed_at`.
- Business-rule refusals return 422 with a translated message (en, ar, hi, es, de); Phase 2 pricing errors now translated too.
- `AvailabilityService::isAvailable()` takes an optional booking id to ignore (extensions). `PricingService::quote()` takes an optional time at which to judge coupon validity.

### Fixed

- `TaxService::apply()` now applies only the tax rules the country pack references (`tax_rules` in `config/countrypacks/{iso}.php` or the admin override in `country_packs.config_json`: one `tax_rules.name` or a list), as MD §3/§5 require ("tax rules ref", "per country pack"). Previously every tax rule of the country applied. A pack without a reference applies no tax.

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
