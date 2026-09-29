# Changelog

## Unreleased

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
