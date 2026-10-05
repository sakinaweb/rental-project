# Customer app (Flutter)

The customer app from MD §7.7: it mirrors the website flow — search → vehicle → availability → quote → sign in / register / phone OTP → checkout (wallet, Razorpay or Stripe) → confirmation → my bookings → cancel / extend / invoice, plus wallet top-up and profile. It talks only to the REST API documented in [`../docs/openapi.json`](../docs/openapi.json).

Stack (MD §1): Flutter 3.x, Riverpod, dio, go_router, intl, flutter_localizations + ARB (en, ar, hi, es, de; Arabic right-to-left), firebase_messaging, google_maps_flutter, razorpay_flutter, flutter_stripe.

```
lib/
  core/       api client + models, session, router, theme/ui helpers, push, config
  features/   auth/  search/  booking/  wallet/
  l10n/       app_<locale>.arb (generated from the website's lang/*/site.php)
```

## Run against the local Docker backend

```sh
# backend: docker compose up -d   (from the repo root)
flutter run                                   # Android emulator: API at http://10.0.2.2:8000/api/v1
flutter run --dart-define=API_BASE_URL=https://api.example.com/api/v1
```

## Optional features (off until configured)

| Feature | Build with |
|---|---|
| Push notifications (booking confirmed/cancelled) | `--dart-define=FIREBASE_API_KEY=… --dart-define=FIREBASE_APP_ID=… --dart-define=FIREBASE_SENDER_ID=… --dart-define=FIREBASE_PROJECT_ID=…`, and `FCM_CREDENTIALS` on the backend |
| Branch map on the vehicle page | `--dart-define=MAPS_API_KEY=…` (also written into the Android manifest) |
| Card payments | Razorpay (`RAZORPAY_*`) and/or Stripe (`STRIPE_*`) keys on the backend; checkout and top-up show the gateways `GET /payments/gateways?currency=` lists for that currency's country pack |

Country packs (Phase 8): India pays by Razorpay; UAE, US and Germany by Stripe (native PaymentSheet). The admin can change a pack's `gateways` list.

## Checks

```sh
flutter analyze
flutter test          # real API client against a fake backend: flows, isolation of payment success, languages/RTL
flutter build apk --debug
```

## Translations

Strings are shared with the website. After changing `backend/lang/*/site.php`:

```sh
docker compose exec -T app php -r 'require "vendor/autoload.php"; $o=[]; foreach (["en","ar","hi","es","de"] as $l) { $o[$l]=["site"=>Illuminate\Support\Arr::dot(require "lang/$l/site.php"),"messages"=>require "lang/$l/messages.php"]; } echo json_encode($o, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);' > customer_app/tool/web_strings.json
cd customer_app && node tool/gen_arb.js && flutter gen-l10n
```
