import 'dart:ui';

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

  @override
  String toString() => message;
}

// Models: only the fields the app uses. Shapes are documented in docs/openapi.json (tag "Staff").

class User {
  User(this.j);
  final Map<String, dynamic> j;
  String get name => j['name'] ?? '';
  String? get role => j['role'];
  String? get locale => j['locale'];
}

class StaffBooking {
  StaffBooking(this.j);
  final Map<String, dynamic> j;
  int get id => j['id'];
  String get status => j['status'];
  DateTime get start => DateTime.parse(j['start_at']).toLocal();
  DateTime get end => DateTime.parse(j['end_at']).toLocal();
  String get customer => j['customer'];
  String? get phone => j['customer_phone'];
  String get vehicle => '${j['vehicle']['title']} · ${j['vehicle']['plate_no']}';
  int? get odometer => j['odometer'];
  String get currency => j['currency_code'] ?? '';
  List<Map<String, dynamic>> get inspections => List<Map<String, dynamic>>.from(j['inspections'] ?? const []);
  List<Map<String, dynamic>> get damages => List<Map<String, dynamic>>.from(j['damages'] ?? const []);

  bool get canHandOver => status == 'confirmed';
  bool get canReceive => status == 'picked_up';
}

class Day {
  Day(this.branch, this.pickups, this.returns);
  final String? branch;
  final List<StaffBooking> pickups;
  final List<StaffBooking> returns;
}

/// What the staff member records at handover or return (owner decision: ≥ 4 photos, odometer, fuel).
class Checklist {
  Checklist({required this.photos, required this.odometer, required this.fuel, this.notes = ''});
  final List<String> photos; // file paths from the camera
  final int odometer;
  final int fuel; // percent
  final String notes;
}

class DamageReport {
  DamageReport(this.description, this.cost, [this.photos = const []]);
  final String description;
  final int cost; // minor units
  final List<String> photos;
}

final dioProvider = Provider<Dio>(createDio);

/// Dio with the session's bearer token on every request (tests reuse it with a fake adapter).
Dio createDio(Ref ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: Config.apiBaseUrl,
      headers: {'Accept': 'application/json'},
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 120), // photo uploads on a slow counter connection
    ),
  );
  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) {
        final token = ref.read(sessionProvider).token;
        if (token != null) options.headers['Authorization'] = 'Bearer $token';
        // Messages before sign-in follow the phone; once signed in the server uses the account's language.
        options.headers['Accept-Language'] = PlatformDispatcher.instance.locale.languageCode;
        handler.next(options);
      },
    ),
  );
  return dio;
}

/// Turns local file paths into multipart files. Overridden in tests (no real files there).
typedef PhotoFile = Future<MultipartFile> Function(String path);
final photoFileProvider = Provider<PhotoFile>(
  (_) =>
      (path) => MultipartFile.fromFile(path),
);

final apiProvider = Provider<Api>((ref) => Api(ref.watch(dioProvider), ref.watch(photoFileProvider)));

class Api {
  Api(this._dio, this._file);
  final Dio _dio;
  final PhotoFile _file;

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

  Future<(String, User)> login(String email, String password) =>
      _call(() => _dio.post('/auth/login', data: {'email': email, 'password': password}), (d) => (d['token'] as String, User(d['user'])));
  Future<User> me() => _call(() => _dio.get('/me'), (d) => User(d['data']));

  Future<Day> day(DateTime date) => _call(
    () => _dio.get('/staff/bookings', queryParameters: {'date': '${date.year}-${_two(date.month)}-${_two(date.day)}'}),
    (d) => Day(
      d['data']['branch'],
      [for (final b in d['data']['pickups']) StaffBooking(b)],
      [for (final b in d['data']['returns']) StaffBooking(b)],
    ),
  );
  Future<StaffBooking> booking(int id) => _call(() => _dio.get('/staff/bookings/$id'), (d) => StaffBooking(d['data']));

  Future<StaffBooking> handOver(int id, Checklist c) async =>
      _call(() async => _dio.post('/staff/bookings/$id/pickup', data: await _form(c)), (d) => StaffBooking(d['data']));

  Future<StaffBooking> receive(int id, Checklist c, List<DamageReport> damages) async =>
      _call(() async => _dio.post('/staff/bookings/$id/return', data: await _form(c, damages)), (d) => StaffBooking(d['data']));

  Future<FormData> _form(Checklist c, [List<DamageReport> damages = const []]) async {
    final form = FormData.fromMap({'odometer': c.odometer, 'fuel_level': c.fuel, if (c.notes.isNotEmpty) 'notes': c.notes});
    for (final p in c.photos) {
      form.files.add(MapEntry('photos[]', await _file(p)));
    }
    for (final (i, d) in damages.indexed) {
      form.fields
        ..add(MapEntry('damages[$i][description]', d.description))
        ..add(MapEntry('damages[$i][cost]', '${d.cost}'));
      for (final p in d.photos) {
        form.files.add(MapEntry('damages[$i][photos][]', await _file(p)));
      }
    }
    return form;
  }

  static String _two(int n) => n.toString().padLeft(2, '0');
}
