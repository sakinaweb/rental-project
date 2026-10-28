# Rental Platform: installation and setup guide

What's included: a Laravel 13 backend (website, admin panel, REST API), the customer app (`customer_app/`, Flutter) and the staff app (`staff_app/`, Flutter, for handovers and returns).

## 1. Server requirements

- PHP 8.3 or newer with these extensions: pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, fileinfo, bcmath, intl, gd, zip
- MySQL 8 (an empty database and a user with full rights on it)
- A web server whose document root can point at `backend/public` (Apache with `mod_rewrite`, or Nginx)
- Cron (to run the scheduler every minute)
- Optional: Redis for a faster cache. Without it, files and the database are used, which works on shared hosting.

The installer checks all of these and lists anything that's missing.

## 2. Installing with the web installer (recommended)

1. Upload the `backend` folder to your server.
2. Point your domain's document root at `backend/public`. On shared hosting where you can't change it, put the contents of `backend/public` in `public_html`, and change the two paths in `public_html/index.php` (`__DIR__.'/../vendor/...'` and `__DIR__.'/../bootstrap/app.php'`) to point at the uploaded `backend` folder.
3. Make `backend/storage` and `backend/bootstrap/cache` writable by the web server. `backend/` itself must be writable as well, so the installer can create `.env`.
4. Open `https://your-domain/install` (any page redirects there until installation is done) and fill in:
   - **Site name and address**
   - **Database** host, port, name, user and password
   - **Redis**: tick it only if Redis runs on the server
   - **Administrator** name, email and password
   - **Demo fleet**: a Mumbai branch with 28 vehicles and photos. Untick it for a live site.
5. Click **Install**. The installer writes `.env` (production mode, a new encryption key), creates the tables, adds the reference data (roles, countries, cities, tax rules, settings), creates your admin and links the storage folder. You then land on the admin sign-in at `/admin`.

When it's done, the installer writes `storage/installed` and `/install` stops working. To install again, delete that file and empty the database.

## 3. Installing from the command line (VPS)

```bash
cd backend
cp .env.example .env            # then fill in APP_URL, DB_*, etc.; every key is explained in the file
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=DemoSeeder --force   # optional demo fleet
php artisan storage:link
php artisan app:create-admin you@example.com --name="Your Name"
touch storage/installed          # tells the site the installer isn't needed
php artisan optimize
```

## 4. After installing (both methods)

**Cron.** Add one line. It cancels unpaid bookings when the payment time runs out and settles finished bookings:

```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

No queue worker is needed.

**HTTPS.** Use HTTPS on a live site. Payment providers require it for webhooks.

**Mail.** Fill in `MAIL_*` in `.env`, otherwise password-reset emails only go to the log. Any SMTP provider works.

## 5. Configuration

Every key is explained in `.env`. You can turn each service on separately; while one is empty, the platform keeps working and writes what it would have sent to `storage/logs/laravel.log`.

| What | `.env` keys | Notes |
|---|---|---|
| Card payments (Stripe) | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | Webhook: `{APP_URL}/api/v1/payments/webhook/stripe`, events `payment_intent.succeeded`, `payment_intent.payment_failed` |
| Card payments (Razorpay) | `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET` | Webhook: `{APP_URL}/api/v1/payments/webhook/razorpay`, events `payment.captured`, `order.paid`, `payment.failed` |
| OTP sign-in by SMS (Twilio Verify) | `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_VERIFY_SID` | The Verify service must use 6-digit codes. Trial accounts only send to verified numbers. |
| OTP sign-in by SMS from your own Android phone | `SMS_GATEWAY_URL`, `SMS_GATEWAY_USERNAME`, `SMS_GATEWAY_PASSWORD` | Used when Twilio is not set. See "SMS from your own phone" below. |
| Push notifications (Firebase) | `FCM_CREDENTIALS` | Absolute path to the service-account JSON file |
| Email | `MAIL_*` | Password reset links |
| File storage on S3 | `FILESYSTEM_DISK=s3`, `AWS_*` | Optional; the local disk is the default |

A payment is marked paid only after the provider's webhook confirms it, so set up the webhooks before taking real payments.

### SMS from your own phone

Any Android phone with a SIM can send the sign-in codes, so you don't need an SMS provider account. Each code is a normal SMS from that SIM, charged at your mobile plan's rate.

1. Install **SMS Gateway for Android** (free, open source: sms-gate.app, on Google Play or GitHub) and allow it to send SMS.
2. Choose how the server reaches the phone:
   - **Local server**: the phone and the server are on the same network (for example your office Wi-Fi). Turn it on in the app; it shows an address like `http://192.168.1.50:8080`, a username and a password. Give the phone a fixed IP address in your router.
   - **Cloud server**: works from anywhere with internet, which is what a hosted website needs. Turn it on in the app; it shows a username and password.
