# Bike & Car Rental — APK release for testing

Version 0.1.0 · Android 6.0 or newer, 64-bit phones (any phone from about 2017 on) · Server: `https://outrank-stays-sports.ngrok-free.dev`

| File | For | Who signs in |
|---|---|---|
| `release/rental-customer-0.1.0.apk` | Customers: search, book, pay, bookings, wallet | Customer account |
| `release/rental-staff-0.1.0.apk` | Branch staff: today's pickups and returns, handover and return checklists | Staff account |

The admin panel is a website: `https://outrank-stays-sports.ngrok-free.dev/admin`.

## 1. Before testing: start the server (on the developer PC)

The apps talk to the server running on the developer's PC through an ngrok tunnel. The PC must be **on and online** for the whole test.

1. Start **Docker Desktop** and wait until it shows "Engine running".
2. In a terminal in the project folder: `docker compose up -d`
3. Start the tunnel and leave the window open:
   ```
   ngrok http --url=outrank-stays-sports.ngrok-free.dev 8000
   ```
4. Check: open `https://outrank-stays-sports.ngrok-free.dev/api/v1/vehicles` in a browser. It should show vehicle data. (ngrok may first show a "You are about to visit" page in a browser; click **Visit Site**. The apps don't see this page.)

## 2. Install on the phone

1. Copy the APK to the phone (WhatsApp to yourself, Google Drive, or USB cable).
2. Tap the file. If Android asks, allow **Install unknown apps** for the app you opened it from (Files, Chrome, WhatsApp…).
3. If Play Protect warns about an unknown developer, tap **More details → Install anyway**. The APK is signed with a test key, not yet with a Play Store key.
4. Open the app. The phone needs internet (Wi-Fi or mobile data); it does not need to be on the same network as the PC.

## 3. Test logins

| App | Email | Password |
|---|---|---|
| Customer app | `qa-customer@example.test` | in `release/logins.txt` |
| Staff app | `qa-staff@example.test` | in `release/logins.txt` |
| Admin panel (browser) | `qa-admin@example.test` | in `release/logins.txt` |

The passwords are in `release/logins.txt`, which comes with the APKs and is not stored in git.

You can also create a new customer account in the app (**Create account**).

## 4. What to try

### Customer app
1. **Browse**: the list shows the bikes and cars with photos and prices. Filter Bike / Car, open a vehicle.
2. **Price**: choose pick-up and return. The price shows rental, tax (18% GST in India), total, refundable deposit and the amount to pay now.
3. **Book**: tap Book. The vehicle is held for a few minutes while you pay.
4. **Pay with wallet**: the demo customer has wallet balance, so **Pay with wallet** confirms the booking at once.
5. **Pay with Razorpay (test mode)**: tap Razorpay. The Razorpay window shows **Test Mode**; no real money moves. Use the test UPI ID `success@razorpay`, or a test card from Razorpay's "Test Card Details" page. See *Known limits* about confirmation.
6. **My bookings**: the booking with its status; open it for details, the invoice, cancel or extend.
7. **Wallet** and **Profile**: balance and history; change the language (English, Arabic right-to-left, Hindi, Spanish, German).
8. **Phone code login** (OTP): sign out, choose phone code, enter the account's phone number. See *Known limits*.

### Staff app
1. Sign in with the staff account. **Today** lists the branch's pickups and the vehicles that are out.
2. Open a booking that is confirmed and due → **Handover**: take at least 4 photos (front, back, left, right), enter the odometer and fuel level, submit. The booking becomes "Picked up".
3. Later → **Return**: photos, odometer (not lower than at handover), fuel, any damage. The booking completes; damages go to the admin for review.

To have a booking to hand over: book a vehicle in the customer app for today at the branch "Andheri Branch" and pay with the wallet.

### Admin panel (browser)
Dashboard (today's pickups and returns, revenue), Bookings, Payments, Vehicles and models (photos, engine size, gallery), Pricing, Users (assign staff to a branch), Settings.

## 5. Known limits of this test build

- **The PC must be running**, with Docker and the ngrok window open. If the apps say they can't connect, start step 1 again.
- **Razorpay card payments** open and complete in test mode, but the booking only turns **Confirmed** after Razorpay's webhook reaches the server. Set it once in the Razorpay dashboard (Test Mode → Webhooks): URL `https://outrank-stays-sports.ngrok-free.dev/api/v1/payments/webhook/razorpay`, events `payment.captured` and `payment.failed`, and a secret you choose there; put the same secret in `RAZORPAY_WEBHOOK_SECRET` in `backend/.env` and restart with `docker compose up -d` (the value there now was made locally and is not yet in the dashboard). Until then, use **Pay with wallet** to see a confirmed booking.
- **OTP by SMS** goes through Twilio, which is a trial account: it only sends to numbers verified in the Twilio console. Verify the tester's number there, or use email and password.
- **Push notifications** are not switched on in this build.
- The server answers a little slower than a real host, because it runs on a PC through a tunnel.
- For the Play Store, the apps must be signed with your own release key and pointed at a permanent server address.
