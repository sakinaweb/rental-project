import 'package:customer_app/core/api.dart';
import 'package:customer_app/core/config.dart';
import 'package:customer_app/core/money.dart';
import 'package:customer_app/core/session.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers.dart';

void main() {
  test('money: minor units in the currency and language', () {
    expect(formatMoney(354000, 'INR', 'en'), '₹3,540.00');
    expect(formatMoney(500000, 'INR', 'hi'), contains('5,000.00'));
    expect(formatMoney(1050, 'AED', 'en'), contains('10.50'));
    expect(toMinor('1500.50', 'INR'), 150050);
    expect(toMinor('0', 'INR'), isNull);
    expect(toMinor('abc', 'INR'), isNull);
  });

  test('money per country pack in its main language (Phase 8)', () {
    expect(formatMoney(312345, 'INR', 'hi'), '₹3,123.45');
    expect(formatMoney(312345, 'AED', 'ar'), '‏3,123.45 dh'); // right-to-left mark first
    expect(formatMoney(312345, 'USD', 'en'), r'$3,123.45');
    expect(formatMoney(312345, 'EUR', 'de'), '3.123,45 €');
    expect(toMinor('30,50', 'EUR'), 3050); // German decimal comma
  });

  test('api client sends the token and language, and maps refusals', () async {
    final backend = FakeBackend()
      ..on('GET /me', (_) => {'data': userJson()})
      ..on(
        'POST /auth/login',
        (_) => (
          422,
          {
            'message': 'These credentials do not match our records.',
            'errors': {
              'email': ['These credentials do not match our records.'],
            },
          },
        ),
      )
      ..on('POST /bookings/42/cancel', (_) => (422, {'message': 'This action is not allowed for the booking in its current status.'}));
    final container = ProviderContainer(
      overrides: [
        dioProvider.overrideWith((ref) => createDio(ref)..httpClientAdapter = backend),
        tokenStoreProvider.overrideWithValue(MemoryTokenStore({'token': 't0k', 'locale': 'hi'})),
      ],
    );
    addTearDown(container.dispose);
    await container.read(sessionProvider.notifier).restore('en');

    final api = container.read(apiProvider);
    expect((await api.me()).email, 'asha@example.com');
    expect(backend.requests.last.headers['Authorization'], 'Bearer t0k');
    expect(backend.requests.last.headers['Accept-Language'], 'hi');

    final login = await api.login('a@b.c', 'x').then<ApiException?>((_) => null, onError: (e) => e as ApiException);
    expect(login!.status, 422);
    expect(login.field('email'), 'These credentials do not match our records.');

    final cancel = await api.cancel(42).then<ApiException?>((_) => null, onError: (e) => e as ApiException);
    expect(cancel!.message, 'This action is not allowed for the booking in its current status.');
    expect(cancel.errors, isEmpty);
  });

  test('network failure is reported as such, not as a server message', () async {
    final dio = Dio(BaseOptions(baseUrl: 'http://127.0.0.1:9/api/v1', connectTimeout: const Duration(seconds: 2)));
    final e = await Api(dio).countries().then<ApiException?>((_) => null, onError: (e) => e as ApiException);
    expect(e!.isNetwork, isTrue);
  });

  test('booking rules the UI relies on', () {
    expect(Booking(bookingJson(status: 'draft')).canCancel, isTrue);
    expect(Booking(bookingJson(status: 'picked_up')).canCancel, isFalse);
    expect(Booking(bookingJson(status: 'pending_payment')).canExtend, isFalse);
    expect(Booking(bookingJson(status: 'confirmed', paid: 854000)).canPay, isFalse);
    expect(Booking(bookingJson(status: 'pending_payment')).waitingForPayment, isTrue);
  });

  test("vehicle photo: a dev server's localhost URL goes to the API host, real hosts are kept", () {
    final api = Uri.parse(Config.apiBaseUrl);
    expect(mediaUrl('http://localhost:8000/storage/vehicle-models/a.jpg'), '${api.scheme}://${api.host}:${api.port}/storage/vehicle-models/a.jpg');
    expect(mediaUrl('https://cdn.example.com/a.jpg'), 'https://cdn.example.com/a.jpg');
    expect(mediaUrl(null), isNull);
    expect(Vehicle({'model': {'image_url': null}}).imageUrl, isNull);
  });
}