3. Put the values in `.env`:
   - Local: `SMS_GATEWAY_URL=http://192.168.1.50:8080/message`
   - Cloud: `SMS_GATEWAY_URL=https://api.sms-gate.app/3rdparty/v1/message`
   - `SMS_GATEWAY_USERNAME=` and `SMS_GATEWAY_PASSWORD=` as shown in the app.
4. Sign in with "Phone code" on the website or the app and check that the SMS arrives.

Keep the phone charged and the app allowed to run in the background (turn off battery optimisation for it). If the phone can't be reached, the error goes to `storage/logs/laravel.log` and the customer can ask for a new code.

You can set up both Twilio and the phone. Twilio is tried first; when it refuses a number (for example a number that is not verified on a Twilio trial account), the phone sends the code instead.

### Countries, taxes and features

- Four country packs ship: India (INR, GST, Razorpay), UAE (AED), USA (USD) and Germany (EUR). Each has age limits, licence rules, deposits, card gateways and feature switches. The defaults are in `backend/config/countrypacks/*.php`, and you can override them per country in the admin panel (Geography → Countries). **Check the tax rates and rules with a local advisor before you go live.**
- Tax rates: admin → Pricing → Tax rules.
- **Partner module** (owners list their vehicles and earn a share) and **Investor module**: switch them on in admin → Settings. A module works only where *both* the global switch and the country pack's switch are on. The investor module is off in every pack by default; switch it on per country only once you're sure it's legal there.
- Languages: English, Arabic (right-to-left), Hindi, Spanish and German. You can change any text in admin → System → Translations.

## 6. The mobile apps

You need the Flutter SDK (3.47 or newer, with Dart 3.13) and Android Studio, or Xcode for iOS.

1. **Change the app IDs** before you publish: `applicationId` in `customer_app/android/app/build.gradle.kts` and `staff_app/android/app/build.gradle.kts` (currently `com.rentalplatform.customer_app` / `com.rentalplatform.staff_app`), and the bundle identifier in Xcode.
2. **Name and icon:** `android:label` in `android/app/src/main/AndroidManifest.xml`; replace the icons in `android/app/src/main/res/mipmap-*`.
3. **Build** and point the app at your server:

```bash
cd customer_app
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://your-domain/api/v1 \
  --dart-define=MAPS_API_KEY=... \
  --dart-define=FIREBASE_API_KEY=... --dart-define=FIREBASE_APP_ID=... \
  --dart-define=FIREBASE_SENDER_ID=... --dart-define=FIREBASE_PROJECT_ID=...

cd ../staff_app
flutter build appbundle --release --dart-define=API_BASE_URL=https://your-domain/api/v1
```

`MAPS_API_KEY` (the branch map, Google Maps SDK for Android) and the four `FIREBASE_*` values (push notifications) are optional; leave them out to build without those features. Release builds must be signed with your own keystore; see Flutter's "Build and release an Android app" guide. Card payments in the app use the Stripe or Razorpay keys from the server, so nothing secret is built into the app.

**Staff accounts:** create a user in the admin panel (System → Users), give them the *staff* role and a branch. They then sign in to the staff app and see that branch's handovers and returns.

## 7. Updating

Back up the database and `.env` first, then upload the new files (keep your `.env` and `storage/`), and run:

```bash
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
```

## 8. Troubleshooting

