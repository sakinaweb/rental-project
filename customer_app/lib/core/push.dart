import 'dart:async';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api.dart';
import 'config.dart';

final pushProvider = Provider<Push>((ref) => Push(ref));

/// Push notifications via firebase_messaging (MD §1). Only active when the Firebase app values are configured
/// (Config.pushEnabled); otherwise every call is a no-op. The backend sends on booking confirmed/cancelled.
class Push {
  Push(this.ref);
  final Ref ref;
  String? _token;
  StreamSubscription<String>? _refresh;

  static Future<void> init() async {
    if (!Config.pushEnabled) return;
    await Firebase.initializeApp(
      options: const FirebaseOptions(
        apiKey: Config.firebaseApiKey,
        appId: Config.firebaseAppId,
        messagingSenderId: Config.firebaseSenderId,
        projectId: Config.firebaseProjectId,
      ),
    );
  }

  /// After sign-in: ask permission, send the FCM token to POST /devices, keep it current.
  Future<void> register() async {
    if (!Config.pushEnabled) return;
    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();
      _token = await messaging.getToken();
      if (_token != null) await ref.read(apiProvider).registerDevice(_token!, Platform.isIOS ? 'ios' : 'android');
      await _refresh?.cancel(); // one listener per signed-in session
      _refresh = messaging.onTokenRefresh.listen((t) {
        _token = t;
        ref.read(apiProvider).registerDevice(t, Platform.isIOS ? 'ios' : 'android').catchError((_) {});
      });
    } catch (e) {
      debugPrint('Push registration failed: $e'); // push is optional; never block sign-in
    }
  }

  Future<void> unregister() async {
    await _refresh?.cancel();
    _refresh = null;
    if (_token == null) return;
    try {
      await ref.read(apiProvider).unregisterDevice(_token!);
    } catch (_) {}
    _token = null;
  }

  /// Booking id from a tapped notification (app opened from background or terminated).
  static Stream<int> openedBookings() async* {
    if (!Config.pushEnabled) return;
    final initial = await FirebaseMessaging.instance.getInitialMessage();
    final id = int.tryParse('${initial?.data['booking_id']}');
    if (id != null) yield id;
    yield* FirebaseMessaging.onMessageOpenedApp.map((m) => int.tryParse('${m.data['booking_id']}')).where((id) => id != null).cast<int>();
  }
}
