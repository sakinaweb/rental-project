import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api.dart';

class Session {
  const Session({this.token, this.user, this.ready = false});

  final String? token;
  final User? user;
  final bool ready; // stored token checked

  bool get signedIn => token != null;
}

/// Sanctum token in the platform keystore. Overridden with an in-memory store in tests.
class TokenStore {
  const TokenStore();
  static const _storage = FlutterSecureStorage();

  Future<String?> read() => _storage.read(key: 'token');
  Future<void> write(String? token) => token == null ? _storage.delete(key: 'token') : _storage.write(key: 'token', value: token);
}

final tokenStoreProvider = Provider<TokenStore>((_) => const TokenStore());

final sessionProvider = NotifierProvider<SessionNotifier, Session>(SessionNotifier.new);

class SessionNotifier extends Notifier<Session> {
  TokenStore get _store => ref.read(tokenStoreProvider);

  @override
  Session build() => const Session();

  /// On app start: keep the stored token only if the server still accepts it for a staff account.
  Future<void> restore() async {
    final token = await _store.read();
    state = Session(token: token);
    if (token != null) {
      try {
        final user = await ref.read(apiProvider).me();
        state = user.role == 'staff' ? Session(token: token, user: user) : const Session();
        if (user.role != 'staff') await _store.write(null);
      } on ApiException catch (e) {
        if (e.status == 401 || e.status == 403) await signOut();
      }
    }
    state = Session(token: state.token, user: state.user, ready: true);
  }

  /// Only staff accounts may use the app; anyone else is refused before the token is stored.
  Future<bool> signIn(String token, User user) async {
    if (user.role != 'staff') return false;
    await _store.write(token);
    state = Session(token: token, user: user, ready: true);
    return true;
  }

  Future<void> signOut() async {
    await _store.write(null);
    state = const Session(ready: true);
  }
}
