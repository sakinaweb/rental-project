import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:staff_app/core/api.dart';
import 'package:staff_app/core/session.dart';
import 'package:staff_app/features/providers.dart';
import 'package:staff_app/main.dart';

/// Fake backend behind the real dio client: "METHOD /path" → JSON (or (status, JSON)). Records every request.
class FakeBackend implements HttpClientAdapter {
  final handlers = <String, Object? Function(RequestOptions)>{};
  final requests = <RequestOptions>[];

  void on(String route, Object? Function(RequestOptions r) handler) => handlers[route] = handler;

  List<RequestOptions> sent(String route) => requests.where((r) => '${r.method} ${r.path}' == route).toList();

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options);
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
  String? token;
  @override
  Future<String?> read() async => token;
  @override
  Future<void> write(String? value) async => token = value;
}

Map<String, dynamic> userJson({String role = 'staff'}) => {'id': 5, 'name': 'Ravi', 'email': 'ravi@example.com', 'role': role};

Map<String, dynamic> bookingJson({
  int id = 42,
  String status = 'confirmed',
  String currency = 'INR',
  List<Map<String, dynamic>> inspections = const [],
}) => {
  'id': id,
  'status': status,
  'start_at': DateTime.now().subtract(const Duration(hours: 1)).toUtc().toIso8601String(),
  'end_at': DateTime.now().add(const Duration(days: 2)).toUtc().toIso8601String(),
  'customer': 'Asha Rao',
  'customer_phone': '+919900000000',
  'customer_email': 'asha@example.com',
  'vehicle': {'id': 7, 'plate_no': 'MH02AB1234', 'title': 'Honda Activa'},
  'odometer': 12000,
  'currency_code': currency,
  'inspections': inspections,
  'damages': [],
};

/// Branch "Andheri" with one pickup (#42, an hour late) and one vehicle out (#43).
FakeBackend branchBackend() => FakeBackend()
  ..on('POST /auth/login', (_) => {'token': 'staff-token', 'user': userJson()})
  ..on('GET /me', (_) => {'data': userJson()})
  ..on(
    'GET /staff/bookings',
    (_) => {
      'data': {
        'branch': 'Andheri',
        'pickups': [bookingJson()],
        'returns': [bookingJson(id: 43, status: 'picked_up')],
      },
    },
  );

/// Pumps the real app (router, l10n, session restore) against [backend]; the camera returns photo-1.jpg, photo-2.jpg…
Future<void> pumpApp(WidgetTester tester, FakeBackend backend, {bool signedIn = false}) async {
  // A tall phone-width screen, so whole pages are built (lists only build what is on screen).
  tester.view.physicalSize = const Size(420, 2400);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  final store = MemoryTokenStore()..token = signedIn ? 'staff-token' : null;
  var shots = 0;
  await tester.pumpWidget(
    ProviderScope(
      retry: (_, _) => null,
      overrides: [
        tokenStoreProvider.overrideWithValue(store),
        dioProvider.overrideWith((ref) => createDio(ref)..httpClientAdapter = backend),
        takePhotoProvider.overrideWithValue(() async => 'photo-${++shots}.jpg'),
        photoFileProvider.overrideWithValue((path) async => MultipartFile.fromString('jpeg', filename: path)),
      ],
      child: const StaffApp(),
    ),
  );
  await settle(tester);
}

Future<void> settle(WidgetTester tester, [int frames = 30]) async {
  for (var i = 0; i < frames; i++) {
    await tester.pump(const Duration(milliseconds: 50));
  }
}

/// Fields and file names of a multipart request.
(Map<String, String>, List<(String, String)>) form(RequestOptions r) {
  final data = r.data as FormData;
  return ({for (final f in data.fields) f.key: f.value}, [for (final f in data.files) (f.key, f.value.filename!)]);
}
