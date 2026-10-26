# API reference

The full, machine-readable contract is **[`openapi.json`](openapi.json)** (OpenAPI 3.1). Open it in Swagger UI / Swagger Editor, or import it into Postman (*Import → File*) to get a ready collection. `backend/tests/Feature/OpenApiDocTest.php` fails if a route is added, removed or renamed without updating the document.

## Conventions

- Base URL: `/api/v1`. From the Android emulator against the local Docker stack: `http://10.0.2.2:8000/api/v1`.
- Send `Accept: application/json`.
- Auth: Sanctum bearer tokens from `POST /auth/register`, `/auth/login` or `/auth/otp/verify` → `Authorization: Bearer <token>`.
- Language: messages follow the signed-in user's `locale`; otherwise `Accept-Language` (en, ar, hi, es, de).
- Money: every amount is an integer in minor units of the accompanying `currency_code` (₹1,500.00 = `150000` INR).
- Lists are paginated: `data` + `links` + `meta` (`current_page`, `last_page`, `total`, …); pass `?page=N`.
- Errors: `401` no/invalid token, `403` not your record or role, `404`, `422` validation (`{message, errors}`) or a refused business rule (`{message}`), `429` rate limited.

## Customer flow (website and app)

1. `GET /countries`, `/cities?country_id=`, `/vehicle-categories`, `/addons`
2. `GET /vehicles?city_id=&category=&type=&from=&to=…` → `GET /vehicles/{id}` → `GET /vehicles/{id}/availability?from=&to=` (`model.image_url` is the model's photo, or null)
3. `POST /bookings/quote` (no account needed) → price breakdown + deposit
4. Sign in: `POST /auth/login`, or `POST /auth/otp/request` + `/auth/otp/verify`, or `POST /auth/register`. Forgot password: `POST /auth/password/forgot` emails a link to the website reset page (`POST /auth/password/reset` with its token sets the new password and revokes every token of the account)
5. `POST /bookings` → draft that holds the vehicle for 15 minutes
6. `GET /payments/gateways?currency={booking currency}` → `POST /payments/intent` with `wallet` (paid at once) or a gateway from that list (open its checkout with `client`; Stripe returns `client_secret` + `publishable_key`, Razorpay `order_id` + `key_id`)
7. Poll `GET /bookings/{id}` until `confirmed` (set by the webhook), or `cancelled` after the 30-minute payment timeout
8. `GET /bookings`, `POST /bookings/{id}/cancel|extend`, `GET /bookings/{id}/invoice`, `GET /wallet`, `POST /wallet/topup`, `PATCH /me`
9. Push: `POST /devices` after sign-in with the FCM token; `DELETE /devices/{token}` on sign-out

## Staff flow (Phase 10, `staff_app/`)

Staff role only; the admin assigns each staff user to a branch, and every call is limited to that branch.

1. `POST /auth/login` → check `user.role === "staff"`
2. `GET /staff/bookings?date=` → `pickups` (confirmed, due by that day) and `returns` (every rental out)
3. `GET /staff/bookings/{id}` → customer contact, vehicle, checklists so far, damages
4. `POST /staff/bookings/{id}/pickup` (multipart: `photos[]` ≥ 4, `odometer`, `fuel_level`, `notes`) → `picked_up`
5. `POST /staff/bookings/{id}/return` (same + `damages[i][description|cost|photos][]`) → `returned`; damages are `reported` for the admin

## Partner flow (Phase 9)

Only where the partner feature is on (Settings and the user's country pack); otherwise every `/partner/*` call is `403`.

1. `POST /partner/apply` with `payout_details` (bank details) → `pending` until the admin approves
2. `GET /partner/dashboard` → status, vehicles, balances (`balance`, `available`), recent payout requests
3. `POST /partner/vehicles` → `pending`; listed for customers once the admin approves it and sets the commission
4. `POST /partner/vehicles/{id}/blockout` / `DELETE /partner/vehicles/{id}/blockouts/{blockout}` → dates customers can't book
5. Earnings arrive when a booking of the vehicle settles: `GET /partner/earnings` (rental net of tax minus the platform commission)
6. `POST /partner/payout-requests` up to `available` → the admin pays it by bank transfer in a batch (`POST /admin/payout-batches`)

## Investor flow (Phase 11)

Only where the investor feature is on (Settings and the user's country pack; off in every pack by default); otherwise every investor call is `403`.

1. `POST /investor/apply` with `payout_details` (bank details) → `kyc_status: pending` until the admin approves KYC
2. `GET /investor/disclosure` → `POST /investor/disclosure` with that `version` (asked again when the version changes)
3. `GET /investment-plans` (the user's country) → `POST /investments` with `plan_id` and `amount` (at least `min_amount`) → `pending`
4. The investor transfers the amount with reference `INV-{id}`; the admin marks it received and places it on company vehicles → `active`, the term starts
5. Each settled booking of those vehicles that starts within the term pays the plan's `revenue_share_pct` of its rental net of tax, split between the vehicle's investors by allocated amount, out of the platform's share
6. `GET /investor/dashboard`, `GET /investor/statements/{YYYY-MM}`, `POST /investor/payout-requests` (same batches as partners)
7. At the end of the term the admin pays the principal back by bank transfer and closes the investment
