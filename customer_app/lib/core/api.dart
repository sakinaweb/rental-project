import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'config.dart';
import 'session.dart';

/// A refusal from the API: validation (field errors) or a business rule ({message}). status null = no connection.
class ApiException implements Exception {
  ApiException(this.message, {this.status, this.errors = const {}});

  final String message;
  final int? status;
  final Map<String, List<String>> errors;

  bool get isNetwork => status == null;

  String? field(String name) => errors[name]?.first;

  @override
  String toString() => message;
}

// ---------------------------------------------------------------------------------------------------------
// Models: only the fields the app uses. Shapes are documented in docs/openapi.json.

class User {
  User(this.j);
  final Map<String, dynamic> j;
  int get id => j['id'];
  String get name => j['name'] ?? '';
  String get email => j['email'] ?? '';
  String? get phone => j['phone'];
  int? get countryId => j['country_id'];
  String get locale => j['locale'] ?? 'en';
}

class Country {
  Country(this.id, this.name, this.currency);
  factory Country.fromJson(Map<String, dynamic> j) => Country(j['id'], j['name'], j['currency_code']);
  final int id;
  final String name;
  final String currency;
}

class Named {
  Named(this.id, this.name, [this.type]);
  factory Named.fromJson(Map<String, dynamic> j) => Named(j['id'], j['name'], j['type']);
  final int id;
  final String name;
  final String? type;
}

class Addon {
  Addon(this.j);
  final Map<String, dynamic> j;
  int get id => j['id'];
  String get name => j['name'];
  int get price => j['price'];
  bool get perDay => j['type'] == 'per_day';
}

class Vehicle {
  Vehicle(this.j);
  final Map<String, dynamic> j;
  int get id => j['id'];
  String get title => '${j['brand']?['name'] ?? ''} ${j['model']?['name'] ?? ''}'.trim();
  String get category => j['category']?['name'] ?? '';
  String get type => j['category']?['type'] ?? 'car';
  int? get seats => j['model']?['seats'];
  String? get transmission => j['model']?['transmission'];
  String? get fuel => j['model']?['fuel_type'];
  String get branch => j['branch']?['name'] ?? '';
  String get locationName => j['branch']?['location']?['name'] ?? '';
  double? get lat => double.tryParse('${j['branch']?['location']?['lat']}');
  double? get lng => double.tryParse('${j['branch']?['location']?['lng']}');
}

class Quote {
  Quote(this.j);
  final Map<String, dynamic> j;
  String get currency => j['currency_code'];
  int get subtotal => j['subtotal'];
  int get discount => j['discount_total'];
  int get tax => j['tax_total'];
  int get total => j['grand_total'];
  int get deposit => j['deposit_amount'];
  int get dueNow => total + deposit;
}

class Booking {
  Booking(this.j);
  final Map<String, dynamic> j;
  int get id => j['id'];
  String get status => j['status'];
  Vehicle? get vehicle => j['vehicle'] == null ? null : Vehicle(j['vehicle']);
  DateTime get start => DateTime.parse(j['start_at']).toLocal();
  DateTime get end => DateTime.parse(j['end_at']).toLocal();
  DateTime? get expiresAt => j['expires_at'] == null ? null : DateTime.parse(j['expires_at']).toLocal();
  String get currency => j['currency_code'];
  int get subtotal => j['subtotal'];
  int get discount => j['discount_total'];
  int get tax => j['tax_total'];
  int get total => j['grand_total'];
  int get deposit => j['deposit_amount'];
  int get paid => j['amount_paid'] ?? 0;
  int get due => j['amount_due'] ?? 0;
  String? get depositStatus => j['deposit']?['status'];
  List<Map<String, dynamic>> get addons => [...?(j['addons'] as List?)?.cast<Map<String, dynamic>>()];

  bool get waitingForPayment => status == 'draft' || status == 'pending_payment';
  bool get isExpiredDraft => status == 'draft' && (expiresAt?.isBefore(DateTime.now()) ?? false);
  bool get canCancel => const ['draft', 'pending_payment', 'confirmed'].contains(status);
  bool get canExtend => const ['draft', 'confirmed', 'picked_up'].contains(status) && !isExpiredDraft;
  bool get canPay => due > 0 && const ['draft', 'pending_payment', 'confirmed', 'picked_up'].contains(status) && !isExpiredDraft;
}

