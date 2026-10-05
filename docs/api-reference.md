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
2. `GET /vehicles?city_id=&category=&type=&from=&to=…` → `GET /vehicles/{id}` → `GET /vehicles/{id}/availability?from=&to=`
3. `POST /bookings/quote` (no account needed) → price breakdown + deposit
4. Sign in: `POST /auth/login`, or `POST /auth/otp/request` + `/auth/otp/verify`, or `POST /auth/register`
5. `POST /bookings` → draft that holds the vehicle for 15 minutes
6. `GET /payments/gateways?currency={booking currency}` → `POST /payments/intent` with `wallet` (paid at once) or a gateway from that list (open its checkout with `client`; Stripe returns `client_secret` + `publishable_key`, Razorpay `order_id` + `key_id`)
7. Poll `GET /bookings/{id}` until `confirmed` (set by the webhook), or `cancelled` after the 30-minute payment timeout
8. `GET /bookings`, `POST /bookings/{id}/cancel|extend`, `GET /bookings/{id}/invoice`, `GET /wallet`, `POST /wallet/topup`, `PATCH /me`
9. Push: `POST /devices` after sign-in with the FCM token; `DELETE /devices/{token}` on sign-out
