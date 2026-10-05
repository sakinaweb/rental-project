import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

// The customer flow on the India pack (INR, GST 18%, Razorpay), mirroring the website (MD §7.7).
void main() {
  testWidgets('search lists vehicles with filters and real prices per period', (tester) async {
    final backend = indiaBackend();
    await pumpApp(tester, backend);

    expect(find.text('Find a bike or car'), findsWidgets);
    expect(find.text('Maruti Swift'), findsOneWidget);
    expect(find.text('Choose pick-up and return to see the price.'), findsOneWidget);

    await tester.tap(find.text('Bike'));
    await settle(tester);
    expect(find.text('Maruti Swift'), findsNothing);
    expect(find.text('No vehicles match your search. Try other dates or filters.'), findsOneWidget);
    expect(backend.sent('GET /vehicles').last.queryParameters['type'], 'bike');
  });

  testWidgets('vehicle details: availability, quote with add-ons, guest is sent to sign in and back', (tester) async {
    final backend = indiaBackend();
    await pumpApp(tester, backend, initial: '/vehicles/7?from=2026-11-02T15:30:00.000&to=2026-11-04T15:30:00.000');

    expect(find.text('Available for these dates'), findsOneWidget);
    expect(find.text('₹3,540.00'), findsOneWidget); // 2 days × ₹1,500 + 18% GST
    expect(find.text('₹8,540.00'), findsOneWidget); // + ₹5,000 deposit
    expect(backend.sent('POST /bookings/quote').last.data['start_at'], endsWith('Z')); // sent as UTC

    await tester.tap(find.byIcon(Icons.add));
    await settle(tester);
    expect(find.text('₹3,776.00'), findsOneWidget); // + helmet ₹100 × 2 days, taxed
    expect(backend.sent('POST /bookings/quote').last.data['addons'], [
      {'id': 1, 'qty': 1},
    ]);

    await tester.ensureVisible(find.byKey(const Key('book')));
    await tester.tap(find.byKey(const Key('book')));
    await settle(tester);
    expect(find.text('Sign in'), findsWidgets);
    expect(backend.sent('POST /bookings'), isEmpty);
  });

  testWidgets('sign in with password returns to the vehicle, then book → checkout → wallet → confirmed', (tester) async {
    final backend = indiaBackend();
    var status = 'draft';
    backend
      ..on('POST /auth/login', (_) => {'token': 'new-token', 'user': userJson()})
      ..on('POST /bookings', (_) => {'data': bookingJson()})
      ..on('GET /bookings/42', (_) => {'data': bookingJson(status: status, paid: status == 'confirmed' ? 854000 : 0)})
      ..on('POST /payments/intent', (r) {
        status = 'confirmed';
        return (
          201,
          {
            'data': {
              'payment': {'id': 1, 'status': 'succeeded'},
              'client': null,
              'booking_status': 'confirmed',
            },
          },
        );
      });
    await pumpApp(tester, backend, initial: '/login?from=/vehicles/7%3Ffrom%3D2026-11-02T15:30:00.000%26to%3D2026-11-04T15:30:00.000');

    await tester.enterText(find.byKey(const Key('email')), 'asha@example.com');
    await tester.enterText(find.byKey(const Key('password')), 'pw');
    await tester.tap(find.byKey(const Key('submit')));
    await settle(tester);
    expect(find.text('Maruti Swift'), findsWidgets); // back on the vehicle
    expect(backend.sent('POST /bookings/quote').last.headers['Authorization'], 'Bearer new-token');

    await tester.ensureVisible(find.byKey(const Key('book')));
    await tester.tap(find.byKey(const Key('book')));
    await settle(tester);
    expect(find.text('Checkout'), findsWidgets);
    expect(find.text('₹8,540.00'), findsWidgets); // due now

    await tester.tap(find.byKey(const Key('pay-wallet')));
    await settle(tester);
    expect(backend.sent('POST /payments/intent').single.data, {'booking_id': 42, 'gateway': 'wallet'});
    expect(find.text('Your booking is confirmed.'), findsOneWidget);
  });

  testWidgets('card payment never fakes success: the confirmation waits for the webhook status', (tester) async {
    final backend = indiaBackend();
    var status = 'pending_payment';
    backend
      ..on(
        'GET /payments/gateways',
        (_) => {
          'data': ['razorpay'],
        },
      )
      ..on('GET /bookings/42', (_) => {'data': bookingJson(status: status)})
      ..on(
        'POST /payments/intent',
        (_) => (
          201,
          {
            'data': {
              'payment': {'id': 1, 'status': 'pending'},
              'client': {'order_id': 'order_1', 'key_id': 'rzp_test'},
              'booking_status': 'pending_payment',
            },
          },
        ),
      );
    Map<String, dynamic>? opened;
    await pumpApp(
      tester,
      backend,
      signedIn: true,
      initial: '/checkout/42',
      cardCheckout: (gateway, client, {required title}) async {
        opened = client;
        return true; // customer finished Razorpay checkout
      },
    );

    await tester.tap(find.byKey(const Key('pay-razorpay')));
    await settle(tester);
    expect(opened, {'order_id': 'order_1', 'key_id': 'rzp_test'});
    expect(find.text('Waiting for payment confirmation. This page updates automatically.'), findsOneWidget);

    status = 'confirmed'; // the webhook arrives
    await tester.pump(const Duration(seconds: 3));
    await settle(tester);
    expect(find.text('Your booking is confirmed.'), findsOneWidget);
  });

  testWidgets('a UAE booking (AED) pays by Stripe: only its pack\'s gateways are offered', (tester) async {
    final backend = indiaBackend()
      ..on(
        'GET /payments/gateways',
        (r) => {
          'data': [if (r.queryParameters['currency'] == 'AED') 'stripe' else 'razorpay'],
        },
      )
      ..on('GET /bookings/42', (_) => {'data': bookingJson(status: 'pending_payment', currency: 'AED')})
      ..on(
        'POST /payments/intent',
        (_) => (
          201,
          {
            'data': {
              'payment': {'id': 1, 'status': 'pending'},
              'client': {'client_secret': 'pi_1_secret', 'publishable_key': 'pk_test'},
              'booking_status': 'pending_payment',
            },
          },
        ),
      );
    (String, Map<String, dynamic>)? opened;
    await pumpApp(
      tester,
      backend,
      signedIn: true,
      initial: '/checkout/42',
      cardCheckout: (gateway, client, {required title}) async {
        opened = (gateway, client);
        return true;
      },
    );

    expect(backend.sent('GET /payments/gateways').last.queryParameters, {'currency': 'AED'});
    expect(find.byKey(const Key('pay-razorpay')), findsNothing);
    await tester.tap(find.byKey(const Key('pay-stripe')));
    await settle(tester);
    expect(backend.sent('POST /payments/intent').single.data, {'booking_id': 42, 'gateway': 'stripe'});
    expect(opened?.$1, 'stripe');
    expect(opened?.$2, {'client_secret': 'pi_1_secret', 'publishable_key': 'pk_test'});
    expect(find.text('Waiting for payment confirmation. This page updates automatically.'), findsOneWidget);
  });

  testWidgets('confirmation polling waits for a slow server instead of restarting the request', (tester) async {
    var status = 'pending_payment';
    final backend = indiaBackend()..on('GET /bookings/42', (_) => {'data': bookingJson(status: status)});
    await pumpApp(tester, backend, signedIn: true, initial: '/confirmation/42');
    await settle(tester);
    expect(find.text('Waiting for payment confirmation. This page updates automatically.'), findsOneWidget);

    backend.delay = const Duration(seconds: 10); // slower than the 3-second poll
    status = 'confirmed';
    for (var i = 0; i < 15; i++) {
      await tester.pump(const Duration(seconds: 1));
    }
    await settle(tester);
    expect(find.text('Your booking is confirmed.'), findsOneWidget);
    backend.delay = Duration.zero;
  });

  testWidgets('dismissed card checkout shows it was not completed and stays on checkout', (tester) async {
    final backend = indiaBackend()
      ..on(
        'GET /payments/gateways',
        (_) => {
          'data': ['razorpay'],
        },
      )
      ..on('GET /bookings/42', (_) => {'data': bookingJson()})
      ..on(
        'POST /payments/intent',
        (_) => (
          201,
          {
            'data': {
              'payment': {'id': 1},
              'client': {'order_id': 'o', 'key_id': 'k'},
            },
          },
        ),
      );
    await pumpApp(tester, backend, signedIn: true, initial: '/checkout/42', cardCheckout: (_, _, {required title}) async => false);

    await tester.tap(find.byKey(const Key('pay-razorpay')));
    await settle(tester);
    expect(find.text('The payment was not completed.'), findsOneWidget);
    expect(find.text('Checkout'), findsWidgets);
  });

  testWidgets('OTP sign-in and account tabs require sign-in', (tester) async {
    final backend = indiaBackend()
      ..on('POST /auth/otp/request', (_) => {'message': 'If this number is registered, a verification code has been sent.'})
      ..on(
        'POST /auth/otp/verify',
        (r) => r.data['code'] == '123456'
            ? {'token': 'otp-token', 'user': userJson()}
            : (
                422,
                {
                  'message': 'bad',
                  'errors': {
                    'code': ['The verification code is invalid or has expired.'],
                  },
                },
              ),
      )
      ..on('GET /bookings', (_) => page([bookingJson(status: 'confirmed', paid: 854000)]));
    await pumpApp(tester, backend);

    await tester.tap(find.text('My bookings'));
    await settle(tester);
    expect(find.byKey(const Key('submit')), findsOneWidget); // redirected to sign-in

    await tester.tap(find.text('Phone code'));
    await settle(tester);
    await tester.enterText(find.byKey(const Key('phone')), '+919900000001');
    await tester.tap(find.byKey(const Key('submit')));
    await settle(tester);
    expect(find.text('If this number is registered, a verification code has been sent.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('code')), '000000');
    await tester.tap(find.byKey(const Key('submit')));
    await settle(tester);
    expect(find.text('The verification code is invalid or has expired.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('code')), '123456');
    await tester.tap(find.byKey(const Key('submit')));
    await settle(tester);
    expect(find.textContaining('#42'), findsOneWidget); // my bookings
  });

  testWidgets('booking detail: cancel after confirming, refund notice', (tester) async {
    var status = 'confirmed';
    final backend = indiaBackend()
      ..on('GET /bookings/42', (_) => {'data': bookingJson(status: status, paid: 854000)})
      ..on('GET /bookings', (_) => page([bookingJson(status: status, paid: 854000)]))
      ..on('POST /bookings/42/cancel', (_) {
        status = 'cancelled';
        return {'data': bookingJson(status: 'cancelled')};
      });
    await pumpApp(tester, backend, signedIn: true, initial: '/bookings/42');

    expect(find.text('Confirmed'), findsOneWidget);
    await tester.ensureVisible(find.byKey(const Key('cancel')));
    await tester.tap(find.byKey(const Key('cancel')));
    await settle(tester);
    await tester.tap(find.byKey(const Key('confirm-cancel')));
    await settle(tester);

    expect(backend.sent('POST /bookings/42/cancel'), hasLength(1));
    expect(find.text('Booking cancelled. The refund is in your wallet.'), findsOneWidget);
    expect(find.text('Cancelled'), findsOneWidget);
    expect(find.byKey(const Key('cancel')), findsNothing);
  });

  testWidgets('wallet shows balances; top-up opens Razorpay but the balance waits for the webhook', (tester) async {
    final backend = indiaBackend()
      ..on(
        'GET /payments/gateways',
        (_) => {
          'data': ['razorpay'],
        },
      )
      ..on(
        'POST /wallet/topup',
        (_) => (
          201,
          {
            'data': {
              'payment': {'id': 9},
              'client': {'order_id': 'order_w', 'key_id': 'k'},
            },
          },
        ),
      );
    await pumpApp(tester, backend, signedIn: true, initial: '/wallet', cardCheckout: (_, _, {required title}) async => true);

    expect(find.text('₹20,000.00'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('amount')), '250.50');
    await tester.tap(find.byKey(const Key('topup-razorpay')));
    await settle(tester);

    expect(backend.sent('POST /wallet/topup').single.data, {'amount': 25050, 'currency_code': 'INR', 'gateway': 'razorpay'});
    expect(find.textContaining('Your balance updates once the payment provider confirms it.'), findsOneWidget);
    expect(find.text('₹20,000.00'), findsOneWidget); // unchanged until the webhook
  });

  testWidgets('profile: saving a new language switches the app, Arabic is right-to-left', (tester) async {
    final backend = indiaBackend()..on('PATCH /me', (r) => {'data': userJson(locale: r.data['locale'])});
    await pumpApp(tester, backend, signedIn: true, initial: '/profile');

    await tester.tap(find.byKey(const Key('locale')));
    await settle(tester);
    await tester.tap(find.text('العربية').last);
    await settle(tester);
    await tester.tap(find.byKey(const Key('save')));
    await settle(tester);

    expect(backend.sent('PATCH /me').single.data['locale'], 'ar');
    expect(find.text('الملف الشخصي'), findsWidgets); // "Profile" in Arabic
    expect(Directionality.of(tester.element(find.byKey(const Key('save')))), TextDirection.rtl);
  });

  testWidgets('all five languages render translated, with the session language sent to the API', (tester) async {
    const titles = {
      'en': 'Find a bike or car',
      'ar': 'ابحث عن دراجة أو سيارة',
      'hi': 'बाइक या कार खोजें',
      'es': 'Encuentra una moto o un coche',
      'de': 'Motorrad oder Auto finden',
    };
    for (final MapEntry(key: locale, value: title) in titles.entries) {
      final backend = indiaBackend();
      await pumpApp(tester, backend, locale: locale);
      expect(find.text(title), findsWidgets, reason: locale);
      expect(backend.requests.first.headers['Accept-Language'], locale);
      expect(Directionality.of(tester.element(find.text(title).first)), locale == 'ar' ? TextDirection.rtl : TextDirection.ltr);
    }
  });

  testWidgets('a stored token the server rejects (e.g. suspended user) signs the app out', (tester) async {
    final backend = indiaBackend()..on('GET /me', (_) => (401, {'message': 'Unauthenticated.'}));
    await pumpApp(tester, backend, signedIn: true);

    await tester.tap(find.text('Wallet'));
    await settle(tester);
    expect(find.byKey(const Key('submit')), findsOneWidget); // sign-in page, not the wallet
    expect(backend.sent('GET /wallet'), isEmpty);
  });
}