- **Every page goes to `/install`:** the `storage/installed` file is missing. Create it if the site is already installed (section 3).
- **Error 500 after installing:** set `APP_DEBUG=true` in `.env` for a moment to see the message, or read `storage/logs/laravel.log`. The usual causes are folder permissions on `storage`/`bootstrap/cache` or a missing PHP extension.
- **Vehicle or checklist photos don't show:** run `php artisan storage:link`. On hosts without symlinks, copy `storage/app/public` to `public/storage`.
- **The app says "no connection":** check that `API_BASE_URL` ends with `/api/v1` and uses HTTPS. Android blocks plain HTTP in release builds.
- **Payments stay "processing":** the webhook hasn't arrived. Check its URL and secret in the provider's dashboard.

## 9. Credits for the demo photos

The demo fleet's photos come from Wikimedia Commons under the licences below, which allow commercial use with attribution. If you keep these photos on a public site, keep this credit list available (for example on an "About" or "Credits" page). The demo seeder crops and compresses each photo to 1200×750 when it copies it into storage. They are also listed in `backend/database/seeders/images/CREDITS.tsv`.

| Photo (model) | Author | Licence | Source |
|---|---|---|---|
| toyota-fortuner | NZ Car Freak | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:2015_Toyota_Fortuner_(New_Zealand).jpg>) |
| hyundai-creta | CARS WORLD Kannada | [CC BY 3.0](<https://creativecommons.org/licenses/by/3.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:2024_Hyundai_Creta_1.5_MPi_SX(O)_(India)_front_view.png>) |
| tvs-apache | Crazyranga | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Apache_rtr_310.jpg>) |
| bajaj-pulsar | HiveMynd | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Bajaj-NS400Z.jpg>) |
| honda-activa | Aash Gates | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Gold_Metallic_Honda_Activa.jpg>) |
| honda-city | © M 93 | [CC BY-SA 3.0 de](<https://creativecommons.org/licenses/by-sa/3.0/de/deed.en>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Honda_City_1.5_i-VTEC_V_(VIII,_Facelift)_–_f_22032025.jpg>) |
| ktm-duke-390 | 28pg | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Ktm_duke_390.jpg>) |
| mahindra-thar | Ank Kumar | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Mahindra_Thar_SUV_in_"Red_Rage"_color_at_Ashiana_Brahmanda,_East_Singbhum_India_(Ank_Kumar,_Infosys_limited)_02_(cropped).jpg>) |
| re-classic-350 | Samihasib | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Royal_Enfield_Classic_350_(2017_Model_Year).jpg>) |
| maruti-swift | Alexander-93 | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Suzuki_Swift_(2024)_hybrid_DSC_6076.jpg>) |
| tata-nexon | Keithkberger | Public domain | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Tata_Nexon_Blue_Dual_Tone.jpg>) |
| toyota-innova | © M 93 | [CC BY-SA 3.0 de](<https://creativecommons.org/licenses/by-sa/3.0/de/deed.en>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Toyota_Innova_Zenix_2.0_V_(III)_–_f_22032025.jpg>) |
| yamaha-r15 | Akshayanil919 | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Yamaha_R15_V3.0.jpg>) |
| hero-splendor | Sonachalam 2k Ramalingame | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Hero_Splendor_ISMART_100cc_2014.jpg>) |
| skoda-slavia | Athira Murali | [CC BY 3.0](<https://creativecommons.org/licenses/by/3.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:2021_%C5%A0koda_Slavia_1.5_TSI_Style_(India)_front_view.png>) |
| hyundai-i20 | Alexander Migl | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Hyundai_i20_(BC3)_1X7A6488.jpg>) |
| bmw-3-series | Dinkun Chen | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:BMW_3_SERIES_SEDAN_(G20)_China.jpg>) |
| suzuki-access | Faraz Sualeh | [CC BY 4.0](<https://creativecommons.org/licenses/by/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Suzuki_Access_125,_2023.jpg>) |
| mercedes-e-class | Alexander-93 | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Mercedes-Benz_W214_1X7A1841.jpg>) |
| kia-carens | Ganesh Mohan T | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Kia_Carens_2024_Model.jpg>) |
| re-himalayan | MotorideSA | [CC BY-SA 4.0](<https://creativecommons.org/licenses/by-sa/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Royal_Enfield_Himalayan_450_Mana_Black.jpg>) |
| triumph-speed-400 | Corvettec6r | [CC BY 4.0](<https://creativecommons.org/licenses/by/4.0/>) | [Wikimedia Commons](<https://commons.wikimedia.org/wiki/File:Triumph_Speed_400.jpg>) |
