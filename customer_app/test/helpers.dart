import 'dart:convert';
import 'dart:typed_data';

import 'package:customer_app/core/api.dart';
import 'package:customer_app/core/router.dart';
import 'package:customer_app/core/session.dart';
import 'package:customer_app/features/booking/card_checkout.dart';
import 'package:customer_app/main.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// Fake backend behind the real dio client: "METHOD /path" → JSON (or (status, JSON)). Records every request.
class FakeBackend implements HttpClientAdapter {
  final handlers = <String, Object? Function(RequestOptions)>{};
  final requests = <RequestOptions>[];
  Duration delay = Duration.zero; // simulates a slow server

  void on(String route, Object? Function(RequestOptions r) handler) => handlers[route] = handler;

  List<RequestOptions> sent(String route) => requests.where((r) => '${r.method} ${r.path}' == route).toList();

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options);
    if (delay > Duration.zero) await Future<void>.delayed(delay);
    final handler = handlers['${options.method} ${options.path}'];
    final result = handler == null ? (404, {'message': 'Not found'}) : handler(options);
    final (status, data) = result is (int, Object?) ? result : (200, result);
    return ResponseBody.fromString(
      jsonEncode(data),
      status,
      headers: {
        Headers.contentTypeHeader: ['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

class MemoryTokenStore extends TokenStore {
  MemoryTokenStore([Map<String, String>? initial]) : values = {...?initial};
  final Map<String, String> values;

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String? value) async => value == null ? values.remove(key) : values[key] = value;
}

/// Standard India-pack catalogue (INR). A Mon–Wed booking at ₹1,500/day + 18% GST = ₹3,540, deposit ₹5,000.
FakeBackend indiaBackend() {
  final b = FakeBackend();
  b.on(
    'GET /countries',
    (_) => {
      'data': [
        {'id': 1, 'iso_code': 'IN', 'name': 'India', 'currency_code': 'INR'},
      ],
    },
  );
  b.on(
    'GET /cities',
    (_) => {
      'data': [
        {'id': 10, 'country_id': 1, 'name': 'Mumbai'},
      ],
    },
  );
  b.on(
    'GET /vehicle-categories',
    (_) => {
      'data': [
        {'id': 1, 'name': 'Hatchback', 'type': 'car'},
        {'id': 2, 'name': 'Scooter', 'type': 'bike'},
      ],
    },
  );
  b.on(
    'GET /addons',
    (_) => {
      'data': [
        {'id': 1, 'name': 'Helmet', 'price': 10000, 'type': 'per_day'},
      ],
    },
  );
  b.on('GET /payments/gateways', (_) => {'data': <String>[]});
  b.on('GET /vehicles', (r) => page([if (r.queryParameters['type'] != 'bike') vehicleJson()]));
  b.on('GET /vehicles/7', (_) => {'data': vehicleJson()});
  b.on(
    'GET /vehicles/7/availability',
    (_) => {
      'data': {'available': true},
    },
  );
  b.on('POST /bookings/quote', (r) => {'data': quoteJson(addons: (r.data['addons'] as List).isEmpty ? 0 : 20000)});
  b.on('GET /me', (_) => {'data': userJson()});
  b.on(
    'GET /wallet',
    (_) => {
      'data': [],
      'balances': [
        {'currency_code': 'INR', 'balance': 2000000},
      ],
      'meta': {'current_page': 1, 'last_page': 1},
    },
  );
  return b;
}

Map<String, dynamic> page(List<Object> items) => {
  'data': items,
  'links': {},
  'meta': {'current_page': 1, 'last_page': 1, 'total': items.length},
};

Map<String, dynamic> userJson({String locale = 'en'}) => {
  'id': 1,
  'name': 'Asha',
  'email': 'asha@example.com',
  'phone': '+919900000001',
  'role': 'customer',
  'country_id': 1,
  'locale': locale,
  'status': 'active',
};

Map<String, dynamic> vehicleJson() => {
  'id': 7,
  'plate_no': 'MH02AB1234',
  'status': 'available',
  'model': {'id': 1, 'name': 'Swift', 'seats': 5, 'transmission': 'manual', 'fuel_type': 'petrol'},
  'brand': {'id': 1, 'name': 'Maruti'},
  'category': {'id': 1, 'name': 'Hatchback', 'type': 'car'},
  'branch': {
    'id': 1,
    'name': 'Andheri Branch',
    'location': {'id': 1, 'name': 'Andheri West', 'city_id': 10, 'lat': '19.1360000', 'lng': '72.8290000'},
  },
};

Map<String, dynamic> quoteJson({int addons = 0}) {
  final subtotal = 300000 + addons;
  final tax = (subtotal * 0.18).round();
  return {
    'currency_code': 'INR',
    'unit': 'day',
    'units': 2,
    'rental': 300000,
    'addons_total': addons,
    'subtotal': subtotal,
    'long_rental_discount': 0,
    'coupon_discount': 0,
    'discount_total': 0,
    'tax_total': tax,
    'grand_total': subtotal + tax,
    'deposit_amount': 500000,
  };
}

Map<String, dynamic> bookingJson({int id = 42, String status = 'draft', int paid = 0}) => {
  'id': id,
  'status': status,
  'expires_at': DateTime.now().add(const Duration(minutes: 15)).toUtc().toIso8601String(),
  'vehicle': vehicleJson(),
  'start_at': '2026-11-02T10:00:00Z',
  'end_at': '2026-11-04T10:00:00Z',
  'currency_code': 'INR',
  'subtotal': 300000,
  'discount_total': 0,
  'tax_total': 54000,
  'grand_total': 354000,
  'deposit_amount': 500000,
  'amount_paid': paid,
  'amount_due': status == 'cancelled' ? 0 : 854000 - paid,
  'addons': [],
  'deposit': null,
};

/// Pumps the real app (router, l10n, session restore) against [backend].
Future<void> pumpApp(
  WidgetTester tester,
  FakeBackend backend, {
  bool signedIn = false,
  String locale = 'en',
  CardCheckout? cardCheckout,
  String initial = '/',
}) async {
  // A tall phone-width screen, so whole pages are built (lists only build what is on screen).
  tester.view.physicalSize = const Size(420, 2400);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  final store = MemoryTokenStore({'locale': locale, if (signedIn) 'token': 'test-token'});
  late ProviderContainer container;
  await tester.pumpWidget(
    ProviderScope(
      key: UniqueKey(), // a fresh app each call
      retry: (_, _) => null,
      overrides: [
        dioProvider.overrideWith((ref) => createDio(ref)..httpClientAdapter = backend),
        tokenStoreProvider.overrideWithValue(store),
        if (cardCheckout != null) cardCheckoutProvider.overrideWithValue(cardCheckout),
      ],
      child: Consumer(
        builder: (context, ref, _) {
          container = ProviderScope.containerOf(context);
          return const RentalApp();
        },
      ),
    ),
  );
  await settle(tester);
  if (initial != '/') {
    container.read(routerProvider).go(initial);
    await settle(tester);
  }
}

/// Lets requests and rebuilds finish. (pumpAndSettle would never return while an indeterminate progress bar spins.)
Future<void> settle(WidgetTester tester, [int frames = 30]) async {
  for (var i = 0; i < frames; i++) {
    await tester.pump(const Duration(milliseconds: 50));
  }
}