class WalletTx {
  WalletTx(this.j);
  final Map<String, dynamic> j;
  String get type => j['type'];
  int get amount => j['amount'];
  int get balanceAfter => j['balance_after'];
  String get currency => j['currency_code'];
  DateTime get at => DateTime.parse(j['created_at']).toLocal();
}

class Wallet {
  Wallet(this.balances, this.transactions);
  final Map<String, int> balances;
  final List<WalletTx> transactions;
}

class Page<T> {
  Page(this.items, this.currentPage, this.lastPage);
  final List<T> items;
  final int currentPage;
  final int lastPage;
  bool get hasMore => currentPage < lastPage;
}

/// Card checkout data from POST /payments/intent or /wallet/topup (Razorpay: order_id + key_id).
class Intent {
  Intent(this.j);
  final Map<String, dynamic> j;
  Map<String, dynamic>? get client => j['client'];
  String? get bookingStatus => j['booking_status'];
}

/// Search/booking period. Sent as UTC ISO-8601; the backend accepts any offset.
typedef Period = ({DateTime from, DateTime to});

// ---------------------------------------------------------------------------------------------------------

final dioProvider = Provider<Dio>(createDio);

/// Dio with the session's bearer token and language on every request (tests reuse it with a fake adapter).
Dio createDio(Ref ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: Config.apiBaseUrl,
      headers: {'Accept': 'application/json'},
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 90), // the local Docker stack on Windows is slow
    ),
  );
  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) {
        final session = ref.read(sessionProvider);
        if (session.token != null) options.headers['Authorization'] = 'Bearer ${session.token}';
        options.headers['Accept-Language'] = session.locale;
        handler.next(options);
      },
    ),
  );
  return dio;
}

final apiProvider = Provider<Api>((ref) => Api(ref.watch(dioProvider)));

/// The REST API (docs/openapi.json). Every method throws [ApiException] on refusal.
class Api {
  Api(this._dio);
  final Dio _dio;

  Future<T> _call<T>(Future<Response> Function() request, T Function(dynamic data) parse) async {
    try {
      return parse((await request()).data);
    } on DioException catch (e) {
      final data = e.response?.data;
      if (data is Map && data['message'] is String) {
        final errors = <String, List<String>>{};
        (data['errors'] as Map?)?.forEach((k, v) => errors['$k'] = List<String>.from(v));
        throw ApiException(data['message'], status: e.response?.statusCode, errors: errors);
      }
      throw ApiException('', status: e.response?.statusCode);
    }
  }

  static String _iso(DateTime t) => t.toUtc().toIso8601String();

  Page<T> _page<T>(dynamic d, T Function(Map<String, dynamic>) item) =>
      Page([for (final j in d['data']) item(j)], d['meta']?['current_page'] ?? 1, d['meta']?['last_page'] ?? 1);

  // Auth & account
  (String, User) _token(dynamic d) => (d['token'] as String, User(d['user']));
  Future<(String, User)> login(String email, String password) => _call(() => _dio.post('/auth/login', data: {'email': email, 'password': password}), _token);
  Future<(String, User)> register(Map<String, dynamic> body) => _call(() => _dio.post('/auth/register', data: body), _token);
  Future<String> requestOtp(String phone) => _call(() => _dio.post('/auth/otp/request', data: {'phone': phone}), (d) => d['message'] as String);
  Future<(String, User)> verifyOtp(String phone, String code) => _call(() => _dio.post('/auth/otp/verify', data: {'phone': phone, 'code': code}), _token);
  Future<User> me() => _call(() => _dio.get('/me'), (d) => User(d['data']));
  Future<User> updateMe(Map<String, dynamic> body) => _call(() => _dio.patch('/me', data: body), (d) => User(d['data']));
  Future<void> registerDevice(String token, String platform) => _call(() => _dio.post('/devices', data: {'token': token, 'platform': platform}), (_) {});
  Future<void> unregisterDevice(String token) => _call(() => _dio.delete('/devices/${Uri.encodeComponent(token)}'), (_) {});

