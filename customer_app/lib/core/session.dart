import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api.dart';
import 'config.dart';
import 'push.dart';

class Session {
  const Session({this.token, this.user, this.locale = 'en', this.ready = false});

  final String? token;
  final User? user;
  final String locale;
  final bool ready; // stored token checked

  bool get signedIn => token != null;

  Session copyWith({String? token, User? user, String? locale, bool? ready, bool clear = false}) =>
      Session(token: clear ? null : token ?? this.token, user: clear ? null : user ?? this.user, locale: locale ?? this.locale, ready: ready ?? this.ready);
}

/// Sanctum token + chosen language in the platform keystore. Overridden with an in-memory store in tests.
class TokenStore {
  const TokenStore();
  static const _storage = FlutterSecureStorage();

  Future<String?> read(String key) => _storage.read(key: key);
  Future<void> write(String key, String? value) => value == null ? _storage.delete(key: key) : _storage.write(key: key, value: value);
}

final tokenStoreProvider = Provider<TokenStore>((_) => const TokenStore());

final sessionProvider = NotifierProvider<SessionNotifier, Session>(SessionNotifier.new);

class SessionNotifier extends Notifier<Session> {
  TokenStore get _store => ref.read(tokenStoreProvider);

  @override
  Session build() => const Session();

  /// On app start: stored language, stored token (dropped if the server no longer accepts it, e.g. suspended user).
  Future<void> restore(String deviceLocale) async {
    final locale = await _store.read('locale') ?? (Config.supportedLocales.contains(deviceLocale) ? deviceLocale : 'en');
    final token = await _store.read('token');
    state = Session(token: token, locale: locale);
    if (token != null) {
      try {
        state = state.copyWith(user: await ref.read(apiProvider).me());
      } on ApiException catch (e) {
        if (e.status == 401 || e.status == 403) await signOut();
      }
    }
    state = state.copyWith(ready: true);
  }

  Future<void> signIn(String token, User user) async {
    await _store.write('token', token);
    await _store.write('locale', user.locale);
    state = state.copyWith(token: token, user: user, locale: user.locale);
    await ref.read(pushProvider).register();
  }

  void updateUser(User user) {
    state = state.copyWith(user: user, locale: user.locale);
    _store.write('locale', user.locale);
  }

  Future<void> setLocale(String locale) async {
    await _store.write('locale', locale);
    state = state.copyWith(locale: locale);
  }

  Future<void> signOut() async {
    await ref.read(pushProvider).unregister();
    await _store.write('token', null);
    state = state.copyWith(clear: true);
  }
}