  // Catalogue & search
  Future<List<Country>> countries() => _call(() => _dio.get('/countries'), (d) => [for (final j in d['data']) Country.fromJson(j)]);
  Future<List<Named>> cities(int countryId) =>
      _call(() => _dio.get('/cities', queryParameters: {'country_id': countryId}), (d) => [for (final j in d['data']) Named.fromJson(j)]);
  Future<List<Named>> categories() => _call(() => _dio.get('/vehicle-categories'), (d) => [for (final j in d['data']) Named.fromJson(j)]);
  Future<List<Addon>> addons() => _call(() => _dio.get('/addons'), (d) => [for (final j in d['data']) Addon(j)]);
  Future<Page<Vehicle>> vehicles(Map<String, dynamic> filters, {int page = 1}) =>
      _call(() => _dio.get('/vehicles', queryParameters: {...filters, 'page': page}), (d) => _page(d, Vehicle.new));
  Future<Vehicle> vehicle(int id) => _call(() => _dio.get('/vehicles/$id'), (d) => Vehicle(d['data']));
  Future<bool> available(int id, Period p) =>
      _call(() => _dio.get('/vehicles/$id/availability', queryParameters: {'from': _iso(p.from), 'to': _iso(p.to)}), (d) => d['data']['available'] as bool);

  static Map<String, dynamic> _bookingBody(int vehicleId, Period p, Map<int, int> addons, String coupon) => {
    'vehicle_id': vehicleId,
    'start_at': _iso(p.from),
    'end_at': _iso(p.to),
    'addons': [
      for (final e in addons.entries.where((e) => e.value > 0)) {'id': e.key, 'qty': e.value},
    ],
    if (coupon.trim().isNotEmpty) 'coupon_code': coupon.trim(),
  };

  // Bookings
  Future<Quote> quote(int vehicleId, Period p, {Map<int, int> addons = const {}, String coupon = ''}) =>
      _call(() => _dio.post('/bookings/quote', data: _bookingBody(vehicleId, p, addons, coupon)), (d) => Quote(d['data']));
  Future<Booking> createBooking(int vehicleId, Period p, {Map<int, int> addons = const {}, String coupon = ''}) =>
      _call(() => _dio.post('/bookings', data: _bookingBody(vehicleId, p, addons, coupon)), (d) => Booking(d['data']));
  Future<Page<Booking>> bookings({int page = 1}) => _call(() => _dio.get('/bookings', queryParameters: {'page': page}), (d) => _page(d, Booking.new));
  Future<Booking> booking(int id) => _call(() => _dio.get('/bookings/$id'), (d) => Booking(d['data']));
  Future<Booking> cancel(int id) => _call(() => _dio.post('/bookings/$id/cancel'), (d) => Booking(d['data']));
  Future<Booking> extend(int id, DateTime end) => _call(() => _dio.post('/bookings/$id/extend', data: {'end_at': _iso(end)}), (d) => Booking(d['data']));
  Future<Uint8List> invoice(int id) => _call(
    () => _dio.get(
      '/bookings/$id/invoice',
      options: Options(responseType: ResponseType.bytes, headers: {'Accept': 'application/pdf'}),
    ),
    (d) => Uint8List.fromList(d as List<int>),
  );

  // Payments & wallet
  /// Card gateways the currency's country pack allows (Phase 8).
  Future<List<String>> gateways(String currency) =>
      _call(() => _dio.get('/payments/gateways', queryParameters: {'currency': currency}), (d) => List<String>.from(d['data']));
  Future<Intent> payBooking(int bookingId, String gateway) =>
      _call(() => _dio.post('/payments/intent', data: {'booking_id': bookingId, 'gateway': gateway}), (d) => Intent(d['data']));
  Future<Wallet> wallet() => _call(
    () => _dio.get('/wallet'),
    (d) => Wallet({for (final b in d['balances']) b['currency_code'] as String: b['balance'] as int}, [for (final j in d['data']) WalletTx(j)]),
  );
  Future<Intent> topup(int amountMinor, String currency, String gateway) =>
      _call(() => _dio.post('/wallet/topup', data: {'amount': amountMinor, 'currency_code': currency, 'gateway': gateway}), (d) => Intent(d['data']));
}
